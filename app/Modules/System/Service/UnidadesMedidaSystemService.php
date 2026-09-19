<?php
namespace App\Modules\System\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\System\Data\UnidadesMedidaSystemData;

class UnidadesMedidaSystemService
{
    public static function listar()
    {
        return ApiResponse::success(UnidadesMedidaSystemData::listar());
    }

    public static function crear(?string $nombre, ?string $abreviatura)
    {
        $nombre = $nombre !== null ? trim($nombre) : '';
        $abreviatura = $abreviatura !== null ? mb_strtoupper(trim($abreviatura)) : '';

        if ($nombre === '' || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 64) {
            return ApiResponse::error('El nombre debe tener entre 2 y 64 caracteres.');
        }
        if ($abreviatura === '' || mb_strlen($abreviatura) < 1 || mb_strlen($abreviatura) > 8) {
            return ApiResponse::error('La abreviatura debe tener entre 1 y 8 caracteres.');
        }
        if (UnidadesMedidaSystemData::ya_existe($nombre, $abreviatura)) {
            return ApiResponse::error('Ya existe una unidad de medida con ese nombre o abreviatura.');
        }

        $id = UnidadesMedidaSystemData::crear($nombre, $abreviatura);
        return ApiResponse::success(UnidadesMedidaSystemData::obtener($id), 'Unidad creada correctamente.');
    }

    public static function editar(int $id, ?string $nombre, ?string $abreviatura)
    {
        $unidad = UnidadesMedidaSystemData::obtener($id);
        if (!$unidad) return ApiResponse::error('Unidad no encontrada.');

        $nombre = $nombre !== null ? trim($nombre) : '';
        $abreviatura = $abreviatura !== null ? mb_strtoupper(trim($abreviatura)) : '';

        if ($nombre === '' || mb_strlen($nombre) < 2 || mb_strlen($nombre) > 64) {
            return ApiResponse::error('El nombre debe tener entre 2 y 64 caracteres.');
        }
        if ($abreviatura === '' || mb_strlen($abreviatura) < 1 || mb_strlen($abreviatura) > 8) {
            return ApiResponse::error('La abreviatura debe tener entre 1 y 8 caracteres.');
        }
        if (UnidadesMedidaSystemData::ya_existe($nombre, $abreviatura, $id)) {
            return ApiResponse::error('Ya existe otra unidad con ese nombre o abreviatura.');
        }

        UnidadesMedidaSystemData::editar($id, $nombre, $abreviatura);
        return ApiResponse::success(UnidadesMedidaSystemData::obtener($id), 'Unidad actualizada.');
    }

    public static function eliminar(int $id)
    {
        if (!UnidadesMedidaSystemData::obtener($id)) {
            return ApiResponse::error('Unidad no encontrada.');
        }
        if (UnidadesMedidaSystemData::tiene_conversiones_o_uso($id)) {
            return ApiResponse::error('No se puede eliminar: la unidad tiene conversiones o está en uso.');
        }
        UnidadesMedidaSystemData::eliminar($id);
        return ApiResponse::success(null, 'Unidad eliminada.');
    }
}
