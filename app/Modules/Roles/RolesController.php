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
     * Registrar un nuevo rol con sus permisos (multi-nivel).
     */
    public function crear_rol(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:64',
            'descripcion' => 'nullable|string|max:512',
            'permisos' => 'required|array|min:1',
            'permisos.*.tipo' => 'required|in:menu,submenu,modulo',
            'permisos.*.id' => 'required|integer|min:1',
        ], [
            'nombre.required' => 'El nombre del rol es obligatorio.',
            'permisos.required' => 'Debe seleccionar al menos un permiso.',
            'permisos.*.in' => 'El tipo de permiso debe ser menu|submenu|modulo.',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = RolesService::crear_rol($request->all());
        return response()->json($result);
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