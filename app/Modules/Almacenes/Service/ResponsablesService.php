<?php

namespace App\Modules\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Data\ResponsablesData;

class ResponsablesService
{

    public static function nuevo_responsable(int $id_almacen, int $id_empleado, string $fecha_inicio)
    {
        // Crear nuevo usando el id de la tabla empleado
        $id_nuevo_responsable = ResponsablesData::nuevo_responsable($id_almacen, $id_empleado, $fecha_inicio);
        $nuevoResponsable = ResponsablesData::get_responsable_by_id($id_nuevo_responsable);

        return ApiResponse::success($nuevoResponsable, 'Responsable asignado correctamente');
    }

    public static function inactivar_responsable(int $id_responsable, string $fecha_fin)
    {
        ResponsablesData::inactivar_responsable($id_responsable, $fecha_fin);
        return ApiResponse::success(null, 'Responsable inactivado correctamente');
    }

    public static function get_historial_responsables(int $id_almacen)
    {
        $historial = ResponsablesData::get_historial_responsables($id_almacen);

        return ApiResponse::success($historial);
    }
}
