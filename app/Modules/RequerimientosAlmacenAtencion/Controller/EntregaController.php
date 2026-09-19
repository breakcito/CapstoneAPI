<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Service\EntregaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class EntregaController extends Controller
{

    /**
     * Registrar la entrega física de productos.
     */
    public function crear_entrega(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento' => 'required|integer',
            'id_empleado_recibe' => 'nullable|integer',
            'id_contratista_recibe' => 'nullable|integer',
            'fecha_entrega' => 'required|date',
            'observacion' => 'nullable|string',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_requerimiento_almacen_detalle' => 'required|integer',
            'detalles.*.id_lote_producto' => 'nullable|integer',
            'detalles.*.id_activo_fijo' => 'nullable|integer',
            'detalles.*.cantidad_base' => 'required|numeric|min:0.01',
            'detalles.*.cantidad_lote' => 'nullable|numeric|min:0.01',
            'detalles.*.cantidad_requerimiento' => 'required|numeric|min:0.01',
            'detalles.*.para_mantenimiento' => 'nullable|boolean',
            'detalles.*.para_produccion' => 'nullable|boolean',
            'detalles.*.id_activo_fijo_destino' => 'nullable|integer',
            'detalles.*.id_lote_mineral' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (! $authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = EntregaService::registrar_entrega(
            id_empleado_entrega: $authUser->id_empleado,
            id_requerimiento: (int) $request->id_requerimiento,
            id_empleado_recibe: $request->id_empleado_recibe ? (int) $request->id_empleado_recibe : null,
            id_contratista_recibe: $request->id_contratista_recibe ? (int) $request->id_contratista_recibe : null,
            fecha_entrega: $request->fecha_entrega,
            observacion: $request->observacion,
            evidencias: $request->file('evidencias'),
            detalles: $request->detalles
        );

        return response()->json($result);
    }

    /**
     * Obtener el historial de entregas realizadas para un requerimiento específico.
     */
    public function get_historial_entregas(Request $request): JsonResponse
    {
        $id_requerimiento = $request->input('id_requerimiento');
        if (! $id_requerimiento) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = EntregaService::obtener_historial_entregas((int) $id_requerimiento);

        return response()->json($result);
    }
}
