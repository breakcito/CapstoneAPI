<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Shared\Enums\_Generic\Premura;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
use App\Shared\Responses\ApiResponse;
use App\Models\RequerimientoAlmacenDetalle;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento;
use Illuminate\Support\Facades\DB;

class AtencionService
{
    /**
     * ------------------------------------------------------
     * PARA LA CABECERA
     * ------------------------------------------------------
     */


    /**
     * Obtiene los requerimientos por almacén y periodo
     */
    public static function get_requerimientos(int $id_almacen, string $mes, string $yearcito)
    {
        $data = RequerimientosData::get_resumen_requerimientos($id_almacen, $mes, $yearcito);

        // Adjuntar labores a cada requerimiento
        foreach ($data as $req) {
            $req->evidencias = $req->evidencias ? json_decode($req->evidencias) : null;
        }

        return ApiResponse::success($data);
    }

    /**
     * Registrar un requerimiento de almacen desde el punto de vista del almacenero
     * labores: array de ids de labores
     * detalles: [
     *  {
     *   id_producto,
     *   id_unidad_medida,
     *   contenido_por_presentacion,
     *   cantidad_solicitada, // segun la unidad de medida del detalle (igual o diferente a la unidad base)
     *   comentario,
     *  }
     * ]
     */
    public static function registrar_requerimiento(
        ?int $id_empleado_solicitante,
        ?int $id_contratista_solicitante,
        int $id_empleado_registro,
        ?int $id_labor,
        int $id_almacen_destino,
        bool $es_auditable,
        Premura $premura,
        array $detalles,
        ?string $fecha_entrega_requerida = null,
        ?string $fecha_solicitud = null,
        ?string $observacion = null,
        ?array $evidencias = null
    ) {
        return DB::transaction(function () use ($id_empleado_solicitante, $id_contratista_solicitante, $id_empleado_registro, $id_labor, $id_almacen_destino, $es_auditable, $premura, $observacion, $fecha_entrega_requerida, $fecha_solicitud, $detalles, $evidencias) {
            // 1. Generar correlativo
            $correlativo = RequerimientosData::get_nuevo_correlativo();

            // 2. Procesar evidencias
            $evidenciasFinal = null;
            if (!empty($evidencias)) {
                $evidenciasFinal = RequerimientosData::guardar_evidencias($evidencias);
            }

            // 3. Crear cabecera
            $id_requerimiento = RequerimientosData::crear_requerimiento(
                id_empleado_solicitante: $id_empleado_solicitante,
                id_contratista_solicitante: $id_contratista_solicitante,
                id_empleado_registro: $id_empleado_registro,
                id_labor: $id_labor,
                id_almacen_destino: $id_almacen_destino,
                correlativo: $correlativo['correlativo'],
                numero_correlativo: $correlativo['numero_correlativo'],
                es_auditable: $es_auditable,
                premura: $premura,
                observacion: $observacion,
                fecha_entrega_requerida: $fecha_entrega_requerida,
                fecha_solicitud: $fecha_solicitud,
                evidencias: $evidenciasFinal
            );

            // 4. Crear Detalles y Trazabilidad
            foreach ($detalles as $detalle) {
                $contenido = (float) $detalle['contenido_por_presentacion'];
                $cantidad = (float) $detalle['cantidad_solicitada'];

                // Cuando el detalle trae "magnitud por ítem" (con_magnitud=1),
                // el contenido_por_presentacion que envía el front puede venir
                // como 1 (porque la conversión va implícita en
                // `valor_magnitud_base`). En ese caso reconstruimos el total en
                // unidad base con `cantidad_items × valor_magnitud_base`, que
                // es la fuente de verdad. Si no hay magnitud, usamos la
                // fórmula clásica `cantidad × contenido`.
                $conMagnitud = (bool) ($detalle['con_magnitud'] ?? false);
                $cantidadItems = (float) ($detalle['cantidad_items'] ?? 0);
                $valorMagnitudBase = (float) ($detalle['valor_magnitud_base'] ?? 0);
                if ($conMagnitud && $cantidadItems > 0 && $valorMagnitudBase > 0) {
                    $cantidad_base = $cantidadItems * $valorMagnitudBase;
                } else {
                    $cantidad_base = $cantidad * $contenido;
                }

                $id_detalle = RequerimientosDetalleData::crear_detalle(
                    $id_requerimiento,
                    $detalle['id_producto'],
                    $detalle['id_unidad_medida'],
                    $cantidad,
                    $contenido,
                    $cantidad_base,
                    $detalle['comentario'] ?? null,
                    (bool) ($detalle['para_mantenimiento'] ?? false),
                    $detalle['id_activo_fijo_destino'] ?? null,
                    con_magnitud: $conMagnitud,
                    cantidad_items: $cantidadItems > 0 ? $cantidadItems : null,
                    valor_magnitud: isset($detalle['valor_magnitud']) ? (float) $detalle['valor_magnitud'] : null,
                    valor_magnitud_base: $valorMagnitudBase > 0 ? $valorMagnitudBase : null,
                );

                RequerimientosDetalleData::registrar_trazabilidad($id_detalle, $id_empleado_registro);
            }

            // 5. Obtener resumen para el front
            $resumen = RequerimientosData::get_requerimiento_by_id($id_requerimiento);
            $resumen->evidencias = $resumen->evidencias ? json_decode($resumen->evidencias) : null;
            $resumen->detalles = RequerimientosDetalleData::get_detalles_by_requerimiento($id_requerimiento);

            return ApiResponse::success(
                $resumen,
                'Requerimiento generado correctamente'
            );
        });
    }


    /**
     * ------------------------------------------------------
     * PARA EL DETALLE
     * ------------------------------------------------------
     */


    /**
     * Obtiene los detalles de un requerimiento
     */
    public static function get_detalles_requerimiento(int $id_requerimiento)
    {
        $data = RequerimientosDetalleData::get_detalles_by_requerimiento($id_requerimiento);
        return ApiResponse::success($data);
    }

    /**
     * Cambia el estado de uno o varios productos (Aprobado/Rechazado) y registra en Timeline.
     */
    public static function cambiar_estado_detalle(int $id_empleado, array $ids_detalles, string $nuevo_estado, ?string $comentario_decision = null)
    {
        return DB::transaction(function () use ($id_empleado, $ids_detalles, $nuevo_estado, $comentario_decision) {

            foreach ($ids_detalles as $id_detalle) {
                // 1. Actualizar el estado del detalle
                RequerimientosDetalleData::update_detalle_estado((int) $id_detalle, $nuevo_estado, $id_empleado, $comentario_decision);

                // 2. Determinar el Enum para el log
                $estadoEnum = EstadoRequerimientoDetalle::from($nuevo_estado);

                // 3. Colocar en estado de proceso al requerimiento si uno de sus detalles es aprobado
                if (EstadoRequerimientoDetalle::Aprobado->value === $nuevo_estado) {
                    $requerimiento = RequerimientosDetalleData::get_id_requerimiento_by_detalle((int) $id_detalle);
                    RequerimientosData::update_requerimiento_estado((int) $requerimiento->id_requerimiento_almacen, EstadoRequerimiento::EnDespacho->value);
                }

                $descripcion = $estadoEnum->getGlosa();
                RequerimientosDetalleData::insert_detalle_log(
                    (int) $id_detalle,
                    $id_empleado,
                    $comentario_decision ?? $descripcion,
                    EstadoRequerimientoDetalleLog::from($nuevo_estado)
                );
            }

            $mensaje = count($ids_detalles) > 1
                ? 'Estado de los productos actualizado correctamente'
                : 'Estado del producto actualizado correctamente';

            return ApiResponse::success(null, $mensaje);
        });
    }

    /**
     * Obtiene la trazabilidad de un detalle de requerimiento
     */
    public static function obtener_trazabilidad(int $id_detalle)
    {
        $data = RequerimientosDetalleData::get_detalle_logs($id_detalle);
        return ApiResponse::success($data);
    }

    /**
     * Sube y asocia más evidencias a un requerimiento de almacén.
     *
     * @param array $evidencias Listado de archivos a subir (Illuminate\Http\UploadedFile[])
     */
    public static function subir_evidencias(int $id_requerimiento, array $evidencias)
    {
        return DB::transaction(function () use ($id_requerimiento, $evidencias) {
            $requerimiento = \App\Models\RequerimientoAlmacen::find($id_requerimiento);
            if (!$requerimiento) {
                throw new \Exception('Requerimiento no encontrado');
            }

            $nuevasEvidencias = RequerimientosData::guardar_evidencias($evidencias);

            $evidenciasExistentes = $requerimiento->evidencias ? json_decode($requerimiento->evidencias, true) : [];
            $evidenciasFinal = array_merge($evidenciasExistentes, $nuevasEvidencias);

            $requerimiento->evidencias = json_encode($evidenciasFinal);
            $requerimiento->save();

            return ApiResponse::success($evidenciasFinal, 'Evidencias subidad correctamente');
        });
    }

    /**
     * Edita un requerimiento. Cabecera siempre editable; detalles solo si
     * `cantidad_entregada_base = 0`. La operacion se ejecuta en una sola
     * transaccion y rechaza si algun detalle a modificar ya tiene entregas.
     *
     * Ademas permite:
     * - `detalles_crear`: crear nuevos detalles en este requerimiento
     *   (reutiliza el mismo flujo que el registro).
     * - `detalles_eliminar`: ids de detalles existentes a eliminar
     *   (solo los que no tengan entregas iniciadas).
     */
    public static function editar_requerimiento(
        int $id_requerimiento,
        int $id_empleado_editor,
        array $cabecera,
        array $detalles_editar,
        array $detalles_eliminar,
        ?array $evidencias_nuevas = null,
        array $detalles_crear = []
    ) {
        return DB::transaction(function () use ($id_requerimiento, $id_empleado_editor, $cabecera, $detalles_editar, $detalles_eliminar, $evidencias_nuevas, $detalles_crear) {
            $requerimiento = \App\Models\RequerimientoAlmacen::find($id_requerimiento);
            if (!$requerimiento) {
                return ApiResponse::error('Requerimiento no encontrado');
            }

            // Validar que al menos un detalle siga sin entrega iniciada; si
            // todos los detalles ya tienen despacho, no permitimos editar.
            $detallesActuales = RequerimientoAlmacenDetalle::where(
                'id_requerimiento_almacen',
                $id_requerimiento,
            )->get();

            $algunoNoEntregado = $detallesActuales->contains(
                fn($d) => (float) $d->cantidad_entregada_base === 0.0,
            );

            if (!$algunoNoEntregado) {
                return ApiResponse::error(
                    'No se puede editar un requerimiento que ya tiene entregas iniciadas',
                );
            }

            // 1. Actualizar cabecera (whitelist ya aplicada por Data).
            RequerimientosData::update_requerimiento_cabecera(
                $id_requerimiento,
                $cabecera,
            );

            // 2. Procesar detalles a editar.
            foreach ($detalles_editar as $det) {
                $idDetalle = (int) ($det['id_requerimiento_almacen_detalle'] ?? 0);
                if ($idDetalle <= 0) {
                    continue;
                }

                $fila = RequerimientosDetalleData::get_detalle_raw($idDetalle);
                if (!$fila || (int) $fila->id_requerimiento_almacen !== $id_requerimiento) {
                    return ApiResponse::error(
                        "El detalle {$idDetalle} no pertenece a este requerimiento",
                    );
                }
                if ((float) $fila->cantidad_entregada_base > 0) {
                    return ApiResponse::error(
                        "El detalle {$idDetalle} ya tiene entregas iniciadas y no puede modificarse",
                    );
                }

                // Tomamos valores actuales como base; el frontend envia solo
                // los campos a modificar, asi que mezclamos con la fila real.
                $cantidad = isset($det['cantidad_solicitada'])
                    ? (float) $det['cantidad_solicitada']
                    : (float) $fila->cantidad_solicitada;
                $contenido = isset($det['contenido_por_presentacion'])
                    ? (float) $det['contenido_por_presentacion']
                    : (float) $fila->contenido_por_presentacion;
                $conMagnitud = array_key_exists('con_magnitud', $det)
                    ? (bool) $det['con_magnitud']
                    : (bool) $fila->con_magnitud;
                $cantidadItems = isset($det['cantidad_items'])
                    ? (float) $det['cantidad_items']
                    : (float) ($fila->cantidad_items ?? 0);
                $valorMagnitudBase = isset($det['valor_magnitud_base'])
                    ? (float) $det['valor_magnitud_base']
                    : (float) ($fila->valor_magnitud_base ?? 0);

                $cantidad_base = RequerimientosDetalleData::recalcular_cantidad_base(
                    $cantidad,
                    $contenido,
                    $conMagnitud,
                    $cantidadItems,
                    $valorMagnitudBase,
                );

                RequerimientosDetalleData::update_detalle_editable(
                    $idDetalle,
                    [
                        'id_unidad_medida' => isset($det['id_unidad_medida'])
                            ? (int) $det['id_unidad_medida']
                            : (int) $fila->id_unidad_medida,
                        'cantidad_solicitada' => $cantidad,
                        'contenido_por_presentacion' => $contenido,
                        'cantidad_solicitada_base' => $cantidad_base,
                        'comentario' => array_key_exists('comentario', $det)
                            ? ($det['comentario'] ?: null)
                            : $fila->comentario,
                        'para_mantenimiento' => array_key_exists('para_mantenimiento', $det)
                            ? (bool) $det['para_mantenimiento']
                            : (bool) $fila->para_mantenimiento,
                        'id_activo_fijo_destino' => array_key_exists('id_activo_fijo_destino', $det)
                            ? ($det['id_activo_fijo_destino'] ?: null)
                            : $fila->id_activo_fijo_destino,
                        'con_magnitud' => $conMagnitud,
                        'cantidad_items' => $cantidadItems ?: null,
                        'valor_magnitud' => isset($det['valor_magnitud'])
                            ? (float) $det['valor_magnitud']
                            : ($fila->valor_magnitud ?? null),
                        'valor_magnitud_base' => $valorMagnitudBase ?: null,
                    ],
                );
            }

            // 3. Procesar detalles nuevos (crear). Mismas reglas de
            //    validacion que `registrar_requerimiento` (cantidad > 0,
            //    contenido > 0, mantenimiento => activo fijo destino).
            foreach ($detalles_crear as $det) {
                $idProducto = (int) ($det['id_producto'] ?? 0);
                $idUnidad = (int) ($det['id_unidad_medida'] ?? 0);
                $cantidad = (float) ($det['cantidad_solicitada'] ?? 0);
                $contenido = (float) ($det['contenido_por_presentacion'] ?? 0);
                if ($idProducto <= 0 || $idUnidad <= 0 || $cantidad <= 0 || $contenido <= 0) {
                    return ApiResponse::error(
                        'Datos inválidos en un producto nuevo: revise cantidad, unidad y contenido',
                    );
                }

                $conMagnitud = (bool) ($det['con_magnitud'] ?? false);
                $cantidadItems = (float) ($det['cantidad_items'] ?? 0);
                $valorMagnitudBase = (float) ($det['valor_magnitud_base'] ?? 0);
                $cantidadBaseCalculada = RequerimientosDetalleData::recalcular_cantidad_base(
                    $cantidad,
                    $contenido,
                    $conMagnitud,
                    $cantidadItems,
                    $valorMagnitudBase,
                );

                $paraMantenimiento = (bool) ($det['para_mantenimiento'] ?? false);
                $idActivoDestino = $paraMantenimiento
                    ? (int) ($det['id_activo_fijo_destino'] ?? 0) ?: null
                    : null;

                RequerimientosDetalleData::crear_detalle(
                    $id_requerimiento,
                    $idProducto,
                    $idUnidad,
                    $cantidad,
                    $contenido,
                    $cantidadBaseCalculada,
                    $det['comentario'] ?? null,
                    $paraMantenimiento,
                    $idActivoDestino,
                    con_magnitud: $conMagnitud,
                    cantidad_items: $cantidadItems > 0 ? $cantidadItems : null,
                    valor_magnitud: isset($det['valor_magnitud']) ? (float) $det['valor_magnitud'] : null,
                    valor_magnitud_base: $valorMagnitudBase > 0 ? $valorMagnitudBase : null,
                );
            }

            // 4. Procesar detalles a eliminar.
            foreach ($detalles_eliminar as $idDetalle) {
                $idDetalle = (int) $idDetalle;
                if ($idDetalle <= 0) {
                    continue;
                }
                $fila = RequerimientosDetalleData::get_detalle_raw($idDetalle);
                if (!$fila || (int) $fila->id_requerimiento_almacen !== $id_requerimiento) {
                    continue;
                }
                if ((float) $fila->cantidad_entregada_base > 0) {
                    return ApiResponse::error(
                        "El detalle {$idDetalle} ya tiene entregas iniciadas y no puede eliminarse",
                    );
                }
                RequerimientosDetalleData::delete_detalle_si_no_entregado($idDetalle);
            }

            // 5. Adjuntar evidencias nuevas si llegaron.
            if (!empty($evidencias_nuevas)) {
                $requerimiento = \App\Models\RequerimientoAlmacen::find($id_requerimiento);
                $nuevas = RequerimientosData::guardar_evidencias($evidencias_nuevas);
                $existentes = $requerimiento->evidencias
                    ? json_decode($requerimiento->evidencias, true)
                    : [];
                $requerimiento->evidencias = json_encode(array_merge($existentes, $nuevas));
                $requerimiento->save();
            }

            // 6. Devolver resumen + detalles actualizados para refrescar UI.
            $resumen = RequerimientosData::get_requerimiento_by_id($id_requerimiento);
            $resumen->evidencias = $resumen->evidencias ? json_decode($resumen->evidencias) : null;
            $resumen->detalles = RequerimientosDetalleData::get_detalles_by_requerimiento($id_requerimiento);

            return ApiResponse::success(
                $resumen,
                'Requerimiento actualizado correctamente',
            );
        });
    }
}
