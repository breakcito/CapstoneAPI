<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Controller;

use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use App\Shared\Helpers\UploadHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Service\AtencionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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
        $id_almacen = $request->input('id_almacen') ? (int) $request->input('id_almacen') : null;
        $mes = $request->input('mes');
        $yearcito = $request->input('yearcito');

        $result = AtencionService::get_requerimientos($id_almacen, $mes, $yearcito);

        return response()->json($result);
    }

    public function crear_requerimiento(Request $request): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $reglas = [
            'id_contratista_solicitante' => 'nullable|integer',
            'solicitante_es_contratista' => 'nullable|boolean',
            'id_almacen_destino' => 'required|integer',
            'fecha_solicitud' => 'nullable|date',
            'observacion' => 'nullable|string',
            'detalles' => 'required|array|min:1',
            'detalles.*.id_producto' => 'required|integer',
            'detalles.*.id_unidad_medida' => 'required|integer',
            'detalles.*.cantidad_solicitada' => 'required|numeric|min:0.01',
            'detalles.*.contenido_por_presentacion' => 'required|numeric|min:0.01',
            'detalles.*.comentario' => 'nullable|string',
            'detalles.*.con_magnitud' => 'nullable|boolean',
            'detalles.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles.*.valor_magnitud_base' => 'nullable|numeric|min:0',
            'evidencias' => 'nullable|array|max:' . (UploadHelper::MAX_TOTAL_BYTES / 1024),
            'evidencias.*' => 'file|max:' . (UploadHelper::MAX_FILE_BYTES / 1024),
        ];

        // Validar los archivos de forma previa. La regla `file` de Laravel
        // falla con un mensaje generico si PHP rechazo el archivo por
        // tamano (UPLOAD_ERR_INI_SIZE). Aqui leemos `$_FILES` y damos un
        // mensaje claro.
        $erroresUpload = UploadHelper::validar($request, 'evidencias');
        if (!empty($erroresUpload)) {
            return response()->json(ApiResponse::error('Archivos invalidos: ' . implode(' ', $erroresUpload)));
        }

        $validator = Validator::make($request->all(), $reglas);

        if ($validator->fails()) {
            $errores = $validator->errors()->all();
            return response()->json(ApiResponse::error('Datos inválidos: ' . implode(', ', $errores)));
        }

        $id_empleado_logueado = (int) $authUser->id_empleado;
        $evidencias = $request->file('evidencias', []);

        // Determinar el solicitante. El front envia `id_contratista_solicitante`
        // (que en realidad es el id del solicitante, sea contratista o
        // empleado) y `solicitante_es_contratista` (bool).
        //
        // - Si es contratista: ese id se guarda en
        //   `requerimiento_almacen.id_contratista_solicitante`. El
        //   `id_empleado_registro` queda con el logueado (quien registra).
        // - Si es empleado: ese id se guarda en
        //   `requerimiento_almacen.id_empleado_registro` (sobrescribiendo
        //   al logueado, porque el solicitante ES ese empleado). El
        //   `id_contratista_solicitante` queda null.
        $id_solicitante = $request->id_contratista_solicitante
            ? (int) $request->id_contratista_solicitante
            : null;
        $solicitante_es_contratista = $request->has('solicitante_es_contratista')
            ? $request->boolean('solicitante_es_contratista')
            : false;

        $id_contratista_a_persistir = null;
        $id_empleado_a_persistir = $id_empleado_logueado;

        if ($id_solicitante !== null) {
            if ($solicitante_es_contratista) {
                $id_contratista_a_persistir = $id_solicitante;
                // id_empleado_registro queda con el logueado.
            } else {
                $id_empleado_a_persistir = $id_solicitante;
                // id_contratista_solicitante queda null.
            }
        }

        try {
            $resultado = AtencionService::registrar_requerimiento(
                id_contratista_solicitante: $id_contratista_a_persistir,
                id_empleado_registro: $id_empleado_a_persistir,
                id_almacen_destino: (int) $request->id_almacen_destino,
                detalles: $request->detalles,
                fecha_solicitud: $request->fecha_solicitud,
                observacion: $request->observacion,
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
            'evidencias.*' => 'file|max:' . (UploadHelper::MAX_FILE_BYTES / 1024),
        ]);

        // Validar tamano de los archivos antes de la validacion de Laravel
        // para dar un mensaje claro si excede el limite de PHP.
        $erroresUpload = UploadHelper::validar($request, 'evidencias');
        if (!empty($erroresUpload)) {
            return response()->json(ApiResponse::error('Archivos invalidos: ' . implode(' ', $erroresUpload)), 400);
        }

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
     * detalles que aun no tengan entregas activas (estado de entrega = 'Entregado').
     */
    public function editar_requerimiento(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $reglas = [
            'id_contratista_solicitante' => 'nullable|integer',
            'solicitante_es_contratista' => 'nullable|boolean',
            'fecha_solicitud' => 'nullable|date',
            'observacion' => 'nullable|string',
            'evidencias_nuevas' => 'nullable|array',
            'evidencias_nuevas.*' => 'file|max:' . (UploadHelper::MAX_FILE_BYTES / 1024),
            'detalles_editar' => 'nullable|array',
            'detalles_editar.*.id_requerimiento_almacen_detalle' => 'required_with:detalles_editar|integer',
            'detalles_editar.*.id_unidad_medida' => 'nullable|integer',
            'detalles_editar.*.cantidad_solicitada' => 'nullable|numeric|min:0',
            'detalles_editar.*.contenido_por_presentacion' => 'nullable|numeric|min:0.0001',
            'detalles_editar.*.comentario' => 'nullable|string',
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
            'detalles_crear.*.con_magnitud' => 'nullable|boolean',
            'detalles_crear.*.cantidad_items' => 'nullable|numeric|min:0',
            'detalles_crear.*.valor_magnitud' => 'nullable|numeric|min:0',
            'detalles_crear.*.valor_magnitud_base' => 'nullable|numeric|min:0',
        ];

        $validator = Validator::make($request->all(), $reglas);

        // Validar tamano de archivos subidos para que el mensaje sea claro
        // si excede el limite del servidor.
        $erroresUpload = UploadHelper::validar($request, 'evidencias_nuevas');
        if (!empty($erroresUpload)) {
            return response()->json(ApiResponse::error('Archivos invalidos: ' . implode(' ', $erroresUpload)));
        }

        if ($validator->fails()) {
            $errores = $validator->errors()->all();
            return response()->json(ApiResponse::error('Datos inválidos: ' . implode(', ', $errores)));
        }

        // Misma logica de "solicitante dual" que en crear_requerimiento:
        // el id llega en `id_contratista_solicitante` y el flag
        // `solicitante_es_contratista` indica en que columna persistirlo.
        $id_solicitante_edit = $request->has('id_contratista_solicitante')
            ? ($request->id_contratista_solicitante ? (int) $request->id_contratista_solicitante : null)
            : null;
        $solicitante_es_contratista_edit = $request->has('solicitante_es_contratista')
            ? $request->boolean('solicitante_es_contratista')
            : false;

        $cabecera = [
            'id_contratista_solicitante' => null,
            'id_empleado_registro' => (int) $authUser->id_empleado,
            'fecha_solicitud' => $request->input('fecha_solicitud'),
            'observacion' => $request->input('observacion'),
        ];

        if ($id_solicitante_edit !== null) {
            if ($solicitante_es_contratista_edit) {
                $cabecera['id_contratista_solicitante'] = $id_solicitante_edit;
                // id_empleado_registro queda con el logueado (quien edita).
            } else {
                $cabecera['id_empleado_registro'] = $id_solicitante_edit;
                // id_contratista_solicitante queda null.
            }
        }

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

    /**
     * Anular un requerimiento completo.
     *
     * Comportamiento:
     * - Si el requerimiento NO tiene entregas activas: solo cambia el estado
     *   de la cabecera a "Anulado".
     * - Si el requerimiento TIENE entregas (estado='Entregado'): ademas de
     *   cambiar la cabecera a "Anulado", anula cada entrega activa, lo que
     *   REINTEGRA el stock a los lotes originales y registra el movimiento
     *   inverso en Kardex (Ingreso / Reingreso) por cada item.
     * - Si ya esta Anulado, no-op (devuelve ok para idempotencia).
     */
    public function anular_requerimiento(Request $request, int $id): JsonResponse
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser) {
            return response()->json(ApiResponse::error('No autorizado'), 401);
        }

        $motivo = $request->input('motivo');

        try {
            $requerimiento = DB::table('requerimiento_almacen')->where('id', $id)->first();
            if (!$requerimiento) {
                return response()->json(ApiResponse::error('Requerimiento no encontrado'), 404);
            }

            if ($requerimiento->estado === EstadoRequerimiento::Anulado->value) {
                return response()->json(ApiResponse::success(null, 'El requerimiento ya se encontraba anulado'));
            }

            // Anular cabecera + cada entrega activa. Si una entrega falla,
            // la transaccion hace rollback automatico y no se anula nada.
            DB::transaction(function () use ($id, $motivo) {
                // 1. Listar entregas activas (estado='Entregado').
                $entregasActivasIds = DB::table('requerimiento_almacen_entrega')
                    ->where('id_requerimiento_almacen', $id)
                    ->where('estado', 'Entregado')
                    ->pluck('id')
                    ->toArray();

                // 2. Anular cada entrega. Cada llamada ya hace su propia
                //    transaccion interna (reintegrar_stock + kardex inverso
                //    + marcar cabecera entrega como Anulada). Como estamos
                //    dentro de una transaccion padre, cualquier error hace
                //    rollback global.
                foreach ($entregasActivasIds as $idEntrega) {
                    $result = \App\Modules\RequerimientosAlmacenAtencion\Service\EntregaService::anular_entrega(
                        (int) $idEntrega,
                        $motivo ? "Anulacion por cancelacion de requerimiento: {$motivo}" : null
                    );
                    // EntregaService::anular_entrega devuelve ApiResponse.
                    // Si fallo, lanzamos excepcion para activar rollback.
                    if (!$result['success']) {
                        throw new \Exception($result['message'] ?? 'Error al anular entrega');
                    }
                }

                // 3. Cambiar el estado de la cabecera del requerimiento.
                DB::table('requerimiento_almacen')
                    ->where('id', $id)
                    ->update([
                        'estado' => EstadoRequerimiento::Anulado->value,
                    ]);
            });

            $cantEntregas = DB::table('requerimiento_almacen_entrega')
                ->where('id_requerimiento_almacen', $id)
                ->where('estado', 'Anulado')
                ->count();

            $msg = $cantEntregas > 0
                ? "Requerimiento anulado correctamente. Se anularon {$cantEntregas} entrega(s) con reingreso de stock y Kardex."
                : 'Requerimiento anulado correctamente';

            return response()->json(ApiResponse::success(null, $msg));
        } catch (\Exception $e) {
            return response()->json(ApiResponse::error('Error al anular requerimiento: ' . $e->getMessage()), 500);
        }
    }
}
