<?php
namespace App\Modules\System\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\System\Data\ArchivosSystemData;

class ArchivosSystemService
{
    public static function listar(string $carpeta)
    {
        if (!in_array($carpeta, ArchivosSystemData::CARPETAS, true)) {
            return ApiResponse::error('Carpeta no permitida.');
        }
        return ApiResponse::success(ArchivosSystemData::listar($carpeta));
    }

    public static function descargar(string $carpeta, string $archivo): array
    {
        if (!in_array($carpeta, ArchivosSystemData::CARPETAS, true)) {
            return ['error' => 'Carpeta no permitida.'];
        }
        if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $archivo)) {
            return ['error' => 'Nombre de archivo invalido.'];
        }
        if (!ArchivosSystemData::existe($carpeta, $archivo)) {
            return ['error' => 'Archivo no encontrado.'];
        }
        return ['path' => ArchivosSystemData::base_path() . '/' . $carpeta . '/' . $archivo];
    }

    public static function renombrar(string $carpeta, string $old, string $new)
    {
        if (!in_array($carpeta, ArchivosSystemData::CARPETAS, true)) {
            return ApiResponse::error('Carpeta no permitida.');
        }
        if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $new)) {
            return ApiResponse::error('El nuevo nombre solo permite letras, numeros, ., _, -.');
        }
        if (!ArchivosSystemData::existe($carpeta, $old)) {
            return ApiResponse::error('Archivo origen no encontrado.');
        }
        if (!ArchivosSystemData::renombrar($carpeta, $old, $new)) {
            return ApiResponse::error('No se pudo renombrar (¿ya existe un archivo con ese nombre?).');
        }
        return ApiResponse::success(null, 'Archivo renombrado.');
    }

    public static function eliminar(string $carpeta, string $archivo)
    {
        if (!in_array($carpeta, ArchivosSystemData::CARPETAS, true)) {
            return ApiResponse::error('Carpeta no permitida.');
        }
        if (!ArchivosSystemData::existe($carpeta, $archivo)) {
            return ApiResponse::error('Archivo no encontrado.');
        }
        if (!ArchivosSystemData::eliminar($carpeta, $archivo)) {
            return ApiResponse::error('No se pudo eliminar.');
        }
        return ApiResponse::success(null, 'Archivo eliminado.');
    }
}
