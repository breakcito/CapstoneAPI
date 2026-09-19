<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Service;

use App\Data\LotesProductosData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoDetalle;
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
                // --- Camino: Producto Común con Lote ---
                $id_lote = (int) $item['id_lote_producto'];
                $lote = $lotesMap->get($id_lote);

                $costo_unitario = (float) ($lote['costo_por_unidad'] ?? 0);
                $subtotal = $costo_unitario * (float) $item['cantidad_lote'];

                // Crear Detalle de Entrega
                EntregasDetalleData::crear_detalle_entrega(
                    $id_entrega,
                    $id_rad,
                    $id_lote,
                    (float) $item['cantidad_base'],
                    (float) $item['cantidad_lote'],
                    (float) $item['cantidad_requerimiento'],
                    $subtotal
                );

                // Descontar Stock y registrar Kardex (Salida)
                LotesProductosService::descontar_stock(
                    id_lote: $id_lote,
                    cantidad_lote: (float) $item['cantidad_lote'],
                    cantidad_base: (float) $item['cantidad_base'],
                    tipo_origen: KardexOrigenMovimiento::Entrega->value,
                    descripcion: "Salida por entrega N° {$correlativoData['correlativo']}"
                );

                // Actualizar Requerimiento Detalle
                RequerimientosDetalleData::update_detalle_estado(
                    $id_rad,
                    EstadoRequerimientoDetalle::Completado->value,
                    $id_empleado_entrega
                );
            }

            // Actualizar estado general del requerimiento a Completado
            RequerimientosData::update_requerimiento_estado(
                $id_requerimiento,
                \App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimiento::Completado->value
            );

            return ApiResponse::success(
                $correlativoData['correlativo'],
                "Entrega N° {$correlativoData['correlativo']} registrada exitosamente"
            );
        });
    }
}
