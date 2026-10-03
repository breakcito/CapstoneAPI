<?php

namespace App\Shared\Helpers;

/**
 * Helper para validar uploads de archivos en PHP / Laravel.
 *
 * Por que existe:
 * - Cuando un archivo excede `upload_max_filesize` o `post_max_size` del
 *   php.ini, PHP lo descarta ANTES de que Laravel pueda inspeccionarlo.
 *   El resultado: la regla `file` de Laravel falla con un mensaje generico
 *   del tipo "evidencias.0 failed to upload" sin explicar que el problema
 *   es el tamano.
 * - Esta clase revisa `$_FILES` y `$request->file()` para detectar los
 *   codigos UPLOAD_ERR_* y devolver un mensaje claro para el front.
 */
class UploadHelper
{
    public const MAX_FILE_BYTES = 25 * 1024 * 1024; // 25 MB
    public const MAX_TOTAL_BYTES = 30 * 1024 * 1024; // 30 MB

    /**
     * Mapea el codigo UPLOAD_ERR_* a un mensaje legible.
     */
    public static function mensaje_error_upload(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamano maximo permitido por el servidor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamano maximo del formulario.',
            UPLOAD_ERR_PARTIAL => 'El archivo se subio solo parcialmente. Intentalo de nuevo.',
            UPLOAD_ERR_NO_FILE => 'No se recibio ningun archivo.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene un directorio temporal configurado para uploads.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo en disco.',
            UPLOAD_ERR_EXTENSION => 'Una extension de PHP detuvo la subida del archivo.',
            default => 'Error desconocido al subir el archivo (codigo ' . $errorCode . ').',
        };
    }

    /**
     * Valida los archivos recibidos en el request. Devuelve un array de
     * mensajes de error legibles, vacio si todo esta OK.
     *
     * Reglas:
     * 1. Verifica que cada archivo no tenga UPLOAD_ERR_* distinto de UPLOAD_ERR_NO_FILE.
     *    (UPLOAD_ERR_NO_FILE = 4 = "no se envio", lo tratamos como OK
     *    para no romper submits sin adjuntos).
     * 2. Verifica tamano por archivo (25 MB por defecto).
     * 3. Verifica tamano total acumulado (30 MB por defecto).
     *
     * Llamar ANTES de las reglas de validacion de Laravel para que los
     * mensajes sean utiles.
     */
    public static function validar(\Illuminate\Http\Request $request, string $campo = 'evidencias'): array
    {
        $errores = [];
        $archivos = $request->file($campo, []);

        // Caso: no se envio nada
        if (empty($archivos)) {
            return $errores;
        }

        // Normalizar a array (puede venir un solo archivo o un array)
        if (!is_array($archivos)) {
            $archivos = [$archivos];
        }

        $totalBytes = 0;

        foreach ($archivos as $index => $archivo) {
            if ($archivo === null || $archivo === false) {
                continue;
            }

            if (!$archivo->isValid()) {
                $errores[] = self::mensaje_error_upload($archivo->getError());
                continue;
            }

            $size = (int) $archivo->getSize();
            $totalBytes += $size;

            if ($size > self::MAX_FILE_BYTES) {
                $errores[] = sprintf(
                    'El archivo "%s" (%s) excede el tamano maximo permitido (%s).',
                    $archivo->getClientOriginalName(),
                    self::formatear_bytes($size),
                    self::formatear_bytes(self::MAX_FILE_BYTES),
                );
            }
        }

        if ($totalBytes > self::MAX_TOTAL_BYTES) {
            $errores[] = sprintf(
                'El total de archivos (%s) excede el tamano maximo permitido (%s).',
                self::formatear_bytes($totalBytes),
                self::formatear_bytes(self::MAX_TOTAL_BYTES),
            );
        }

        return $errores;
    }

    public static function formatear_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / (1024 * 1024), 1) . ' MB';
    }
}
