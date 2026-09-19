<?php

namespace App\Shared\Helpers;

class ArchivoBase64Helper
{
    /**
     * Convierte una URL (relativa o absoluta) o un path a data URL base64.
     * Si ya es data URL, la retorna tal cual. Si el archivo no existe,
     * retorna null.
     */
    public static function toBase64(?string $logo): ?string
    {
        if (empty($logo)) {
            return null;
        }
        if (str_starts_with($logo, 'data:')) {
            return $logo;
        }

        if (str_starts_with($logo, 'http')) {
            $parsed = parse_url($logo, PHP_URL_PATH);
            $relativePath = ltrim(str_replace('/storage/', '', $parsed ?? ''), '/');
        } else {
            $relativePath = ltrim($logo, '/');
        }

        if ($relativePath === '') {
            return null;
        }

        $fullPath = storage_path('app/public/'.$relativePath);
        if (! file_exists($fullPath)) {
            return null;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullPath));
    }
}
