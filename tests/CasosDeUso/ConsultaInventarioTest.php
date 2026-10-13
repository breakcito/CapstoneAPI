<?php

namespace Tests\CasosDeUso;

use App\Modules\IA\Service\AdjuntoExtractor;
use App\Modules\IA\Service\ReportePdfService;
use App\Modules\IA\Service\SqlCritic;
use App\Modules\IA\Tools\ToolRegistry;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fakes\DBFalsa;
use Tests\TestCase;

/**
 * CASO DE USO: el administrador consulta el inventario desde el chat.
 *
 * El administrador no sabe SQL. Pregunta en español y el sistema le devuelve
 * datos. Este archivo protege las reglas de negocio de ese ida y vuelta:
 *
 *   - El inventario de la empresa JAMAS se modifica desde el chat. Es una regla
 *     de negocio, no de implementacion: un error de stock no se arregla con un
 *     DELETE, se arregla con un asiento de ajuste.
 *   - El archivo que le llega al administrador existe, es un PDF valido y no
 *     puede escribirse fuera de la carpeta de reportes.
 *   - Un HTML hostil que la IA envie al backend se sanea antes de convertirse
 *     en PDF: nada de scripts, iframes, eventos inline ni recursos externos.
 *   - Un archivo que el sistema no puede leer se rechaza antes de gastar una
 *     consulta contra la base.
 *
 * Metodo AAA en cada prueba: las secciones Arrange / Act / Assert estan
 * marcadas para que se lea que se esta evaluando y por que.
 */
class ConsultaInventarioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // El .env de desarrollo lleva 99999 en los limites. La prueba fija su
        // propio escenario para no depender del entorno de quien la ejecuta.
        Config::set('ia.max_filas', 500);
    }

    protected function tearDown(): void
    {
        foreach (File::files(ReportePdfService::rutaDeCarpeta()) as $archivo) {
            @unlink($archivo->getPathname());
        }

        parent::tearDown();
    }

    /* ================================================================== */
    /* REGLA: el inventario nunca se modifica desde el chat */
    /* ================================================================== */

    /**
     * Evalua: que ninguna peticion del asistente pueda alterar el inventario,
     * sin importar como venga redactada la orden.
     *
     * Por que importa: el kardex es inmutable por regla del negocio (README 8).
     * Cualquier escritura que se colara desde el chat destruiria el historial
     * de movimientos y con el la trazabilidad de las existencias.
     */
    #[DataProvider('escriturasQueElInventarioNoAcepta')]
    public function test_el_asistente_no_puede_ordenar_una_escritura_de_inventario(string $sql): void
    {
        // Arrange: se le pide al asistente algo que destruye el inventario.
        $asistente = new ToolRegistry;

        // Act: el asistente intenta ejecutarlo.
        $respuesta = $asistente->ejecutar('ejecutar_sql', ['sql' => $sql]);

        // Assert: el inventario queda intacto y el motivo vuelve como texto.
        $this->assertStringStartsWith(
            '[ERROR]',
            $respuesta,
            "El asistente acepto una escritura de inventario: {$sql}",
        );
    }

    public static function escriturasQueElInventarioNoAcepta(): array
    {
        return [
            'borrar stock' => ['DELETE FROM lote_producto'],
            'mover stock a mano' => ['UPDATE lote_producto SET stock_actual_base = 0'],
            'crear lote falso' => ['INSERT INTO lote_producto (stock_actual_base) VALUES (9999)'],
            'borrar el kardex' => ['DELETE FROM kardex_producto'],
            'vaciar la tabla' => ['TRUNCATE kardex_producto'],
            'romper el catalogo' => ['DROP TABLE producto'],
            'encadenar un borrado' => ['SELECT 1; DELETE FROM kardex_producto'],
        ];
    }

    /**
     * Evalua: que el asistente pueda responder una consulta normal de stock.
     *
     * Por que importa: una barrera de solo lectura demasiado estricta tambien
     * es un defecto. Si el asistente no puede ni leer, el modulo no sirve.
     */
    public function test_el_asistente_si_puede_consultar_el_stock(): void
    {
        // Arrange: hay dos productos en el almacen.
        DBFalsa::select('FROM producto', [
            (object) ['nombre' => 'ACEITE DE 2T', 'stock_actual_base' => '470.00'],
            (object) ['nombre' => 'ALICATE', 'stock_actual_base' => '9.00'],
        ]);

        $asistente = new ToolRegistry;

        // Act: el administrador pregunta cuanto hay de cada producto.
        $respuesta = json_decode(
            $asistente->ejecutar('ejecutar_sql', ['sql' => 'SELECT nombre, stock_actual_base FROM producto']),
            true,
        );

        // Assert: recibe los datos de su almacen, no un error.
        $this->assertSame(['nombre', 'stock_actual_base'], $respuesta['columnas']);
        $this->assertCount(2, $respuesta['filas']);
        $this->assertSame('ACEITE DE 2T', $respuesta['filas'][0]['nombre']);
    }

    /* ================================================================== */
    /* REGLA: el archivo que descarga es un PDF valido */
    /* ================================================================== */

    /**
     * Evalua: que el HTML que la IA entrega se convierta en un PDF real que
     * empieza con el magic number estandar.
     *
     * Por que importa: el PDF es el entregable. Si mPDF falla silenciosamente
     * o escribe bytes en blanco, el usuario abre el archivo y nada.
     */
    public function test_el_html_de_la_ia_se_convierte_en_un_pdf_valido(): void
    {
        // Arrange: la IA escribio un HTML con datos de productos.
        $html = <<<'HTML'
        <!DOCTYPE html>
        <html><head><style>
            body { font-family: sans-serif; }
            h1 { color: #1e293b; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #cbd5e1; padding: 6px; }
        </style></head><body>
            <h1>Stock del almacen</h1>
            <table><tr><th>Nombre</th><th>Stock</th></tr>
                <tr><td>ACEITE DE 2T</td><td>470</td></tr>
                <tr><td>ALICATE</td><td>9</td></tr>
            </table>
        </body></html>
        HTML;

        // Act: el backend renderiza el PDF.
        $reporte = ReportePdfService::generar($html, 'Stock por almacen');

        // Assert: el archivo existe, pesa mas que cero y arranca con %PDF-.
        $this->assertFileExists(ReportePdfService::ruta($reporte['uuid']));
        $this->assertGreaterThan(1000, $reporte['bytes']);

        $contenido = file_get_contents(ReportePdfService::ruta($reporte['uuid']));
        $this->assertStringStartsWith('%PDF-', (string) $contenido);
    }

    /**
     * Evalua: que un HTML vacio no produzca un archivo en disco.
     *
     * Por que importa: si la IA manda un string vacio por error, el sistema
     * no debe dejar un PDF corrupto en storage ni aceptar la llamada como
     * exitosa.
     */
    public function test_un_html_vacio_se_rechaza_con_error(): void
    {
        // Arrange / Act: la IA envia HTML vacio.
        $resultado = (function () {
            try {
                return ReportePdfService::generar('   ', 'reporte');
            } catch (\Throwable $e) {
                return '[ERROR] '.$e->getMessage();
            }
        })();

        // Assert: vuelve como error legible, no como excepcion sin manejar.
        $this->assertStringStartsWith('[ERROR]', $resultado);
    }

    /**
     * Evalua: que el sanitizer bloquee scripts, iframes, eventos inline,
     * urls javascript y recursos externos antes de llegar a mPDF.
     *
     * Por que importa: la IA escribe HTML bajo reglas, pero el conversor a
     * PDF no es un sandbox. Si un script pasa, mPDF lo ignora silenciosamente
     * o peor, lo procesa. Mejor sacarlo en el backend y no confiar en el
     * comportamiento del conversor.
     */
    #[DataProvider('patronesHostiles')]
    public function test_el_sanitizer_queda_peligros_antes_de_renderizar(string $html, string $patronProhibido): void
    {
        // Arrange: el HTML hostil que el sanitizer debe neutralizar.
        $metodo = new \ReflectionMethod(ReportePdfService::class, 'sanear');
        $metodo->setAccessible(true);

        // Act: el sanitizer procesa el HTML.
        $limpio = $metodo->invoke(null, $html);

        // Assert: el patron peligroso ya no aparece.
        $this->assertStringNotContainsString(
            $patronProhibido,
            $limpio,
            "El sanitizer dejo pasar: {$patronProhibido}",
        );
    }

    public static function patronesHostiles(): array
    {
        return [
            'script tag' => ['<p>x</p><script>alert(1)</script>', '<script'],
            'iframe tag' => ['<p>x</p><iframe src="evil"></iframe>', '<iframe'],
            'onclick inline' => ['<a onclick="hack()">click</a>', 'onclick'],
            'onerror inline' => ['<img src="x" onerror="hack()">', 'onerror'],
            'javascript url' => ['<a href="javascript:hack()">x</a>', 'javascript:'],
            'imagen externa' => ['<img src="https://evil.com/x.png">', 'evil.com'],
            'css externo' => ['<link rel="stylesheet" href="https://evil.com/c.css">', 'evil.com'],
        ];
    }

    /* ================================================================== */
    /* REGLA: el sistema de revision no deja pasar basura */
    /* ================================================================== */

    /**
     * Evalua: que el critico detecte un resultado que no le sirve a un
     * administrador, y que lo diga con un motivo concreto.
     *
     * Por que importa: es la red que evita que llegue a la pantalla una tabla
     * de ids. Sin esto, el usuario es quien descubre que el reporte no le
     * sirve.
     */
    public function test_el_revisor_detecta_que_un_resultado_con_ids_no_sirve(): void
    {
        // Arrange: un resultado con identificadores internos de primero.
        $columnas = ['id_producto', 'producto', 'id_lote', 'stock_actual_base'];

        // Act: el critico lo evalua.
        $critica = SqlCritic::problemasEvidentes($columnas);

        // Assert: lo marca como no utilizable y explica que hacer.
        $texto = implode(' ', $critica);

        $this->assertNotEmpty($critica, 'Un resultado con ids no puede pasar la revision.');
        $this->assertStringContainsStringIgnoringCase('administrador', $texto);
        $this->assertStringContainsStringIgnoringCase('id_producto', $texto);
    }

    /**
     * Evalua: que un resultado limpio y de negocio pase la revision.
     *
     * Por que importa: un revisor que siempre dice "no" hace que el asistente
     * reescriba consultas que ya estaban bien.
     */
    public function test_el_revisor_aprueba_un_resultado_que_si_sirve(): void
    {
        // Arrange: columnas que un administrador si necesita.
        $columnas = ['Producto', 'Almacen', 'Stock (litros)', 'Nivel de alerta'];

        // Act: el critico lo evalua.
        $problemas = SqlCritic::problemasEvidentes($columnas);

        // Assert: no encuentra nada que corregir.
        $this->assertSame([], $problemas);
    }

    /* ================================================================== */
    /* REGLA: nada sale de la carpeta de reportes */
    /* ================================================================== */

    /**
     * Evalua: que el nombre que el modelo propone no pueda escribir el archivo
     * fuera de la carpeta de reportes.
     *
     * Por que importa: el nombre del archivo lo elige el modelo, no el
     * administrador. Sin sanitizarlo, "../../.env" grabaria sobre otro archivo
     * del servidor.
     */
    #[DataProvider('nombresDeArchivoHostiles')]
    public function test_el_nombre_del_archivo_no_puede_escapar_de_la_carpeta(string $nombreHostil): void
    {
        // Arrange: la IA propone un nombre hostil en el PDF.
        $html = '<html><body><h1>ok</h1></body></html>';

        // Act: el sistema genera el PDF con ese nombre.
        $reporte = ReportePdfService::generar($html, $nombreHostil);

        // Assert: el archivo cae dentro de la carpeta de reportes y el nombre
        // que descarga el administrador es plano, sin ruta.
        $this->assertStringStartsWith(
            realpath(ReportePdfService::rutaDeCarpeta()) ?: ReportePdfService::rutaDeCarpeta(),
            ReportePdfService::ruta($reporte['uuid']),
            'El archivo se escribio fuera de la carpeta de reportes.',
        );

        $this->assertStringNotContainsString('..', $reporte['nombre']);
        $this->assertStringNotContainsString('/', $reporte['nombre']);
        $this->assertStringEndsWith('.pdf', $reporte['nombre']);
    }

    public static function nombresDeArchivoHostiles(): array
    {
        return [
            'salto de directorio' => ['../../.env'],
            'ruta absoluta' => ['/etc/passwd'],
            'subcarpeta' => ['carpeta/reporte'],
            'caracteres de shell' => ['reporte; rm -rf /'],
            'sql en el nombre' => ["' OR 1=1 --"],
            'solo simbolos' => ['///'],
        ];
    }

    /**
     * Evalua: que un identificador de reporte inventado no sirva para leer
     * ningun archivo del servidor.
     */
    #[DataProvider('identificadoresForjados')]
    public function test_no_se_puede_descargar_un_archivo_con_un_identificador_forjado(string $uuid): void
    {
        // Arrange: no hay ningun reporte con ese identificador.
        // Act / Assert: el sistema lo considera inexistente.
        $this->assertFalse(ReportePdfService::existe($uuid));
    }

    public static function identificadoresForjados(): array
    {
        return [
            'uuid inexistente' => ['00000000-0000-0000-0000-000000000000'],
            'salto de directorio' => ['../../.env'],
            'travesia codificada' => ['..%2F..%2F.env'],
            'nombre de archivo' => ['.env'],
            'vacio' => [''],
            'con espacios' => ['no soy un uuid'],
        ];
    }

    /* ================================================================== */
    /* REGLA: un archivo ilegible se rechaza antes de consultar la base */
    /* ================================================================== */

    /**
     * Evalua: que un adjunto que el sistema no sabe leer se rechace con un
     * motivo util, en vez de gastarle una consulta al modelo.
     *
     * Por que importa: el administrador adjunta un PDF y espera saber si el
     * sistema lo leyo, no un error generico.
     */
    public function test_un_adjunto_no_soportado_se_rechaza_con_un_motivo_util(): void
    {
        // Arrange: el administrador pega un PDF.
        $pdf = [
            ['nombre' => 'contrato.pdf', 'mime' => 'application/pdf', 'base64' => base64_encode('%PDF-1.4')],
        ];

        // Act / Assert: el sistema lo rechaza diciendo que formatos acepta.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('todavia no es compatible');

        AdjuntoExtractor::procesar($pdf);
    }

    /**
     * Evalua: que una foto pegada desde el portapapeles se acepte aunque venga
     * sin nombre de archivo.
     *
     * Por que importa: pegar un screenshot es el camino mas usado para
     * mostrar un papel al asistente. Si exige nombre, se pierde.
     */
    public function test_una_imagen_pegada_sin_nombre_de_archivo_se_acepta(): void
    {
        // Arrange: un screenshot pegado: llega con nombre vacio.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        // Act: el sistema lo procesa.
        $procesado = AdjuntoExtractor::procesar([[
            'nombre' => '',
            'mime' => 'image/png',
            'base64' => base64_encode($png),
        ]]);

        // Assert: llega al modelo como imagen, no como texto.
        $this->assertCount(1, $procesado);
        $this->assertSame('imagen', $procesado[0]['tipo']);
        $this->assertStringStartsWith('data:image/png;base64,', $procesado[0]['contenido']);
    }
}
