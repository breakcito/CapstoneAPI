<?php

namespace App\Modules\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Data\AbastecimientoData;

class AbastecimientoService
{
    public static function nueva_mina_por_abastecer(int $id_almacen, int $id_mina)
    {
        if (AbastecimientoData::verificar_abastecimiento_mina($id_almacen, $id_mina)) {
            return ApiResponse::error('Esta mina ya está siendo abastecida por este almacén.');
        }

        $id = AbastecimientoData::nueva_mina_por_abastecer($id_almacen, $id_mina);

        $nuevaAsignacion = AbastecimientoData::get_mina_abastecida_by_id($id);

        return ApiResponse::success($nuevaAsignacion, 'Mina asignada correctamente');
    }

    public static function eliminar_abastecimiento_mina(int $id_almacen_mina)
    {
        AbastecimientoData::eliminar_abastecimiento_mina($id_almacen_mina);

        return ApiResponse::success(null, 'Se detuvo el abastecimiento de esta mina');
    }

    public static function get_minas_abastecidas(int $id_almacen)
    {
        $result = AbastecimientoData::get_minas_abastecidas($id_almacen);

        return ApiResponse::success($result);
    }

    public static function get_minas(int $id_almacen)
    {
        $result = AbastecimientoData::get_minas($id_almacen);

        return ApiResponse::success($result);
    }
}
