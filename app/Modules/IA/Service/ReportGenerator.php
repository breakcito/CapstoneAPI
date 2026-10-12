<?php

namespace App\Modules\IA\Service;

use App\Modules\IA\Data\IAData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Convierte una consulta de solo lectura en un .xlsx descargable.
 *
 * El modelo nunca escribe bytes: propone el SQL y este service lo materializa
 * con OpenSpout, que escribe en streaming (memoria constante) y no necesita el
 * archivo completo en RAM.
 */
class ReportGenerator
{
    private const CARPETA = 'ia/reportes';

    /**
     * Claves internas de la base que no le dicen nada a quien abre el archivo.
     */
    private const COLUMNAS_INTERNAS = [
        'id',
        'id_producto',
        'id_lote_producto',
        'id_lote',
        'id_almacen',
        'id_unidad_medida',
        'id_empleado',
        'id_empleado_registro',
        'id_empleado_solicitante',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Ejecuta el SQL y guarda el resultado como .xlsx.
     *
     * @return array{uuid: string, nombre: string, filas: int}
     *
     * @throws RuntimeException si el SQL es rechazado o no se puede ejecutar
     */
    public static function generar(string $sql, string $nombre): array
    {
        return self::generarConSecciones([['nombre' => 'Datos', 'sql' => $sql]], $nombre);
    }

    /**
     * Reporte multi-hoja: cada seccion es una hoja del mismo .xlsx.
     *
     * Es lo que hace util un "reporte general de inventario": un solo archivo
     * con Stock valorizado, Alertas, Rotacion y KPIs, en vez de un muro de
     * texto en el chat o cinco descargas sueltas.
     *
     * @param  array<int, array{nombre?: string, sql: string}>  $secciones
     * @return array{uuid: string, nombre: string, filas: int, hojas: array<int, string>}
     *
     * @throws RuntimeException si alguna seccion tiene SQL invalido
     */
    public static function generarConSecciones(array $secciones, string $nombre): array
    {
        $secciones = array_values(array_filter(
            array_map(static function (array $s) {
                $sql = trim((string) ($s['sql'] ?? ''));

                return $sql === '' ? null : [
                    'nombre' => trim((string) ($s['nombre'] ?? 'Datos')) ?: 'Datos',
                    'sql' => $sql,
                ];
            }, $secciones),
        ));

        if ($secciones === []) {
            throw new RuntimeException('El reporte no tiene ninguna seccion con datos.');
        }

        if (count($secciones) > 12) {
            throw new RuntimeException('Un reporte admite hasta 12 secciones.');
        }

        $nombreArchivo = self::sanearNombre($nombre);
        $uuid = (string) Str::uuid();
        $path = self::ruta($uuid);

        File::ensureDirectoryExists(dirname($path));

        // Se ejecutan TODAS las consultas antes de escribir: si una falla, no
        // queda un .xlsx a medio hacer en el disco.
        $hojas = [];

        foreach ($secciones as $seccion) {
            $error = SqlGuard::validar($seccion['sql']);

            if ($error !== null) {
                throw new RuntimeException('SQL rechazado: '.$error);
            }

            try {
                $resultado = IAData::ejecutar(SqlGuard::forzarLimite($seccion['sql']));
            } catch (\Throwable $e) {
                throw new RuntimeException('SQL invalido: '.$e->getMessage(), 0, $e);
            }

            $hojas[] = [
                'titulo' => self::nombreHojaValido($seccion['nombre'], count($hojas)),
                'resultado' => $resultado,
            ];
        }

        // Un reporte lo lee una persona, no un sistema: se limpian los
        // identificadores internos y se traducen los nombres de columna
        // ANTES de escribir, para que no dependa de que el modelo acierte.
        foreach ($hojas as &$hoja) {
            $hoja['resultado'] = self::limpiarParaPersonas($hoja['resultado']);
        }
        unset($hoja);

        $writer = new Writer;
        $writer->openToFile($path);

        $totalFilas = 0;
        $titulos = [];

        try {
            foreach ($hojas as $indice => $hoja) {
                if ($indice > 0) {
                    $writer->addNewSheetAndMakeItCurrent();
                }

                $resultado = $hoja['resultado'];
                $titulos[] = $hoja['titulo'];

                if ($resultado['columnas'] === []) {
                    $writer->addRow(Row::fromValues(
                        ['(sin registros para esta seccion)'],
                        self::estiloEncabezado(),
                    ));

                    continue;
                }

                $writer->addRow(Row::fromValues(
                    array_map(self::encabezadoHumano(...), $resultado['columnas']),
                    self::estiloEncabezado(),
                ));

                foreach ($resultado['filas'] as $fila) {
                    // Se proyecta sobre las columnas declaradas: el driver puede
                    // devolver las claves en otro orden que getColumnNames().
                    $writer->addRow(Row::fromValues(array_map(
                        static fn (string $columna) => $fila[$columna] ?? null,
                        $resultado['columnas'],
                    )));
                }

                $totalFilas += count($resultado['filas']);
            }
        } finally {
            $writer->close();
        }

        self::ajustarTodasLasHojas($path, $hojas);

        return [
            'uuid' => $uuid,
            'nombre' => $nombreArchivo,
            'filas' => $totalFilas,
            'hojas' => $titulos,
        ];
    }

    /**
     * Aplica ancho de columna, fila 1 congelada y nombre de hoja.
     *
     * OpenSpout 4 no expone auto-ancho de columna, panel congelado ni nombre
     * de hoja (`Sheet` no tiene setter: se llaman "Sheet1", "Sheet2"...), asi
     * que se inyectan directo en el XML del .xlsx ya cerrado.
     *
     * Sin el ancho, Excel muestra "stock_minimo_" cortado en la fila 1, que
     * es exactamente el defecto que hace el reporte ilegible.
     *
     * @param  array<int, array{titulo: string, resultado: array<string, mixed>}>  $hojas
     */
    private static function ajustarTodasLasHojas(string $path, array $hojas): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return;
        }

        for ($i = 0; $i < count($hojas); $i++) {
            $nombreXml = 'xl/worksheets/sheet'.($i + 1).'.xml';
            $xml = $zip->getFromName($nombreXml);

            if ($xml === false) {
                continue;
            }

            $columnas = $hojas[$i]['resultado']['columnas'];
            $filas = $hojas[$i]['resultado']['filas'];

            $anchos = $columnas === [] ? [] : self::calcularAnchos($columnas, $filas);

            $cols = '';

            foreach ($anchos as $indice => $caracteres) {
                $posicion = $indice + 1;
                $ancho = max(10, min(42, $caracteres + 2));
                $cols .= '<col min="'.$posicion.'" max="'.$posicion.'" width="'.$ancho.'" customWidth="1"/>';
            }

            $inyectado = '<sheetViews><sheetView workbookViewId="0">'
                .'<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
                .'</sheetView></sheetViews><cols>'.$cols.'</cols>';

            // <cols> va despues de <dimension> y antes de <sheetData> segun el esquema.
            $modificado = preg_replace('/<sheetData>/', $inyectado.'<sheetData>', $xml, 1);

            if ($modificado !== null && $modificado !== $xml) {
                $zip->addFromString($nombreXml, $modificado);
            }
        }

        self::renombrarHojas($zip, array_column($hojas, 'titulo'));

        $zip->close();
    }

    /**
     * Sustituye los nombres "Sheet1", "Sheet2"... del workbook por los titulos
     * reales de cada seccion.
     *
     * @param  array<int, string>  $titulos
     */
    private static function renombrarHojas(ZipArchive $zip, array $titulos): void
    {
        $workbook = $zip->getFromName('xl/workbook.xml');

        if ($workbook === false) {
            return;
        }

        $indice = 0;

        // callback de preg_replace: cada <sheet name="..."> toma el titulo que le toca
        $modificado = preg_replace_callback(
            '/<sheet name="[^"]*"/',
            static function (array $m) use ($titulos, &$indice): string {
                $titulo = $titulos[$indice] ?? null;
                $indice++;

                if ($titulo === null) {
                    return $m[0];
                }

                return '<sheet name="'.htmlspecialchars($titulo, ENT_XML1 | ENT_QUOTES, 'UTF-8').'"';
            },
            $workbook,
        );

        if ($modificado !== null && $modificado !== $workbook) {
            $zip->addFromString('xl/workbook.xml', $modificado);
        }
    }

    /**
     * Excel no admite : \ / ? * [ ] en el nombre de una hoja y topa en 31
     * caracteres.
     */
    private static function nombreHojaValido(string $titulo, int $indice): string
    {
        $limpio = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $titulo);
        $limpio = trim((string) preg_replace('/\s+/', ' ', $limpio));
        $limpio = mb_substr($limpio === '' ? 'Datos '.($indice + 1) : $limpio, 0, 31);

        return $limpio === '' ? 'Datos '.($indice + 1) : $limpio;
    }

    /**
     * Ancho en caracteres por columna: el texto mas largo, contando el encabezado.
     *
     * @return array<int, int>
     */
    private static function calcularAnchos(array $columnas, array $filas): array
    {
        $anchos = [];

        foreach ($columnas as $indice => $columna) {
            $largo = mb_strlen(self::encabezadoHumano($columna));

            foreach ($filas as $fila) {
                $largo = max($largo, mb_strlen((string) ($fila[$columna] ?? '')));
            }

            $anchos[$indice] = $largo;
        }

        return $anchos;
    }

    /**
     * "stock_minimo_base" -> "Stock Minimo Base". Los encabezados crudos de la
     * base no son legibles en un reporte que va a una persona.
     */
    /**
     * Quita los identificadores internos y deja el resto legible.
     *
     * "Id Producto", "Id Lote" y "Created At" son claves internas de la base:
     * no le sirven a un administrador. En cambio "contenido_por_presentacion"
     * SI es informacion de negocio (cuantas unidades base trae una
     * presentacion), asi que se conserva y se traduce, nunca se borra.
     *
     * @param  array{columnas: array<int, string>, filas: array<int, array<string, mixed>>}  $resultado
     * @return array{columnas: array<int, string>, filas: array<int, array<string, mixed>>}
     */
    private static function limpiarParaPersonas(array $resultado): array
    {
        if ($resultado['columnas'] === []) {
            return $resultado;
        }

        $utiles = array_values(array_filter(
            $resultado['columnas'],
            static fn (string $c): bool => ! in_array(strtolower(trim($c)), self::COLUMNAS_INTERNAS, true),
        ));

        // Si al filtrar no queda nada, el reporte era solo ruido: se conserva.
        if ($utiles === []) {
            $utiles = $resultado['columnas'];
        }

        return [
            'columnas' => $utiles,
            'filas' => array_map(
                static function (array $fila) use ($utiles): array {
                    $limpia = [];

                    foreach ($utiles as $columna) {
                        $limpia[$columna] = $fila[$columna] ?? null;
                    }

                    return $limpia;
                },
                $resultado['filas'],
            ),
        ];
    }

    /**
     * Traduce el nombre de columna a como lo escribiría un administrador.
     *
     * El catálogo existe porque estos nombres aparecen en casi todos los
     * reportes: el modelo puede inventar alias raros, pero estas columnas
     * siempre suenan igual y el encabezado tiene que leerse bien.
     */
    private const ENCABEZADOS_CLAROS = [
        'contenido_por_presentacion' => 'Unidades base por presentacion',
        'contenido_x_presentacion' => 'Unidades base por presentacion',
        'unidades_base_por_presentacion' => 'Unidades base por presentacion',
        'stock_base' => 'Stock (unidad base)',
        'stock_actual_base' => 'Stock (unidad base)',
        'stock_anterior_base' => 'Stock anterior (unidad base)',
        'stock_resultante_base' => 'Stock resultante (unidad base)',
        'cantidad_movimiento_base' => 'Cantidad movida (unidad base)',
        'cantidad_base' => 'Cantidad (unidad base)',
        'costo_por_unidad_base' => 'Costo unitario (unidad base)',
        'costo_unitario_base' => 'Costo unitario (unidad base)',
        'costo_por_unidad' => 'Costo unitario',
        'stock_minimo_base' => 'Stock minimo (unidad base)',
        'stock_actual' => 'Stock',
        'valorizado' => 'Valor del inventario (S/)',
        'valor_stock' => 'Valor del inventario (S/)',
        'unidades_base_total' => 'Unidades base totales',
        'productos_activos' => 'Productos con stock',
        'lotes_sin_stock' => 'Lotes sin stock',
        'nombre' => 'Nombre',
        'producto' => 'Producto',
        'almacen' => 'Almacen',
        'fecha_vencimiento' => 'Vence',
        'nivel_alerta' => 'Nivel de alerta',
        'deficit_base' => 'Deficit',
    ];

    private static function encabezadoHumano(string $columna): string
    {
        $base = strtolower(trim($columna));

        if (isset(self::ENCABEZADOS_CLAROS[$base])) {
            return self::ENCABEZADOS_CLAROS[$base];
        }

        $palabras = preg_split('/[_\s]+/', $columna) ?: [$columna];

        $texto = implode(' ', array_map(
            static fn (string $p) => $p === '' ? $p : mb_convert_case($p, MB_CASE_TITLE, 'UTF-8'),
            $palabras,
        ));

        return $texto === '' ? $columna : $texto;
    }

    private static function estiloEncabezado(): Style
    {
        $estilo = new Style;
        $estilo->setFontBold();
        $estilo->setFontSize(11);
        $estilo->setFontColor('FFFFFF');
        $estilo->setBackgroundColor('1E293B');
        $estilo->setCellAlignment(CellAlignment::CENTER);
        $estilo->setCellVerticalAlignment(CellVerticalAlignment::CENTER);

        return $estilo;
    }

    /**
     * Carpeta donde se guardan los reportes.
     */
    public static function rutaDeCarpeta(): string
    {
        return Storage::disk('local')->path(self::CARPETA);
    }

    /**
     * Ruta absoluta del archivo en disco.
     */
    public static function ruta(string $uuid): string
    {
        return Storage::disk('local')->path(self::CARPETA.'/'.$uuid.'.xlsx');
    }

    public static function existe(string $uuid): bool
    {
        return self::uuidValido($uuid) && Storage::disk('local')->exists(self::CARPETA.'/'.$uuid.'.xlsx');
    }

    public static function nombreDe(string $uuid): string
    {
        return $uuid.'.xlsx';
    }

    /**
     * Descarga el archivo como respuesta HTTP.
     */
    public static function descargar(string $uuid, string $nombre): StreamedResponse
    {
        $ruta = self::ruta($uuid);

        return response()->stream(
            function () use ($ruta) {
                // Storage::download() devuelve una BinaryFileResponse, que hace
                // ob_end_clean() y se desalinea si quedo salida previa en el
                // buffer. Limpiar aqui garantiza entregar los bytes exactos
                // del .xlsx, sin un byte de ruido delante.
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }

                $manejador = fopen($ruta, 'rb');

                if ($manejador === false) {
                    return;
                }

                while (! feof($manejador)) {
                    echo fread($manejador, 8192);
                    flush();
                }

                fclose($manejador);
            },
            200,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Length' => (string) filesize($ruta),
                'Content-Disposition' => 'attachment; filename="'.basename($nombre).'"',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    /**
     * El uuid tambien viene del modelo (va en la URL), asi que se valida antes
     * de tocar el disco.
     */
    public static function uuidValido(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }

    private static function sanearNombre(string $nombre): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9_\- ]/u', '', $nombre) ?? 'reporte';
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? '');
        $nombre = mb_substr($nombre === '' ? 'reporte' : $nombre, 0, 80);

        if (! str_ends_with(strtolower($nombre), '.xlsx')) {
            $nombre .= '.xlsx';
        }

        return $nombre;
    }
}
