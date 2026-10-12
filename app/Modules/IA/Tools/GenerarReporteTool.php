<?php

namespace App\Modules\IA\Tools;

use App\Modules\IA\Service\ReportGenerator;

/**
 * Materializa el SQL propuesto por el modelo en un .xlsx descargable.
 *
 * El modelo no escribe bytes: devuelve el SQL y el backend produce el archivo
 * con OpenSpout. El cliente lo descarga luego por su uuid.
 */
class GenerarReporteTool implements ToolInterface
{
    public function nombre(): string
    {
        return 'generar_reporte';
    }

    public function definicion(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->nombre(),
                'description' => 'Genera un archivo Excel (.xlsx) descargable a partir de consultas SQL de SOLO LECTURA. '
                    .'USALA SIEMPRE que el usuario pida un "reporte", "exportar", "excel", "archivo", '
                    .'"descargar" o "pasame los datos", aunque NO haya dicho la palabra excel: en ese caso '
                    .'quiere un archivo, no un texto largo en el chat. Llama a esta herramienta PRIMERO, sin '
                    .'anunciarlo antes, y despues responde en una o dos lineas diciendo que ya puede descargarlo. '
                    .'IMPORTANTE: por defecto genera UNA SOLA HOJA a partir de un solo SQL. "Reporte de stock" '
                    .'es UNA hoja con una fila por producto: NO lo conviertas en varias hojas aunque el tema '
                    .'suene amplio. Usa "secciones" (una hoja por tematica) UNICAMENTE si el usuario pidio '
                    .'explicitamente mas de una: "reporte completo", "con alertas y rotacion", etc. '
                    .'El archivo lo produce el servidor; tu solo aportas el SQL, el nombre y las secciones. '
                    .'El SQL se trunca al tope de filas configurado, asi que incluye las columnas que el '
                    .'usuario pidio ver, sin filtros de paginacion. '
                    .'Este modulo NO genera PDF: si lo piden, di que solo puedes entregar Excel y ofrece '
                    .'convertirlo desde el Excel o generar una hoja por seccion.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sql' => [
                            'type' => 'string',
                            'description' => 'Consulta SQL de solo lectura que define el contenido del archivo. Empieza por SELECT o WITH. Omitela si usas "secciones".',
                        ],
                        'nombre_archivo' => [
                            'type' => 'string',
                            'description' => 'Nombre sugerido del archivo en español, sin extension. Ej: "Reporte general de inventario".',
                        ],
                        'secciones' => [
                            'type' => 'array',
                            'description' => 'Para un reporte con varias tematic (valorizado, alertas, rotacion, KPIs), envia una seccion por hoja del mismo archivo. Cada elemento lleva su propio SQL y produce una hoja. Maximo 12.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'nombre' => [
                                        'type' => 'string',
                                        'description' => 'Titulo corto de la hoja, en español. Ej: "Stock valorizado", "Alertas de stock".',
                                    ],
                                    'sql' => [
                                        'type' => 'string',
                                        'description' => 'Consulta SQL de solo lectura para esa hoja.',
                                    ],
                                ],
                                'required' => ['nombre', 'sql'],
                            ],
                        ],
                    ],
                    'required' => ['nombre_archivo'],
                ],
            ],
        ];
    }

    /**
     * Tope de hojas sin que el usuario las pidiera.
     *
     * Un "reporte de stock" son 4 filas en una hoja. Cuatro hojas son un
     * informe que el administrador no pidio y que luego tiene que devolver
     * cada vez que alguien pregunte por el mismo dato.
     */
    private const HOJAS_SIN_PEDIDO = 3;

    public function ejecutar(array $args): string
    {
        $nombre = trim((string) ($args['nombre_archivo'] ?? ''));

        if ($nombre === '') {
            $nombre = 'reporte';
        }

        // Dos formas de pedirlo: un solo SQL, o "secciones" para un archivo
        // con varias hojas. El modelo usa la segunda para reportes amplios.
        $secciones = $args['secciones'] ?? [];
        $sql = trim((string) ($args['sql'] ?? ''));

        // Si el modelo se pasa de creativo, se recorta a una sola hoja: el
        // administrador recibe el reporte pedido, no el que el modelo imagino.
        if (is_array($secciones) && $secciones !== [] && count($secciones) > self::HOJAS_SIN_PEDIDO) {
            $secciones = array_slice($secciones, 0, 1);
        }

        if (is_array($secciones) && $secciones !== []) {
            try {
                $reporte = ReportGenerator::generarConSecciones($secciones, $nombre);
            } catch (\Throwable $e) {
                return '[ERROR] '.$e->getMessage();
            }
        } elseif ($sql !== '') {
            try {
                $reporte = ReportGenerator::generar($sql, $nombre);
            } catch (\Throwable $e) {
                return '[ERROR] '.$e->getMessage();
            }
        } else {
            return '[ERROR] Falta el parametro "sql" o la lista "secciones" con al menos un SQL.';
        }

        return json_encode([
            'uuid' => $reporte['uuid'],
            'nombre' => $reporte['nombre'],
            'filas' => $reporte['filas'],
            'hojas' => $reporte['hojas'],
            'mensaje' => 'Archivo generado. Coméntale al usuario que ya puede descargarlo desde el chat.',
        ], JSON_UNESCAPED_UNICODE);
    }
}
