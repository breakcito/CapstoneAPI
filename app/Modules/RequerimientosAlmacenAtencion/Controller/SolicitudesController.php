<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Service\SolicitudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class SolicitudesController extends Controller
{

    /**
     * Registrar una solicitud a logística (reabastecimiento).
     */
    public function registrar_solicitud(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento' => 'required|integer',
            'observacion' => 'nullable|string',
            'premura' => 'required|string',
            'es_auditable' => 'required|boolean',
            'fecha_entrega_requerida' => 'required|date',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_requerimiento_almacen_detalle' => 'required|integer',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric',
            'detalles.*.contenido_por_presentacion' => 'required|numeric',
            'detalles.*.cantidad_solicitada_base' => 'required|numeric',
            'detalles.*.comentario' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $premura = Premura::from($request->input('premura'));
        $result = SolicitudService::registrarSolicitudLogistica(
            id_requerimiento: (int) $request->id_requerimiento,
            id_empleado: (int) $authUser->id_empleado,
            premura: $premura,
            fecha_entrega_requerida: $request->fecha_entrega_requerida,
            es_auditable: $request->es_auditable,
            detalles: $request->detalles,
            observacion: $request->observacion
        );

        return response()->json($result);
    }

    /**
     * Obtener el historial de solicitudes asociadas a un requerimiento.
     */
    public function get_historial_solicitudes(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = SolicitudService::obtenerHistorialSolicitudes((int) $id);

        return response()->json($result);
    }
}
