<?php

namespace App\Shared\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;


class ArchivoHelper
{
    /**
     * Guarda un array de archivos de forma segura en el disco público.
     *
     * @param  string  $carpetaDestino  Carpeta dentro de storage/app/public (ej. "pagos").
     * @param  UploadedFile[]  $archivos  Archivos recibidos en el request.
     * @return array Retorna: { url (URL pública), nombre_original (nombre original del archivo), extension (docx, jpg, etc) }
     * 
     * Comando de ayuda: php artisan storage:link
     */
    public static function guardarArchivos(string $carpetaDestino, array $archivos): array
    {
        $resultados = [];

        // Agrupamos los archivos en subcarpetas por fecha (ej. "pagos/28-02-26")
        $rutaDestino = trim($carpetaDestino, '/') . '/' . date('d-m-y');

        foreach ($archivos as $archivo) {
            if (!$archivo instanceof UploadedFile || !$archivo->isValid()) {
                continue;
            }

            // Extraemos el nombre original y lo limpiamos
            $nombreOriginal = pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME);
            $nombreLimpio = Str::slug($nombreOriginal);
            $extension = $archivo->getClientOriginalExtension() ?: ($archivo->guessExtension() ?? 'bin');

            // Usamos store() en el disco 'public'
            $pathRelativo = $archivo->store($rutaDestino, ['disk' => 'public']);

            if ($pathRelativo) {
                $resultados[] = [
                    'url' => asset('storage/' . $pathRelativo),
                    'path_relativo' => $pathRelativo,
                    'nombre_original' => $nombreLimpio,
                    'extension' => $extension,
                ];
            }
        }

        return $resultados;
    }

    /**
     * Extrae la ruta relativa desde un string, array o URL.
     *
     * @param  string|array  $archivo
     * @return string|null
     */
    private static function extraerPathRelativo(string|array $archivo): ?string
    {
        if (is_array($archivo)) {
            return $archivo['path_relativo'] ?? null;
        }

        $path = $archivo;

        // Si contiene '/storage/' (URL completa o relativa)
        if (str_contains($path, '/storage/')) {
            $path = explode('/storage/', $path, 2)[1] ?? $path;
        }

        // Si empieza con 'storage/' (sin slash inicial)
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        // Eliminar slashes iniciales sobrantes
        $path = ltrim($path, '/');

        return $path ?: null;
    }

    /**
     * Elimina un archivo del disco público.
     *
     * @param  string|array  $archivo  Ruta relativa, array con 'path_relativo' o URL pública.
     * @return bool
     */
    public static function eliminarArchivo(string|array $archivo): bool
    {
        $path = self::extraerPathRelativo($archivo);
        if (!$path) {
            return false;
        }
        return Storage::disk('public')->delete($path);
    }

    /**
     * Reemplaza un archivo: elimina el antiguo y guarda el nuevo.
     *
     * @param  string|array  $archivoViejo  Ruta relativa, array o URL.
     * @param  string        $carpetaDestino
     * @param  UploadedFile  $nuevoArchivo
     * @return array
     */
    public static function reemplazarArchivo(string|array $archivoViejo, string $carpetaDestino, UploadedFile $nuevoArchivo): array
    {
        self::eliminarArchivo($archivoViejo);
        return self::guardarArchivos($carpetaDestino, [$nuevoArchivo]);
    }
}
