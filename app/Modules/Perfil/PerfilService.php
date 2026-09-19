<?php

namespace App\Modules\Perfil;

use App\Shared\Responses\ApiResponse;
use App\Modules\Perfil\Data\PerfilData;

class PerfilService
{
    /**
     * Obtener la información del perfil del usuario
     */
    public static function get_perfil(?int $id_usuario): array
    {
        $info = PerfilData::get_info_perfil($id_usuario);

        return ApiResponse::success($info);
    }
}
