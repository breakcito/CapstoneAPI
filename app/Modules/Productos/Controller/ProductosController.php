<?php

namespace App\Modules\Productos\Controller;

use App\Modules\Productos\Service\ProductosService;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductosController
{
    /**
     * Listar productos
     */
    public function get_productos(Request $request): JsonResponse
    {
        $result = ProductosService::get_productos();

        return response()->json($result);
    }

    /**
     * Crear un nuevo producto
     */
    public function crear_producto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_unidad_medida_base' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'tipo_producto' => 'nullable|string|max:128',
            'es_perecible' => 'required|boolean',
            'stock_minimo_base' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => 'nullable|string|max:32',
        ], [
            'id_unidad_medida_base.required' => 'La unidad de medida es requerida',
            'nombre.required' => 'El nombre es requerido',
            'es_perecible.required' => 'Debe indicar si es perecible',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ProductosService::crear_producto(
            id_unidad_medida_base: $request->integer('id_unidad_medida_base'),
            nombre: $request->string('nombre'),
            tipo_producto: $request->input('tipo_producto'),
            es_perecible: $request->boolean('es_perecible'),
            stock_minimo_base: (float) ($request->input('stock_minimo_base') ?? 0),
            tiempo_espera_vencimiento: $request->input('tiempo_espera_vencimiento') !== null ? (int) $request->input('tiempo_espera_vencimiento') : null,
            periodo_espera_vencimiento: $request->input('periodo_espera_vencimiento'),
        );

        return response()->json($result);
    }

    /**
     * Actualizar un producto existente
     */
    public function actualizar_producto(Request $request, int $id_producto): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_unidad_medida_base' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'tipo_producto' => 'nullable|string|max:128',
            'es_perecible' => 'required|boolean',
            'stock_minimo_base' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => 'nullable|string|max:32',
        ], [
            'id_unidad_medida_base.required' => 'La unidad de medida es requerida',
            'nombre.required' => 'El nombre es requerido',
            'es_perecible.required' => 'Debe indicar si es perecible',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ProductosService::actualizar_producto(
            id_producto: $id_producto,
            id_unidad_medida_base: $request->integer('id_unidad_medida_base'),
            nombre: $request->string('nombre'),
            tipo_producto: $request->input('tipo_producto'),
            es_perecible: $request->boolean('es_perecible'),
            stock_minimo_base: (float) ($request->input('stock_minimo_base') ?? 0),
            tiempo_espera_vencimiento: $request->input('tiempo_espera_vencimiento') !== null ? (int) $request->input('tiempo_espera_vencimiento') : null,
            periodo_espera_vencimiento: $request->input('periodo_espera_vencimiento'),
        );

        return response()->json($result);
    }

    /**
     * Eliminar (desactivar) un producto
     */
    public function eliminar_producto(Request $request, int $id_producto): JsonResponse
    {
        $result = ProductosService::eliminar_producto(id_producto: $id_producto);

        return response()->json($result);
    }
}
