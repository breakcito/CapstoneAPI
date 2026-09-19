<?php

namespace App\Modules\Almacenes\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Service\AlmacenesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AlmacenesController extends Controller
{
    /**
     * Listar almacenes.
     */
    public function get_almacenes(Request $request): JsonResponse
    {
        $result = AlmacenesService::get_almacenes();

        return response()->json($result);
    }

    /**
     * Crear almacén
     */
    public function crear_almacen(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'id_departamento' => 'nullable|integer',
            'id_provincia' => 'nullable|integer',
            'id_distrito' => 'nullable|integer',
            'direccion' => 'nullable|string|max:255',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = AlmacenesService::crear_almacen(
            nombre: (string) $v['nombre'],
            id_departamento: isset($v['id_departamento']) ? (int) $v['id_departamento'] : null,
            id_provincia: isset($v['id_provincia']) ? (int) $v['id_provincia'] : null,
            id_distrito: isset($v['id_distrito']) ? (int) $v['id_distrito'] : null,
            direccion: isset($v['direccion']) ? (string) $v['direccion'] : null,
        );

        return response()->json($result);
    }

    /**
     * Actualizar almacén
     */
    public function actualizar_almacen(Request $request, int $id_almacen): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:128',
            'id_departamento' => 'nullable|integer',
            'id_provincia' => 'nullable|integer',
            'id_distrito' => 'nullable|integer',
            'direccion' => 'nullable|string|max:255',
        ], [
            'nombre.required' => 'El nombre es obligatorio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $v = $validator->validated();

        $result = AlmacenesService::actualizar_almacen(
            id_almacen: $id_almacen,
            nombre: (string) $v['nombre'],
            id_departamento: isset($v['id_departamento']) ? (int) $v['id_departamento'] : null,
            id_provincia: isset($v['id_provincia']) ? (int) $v['id_provincia'] : null,
            id_distrito: isset($v['id_distrito']) ? (int) $v['id_distrito'] : null,
            direccion: isset($v['direccion']) ? (string) $v['direccion'] : null,
        );

        return response()->json($result);
    }

    /**
     * Eliminar almacén
     */
    public function eliminar_almacen(int $id_almacen): JsonResponse
    {
        $result = AlmacenesService::eliminar_almacen($id_almacen);

        return response()->json($result);
    }
}
