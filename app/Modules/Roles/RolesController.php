<?php

namespace App\Modules\Roles;

use App\Shared\Responses\ApiResponse;
use App\Modules\Roles\Data\PermisosData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class RolesController extends Controller
{
    public function get_roles(): JsonResponse
    {
        $result = RolesService::get_roles();
        return response()->json($result);
    }

    public function get_estructura_permisos(): JsonResponse
    {
        $result = RolesService::get_estructura_permisos();
        return response()->json($result);
    }

    /**
     * Registrar un nuevo rol (bloqueado: roles fijados en el sistema).
     */
    public function crear_rol(Request $request): JsonResponse
    {
        return response()->json(ApiResponse::error('No está permitido crear nuevos roles. Los roles del sistema están fijados.'));
    }

    /**
     * Obtener los permisos (multi-nivel) de un rol.
     */
    public function get_permisos_rol(int $id_rol): JsonResponse
    {
        $permisos = PermisosData::get_permisos_por_rol($id_rol);
        return response()->json(ApiResponse::success($permisos));
    }

    /**
     * Actualizar los permisos de un rol (diff multi-nivel).
     */
    public function actualizar_permisos_rol(Request $request, int $id_rol): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'permisos' => 'required|array|min:1',
            'permisos.*.tipo' => 'required|in:menu,submenu,modulo',
            'permisos.*.id' => 'required|integer|min:1',
        ], [
            'permisos.required' => 'Debe seleccionar al menos un permiso.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = RolesService::actualizar_permisos_rol(
            $id_rol,
            $request->input('permisos'),
        );
        return response()->json($result);
    }
}