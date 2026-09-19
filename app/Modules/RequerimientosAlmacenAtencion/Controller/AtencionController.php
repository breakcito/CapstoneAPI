<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Service\AtencionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;

class AtencionController extends Controller
{
    /**
     * ------------------------------------------------------
     * PARA LA CABECERA
     * ------------------------------------------------------
     */


    /**
     * Listado de requerimientos para atención por almacén.
     */
    public function get_requerimientos(Request $request): JsonResponse
    {
        $id_almacen = $request->input('id_almacen');
        $mes = $request->input('mes');
        $yearcito = $request->input('yearcito');

        if (!$id_almacen || !$mes || !$yearcito) {
            return response()->json(ApiResponse::error('id_almacen, mes y yearcito son requeridos'), 400);
        }

        $result = AtencionService::get_requerimientos((int) $id_almacen, $mes, $yearcito);

        return response()->json($result);
    }

    public function crear_requerimiento(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $reglas = [
            'id_empleado_solicitante' => 'nullable|integer',
            'id_contratista_solicitante' => 'nullable|integer',
            'id_labor' => 'nullable|integer',
            'id_almacen_destino' => 'required|integer',
            'es_auditable' => 'required|boolean',
            'premura' => 'required|string',
            'fecha_entrega_requerida' => 'required|date',
            'fecha_solicitud' => 'nullable|date',
            'observacion' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
            'detalles.*.para_mantenimiento' => 'nullable|boolean',
            'detalles.*.id_activo_fijo_destino' => 'nullable|integer',
            // Campos de cálculo inteligente con magnitud (opcional; cuando
            // `con_magnitud=1` el sistema usa `cantidad_items` y
            // `valor_magnitud_base` para reconstruir el total en base).
            'detalles.*.con_magnitud' => 'nullable|boolean',
            'detalles.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles.*.valor_magnitud_base' => 'nullable|numeric|min:0',
            'evidencias' => 'nullable|array',
            'evidencias.*' => 'file',
        ];

        $validator = Validator::make($request->all(), $reglas);

        if ($validator->fails()) {
            $errores = $validator->errors()->all();
            return response()->json(ApiResponse::error('Datos inválidos: ' . implode(', ', $errores)));
        }

        $id_empleado_registro = $authUser->id_empleado;
        $evidencias = $request->file('evidencias', []);

        $premura = Premura::from($request->input('premura'));
        try {
            $resultado = AtencionService::registrar_requerimiento(
                id_empleado_solicitante: $request->id_empleado_solicitante ? (int) $request->id_empleado_solicitante : null,
                id_contratista_solicitante: $request->id_contratista_solicitante ? (int) $request->id_contratista_solicitante : null,
                id_empleado_registro: (int) $id_empleado_registro,
                id_labor: $request->id_labor ? (int) $request->id_labor : null,
                id_almacen_destino: (int) $request->id_almacen_destino,
                es_auditable: (bool) $request->es_auditable,
                premura: $premura,
                observacion: $request->observacion,
                fecha_entrega_requerida: $request->fecha_entrega_requerida,
                fecha_solicitud: $request->fecha_solicitud,
                detalles: $request->detalles,
                evidencias: $evidencias
            );

            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al registrar requerimiento: ' . $e->getMessage()), 500);
        }
    }


    /**
     * ------------------------------------------------------
     * PARA EL DETALLE
     * ------------------------------------------------------
     */


    /**
     * Obtener los detalles de un requerimiento.
     */
    public function get_detalles_requerimiento(Request $request): JsonResponse
    {
        $id = $request->input('id_requerimiento');
        if (!$id) {
            return response()->json(ApiResponse::error('El id_requerimiento es requerido'), 400);
        }

        $result = AtencionService::get_detalles_requerimiento((int) $id);

        return response()->json($result);
    }

    /**
     * Aprobar o Rechazar uno o varios ítems del requerimiento.
     */
    public function update_estado_detalle_requerimiento(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento_almacen_detalle' => 'nullable|integer', // Retrocompatibilidad
            'ids_detalles' => 'nullable|array',                     // Nuevo: Masivo
            'ids_detalles.*' => 'integer',
            'nuevo_estado' => 'required|string',
            'comentario_decision' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        // Normalizar los IDs a un solo arreglo para el servicio
        $ids = [];
        if ($request->has('id_requerimiento_almacen_detalle')) {
            $ids[] = (int) $request->id_requerimiento_almacen_detalle;
        }
        if ($request->has('ids_detalles')) {
            $ids = array_merge($ids, $request->ids_detalles);
        }

        // Eliminar duplicados si los hubiera
        $ids = array_unique($ids);

        if (empty($ids)) {
            return response()->json(ApiResponse::error('Debe proporcionar al menos un ID de detalle'), 400);
        }

        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $result = AtencionService::cambiar_estado_detalle(
            $authUser->id_empleado,
            $ids,
            $request->nuevo_estado,
            $request->comentario_decision
        );

        return response()->json($result);
    }

    /**
     * Obtener trazabilidad de un detalle de requerimiento.
     */
    public function get_trazabilidad(Request $request): JsonResponse
    {
        $id_detalle = $request->input('id_requerimiento_almacen_detalle');
        if (!$id_detalle) {
            return response()->json(ApiResponse::error('El id_requerimiento_almacen_detalle es requerido'), 400);
        }

        $result = AtencionService::obtener_trazabilidad((int) $id_detalle);

        return response()->json($result);
    }

    /**
     * Sube más evidencias a un requerimiento de almacén existente.
     */
    public function subir_evidencias(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id_requerimiento' => 'required|integer',
            'evidencias' => 'required|array|min:1',
            'evidencias.*' => 'file',
        ]);

        if ($validator->fails()) {
            return response()->json(ApiResponse::error($validator->errors()->first()), 400);
        }

        $id_requerimiento = (int) $request->input('id_requerimiento');
        $evidencias = $request->file('evidencias', []);

        try {
            $resultado = AtencionService::subir_evidencias($id_requerimiento, $evidencias);
            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al subir evidencias: ' . $e->getMessage()), 500);
        }
    }

    /**
     * Edita un requerimiento existente. Permite modificar la cabecera y los
     * detalles que aun no tengan entrega iniciada (cantidad_entregada_base = 0).
     */
    public function editar_requerimiento(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $reglas = [
            'id_empleado_solicitante' => 'nullable|integer',
            'id_contratista_solicitante' => 'nullable|integer',
            'id_labor' => 'nullable|integer',
            'premura' => 'nullable|string',
            'fecha_entrega_requerida' => 'nullable|date',
            'fecha_solicitud' => 'nullable|date',
            'observacion' => 'nullable|string',
            'es_auditable' => 'nullable|boolean',
            'evidencias_nuevas' => 'nullable|array',
            'evidencias_nuevas.*' => 'file',
            'detalles_editar' => 'nullable|array',
            'detalles_editar.*.id_requerimiento_almacen_detalle' => 'required_with:detalles_editar|integer',
            'detalles_editar.*.id_unidad_medida' => 'nullable|integer',
            'detalles_editar.*.cantidad_solicitada' => 'nullable|numeric|min:0',
            'detalles_editar.*.contenido_por_presentacion' => 'nullable|numeric|min:0.0001',
            'detalles_editar.*.comentario' => 'nullable|string',
            'detalles_editar.*.para_mantenimiento' => 'nullable|boolean',
            'detalles_editar.*.id_activo_fijo_destino' => 'nullable|integer',
            'detalles_editar.*.con_magnitud' => 'nullable|boolean',
            'detalles_editar.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles_editar.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles_editar.*.valor_magnitud_base' => 'nullable|numeric|min:0',
            'detalles_eliminar' => 'nullable|array',
            'detalles_eliminar.*' => 'integer',
            'detalles_crear' => 'nullable|array',
            'detalles_crear.*.id_producto' => 'required_with:detalles_crear|integer',
            'detalles_crear.*.id_unidad_medida' => 'required_with:detalles_crear|integer',
            'detalles_crear.*.cantidad_solicitada' => 'required_with:detalles_crear|numeric|min:0.01',
            'detalles_crear.*.contenido_por_presentacion' => 'required_with:detalles_crear|numeric|min:0.0001',
            'detalles_crear.*.comentario' => 'nullable|string',
            'detalles_crear.*.para_mantenimiento' => 'nullable|boolean',
            'detalles_crear.*.id_activo_fijo_destino' => 'nullable|integer',
            'detalles_crear.*.con_magnitud' => 'nullable|boolean',
            'detalles_crear.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles_crear.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles_crear.*.valor_magnitud_base' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $reglas);

        if ($validator->fails()) {
            $errores = $validator->errors()->all();
            return response()->json(ApiResponse::error('Datos inválidos: ' . implode(', ', $errores)));
        }

        $cabecera = [
            'id_empleado_solicitante' => $request->has('id_empleado_solicitante')
                ? ($request->id_empleado_solicitante ? (int) $request->id_empleado_solicitante : null)
                : null,
            'id_contratista_solicitante' => $request->has('id_contratista_solicitante')
                ? ($request->id_contratista_solicitante ? (int) $request->id_contratista_solicitante : null)
                : null,
            'id_labor' => $request->has('id_labor')
                ? ($request->id_labor ? (int) $request->id_labor : null)
                : null,
            'premura' => $request->input('premura'),
            'fecha_entrega_requerida' => $request->input('fecha_entrega_requerida'),
            'fecha_solicitud' => $request->input('fecha_solicitud'),
            'observacion' => $request->input('observacion'),
            'es_auditable' => $request->has('es_auditable') ? (bool) $request->es_auditable : null,
        ];

        $detalles_editar = $request->input('detalles_editar', []);
        $detalles_crear = $request->input('detalles_crear', []);
        $detalles_eliminar = $request->input('detalles_eliminar', []);
        $evidencias_nuevas = $request->file('evidencias_nuevas', []);

        try {
            $resultado = AtencionService::editar_requerimiento(
                id_requerimiento: $id,
                id_empleado_editor: (int) $authUser->id_empleado,
                cabecera: $cabecera,
                detalles_editar: $detalles_editar,
                detalles_crear: $detalles_crear,
                detalles_eliminar: $detalles_eliminar,
                evidencias_nuevas: $evidencias_nuevas
            );

            return response()->json($resultado);
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al editar requerimiento: ' . $e->getMessage()), 500);
        }
    }
}
