<?php

namespace App\Shared\Helpers;

/**
 * Helper para embeber `url_logo` (URL absoluta, ruta relativa o data URL)
 * como `data:image/...;base64,...` para que pueda ser consumido
 * client-side por `@react-pdf/renderer` sin problemas de CORS ni de
 * path relativo (porque react-pdf fetcha desde el navegador, no desde
 * el backend que sirve el PDF).
 *
 * Si el logo ya viene como `data:` se devuelve tal cual.
 * Si el archivo fisico no existe, retorna null para que el frontend pueda
 * condicionar el render con `if (url)`.
 */
class LogoEmbedder
{
    private const MIME_BY_EXT = [
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    /**
     * Convierte un logo de empresa/proveedor a una `data:` URL base64.
     *
     * @param string|null $logo Valor almacenado en `url_logo`.
     */
    public static function embed(?string $logo): ?string
    {
        if ($logo === null || $logo === '') {
            return null;
        }

        // Si ya es data URL, devolver tal cual.
        if (str_starts_with($logo, 'data:')) {
            return $logo;
        }

        // Resolver a ruta relativa dentro de storage/app/public.
        if (str_starts_with($logo, 'http')) {
            $parsed = parse_url($logo, PHP_URL_PATH);
            $relativePath = ltrim(str_replace('/storage/', '', (string) $parsed), '/');
        } else {
            $relativePath = ltrim($logo, '/');
        }

        $fullPath = storage_path('app/public/' . $relativePath);
        if (!file_exists($fullPath)) {
            return null;
        }

        $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $mime = self::MIME_BY_EXT[$ext] ?? 'image/jpeg';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($fullPath));
    }
}
