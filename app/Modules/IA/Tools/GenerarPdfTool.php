<?php

namespace App\Modules\IA\Tools;

use App\Modules\IA\Service\ReportePdfService;

/**
 * Convierte el HTML+CSS que escribe la IA en un PDF descargable.
 *
 * La IA arma el HTML completo a partir de los datos que ya consulto. El
 * backend lo sanea, lo renderiza con mPDF y devuelve el UUID para descarga.
 *
 * El HTML debe ser autocontenido: CSS en <style>, sin <script>, sin recursos
 * externos. El system prompt fija las reglas.
 */
class GenerarPdfTool implements ToolInterface
{
    public function nombre(): string
    {
        return 'generar_reporte_pdf';
    }

    public function definicion(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->nombre(),
                'description' => 'Convierte HTML+CSS que tu escribiste en un PDF descargable. '
                    .'USALA cuando el usuario pida un "reporte", "exportar", "pdf", "archivo", "descargar" '
                    .'o "pasame los datos". Antes de llamarla, ejecuta todas las consultas SQL que '
                    .'requieras para tener los datos completos (puedes llamar ejecutar_sql varias veces '
                    .'para totales, alertas, comparaciones, top productos). El HTML+CSS debe ser '
                    .'autocontenido: nada de <script>, nada de <iframe>, nada de recursos externos '
                    .'(<link>, <img src=URL externa>), nada de eventos inline (onclick=, onerror=). '
                    .'CSS en <style> dentro del HTML. CSS soportado: tablas, padding, margin, border, '
                    .'color, background-color, font-*, width, height, text-align, vertical-align, '
                    .'display:block. Evita flexbox complejo, grid, position:fixed, animaciones, '
                    .'transform. Despues de llamarla responde en UNA linea: "Listo, ya puedes '
                    .'descargar el reporte." Sin mencionar la herramienta ni el proceso.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'html' => [
                            'type' => 'string',
                            'description' => 'Documento HTML completo y autocontenido. Debe empezar con <!DOCTYPE html> o <html>, incluir <style> con el CSS, y todo el cuerpo del reporte en <body>.',
                        ],
                        'nombre_archivo' => [
                            'type' => 'string',
                            'description' => 'Nombre sugerido del archivo en espanol, sin extension. Ej: "Reporte de stock por almacen".',
                        ],
                    ],
                    'required' => ['html', 'nombre_archivo'],
                ],
            ],
        ];
    }

    public function ejecutar(array $args): string
    {
        $html = (string) ($args['html'] ?? '');
        $nombre = trim((string) ($args['nombre_archivo'] ?? ''));

        if ($nombre === '') {
            $nombre = 'reporte';
        }

        try {
            $reporte = ReportePdfService::generar($html, $nombre);
        } catch (\Throwable $e) {
            return '[ERROR] '.$e->getMessage();
        }

        return json_encode([
            'uuid' => $reporte['uuid'],
            'nombre' => $reporte['nombre'],
            'bytes' => $reporte['bytes'],
            'mensaje' => 'Archivo generado. Comenta al usuario en una linea que ya puede descargarlo.',
        ], JSON_UNESCAPED_UNICODE);
    }
}
