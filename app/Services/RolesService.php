<?php
namespace App\Services;

use App\Data\RolesData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;
class RolesService
{
    /**
     * Listar roles
     */
    public static function get_roles(
        ?int $id_rol = null,
        ?EstadoBase $estado = null,
    ) {
        $data = RolesData::get_roles(
            id_rol: $id_rol,
            estado: $estado
        );

        return ApiResponse::success($data);
    }
}