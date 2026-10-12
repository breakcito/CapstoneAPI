<?php

namespace Tests\CasosDeUso;

use App\Modules\IA\Service\AdjuntoExtractor;
use App\Modules\IA\Service\ReportGenerator;
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
 *   - El archivo que le llega al administrador existe, es un Excel valido y no
 *     puede escribirse fuera de la carpeta de reportes.
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
        foreach (File::files(ReportGenerator::rutaDeCarpeta()) as $archivo) {
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
    /* REGLA: el archivo que descarga es un Excel usable */
    /* ================================================================== */

    /**
     * Evalua: que el reporte generado se pueda abrir y leer.
     *
     * Por que importa: el archivo es el entregable. Un .xlsx corrupto es un
     * modulo inservible aunque el SQL sea correcto.
     */
    public function test_el_reporte_generado_es_un_excel_que_se_puede_leer(): void
    {
        // Arrange: el administrador pide el inventario por producto.
        DBFalsa::select('FROM producto', [
            (object) ['nombre' => 'ACEITE DE 2T', 'stock_actual_base' => '470.00'],
        ]);

        // Act: el sistema arma el archivo.
        $reporte = ReportGenerator::generar(
            'SELECT nombre, stock_actual_base FROM producto',
            'Inventario por producto',
        );

        // Assert: el archivo existe y su contenido se puede recuperar.
        $this->assertFileExists(ReportGenerator::ruta($reporte['uuid']));

        $filas = $this->leerFilasDelExcel($reporte['uuid']);

        $this->assertSame(
            ['Nombre', 'Stock (unidad base)'],
            $filas[0],
            'El encabezado del reporte debe leerse como lo diria un administrador, '
            .'no como el nombre de la columna en la base.',
        );
        $this->assertSame(['ACEITE DE 2T', '470.00'], $filas[1]);
    }

    /**
     * Evalua: que un reporte con varias tematic llegue en un solo archivo.
     *
     * Por que importa: un "reporte general de inventario" que se descarga en
     * cinco archivos sueltos obliga al administrador a unirlos a mano.
     */
    public function test_un_reporte_general_llega_como_un_solo_archivo_con_una_hoja_por_tematica(): void
    {
        // Arrange: tres consultas distintas, como las de un reporte general.
        DBFalsa::select('FROM producto', [(object) ['nombre' => 'ACEITE']]);
        DBFalsa::select('FROM kardex_producto', [(object) ['tipo_movimiento' => 'Salida']]);
        DBFalsa::select('FROM lote_producto', [(object) ['stock_actual_base' => '10']]);

        // Act: el sistema arma un archivo con las tres tematicas.
        $reporte = ReportGenerator::generarConSecciones([
            ['nombre' => 'Inventario', 'sql' => 'SELECT nombre FROM producto'],
            ['nombre' => 'Kardex', 'sql' => 'SELECT tipo_movimiento FROM kardex_producto'],
            ['nombre' => 'Vencimiento', 'sql' => 'SELECT stock_actual_base FROM lote_producto'],
        ], 'Reporte general');

        // Assert: un archivo, tres hojas, cada una con su contenido.
        $this->assertCount(3, $reporte['hojas']);
        $this->assertSame(['Inventario', 'Kardex', 'Vencimiento'], $reporte['hojas']);

        $hojas = $this->leerHojasDelExcel($reporte['uuid']);

        $this->assertCount(3, $hojas);
        // indice 0 es el encabezado humano, indice 1 el primer dato real.
        $this->assertSame(['Nombre'], $hojas['Inventario'][0]);
        $this->assertSame(['ACEITE'], $hojas['Inventario'][1]);
        $this->assertSame(['Tipo Movimiento'], $hojas['Kardex'][0]);
        $this->assertSame(['Salida'], $hojas['Kardex'][1]);
    }

    /**
     * Evalua: que una seccion sin datos no rompa el reporte.
     *
     * Por que importa: el administrador pide un mes donde no hubo movimientos.
     * El archivo debe salir igual, con esa hoja vacia pero presente.
     */
    public function test_una_seccion_sin_datos_no_rompe_el_reporte(): void
    {
        // Arrange: dos tematicas, una de ellas vacia.
        DBFalsa::select('FROM producto', [(object) ['nombre' => 'ACEITE']]);
        DBFalsa::select('FROM kardex_producto', []);

        // Act: el sistema arma el archivo.
        $reporte = ReportGenerator::generarConSecciones([
            ['nombre' => 'Inventario', 'sql' => 'SELECT nombre FROM producto'],
            ['nombre' => 'Kardex del mes', 'sql' => 'SELECT tipo_movimiento FROM kardex_producto'],
        ], 'Reporte con hueco');

        // Assert: el archivo sale con las dos hojas y la vacia lo dice.
        $this->assertCount(2, $reporte['hojas']);

        $hojas = $this->leerHojasDelExcel($reporte['uuid']);

        $this->assertArrayHasKey('Kardex del mes', $hojas);
        $this->assertStringContainsString(
            'sin registros',
            implode(' ', $hojas['Kardex del mes'][0]),
            'La hoja sin datos debe decirlo, no quedar en blanco sin explicación.',
        );
    }

    /**
     * Evalua: que el Excel no traiga identificadores internos de la base.
     *
     * Por que importa: "Id Producto 1", "Id Lote 3" y "Created At" son claves de
     * la base. Un administrador que abre el archivo no puede hacer nada con ellas
     * y el reporte se ve como algo roto.
     */
    public function test_el_excel_no_muestra_identificadores_internos(): void
    {
        // Arrange: el modelo devuelve el SELECT con los ids incluidos.
        DBFalsa::select('FROM producto', [[
            'id_producto' => 7,
            'producto' => 'ACEITE DE 2T',
            'id_almacen' => 3,
            'almacen' => 'Almacén Algamarca',
            'stock_actual_base' => '470.00',
            'created_at' => '2026-10-01 08:00:00',
        ]]);

        // Act: se genera el reporte.
        $reporte = ReportGenerator::generar(
            'SELECT * FROM producto',
            'Stock por producto',
        );

        // Assert: en el archivo solo quedan las columnas de negocio.
        $filas = $this->leerFilasDelExcel($reporte['uuid']);
        $encabezado = implode(' | ', $filas[0]);

        $this->assertSame('Producto | Almacen | Stock (unidad base)', $encabezado);

        $this->assertStringNotContainsStringIgnoringCase('id producto', $encabezado);
        $this->assertStringNotContainsStringIgnoringCase('id almacen', $encabezado);
        $this->assertStringNotContainsStringIgnoringCase('created at', $encabezado);
    }

    /**
     * Evalua: que un nombre de columna tecnico se lea como lo diria un
     * administrador.
     *
     * Por que importa: `contenido_por_presentacion` dice cuantas unidades base
     * trae una presentacion. Es informacion de negocio clave para calcular
     * existencias, pero como encabezado de base no se entiende y la gente lo
     * pasa por alto.
     */
    public function test_los_encabezados_tecnicos_se_traducen_a_lenguaje_de_negocio(): void
    {
        // Arrange: el modelo devuelve columnas con nombres de la base.
        DBFalsa::select('FROM producto', [[
            'producto' => 'ACEITE DE 2T',
            'contenido_por_presentacion' => '12.00',
            'stock_actual_base' => '470.00',
            'costo_por_unidad_base' => '35.00',
        ]]);

        // Act: se genera el reporte.
        $reporte = ReportGenerator::generar(
            'SELECT * FROM producto',
            'Detalle de stock',
        );

        // Assert: los tres encabezados se leen como los entiende el negocio.
        $filas = $this->leerFilasDelExcel($reporte['uuid']);

        $this->assertSame(
            ['Producto', 'Unidades base por presentacion', 'Stock (unidad base)', 'Costo unitario (unidad base)'],
            $filas[0],
        );
    }

    /**
     * Evalua: que el revisor detecte un resultado que no le sirve a un
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
        $columnas = ['Producto', 'Almacen', 'Stock (unidad base)', 'Nivel de alerta'];

        // Act: el critico lo evalua.
        $problemas = SqlCritic::problemasEvidentes($columnas);

        // Assert: no encuentra nada que corregir.
        $this->assertSame([], $problemas);
    }

    /* ================================================================== */
    /* REGLA: nada sale de la carpeta de reportes */
    /* ================================================================== */

    /**
     * Evalua: que un "reporte de stock" simple llegue como UNA sola hoja.
     *
     * Por que importa: el administrador pidio una tabla de stock, no un
     * informe. Cuatro hojas obligan a abrir el archivo y a adivinar cual
     * tiene lo que busca. Si el modelo se pasa de creativo, el sistema debe
     * recortar, no obedecer.
     */
    public function test_un_reporte_simple_llega_como_una_sola_hoja(): void
    {
        // Arrange: hay stock en el almacen y el modelo se inventa 4 secciones.
        DBFalsa::select('FROM producto', [(object) ['nombre' => 'ACEITE', 'stock_actual_base' => '470.00']]);
        DBFalsa::select('FROM kardex_producto', [(object) ['tipo_movimiento' => 'Salida']]);
        DBFalsa::select('FROM lote_producto', [(object) ['fecha_vencimiento' => '2027-01-01']]);
        DBFalsa::select('FROM requerimiento_almacen', [(object) ['correlativo' => 'RP-001']]);

        $asistente = new ToolRegistry;

        // Act: el administrador pide "un reporte de stock" y el modelo
        // responde con cuatro tematicas en vez de una tabla.
        $respuesta = json_decode($asistente->ejecutar('generar_reporte', [
            'nombre_archivo' => 'Reporte de stock',
            'secciones' => [
                ['nombre' => 'Stock por producto', 'sql' => 'SELECT nombre, stock_actual_base FROM producto'],
                ['nombre' => 'Stock valorizado', 'sql' => 'SELECT tipo_movimiento FROM kardex_producto'],
                ['nombre' => 'Alertas de stock', 'sql' => 'SELECT fecha_vencimiento FROM lote_producto'],
                ['nombre' => 'Vencimientos', 'sql' => 'SELECT correlativo FROM requerimiento_almacen'],
            ],
        ]), true);

        // Assert: el administrador recibe una hoja, no cuatro.
        $this->assertCount(
            1,
            $respuesta['hojas'],
            'Un reporte de stock es UNA hoja. El modelo no decide convertirlo en un informe.',
        );

        $hojas = $this->leerHojasDelExcel($respuesta['uuid']);

        $this->assertCount(1, $hojas);
        $this->assertArrayHasKey('Stock por producto', $hojas);
    }

    /**
     * Evalua: que las secciones que el usuario SI pidio se respeten.
     *
     * Por que importa: el recorte anterior no puede volverse un tope fijo que
     * impida un "reporte completo con alertas y rotacion" de verdad.
     */
    public function test_un_reporte_de_varias_hojas_si_pedidas_se_respeta(): void
    {
        // Arrange: el administrador pide un reporte completo.
        DBFalsa::select('FROM producto', [(object) ['nombre' => 'ACEITE']]);
        DBFalsa::select('FROM kardex_producto', [(object) ['tipo_movimiento' => 'Salida']]);
        DBFalsa::select('FROM lote_producto', [(object) ['stock_actual_base' => '10']]);

        $asistente = new ToolRegistry;

        // Act: el modelo entrega las tres tematicas pedidas.
        $respuesta = json_decode($asistente->ejecutar('generar_reporte', [
            'nombre_archivo' => 'Reporte completo',
            'secciones' => [
                ['nombre' => 'Inventario', 'sql' => 'SELECT nombre FROM producto'],
                ['nombre' => 'Kardex', 'sql' => 'SELECT tipo_movimiento FROM kardex_producto'],
                ['nombre' => 'Vencimientos', 'sql' => 'SELECT stock_actual_base FROM lote_producto'],
            ],
        ]), true);

        // Assert: las tres hojas llegan.
        $this->assertSame(['Inventario', 'Kardex', 'Vencimientos'], $respuesta['hojas']);
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
        // Arrange: hay datos y el modelo propone un nombre hostil.
        DBFalsa::select('FROM producto', [(object) ['nombre' => 'ACEITE']]);

        // Act: el sistema genera el reporte con ese nombre.
        $reporte = ReportGenerator::generar('SELECT nombre FROM producto', $nombreHostil);

        // Assert: el archivo cae dentro de la carpeta de reportes y el nombre
        // que descarga el administrador es plano, sin ruta.
        $this->assertStringStartsWith(
            realpath(ReportGenerator::rutaDeCarpeta()) ?: ReportGenerator::rutaDeCarpeta(),
            ReportGenerator::ruta($reporte['uuid']),
            'El archivo se escribio fuera de la carpeta de reportes.',
        );

        $this->assertStringNotContainsString('..', $reporte['nombre']);
        $this->assertStringNotContainsString('/', $reporte['nombre']);
        $this->assertStringEndsWith('.xlsx', $reporte['nombre']);
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
        $this->assertFalse(ReportGenerator::existe($uuid));
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
     * Evalua: que un adjunto que el sistema no sabe leer se rechude con un
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

    /* ================================================================== */
    /* Ayudantes de lectura */
    /* ================================================================== */

    /**
     * @return array<int, array<int, string>>
     */
    private function leerFilasDelExcel(string $uuid): array
    {
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open(ReportGenerator::ruta($uuid));

        $filas = [];

        foreach ($reader->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = array_map(
                    static fn ($celda) => (string) $celda->getValue(),
                    $fila->getCells(),
                );
            }

            break;
        }

        $reader->close();

        return $filas;
    }

    /**
     * @return array<string, array<int, array<int, string>>>
     */
    private function leerHojasDelExcel(string $uuid): array
    {
        $reader = new \OpenSpout\Reader\XLSX\Reader;
        $reader->open(ReportGenerator::ruta($uuid));

        $hojas = [];

        foreach ($reader->getSheetIterator() as $hoja) {
            $filas = [];

            foreach ($hoja->getRowIterator() as $fila) {
                $filas[] = array_map(
                    static fn ($celda) => (string) $celda->getValue(),
                    $fila->getCells(),
                );
            }

            $hojas[$hoja->getName()] = $filas;
        }

        $reader->close();

        return $hojas;
    }
}
