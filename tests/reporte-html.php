<?php

/**
 * Genera un reporte HTML autocontenido de la suite de pruebas.
 *
 * Uso:
 *   php tests/reporte-html.php              # toda la suite
 *   php tests/reporte-html.php --group ia   # solo un grupo
 */
$raiz = dirname(__DIR__);
$destino = $raiz . '/storage/app/reportes-pruebas';
$junit = $destino . '/resultado.xml';
date_default_timezone_set('America/Lima');

if (!is_dir($destino) && !mkdir($destino, 0775, true) && !is_dir($destino)) {
  fwrite(STDERR, "No se pudo crear {$destino}\n");
  exit(1);
}

$argumentos = array_slice($argv, 1);

$comando = array_merge([
  PHP_BINARY,
  $raiz . '/vendor/bin/phpunit',
  '--log-junit',
  $junit,
  '--no-progress',
  '--colors=never',
], $argumentos);

$salida = [];
$codigo = 0;

exec(implode(' ', array_map('escapeshellarg', $comando)) . ' 2>&1', $salida, $codigo);

$texto = implode("\n", $salida);

if (!is_file($junit)) {
  fwrite(STDERR, "PHPUnit no genero el XML de resultados.\n{$texto}\n");
  exit(1);
}

$xml = @simplexml_load_file($junit);

if ($xml === false) {
  fwrite(STDERR, "No se pudo leer el XML de resultados.\n");
  exit(1);
}

/** Recorre un <testsuite> y sus hijos, aplanando los casos. */
$recorrer = function (SimpleXMLElement $suite) use (&$recorrer): array {
  $casos = [];

  foreach ($suite->testsuite as $hijo) {
    $casos = array_merge($casos, $recorrer($hijo));
  }

  foreach ($suite->testcase as $caso) {
    $estado = 'ok';
    $motivo = null;

    if (isset($caso->failure)) {
      $estado = 'fallo';
      $motivo = trim((string) $caso->failure);
    } elseif (isset($caso->error)) {
      $estado = 'error';
      $motivo = trim((string) $caso->error);
    } elseif (isset($caso->skipped)) {
      $estado = 'omitida';
      $motivo = trim((string) $caso->skipped);
    }

    $casos[] = [
      'clase' => (string) ($caso['class'] ?? ''),
      'nombre' => (string) ($caso['name'] ?? ''),
      'tiempo' => (float) ($caso['time'] ?? 0),
      'estado' => $estado,
      'motivo' => $motivo,
    ];
  }

  return $casos;
};

$casos = $recorrer($xml);

$contar = static fn(string $estado): int => count(array_filter(
  $casos,
  static fn(array $c): bool => $c['estado'] === $estado,
));

$ok = $contar('ok');
$fallos = $contar('fallo');
$errores = $contar('error');
$omitidas = $contar('omitida');
$total = count($casos);

$grupos = [];
foreach ($casos as $caso) {
  $grupos[$caso['clase']][] = $caso;
}
ksort($grupos);

$escapar = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$resumen = [];
foreach ($grupos as $clase => $delGrupo) {
  $pasan = 0;
  $ms = 0.0;

  foreach ($delGrupo as $caso) {
    if ($caso['estado'] === 'ok') {
      $pasan++;
    }
    $ms += $caso['tiempo'];
  }

  $totalGrupo = count($delGrupo);

  $resumen[] = [
    'clase' => $clase,
    'corto' => substr(strrchr($clase, '\\') ?: $clase, 1),
    'pasan' => $pasan,
    'total' => $totalGrupo,
    'ms' => $ms,
    'porcentaje' => $totalGrupo > 0 ? (int) round($pasan / $totalGrupo * 100) : 100,
    'estado' => $pasan === $totalGrupo ? 'ok' : ($totalGrupo - $pasan > 0 ? 'rojo' : ''),
  ];
}

usort($resumen, static fn(array $a, array $b): int => [$a['porcentaje'], -$a['ms']] <=> [$b['porcentaje'], -$b['ms']]);

$msTotales = array_sum(array_column($resumen, 'ms'));
$verde = $total > 0 ? (int) round($ok / $total * 100) : 100;
$fallosPct = $total > 0 ? number_format(($fallos / $total) * 100, 4) : '0';
$erroresPct = $total > 0 ? number_format(($errores / $total) * 100, 4) : '0';

$html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reporte de pruebas</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  :root {
    --fondo:#ffffff;
    --panel:#ffffff;
    --panel-hover:#f8fafc;
    --linea:#e2e8f0;
    --linea-suave:#edf2f7;
    --texto:#0f172a;
    --tenue:#475569;
    --apagado:#64748b;
    --verde:#16a34a;
    --ambar:#d97706;
    --rojo:#dc2626;
    --gris:#64748b;
  }
  html { color-scheme: light; }
  body { margin:0; background:var(--fondo); color:var(--texto);
         font:14px/1.65 ui-sans-serif,-apple-system,"Segoe UI",Roboto,sans-serif;
         -webkit-font-smoothing:antialiased; }
  .wrap { max-width:1180px; margin:0 auto; padding:2.5rem 1.5rem 5rem; }

  header { display:flex; align-items:flex-end; justify-content:space-between;
           gap:1.5rem; flex-wrap:wrap; margin-bottom:1.75rem; }
  h1 { margin:0; font-size:1.5rem; letter-spacing:-.02em; font-weight:650; }
  .sub { color:var(--apagado); font-size:.8rem; margin-top:.35rem; }
  .sub code { background:#f1f5f9; border:1px solid var(--linea); border-radius:.35rem;
              padding:.1rem .4rem; font-size:.92em; }

  .resumen { display:grid; grid-template-columns:repeat(auto-fit,minmax(118px,1fr));
             gap:1px; background:var(--linea); border:1px solid var(--linea);
             border-radius:.9rem; overflow:hidden; margin-bottom:1.5rem; }
  .celda { background:var(--panel); padding:1rem 1.15rem; }
  .celda b { display:block; font-size:1.85rem; font-weight:600; letter-spacing:-.03em;
             font-variant-numeric:tabular-nums; line-height:1.15; }
  .celda span { font-size:.68rem; text-transform:uppercase; letter-spacing:.09em; color:var(--apagado); }

  .barra { height:6px; border-radius:99px; background:#f1f5f9; overflow:hidden;
           display:flex; margin-bottom:2rem; }
  .barra i { display:block; height:100%; }

  .graficas { display:grid; grid-template-columns:1fr 2fr 1fr; gap:1rem;
              margin-bottom:1.5rem; }
  .grafica { background:var(--panel); border:1px solid var(--linea);
             border-radius:.75rem; padding:.9rem 1rem 1.1rem; }
  .grafica h3 { margin:0 0 .65rem; font-size:.68rem; text-transform:uppercase;
                letter-spacing:.09em; color:var(--apagado); font-weight:600; }
  .grafica .box { position:relative; height:220px; }

  .tabs { display:flex; gap:.3rem; border-bottom:1px solid var(--linea); margin-bottom:1.25rem;
          position:sticky; top:0; background:var(--fondo); z-index:5; padding-top:.5rem; }
  .tab { appearance:none; background:none; border:0; color:var(--tenue); cursor:pointer;
         padding:.6rem .95rem; font:inherit; font-size:.87rem; border-radius:.5rem .5rem 0 0;
         border-bottom:2px solid transparent; margin-bottom:-1px; }
  .tab:hover { color:var(--texto); background:var(--panel-hover); }
  .tab[aria-selected="true"] { color:var(--texto); font-weight:600; border-bottom-color:var(--verde); }

  table { width:100%; border-collapse:collapse; }
  th { text-align:left; font-size:.68rem; text-transform:uppercase; letter-spacing:.08em;
       color:var(--apagado); font-weight:600; padding:.5rem .75rem;
       border-bottom:1px solid var(--linea); }
  th.num, td.num { text-align:right; font-variant-numeric:tabular-nums; }
  tbody tr { border-bottom:1px solid var(--linea-suave); }
  tbody tr:hover { background:var(--panel-hover); }
  td { padding:.6rem .75rem; vertical-align:top; }
  .clase { font-weight:550; }
  .pct { display:flex; align-items:center; gap:.6rem; justify-content:flex-end; }
  .pct .mini { width:64px; height:5px; border-radius:99px; background:#f1f5f9; overflow:hidden; }
  .pct .mini i { display:block; height:100%; }
  .badge { display:inline-block; font-size:.68rem; padding:.15rem .5rem; border-radius:99px;
           border:1px solid var(--linea); color:var(--tenue); font-variant-numeric:tabular-nums; background:#ffffff; }
  .badge.ok { color:var(--verde); border-color:#bbf7d0; background:#f0fdf4; }
  .badge.rojo { color:var(--rojo); border-color:#fecaca; background:#fef2f2; }

  .panel { display:none; }
  .panel.activo { display:block; }

  .grupo { border:1px solid var(--linea); border-radius:.75rem; margin-bottom:.6rem;
           background:var(--panel); overflow:hidden; }
  .grupo > summary { cursor:pointer; padding:.8rem 1rem; display:flex; align-items:center;
                     gap:.75rem; list-style:none; user-select:none; }
  .grupo > summary::-webkit-details-marker { display:none; }
  .grupo > summary:hover { background:var(--panel-hover); }
  .flecha { color:var(--apagado); transition:transform .15s; font-size:.7rem; }
  .grupo[open] .flecha { transform:rotate(90deg); }
  .grupo .resto { margin-left:auto; display:flex; align-items:center; gap:.6rem;
                  color:var(--apagado); font-size:.78rem; }

  .caso { display:grid; grid-template-columns:14px 1fr auto; gap:.7rem; align-items:start;
          padding:.5rem 1rem; border-top:1px solid var(--linea-suave); }
  .caso .marca { width:7px; height:7px; border-radius:50%; margin-top:.5rem; }
  .caso.ok .marca { background:var(--verde); }
  .caso.fallo .marca { background:var(--ambar); }
  .caso.error .marca { background:var(--rojo); }
  .caso.omitida .marca { background:var(--gris); }
  .caso .titulo { font-size:.88rem; }
  .caso .ms { color:var(--apagado); font-size:.75rem; font-variant-numeric:tabular-nums;
              white-space:nowrap; }
  .detalle { grid-column:2 / -1; margin:.35rem 0 .25rem; padding:.6rem .8rem;
             background:#fff1f2; border:1px solid #fecdd3; border-left:3px solid var(--rojo);
             border-radius:.4rem; color:#9f1239; font:12px/1.6 ui-monospace,SFMono-Regular,monospace;
             white-space:pre-wrap; word-break:break-word; max-height:20rem; overflow:auto; }

  .pie { margin-top:2.5rem; padding-top:1.25rem; border-top:1px solid var(--linea);
         color:var(--apagado); font-size:.8rem; }
  .pie code { background:#f1f5f9; border:1px solid var(--linea); border-radius:.35rem;
              padding:.12rem .4rem; }
</style>
</head>
<body>
<div class="wrap">

<header>
  <div>
    <h1>Reporte de pruebas</h1>
    <div class="sub">{$escapar(date('d/m/Y H:i:s'))}</div>
  </div>
  <!-- <div class="sub">{$escapar(implode(' ', $argumentos) ?: 'suite completa')}</div> -->
</header>

<div class="resumen">
  <div class="celda"><b>{$total}</b><span>Total</span></div>
  <div class="celda"><b style="color:var(--verde)">{$ok}</b><span>Pasaron</span></div>
  <div class="celda"><b style="color:var(--ambar)">{$fallos}</b><span>Fallaron</span></div>
  <div class="celda"><b style="color:var(--rojo)">{$errores}</b><span>Errores</span></div>
  <div class="celda"><b style="color:var(--gris)">{$omitidas}</b><span>Omitidas</span></div>
  <div class="celda"><b>{$verde}%</b><span>Completado</span></div>
  <div class="celda"><b>{$escapar(number_format($msTotales, 0))}<span style="font-size:.9rem"> ms</span></b><span>Duracion</span></div>
</div>

<div class="barra" role="img" aria-label="{$verde}% en verde">
  <i style="width:{$verde}%;background:var(--verde)"></i>
  <i style="width:{$fallosPct}%;background:var(--ambar)"></i>
  <i style="width:{$erroresPct}%;background:var(--rojo)"></i>
</div>

<section class="graficas">
  <div class="grafica">
    <h3>Estado global</h3>
    <div class="box"><canvas id="graf-estado"></canvas></div>
  </div>
  <div class="grafica">
    <h3>% verde por clase</h3>
    <div class="box"><canvas id="graf-clases"></canvas></div>
  </div>
  <div class="grafica">
    <h3>Duración por clase (ms)</h3>
    <div class="box"><canvas id="graf-duracion"></canvas></div>
  </div>
</section>

<div class="tabs" role="tablist">
  <button class="tab" role="tab" aria-selected="true" data-panel="resumen">Resumen por clase</button>
  <button class="tab" role="tab" aria-selected="false" data-panel="detalle">Detalle</button>
</div>

<section class="panel activo" id="resumen">
  <table>
    <thead><tr>
      <th>Clase de prueba</th>
      <th class="num">Completado</th>
      <th class="num">Total</th>
      <th class="num">Duración</th>
    </tr></thead>
    <tbody>
HTML;

foreach ($resumen as $fila) {
  $colorBarra = $fila['porcentaje'] === 100 ? 'var(--verde)' : 'var(--ambar)';

  $html .= '<tr>'
    . '<td class="clase">' . $escapar($fila['corto']) . '</td>'
    . '<td class="num"><div class="pct">'
    . '<span class="mini"><i style="width:' . $fila['porcentaje'] . '%;background:' . $colorBarra . '"></i></span>'
    . '<span>' . $fila['porcentaje'] . '%</span></div></td>'
    . '<td class="num"><span class="badge ' . ($fila['estado'] ?: 'ok') . '">' . $fila['pasan'] . ' / ' . $fila['total'] . '</span></td>'
    . '<td class="num">' . number_format($fila['ms'] * 1000, 1) . ' ms</td>'
    . '</tr>';
}

$html .= <<<'HTML'
    </tbody>
  </table>
</section>

<section class="panel" id="detalle">
HTML;

foreach ($grupos as $clase => $delGrupo) {
  $pasanGrupo = count(array_filter($delGrupo, static fn(array $c): bool => $c['estado'] === 'ok'));
  $abierto = $pasanGrupo === count($delGrupo) ? '' : ' open';

  $html .= '<details class="grupo"' . $abierto . '>'
    . '<summary><span class="flecha">▶</span>'
    . '<span class="clase">' . $escapar(substr(strrchr($clase, '\\') ?: $clase, 1)) . '</span>'
    . '<span class="resto"><span class="badge ' . ($abierto ? 'rojo' : 'ok') . '">'
    . $pasanGrupo . ' / ' . count($delGrupo) . '</span>'
    . '<span>' . number_format(array_sum(array_column($delGrupo, 'tiempo')) * 1000, 1) . ' ms</span>'
    . '</span></summary>';

  foreach ($delGrupo as $caso) {
    $html .= '<div class="caso ' . $caso['estado'] . '">'
      . '<span class="marca"></span>'
      . '<span class="titulo">' . $escapar($caso['nombre']) . '</span>'
      . '<span class="ms">' . number_format($caso['tiempo'] * 1000, 1) . ' ms</span>'
      . '</div>';

    if ($caso['motivo'] !== null && $caso['motivo'] !== '') {
      $html .= '<div class="caso ' . $caso['estado'] . '"><span></span>'
        . '<div class="detalle">' . $escapar($caso['motivo']) . '</div></div>';
    }
  }

  $html .= '</details>';
}

$html .= <<<'HTML'
</section>

<!-- <div class="pie">
  La cobertura de líneas necesita <code>Xdebug</code> o <code>PCOV</code>, que no están
  instalados en Herd: actívalos en <code>Herd.app → Settings → PHP → Extensions</code> y luego
  corre <code>composer test:cobertura</code> para el informe de cobertura línea a línea.
  Este informe muestra el resultado de las pruebas, que es lo que hay que revisar.
</div> -->
</div>
HTML;

$estadoJs = json_encode(
  ['ok' => $ok, 'fallos' => $fallos, 'errores' => $errores, 'omitidas' => $omitidas],
  JSON_THROW_ON_ERROR,
);

$clasesJs = json_encode(
  array_map(static fn(array $c): array => [
    'corto' => $c['corto'],
    'porcentaje' => $c['porcentaje'],
    'ms' => (int) round($c['ms'] * 1000),
  ], $resumen),
  JSON_THROW_ON_ERROR,
);

$html .= "\n" . '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" crossorigin="anonymous"></script>' . "\n";
$html .= '<script>window.__chartEstado = ' . $estadoJs . ';</script>' . "\n";
$html .= '<script>window.__chartClases = ' . $clasesJs . ';</script>' . "\n";

$graficoJs = <<<'JS'
(function () {
  if (!window.Chart) return;
  const C = window.Chart;
  const estado = window.__chartEstado;
  const clases = window.__chartClases;
  if (!estado || !clases) return;

  C.defaults.color = '#64748b';
  C.defaults.font.family = 'ui-sans-serif,-apple-system,"Segoe UI",Roboto,sans-serif';
  C.defaults.font.size = 11;

  new C(document.getElementById('graf-estado'), {
    type: 'doughnut',
    data: {
      labels: ['Pasaron', 'Fallaron', 'Errores', 'Omitidas'],
      datasets: [{
        data: [estado.ok, estado.fallos, estado.errores, estado.omitidas],
        backgroundColor: ['#16a34a', '#d97706', '#dc2626', '#64748b'],
        borderColor: '#ffffff',
        borderWidth: 2,
      }],
    },
    options: {
      cutout: '60%',
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, padding: 12 } },
      },
    },
  });

  new C(document.getElementById('graf-clases'), {
    type: 'bar',
    data: {
      labels: clases.map((c) => c.corto),
      datasets: [{
        data: clases.map((c) => c.porcentaje),
        backgroundColor: clases.map((c) => (c.porcentaje === 100 ? '#16a34a' : '#d97706')),
        borderRadius: 4,
      }],
    },
    options: {
      indexAxis: 'y',
      maintainAspectRatio: false,
      scales: {
        x: { min: 0, max: 100, ticks: { callback: (v) => v + '%' }, grid: { color: '#e2e8f0' } },
        y: { grid: { display: false } },
      },
      plugins: { legend: { display: false } },
    },
  });

  new C(document.getElementById('graf-duracion'), {
    type: 'bar',
    data: {
      labels: clases.map((c) => c.corto),
      datasets: [{
        data: clases.map((c) => c.ms),
        backgroundColor: '#4f46e5',
        borderRadius: 4,
      }],
    },
    options: {
      maintainAspectRatio: false,
      scales: {
        y: { grid: { color: '#e2e8f0' }, beginAtZero: true },
        x: { grid: { display: false }, ticks: { autoSkip: false, maxRotation: 50, minRotation: 50 } },
      },
      plugins: { legend: { display: false } },
    },
  });
})();
JS;

$html .= '<script>' . $graficoJs . '</script>' . "\n";

$html .= <<<'HTML'
<script>
  const tabs = document.querySelectorAll('.tab');
  const panels = document.querySelectorAll('.panel');

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      tabs.forEach((t) => t.setAttribute('aria-selected', String(t === tab)));
      panels.forEach((p) => p.classList.toggle('activo', p.id === tab.dataset.panel));
    });
  });
</script>
</body>
</html>
HTML;

$archivo = $destino . '/index.html';
file_put_contents($archivo, $html);

printf(
  "Reporte: %s\n%d pruebas · %d pasaron · %d fallaron · %d errores · %d omitidas\n",
  $archivo,
  $total,
  $ok,
  $fallos,
  $errores,
  $omitidas,
);

exit(($fallos + $errores) > 0 ? 1 : 0);