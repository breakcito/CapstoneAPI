<?php

namespace App\Modules\Empleados;

use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmpleadosController
{
    /**
     * Listar empleados
     */
    public function get_empleados(Request $request): JsonResponse
    {
        $result = EmpleadosService::get_empleados();

        return response()->json($result);
    }

    /**
     * Crear empleado
     */
    public function crear_empleado(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'apellido' => 'required|string|max:128',
            'dni' => 'nullable|string|max:8',
            'es_contratista' => 'nullable|boolean',
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ], [
            'nombre.required' => 'El nombre es requerido',
            'apellido.required' => 'El apellido es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpleadosService::crear_empleado(
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            dni: $request->input('dni'),
            es_contratista: $request->boolean('es_contratista'),
            foto: $request->file('foto')
        );

        return response()->json($result);
    }

    /**
     * Actualizar empleado
     */
    public function actualizar_empleado(Request $request, int $id_empleado): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'apellido' => 'required|string|max:128',
            'dni' => 'nullable|string|max:8',
            'es_contratista' => 'nullable|boolean',
        ], [
            'nombre.required' => 'El nombre es requerido',
            'apellido.required' => 'El apellido es requerido',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpleadosService::actualizar_empleado(
            id_empleado: $id_empleado,
            nombre: (string) $request->input('nombre'),
            apellido: (string) $request->input('apellido'),
            dni: $request->input('dni'),
            es_contratista: $request->has('es_contratista') ? $request->boolean('es_contratista') : null
        );

        return response()->json($result);
    }

    /**
     * Eliminar empleado
     */
    public function eliminar_empleado(Request $request, int $id_empleado): JsonResponse
    {
        $result = EmpleadosService::eliminar_empleado(id_empleado: $id_empleado);

        return response()->json($result);
    }

    /**
     * Actualizar foto
     */
    public function actualizar_foto(Request $request, int $id_empleado): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = EmpleadosService::actualizar_foto(
            id_empleado: $id_empleado,
            foto: $request->file('foto')
        );

        return response()->json($result);
    }
}
