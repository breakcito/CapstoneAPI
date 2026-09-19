<?php

namespace App\Modules\Perfil;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PerfilController
{
    /**
     * Obtener el perfil del usuario autenticado
     */
    public function get_perfil(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        $response = PerfilService::get_perfil($authUser->id_usuario);

        return response()->json($response);
    }
}
