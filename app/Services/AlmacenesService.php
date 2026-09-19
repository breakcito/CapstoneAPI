<?php
namespace App\Services;

use App\Data\AlmacenesData;
use App\Shared\Responses\ApiResponse;
class AlmacenesService
{
    /**
     * Listar almacenes.
     */
    public static function get_almacenes(
        ?int $id_almacen = null,
        ?int $id_empleado_responsable = null,
    ) {
        $almacenes = AlmacenesData::get_almacenes(
            id_almacen: $id_almacen,
            id_empleado_responsable: $id_empleado_responsable,
        );

        return ApiResponse::success($almacenes);
    }
}