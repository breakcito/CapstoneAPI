<?php

namespace App\Modules\Cuentas;

use App\Shared\Responses\ApiResponse;
use App\Services\EmpleadosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CuentasController extends Controller
{
    /**
     * Listar todas las cuentas de usuario
     */
    public function get_cuentas(): JsonResponse
    {
        $result = CuentasService::get_cuentas();
        return response()->json($result);
    }

    /**
     * Registrar una nueva cuenta de usuario
     */
    public function crear_cuenta(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_rol' => 'required|exists:rol,id',
            'id_empleado' => 'required',
            'username' => 'required|string|max:64',
            'password' => 'required|string|min:6|max:512'
        ], [
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = CuentasService::crear_cuenta(
            id_rol: (int) $v['id_rol'],
            id_empleado: (int) $v['id_empleado'],
            username: (string) $v['username'],
            password: (string) $v['password']
        );
        return response()->json($result);
    }

    /**
     * Actualizar datos de una cuenta existente
     */
    public function actualizar_cuenta(Request $request, int $id_usuario): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_rol' => 'required|exists:rol,id',
            'username' => [
                'required',
                'string',
                'max:64',
                Rule::unique('usuario', 'username')->ignore($id_usuario)
            ],
            'password' => 'nullable|string|min:6|max:512',
            'estado' => 'nullable|string'
        ], [
            'username.unique' => 'El nombre de usuario ya está en uso.',
            'password.min' => 'La nueva contraseña debe tener al menos 6 caracteres.'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = CuentasService::actualizar_cuenta(
            id_usuario: $id_usuario,
            id_rol: (int) $v['id_rol'],
            username: (string) $v['username'],
            password: isset($v['password']) ? (string) $v['password'] : null,
            estado: isset($v['estado']) ? (string) $v['estado'] : null
        );
        return response()->json($result);
    }

    /**
     * Actualizar foto de empleado
     */
    public function actualizar_foto_empleado(Request $request, int $id_empleado): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpleadosService::actualizar_foto($id_empleado, $request->file('foto'));

        return response()->json($result);
    }
}
