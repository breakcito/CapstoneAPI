<?php
namespace App\Modules\System\Data;

class ArchivosSystemData
{
    public const CARPETAS = [
        'contratos_concesion',
        'documentos-empresas',
        'logos-empresas',
        'ordenes-compra-comprobantes',
        'ordenes-compra-recepciones',
        'reabastecimiento_entregas',
        'requerimientos_almacen',
    ];

    public static function base_path(): string
    {
        return storage_path('app/public');
    }

    public static function resolver_path(string $carpeta, string $archivo = ''): string
    {
        $base = realpath(self::base_path());
        $full = $base . '/' . $carpeta . ($archivo ? '/' . $archivo : '');
        $resolved = realpath($full);
        if (!$resolved) return '';
        if (!str_starts_with($resolved, $base)) return '';
        return $resolved;
    }

    public static function listar(string $carpeta): array
    {
        if (!in_array($carpeta, self::CARPETAS, true)) return [];
        $path = self::base_path() . '/' . $carpeta;
        if (!is_dir($path)) return [];

        $files = [];
        foreach (scandir($path) as $f) {
            if ($f === '.' || $f === '..') continue;
            $full = $path . '/' . $f;
            if (!is_file($full)) continue;
            $files[] = [
                'nombre' => $f,
                'tamano_bytes' => filesize($full),
                'fecha_modificacion' => date('c', filemtime($full)),
            ];
        }
        usort($files, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));
        return $files;
    }

    public static function renombrar(string $carpeta, string $old, string $new): bool
    {
        $oldPath = self::resolver_path($carpeta, $old);
        $newFull = self::base_path() . '/' . $carpeta . '/' . $new;
        if (!$oldPath || !file_exists($oldPath)) return false;
        if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $new)) return false;
        if (file_exists($newFull)) return false;
        return rename($oldPath, $newFull);
    }

    public static function eliminar(string $carpeta, string $archivo): bool
    {
        $full = self::resolver_path($carpeta, $archivo);
        if (!$full || !file_exists($full)) return false;
        return unlink($full);
    }

    public static function existe(string $carpeta, string $archivo): bool
    {
        $full = self::resolver_path($carpeta, $archivo);
        return $full && file_exists($full);
    }
}
