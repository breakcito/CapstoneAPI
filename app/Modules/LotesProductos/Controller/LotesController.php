<?php

namespace App\Modules\LotesProductos\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\LotesProductos\Service\LotesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class LotesController extends Controller
{

    public function get_resumen_lotes(Request $request): JsonResponse
    {
        $id_almacen = $request->query('id_almacen');
        if (!$id_almacen) {
            return response()->json(ApiResponse::error('El id_almacen es requerido'), 400);
        }

        $result = LotesService::get_resumen_lotes((int) $id_almacen);

        return response()->json($result);
    }

    public function crear_lote(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_producto' => 'required|integer',
            'id_unidad_medida' => 'required|integer',
            'id_almacen' => 'required|integer',
            'descripcion' => 'nullable|string',
            'stock_inicial' => 'required|numeric|min:0',
            'contenido_por_presentacion' => 'required|numeric|min:0',
            'fecha_hora_ingreso' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_hora_ingreso',
            'comprobante_compra' => 'nullable|string|max:128',
            'costo_por_unidad' => 'nullable|numeric|min:0',
        ], [
            'id_producto.required' => 'El producto es requerido',
            'id_unidad_medida.required' => 'La unidad de medida es requerida',
            'id_almacen.required' => 'El almacén es requerido',
            'stock_inicial.min' => 'El stock inicial no puede ser negativo',
            'contenido_por_presentacion.required' => 'El contenido por presentación es requerido',
            'costo_por_unidad.min' => 'El costo unitario no puede ser negativo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = LotesService::crear_lote(
            id_producto: (int) $request->id_producto,
            id_unidad_medida: (int) $request->id_unidad_medida,
            id_almacen: (int) $request->id_almacen,
            stock_inicial: (float) $request->stock_inicial,
            contenido_por_presentacion: (float) $request->contenido_por_presentacion,
            fecha_hora_ingreso: $request->fecha_hora_ingreso,
            fecha_vencimiento: $request->fecha_vencimiento,
            comprobante_compra: $request->comprobante_compra ?? null,
            costo_por_unidad: $request->has('costo_por_unidad') && $request->costo_por_unidad !== null ? (float) $request->costo_por_unidad : null
        );

        return response()->json($result);
    }

    public function ajustar_stock(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_lote' => 'required|integer',
            'nuevo_stock_base' => 'required|numeric|min:0',
            'motivo' => 'nullable|string',
        ], [
            'id_lote.required' => 'El lote es requerido',
            'nuevo_stock_base.required' => 'El nuevo stock base es requerido',
            'nuevo_stock_base.min' => 'El stock base no puede ser negativo',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = LotesService::ajustar_stock(
            (int) $request->id_lote,
            (float) $request->nuevo_stock_base,
            $request->motivo ?? null
        );

        return response()->json($result);
    }

    /**
     * Actualizar campos administrativos de un lote.
     */
    public function actualizar_lote(Request $request, int $id_lote): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'comprobante_compra' => 'nullable|string|max:64',
            'fecha_hora_ingreso' => 'nullable|date',
            'costo_por_unidad' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()));
        }

        $result = LotesService::actualizar_lote(
            id_lote: $id_lote,
            comprobante_compra: $this->emptyToNull($request->input('comprobante_compra')),
            fecha_hora_ingreso: $request->input('fecha_hora_ingreso'),
            costo_por_unidad: $request->has('costo_por_unidad') && $request->input('costo_por_unidad') !== null ? (float) $request->input('costo_por_unidad') : null
        );

        return response()->json($result);
    }

    /**
     * Desactivar (soft delete) un lote. Cambia estado a Inactivo.
     */
    public function eliminar_lote(Request $request, int $id_lote): JsonResponse
    {
        $result = LotesService::eliminar_lote(id_lote: $id_lote);

        return response()->json($result);
    }

    private function emptyToNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
