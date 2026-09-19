<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Data\LotesProductosData;
use App\Services\ActivosFijosService;
use App\Services\LotesProductosService;
use App\Shared\Enums\ActivoFijo\MovimientoActivoFijo;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalleLog;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use App\Modules\RequerimientosAlmacenAtencion\Data\EntregasData;
use App\Modules\RequerimientosAlmacenAtencion\Data\EntregasDetalleData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosData;
use App\Modules\RequerimientosAlmacenAtencion\Data\RequerimientosDetalleData;
use Illuminate\Support\Facades\DB;

class EntregaService
{

    /**
     * Obtiene el historial de entregas y sus detalles.
     */
    public static function obtener_historial_entregas(int $id_requerimiento)
    {
        $data = EntregasData::get_historial_entregas(id_requerimiento: $id_requerimiento);

        foreach ($data as $entrega) {
            $entrega->evidencias = $entrega->evidencias ? json_decode($entrega->evidencias) : null;
            $entrega->detalles = EntregasDetalleData::get_detalles_entrega(
                id_entrega: (int) $entrega->id_requerimiento_almacen_entrega
            );
        }

        return ApiResponse::success($data);
    }

    /**
     * Registra una entrega física de materiales.
     * detalles: [
     *  {
     *   id_requerimiento_almacen_detalle,
     *   id_lote_producto,
     *   id_activo_fijo,
     *   cantidad_base,
     *   cantidad_lote,
     *   cantidad_requerimiento
     *  }
     * ]
     */
    public static function registrar_entrega(
        int $id_empleado_entrega,
        int $id_requerimiento,
        ?int $id_empleado_recibe,
        ?int $id_contratista_recibe,
        string $fecha_entrega,
        ?string $observacion,
        ?array $evidencias, // archivos
        array $detalles
    ) {
        return DB::transaction(function () use ($id_empleado_entrega, $id_requerimiento, $id_empleado_recibe, $id_contratista_recibe, $fecha_entrega, $observacion, $evidencias, $detalles) {

            // Procesar Evidencias si existen
            $evidenciasData = null;
            if (!empty($evidencias)) {
                $evidenciasData = ArchivoHelper::guardarArchivos('requerimientos_almacen_entregas', $evidencias);
            }

            // Pre-cargar todos los lotes en una sola consulta (solo los ítems de productos comunes)
            $items_con_lote = array_filter($detalles, fn($i) => empty($i['id_activo_fijo']));
            $ids_lotes = array_map(fn($i) => (int) $i['id_lote_producto'], $items_con_lote);

            $lotesMap = !empty($ids_lotes)
                ? collect(LotesProductosData::get_lote_dinamico_by_id(
                    id_lote: $ids_lotes,
                    columnas: ['stock_actual_base', 'correlativo', 'contenido_por_presentacion']
                ))->keyBy('id_lote')
                : collect();

            // Validar Stock solo para productos comunes
            foreach ($items_con_lote as $item) {
                $lote = $lotesMap->get((int) $item['id_lote_producto']);
                if (!$lote || $lote['stock_actual_base'] < $item['cantidad_base']) {
                    return ApiResponse::error("Stock insuficiente en el lote: " . ($lote['correlativo']));
                }
            }

            // Generar Correlativo
            // $requerimiento = RequerimientosData::get_almacen_destino_by_requerimiento($id_requerimiento);
            $correlativoData = EntregasData::get_nuevo_correlativo();

            // Crear Cabecera de Entrega
            $id_entrega = EntregasData::crear_entrega(
                id_requerimiento: $id_requerimiento,
                id_empleado_entrega: $id_empleado_entrega,
                id_empleado_recibe: $id_empleado_recibe,
                id_contratista_recibe: $id_contratista_recibe,
                correlativo: $correlativoData['correlativo'],
                numero_correlativo: $correlativoData['numero_correlativo'],
                fecha_hora_entrega: $fecha_entrega,
                observacion: $observacion,
                evidencias: $evidenciasData,
            );

            foreach ($detalles as $item) {
                $id_rad = $item['id_requerimiento_almacen_detalle'];
                $id_activo = !empty($item['id_activo_fijo']) ? (int) $item['id_activo_fijo'] : null;
                $es_activo = $id_activo !== null;

                $para_mantenimiento = (bool) ($item['para_mantenimiento'] ?? false);
                $para_produccion = (bool) ($item['para_produccion'] ?? false);
                $id_activo_fijo_destino = !empty($item['id_activo_fijo_destino']) ? (int) $item['id_activo_fijo_destino'] : null;
                $id_lote_mineral = !empty($item['id_lote_mineral']) ? (int) $item['id_lote_mineral'] : null;

                if ($es_activo) {
                    // El almacén que entrega pierde el activo; pasa a la mina del requerimiento
                    $id_mina_destino = RequerimientosData::get_id_mina_by_requerimiento($id_requerimiento);

                    ActivosFijosService::new_ubicacion(
                        id_activo: $id_activo,
                        tipo_movimiento: MovimientoActivoFijo::DeAlmacenAMina,
                        id_almacen: null,
                        id_mina: $id_mina_destino,
                        descripcion: "Entrega por requerimiento N° {$correlativoData['correlativo']}",
                        fecha_hora_movimiento: $fecha_entrega
                    );

                    // Detalle sin lote, sin costos (activos no tienen precio de lote en este flujo)
                    EntregasDetalleData::crear_detalle_entrega(
                        $id_entrega,
                        $id_rad,
                        null,   // id_lote
                        1,      // cantidad_base = 1 unidad
                        1,      // cantidad_lote
                        1,      // cantidad_requerimiento
                        0,      // costo_promedio
                        0,      // costo_unidad_lote
                        0,      // subtotal
                        $id_activo,
                        $para_mantenimiento,
                        $para_produccion,
                        $id_activo_fijo_destino,
                        $id_lote_mineral
                    );
                } else {
                    // --- Camino: Producto Común con Lote ---
                    $id_lote = $item['id_lote_producto'];
                    $lote = $lotesMap->get((int) $id_lote);

                    // Calcular el costo de lo entregado
                    $costo_promedio_base = LotesProductosData::get_costo_promedio_producto($id_lote);
                    $costo_unidad_lote = (float) $costo_promedio_base * (float) $lote['contenido_por_presentacion'];
                    $subtotal = $costo_unidad_lote * $item['cantidad_base'];

                    // Crear Detalle de Entrega
                    $id_detalle_entrega = EntregasDetalleData::crear_detalle_entrega(
                        $id_entrega,
                        $id_rad,
                        $id_lote,
                        $item['cantidad_base'],
                        $item['cantidad_lote'],
                        $item['cantidad_requerimiento'],
                        $costo_promedio_base,
                        $costo_unidad_lote,
                        $subtotal,
                        null,
                        $para_mantenimiento,
                        $para_produccion,
                        $id_activo_fijo_destino,
                        $id_lote_mineral
                    );

                    // Actualizar Stock y registrar Kardex (Salida)
                    LotesProductosService::update_stock(
                        id_lote: $id_lote,
                        id_origen: $id_detalle_entrega,
                        tabla_origen: null,
                        tipo_origen: KardexOrigenMovimiento::Entrega,
                        tipo_movimiento: KardexTipoMovimiento::Salida,
                        cantidad_movimiento_base: $item['cantidad_base'],
                        descripcion: "Salida por entrega N° {$correlativoData['correlativo']}",
                    );
                }

                // Actualizar Requerimiento Detalle (común para ambos caminos)
                $detalle_req = RequerimientosDetalleData::get_cantidades_of_detalle_by_id($id_rad);
                $ya_entregado_antes = $detalle_req->cantidad_entregada_base;

                $cant_entregada = $es_activo ? 1 : $item['cantidad_requerimiento'];
                $cant_entregada_base = $es_activo ? 1 : $item['cantidad_base'];

                RequerimientosDetalleData::increment_detalle_entregado($id_rad, $cant_entregada, $cant_entregada_base);

                // Reload para ver el nuevo estado
                $detalle_req = RequerimientosDetalleData::get_cantidades_of_detalle_by_id($id_rad);

                // Actualizar Estado del Item
                $finalizo_item = ($detalle_req->cantidad_entregada_base >= $detalle_req->cantidad_solicitada_base);
                $nuevo_estado_item = $finalizo_item ? EstadoRequerimientoDetalle::Completado->value : EstadoRequerimientoDetalle::EnDespacho->value;

                RequerimientosDetalleData::update_detalle_estado($id_rad, $nuevo_estado_item, $id_empleado_entrega);

                //  Log de Trazabilidad ---
                if ($ya_entregado_antes == 0) { // si es la primera entrega
                    RequerimientosDetalleData::insert_detalle_log(
                        $id_rad,
                        $id_empleado_entrega,
                        EstadoRequerimientoDetalleLog::EnDespacho->getGlosa(),
                        EstadoRequerimientoDetalleLog::EnDespacho
                    );
                }

                // Por nueva entrega
                RequerimientosDetalleData::insert_detalle_log(
                    $id_rad,
                    $id_empleado_entrega,
                    EstadoRequerimientoDetalleLog::NuevaEntrega->getGlosa((string) $cant_entregada),
                    EstadoRequerimientoDetalleLog::NuevaEntrega
                );

                if ($finalizo_item) { // si ya finalizo
                    RequerimientosDetalleData::insert_detalle_log(
                        $id_rad,
                        $id_empleado_entrega,
                        EstadoRequerimientoDetalleLog::Completado->getGlosa(),
                        EstadoRequerimientoDetalleLog::Completado
                    );
                }
            }

            return ApiResponse::success(
                $correlativoData['correlativo'],
                "Entrega N° {$correlativoData['correlativo']} registrada exitosamente"
            );
        });
    }
}
