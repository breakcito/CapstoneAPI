<?php

namespace App\Controllers;

use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ArchivoController extends Controller
{
    /**
     * Sube un archivo al storage y devuelve sus metadatos (IArchivo).
     * Usado por el frontend para guardar fotos/evidencias antes de referenciarlas por URL.
     *
     * Recibe multipart: `archivo` (single file), `carpeta` (opcional, default "evidencias").
     */
    public function upload_archivo(Request $request): JsonResponse
    {
        if (! $request->hasFile('archivo')) {
            return response()->json(ApiResponse::error('No se envió ningún archivo en el campo "archivo".'), 422);
        }

        $carpeta = $request->input('carpeta', 'evidencias');
        $carpeta = is_string($carpeta) ? trim($carpeta) : 'evidencias';

        $archivos = ArchivoHelper::guardarArchivos($carpeta, [$request->file('archivo')]);

        if (empty($archivos)) {
            return response()->json(ApiResponse::error('No se pudo guardar el archivo.'), 500);
        }

        return response()->json(ApiResponse::success(
            $archivos[0],
            'Archivo subido correctamente',
        ));
    }

    /**
     * Endpoint para descarga de archivos.
     */
    public function download_archivo(Request $request): Response
    {
        $pathRelativo = $request->input('path_relativo');

        if (! $pathRelativo) {
            return response()->json(ApiResponse::error('Ruta de archivo (path_relativo) requerida'), 400);
        }

        $fullPath = storage_path('app/public/'.ltrim($pathRelativo, '/'));

        if (! file_exists($fullPath)) {
            return response()->json(ApiResponse::error('Archivo no encontrado en el servidor'), 404);
        }

        return response()->download($fullPath);
    }

    /**
     * Sirve una imagen del storage con headers CORS correctos.
     * Usado por react-pdf para cargar logos de empresas en PDFs.
     */
    public function serve_imagen(Request $request, string $path): Response
    {
        $fullPath = storage_path('app/public/'.ltrim($path, '/'));

        if (! file_exists($fullPath)) {
            return response()->json(['error' => 'Imagen no encontrada'], 404);
        }

        // Detectar MIME por extensión (más confiable en Windows que mime_content_type)
        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mimeType = match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        $contenido = file_get_contents($fullPath);

        return response($contenido, 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => strlen($contenido),
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Proxy de imágenes externas (CDN, S3, etc.).
     * El backend descarga la URL remota con Http y devuelve el blob con CORS
     * abierto, evitando que el navegador sufra preflight CORS al pedirla
     * desde el frontend.
     *
     * Pensado para fotochecks de contratistas cuya foto vive fuera del
     * storage de Laravel.
     */
    public function descargar_externo(Request $request): Response
    {
        $url = $request->input('url');

        if (! $url || ! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(
                ApiResponse::error('Se requiere una URL válida en el parámetro "url".'),
                422,
            );
        }

        // Solo permitir http(s). Bloquear file://, data://, etc.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return response()->json(
                ApiResponse::error('Solo se permiten URLs http(s).'),
                422,
            );
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => 'image/*'])
                ->get($url);

            if (! $response->successful()) {
                return response()->json(
                    ApiResponse::error("No se pudo descargar la imagen externa (HTTP {$response->status()})."),
                    502,
                );
            }

            $contenido = $response->body();
            $contentType = $response->header('Content-Type') ?: 'image/jpeg';

            // Si el servidor externo no envía un MIME reconocible, forzar image/jpeg.
            if (! str_starts_with(strtolower($contentType), 'image/')) {
                $contentType = 'image/jpeg';
            }

            return response($contenido, 200, [
                'Content-Type' => $contentType,
                'Content-Length' => strlen($contenido),
                'Access-Control-Allow-Origin' => '*',
                'Cache-Control' => 'public, max-age=300',
            ]);
        } catch (\Throwable $e) {
            return response()->json(
                ApiResponse::error('Error al descargar la imagen externa: ' . $e->getMessage()),
                502,
            );
        }
    }
}
