<?php

namespace App\Modules\Productos\Controller;

use App\Modules\Productos\Service\ProductosService;
use App\Shared\Enums\_Generic\Moneda;
use App\Shared\Enums\_Generic\Periodo;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;

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
            'id_categoria' => 'required|integer',
            'id_unidad_medida_base' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'prefijo' => 'nullable|string|max:24',
            'es_auditable' => 'required|boolean',
            'es_perecible' => 'required|boolean',
            'para_mantenimiento' => 'required|boolean',
            'stock_minimo_base' => 'nullable|numeric|min:0',
            'moneda' => ['required', new Enum(Moneda::class)],
            'costo_promedio_base' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => ['nullable', new Enum(Periodo::class)],
        ], [
            'id_categoria.required' => 'La categoría es requerida',
            'id_unidad_medida_base.required' => 'La unidad de medida es requerida',
            'nombre.required' => 'El nombre es requerido',
            'es_auditable.required' => 'Debe indicar si es auditable',
            'es_perecible.required' => 'Debe indicar si es perecible',
            'moneda.required' => 'Debe indicar la moneda del costo promedio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = ProductosService::crear_producto(
            id_categoria: $request->integer('id_categoria'),
            id_unidad_medida_base: $request->integer('id_unidad_medida_base'),
            nombre: $request->string('nombre'),
            prefijo: $request->input('prefijo'),
            es_auditable: $request->boolean('es_auditable'),
            es_perecible: $request->boolean('es_perecible'),
            para_mantenimiento: $request->boolean('para_mantenimiento'),
            stock_minimo_base: (float) ($request->input('stock_minimo_base') ?? 0),
            costo_promedio_base: (float) ($request->input('costo_promedio_base') ?? 0),
            tiempo_espera_vencimiento: $request->input('tiempo_espera_vencimiento') ? (int) $request->input('tiempo_espera_vencimiento') : null,
            periodo_espera_vencimiento: $request->input('periodo_espera_vencimiento'),
            moneda: Moneda::from($request->string('moneda')->toString())
        );

        return response()->json($result);
    }

    /**
     * Actualizar un producto existente
     */
    public function actualizar_producto(Request $request, int $id_producto): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_categoria' => 'required|integer',
            'id_unidad_medida_base' => 'required|integer',
            'nombre' => 'required|string|max:128',
            'prefijo' => 'nullable|string|max:24',
            'es_auditable' => 'required|boolean',
            'es_perecible' => 'required|boolean',
            'para_mantenimiento' => 'required|boolean',
            'stock_minimo_base' => 'nullable|numeric|min:0',
            'moneda' => ['required', new Enum(Moneda::class)],
            'costo_promedio_base' => 'nullable|numeric|min:0',
            'tiempo_espera_vencimiento' => 'nullable|integer|min:0',
            'periodo_espera_vencimiento' => ['nullable', new Enum(Periodo::class)],
        ], [
            'id_categoria.required' => 'La categoría es requerida',
            'id_unidad_medida_base.required' => 'La unidad de medida es requerida',
            'nombre.required' => 'El nombre es requerido',
            'es_auditable.required' => 'Debe indicar si es auditable',
            'es_perecible.required' => 'Debe indicar si es perecible',
            'moneda.required' => 'Debe indicar la moneda del costo promedio',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        // Identidad del usuario autenticado (poblada por JwtAuthMiddleware en $request->attributes->set('auth_user', ...))
        $authUser = $request->attributes->get('auth_user');
        $idEmpleado = is_object($authUser) && isset($authUser->id_empleado) ? (int) $authUser->id_empleado : null;
        $nombreEmpleado = is_object($authUser)
            ? trim(($authUser->nombre ?? '') . ' ' . ($authUser->apellido ?? '')) ?: null
            : null;

        $result = ProductosService::actualizar_producto(
            id_producto: $id_producto,
            id_categoria: $request->integer('id_categoria'),
            id_unidad_medida_base: $request->integer('id_unidad_medida_base'),
            nombre: $request->string('nombre'),
            prefijo: $request->input('prefijo'),
            es_auditable: $request->boolean('es_auditable'),
            es_perecible: $request->boolean('es_perecible'),
            para_mantenimiento: $request->boolean('para_mantenimiento'),
            stock_minimo_base: (float) ($request->input('stock_minimo_base') ?? 0),
            costo_promedio_base: (float) ($request->input('costo_promedio_base') ?? 0),
            tiempo_espera_vencimiento: $request->input('tiempo_espera_vencimiento') ? (int) $request->input('tiempo_espera_vencimiento') : null,
            periodo_espera_vencimiento: $request->input('periodo_espera_vencimiento'),
            moneda: Moneda::from($request->string('moneda')->toString()),
            id_empleado: $idEmpleado,
            nombre_empleado: $nombreEmpleado,
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
