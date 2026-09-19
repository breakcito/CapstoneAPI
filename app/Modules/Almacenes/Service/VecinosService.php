<?php

namespace App\Modules\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Data\VecinosData;
use Illuminate\Support\Facades\DB;

class VecinosService
{
    public static function get_vecinos(int $id_almacen)
    {
        $vecinos = VecinosData::get_vecinos($id_almacen);
        return ApiResponse::success($vecinos);
    }

    public static function get_almacenes_disponibles_vecinos(int $id_almacen)
    {
        $disponibles = VecinosData::get_almacenes_disponibles_vecinos($id_almacen);
        return ApiResponse::success($disponibles);
    }

    public static function agregar_vecino(int $id_almacen_a, int $id_almacen_b)
    {
        if ($id_almacen_a === $id_almacen_b) {
            return ApiResponse::error('Un almacén no puede ser vecino de sí mismo.');
        }

        if (VecinosData::verificar_vecino($id_almacen_a, $id_almacen_b)) {
            return ApiResponse::error('Estos almacenes ya están vinculados como vecinos.');
        }

        $id = VecinosData::agregar_vecino($id_almacen_a, $id_almacen_b);
        $nuevoVecino = VecinosData::get_vecino_by_id($id);

        return ApiResponse::success($nuevoVecino, 'Almacén vecino vinculado correctamente.');
    }

    public static function eliminar_vecino(int $id_almacen_vecino)
    {
        VecinosData::eliminar_vecino($id_almacen_vecino);
        return ApiResponse::success(null, 'Almacén vecino desvinculado correctamente.');
    }
}
