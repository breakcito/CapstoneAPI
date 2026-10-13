<?php

namespace App\Modules\IA\Service;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Convierte HTML+CSS que escribe la IA en un PDF descargable.
 *
 * La IA entrega HTML autocontenido (con CSS en <style>, sin <script>, sin
 * recursos externos). El backend lo sanea, lo envuelve en una pagina A4 con
 * margenes razonables, lo renderiza con mPDF y lo guarda en disco.
 *
 * El sanitizador no es defensivo contra TODO: la IA escribe HTML bajo reglas
 * que el system prompt fija. Si el HTML viene roto, mPDF lanza excepcion y
 * el error vuelve al modelo para que lo corrija.
 */
class ReportePdfService
{
    private const CARPETA = 'ia/reportes';

    private const MAX_BYTES = 2_000_000;

    /**
     * Renderiza el HTML y guarda el PDF.
     *
     * @return array{uuid: string, nombre: string, bytes: int}
     *
     * @throws RuntimeException si el HTML esta vacio, es hostil o mPDF falla
     */
    public static function generar(string $html, string $nombre): array
    {
        $html = trim($html);

        if ($html === '') {
            throw new RuntimeException('El HTML del reporte esta vacio.');
        }

        if (strlen($html) > self::MAX_BYTES) {
            throw new RuntimeException(
                'El HTML del reporte supera el limite de '
                .(self::MAX_BYTES / 1_000_000).' MB.',
            );
        }

        $html = self::sanear($html);

        $nombreArchivo = self::sanearNombre($nombre);
        $uuid = (string) Str::uuid();
        $path = self::ruta($uuid);

        File::ensureDirectoryExists(dirname($path));

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 14,
            'margin_right' => 14,
            'margin_top' => 18,
            'margin_bottom' => 18,
            'margin_header' => 8,
            'margin_footer' => 8,
            'tempDir' => storage_path('app/mpdf-temp'),
        ]);

        File::ensureDirectoryExists(storage_path('app/mpdf-temp'));

        try {
            $mpdf->WriteHTML($html);
            $mpdf->Output($path, 'F');
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'No se pudo renderizar el PDF: '.$e->getMessage(),
                0,
                $e,
            );
        }

        clearstatcache(true, $path);
        $bytes = (int) filesize($path);

        if ($bytes === 0) {
            throw new RuntimeException('El PDF se genero vacio.');
        }

        return [
            'uuid' => $uuid,
            'nombre' => $nombreArchivo,
            'bytes' => $bytes,
        ];
    }

    /**
     * Quita lo que el modelo nunca deberia mandar: scripts, iframes, eventos
     * inline, urls javascript, recursos externos.
     *
     * No intenta arreglar HTML mal cerrado: eso es trabajo del modelo.
     */
    private static function sanear(string $html): string
    {
        $html = (string) preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', '', $html);
        $html = (string) preg_replace('/<iframe\b[^>]*>.*?<\/iframe\s*>/is', '', $html);
        $html = (string) preg_replace('/<object\b[^>]*>.*?<\/object\s*>/is', '', $html);
        $html = (string) preg_replace('/<embed\b[^>]*\/?>/is', '', $html);
        $html = (string) preg_replace('/<form\b[^>]*>.*?<\/form\s*>/is', '', $html);
        $html = (string) preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $html);
        $html = (string) preg_replace("/\son[a-z]+\s*=\s*'[^']*'/i", '', $html);
        $html = (string) preg_replace('/\son[a-z]+\s*=\s*[^\s>]+/i', '', $html);
        $html = (string) preg_replace('/href\s*=\s*"javascript:[^"]*"/i', 'href="#"', $html);
        $html = (string) preg_replace("/href\s*=\s*'javascript:[^']*'/i", "href='#'", $html);
        $html = (string) preg_replace('/src\s*=\s*"javascript:[^"]*"/i', 'src=""', $html);
        $html = (string) preg_replace("/src\s*=\s*'javascript:[^']*'/i", "src=''", $html);
        $html = (string) preg_replace('/src\s*=\s*"https?:\/\/[^"]+"/i', 'src=""', $html);
        $html = (string) preg_replace("/src\s*=\s*'https?:\/\/[^']+'/i", "src=''", $html);
        $html = (string) preg_replace('/href\s*=\s*"https?:\/\/[^"]+"/i', 'href="#"', $html);
        $html = (string) preg_replace("/href\s*=\s*'https?:\/\/[^']+'/i", "href='#'", $html);

        return trim($html);
    }

    private static function sanearNombre(string $nombre): string
    {
        $nombre = preg_replace('/[^A-Za-z0-9_\- ]/u', '', $nombre) ?? 'reporte';
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? '');
        $nombre = mb_substr($nombre === '' ? 'reporte' : $nombre, 0, 80);

        if (! str_ends_with(strtolower($nombre), '.pdf')) {
            $nombre .= '.pdf';
        }

        return $nombre;
    }

    /**
     * Ruta absoluta del archivo en disco.
     */
    public static function ruta(string $uuid): string
    {
        return Storage::disk('local')->path(self::CARPETA.'/'.$uuid.'.pdf');
    }

    /**
     * Carpeta donde se guardan los PDFs.
     */
    public static function rutaDeCarpeta(): string
    {
        return Storage::disk('local')->path(self::CARPETA);
    }

    public static function existe(string $uuid): bool
    {
        return self::uuidValido($uuid)
            && Storage::disk('local')->exists(self::CARPETA.'/'.$uuid.'.pdf');
    }

    public static function nombreDe(string $uuid): string
    {
        return $uuid.'.pdf';
    }

    public static function uuidValido(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid,
        );
    }

    public static function descargar(string $uuid, string $nombre): StreamedResponse
    {
        $ruta = self::ruta($uuid);

        return response()->stream(
            function () use ($ruta) {
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
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) filesize($ruta),
                'Content-Disposition' => 'attachment; filename="'.basename($nombre).'"',
                'Cache-Control' => 'no-store',
            ],
        );
    }
}
