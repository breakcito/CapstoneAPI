<?php

namespace App\Modules\LotesProductos\Service;

use App\Data\LotesProductosData;
use App\Services\LotesProductosService;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;
use App\Modules\LotesProductos\Data\LotesData;
use Illuminate\Support\Facades\DB;

class LotesService
{
    /**
     * Listar lotes de un almacén.
     */
    public static function get_resumen_lotes(int $id_almacen)
    {
        $lotes = LotesData::get_resumen_lotes($id_almacen);

        return ApiResponse::success($lotes);
    }

    /**
     * Crear nuevo lote e insertar en Kardex si aplica.
     */
    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        ?string $descripcion,
        float $stock_inicial,
        float $contenido_por_presentacion,
        string $fecha_hora_ingreso,
        ?string $fecha_vencimiento,
        // Nuevos
        ?string $serie_factura_compra = null,
        ?string $numero_factura_compra = null,
        ?float $costo_por_unidad = null
    ) {
        $new_lote_response = LotesProductosService::crear_lote(
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            id_almacen: $id_almacen,
            id_origen: null,
            //
            tabla_origen: null,
            //
            contenido_por_presentacion: $contenido_por_presentacion,
            stock_inicial: $stock_inicial,
            //
            fecha_hora_ingreso: $fecha_hora_ingreso,
            descripcion: $descripcion,
            fecha_vencimiento: $fecha_vencimiento,
            // Nuevos
            serie_factura_compra: $serie_factura_compra,
            numero_factura_compra: $numero_factura_compra,
            costo_por_unidad: $costo_por_unidad
        );

        $id_lote = $new_lote_response['data'];
        return ApiResponse::success(LotesData::get_lote_by_id(id_lote: $id_lote), 'Lote registrado correctamente');
    }

    public static function ajustar_stock(int $id_lote, float $nuevo_stock_base, ?string $motivo = null)
    {
        return DB::transaction(function () use ($id_lote, $nuevo_stock_base, $motivo) {
            $lote = LotesProductosData::get_lote_dinamico_by_id(id_lote: $id_lote, columnas: ['stock_actual_base']);
            if (!$lote) {
                return ApiResponse::error('Lote no encontrado');
            }

            $stock_anterior_base = (float) $lote['stock_actual_base'];
            if ($stock_anterior_base == $nuevo_stock_base) {
                return ApiResponse::error('El nuevo stock es igual al actual');
            }

            $diferencia_base = $nuevo_stock_base - $stock_anterior_base;
            $tipo_movimiento = $diferencia_base > 0 ? KardexTipoMovimiento::Ingreso : KardexTipoMovimiento::Salida;

            LotesProductosService::update_stock(
                id_lote: $id_lote,
                id_origen: null,
                tabla_origen: null,
                tipo_origen: KardexOrigenMovimiento::AjusteStock,
                tipo_movimiento: $tipo_movimiento,
                cantidad_movimiento_base: abs($diferencia_base),
                descripcion: $motivo
            );

            return ApiResponse::success(LotesData::get_lote_by_id(id_lote: $id_lote), 'Stock del lote ajustado correctamente');
        });
    }

    /**
     * Obtener información de lotes para impresión de tickets.
     */
    public static function get_info_to_tickets(array $ids_lotes)
    {
        $info = LotesProductosData::get_info_to_ticket(ids_lotes: $ids_lotes);
        return ApiResponse::success($info);
    }

    /**
     * Actualizar campos administrativos de un lote (NO stock/identificadores/estado/fecha_vencimiento).
     * Si se recibe id_empleado + nombre_empleado se calcula diff y se apendea
     * a cambios_log para trazabilidad.
     */
    public static function actualizar_lote(
        int $id_lote,
        string $descripcion,
        ?string $serie_factura_compra,
        ?string $numero_factura_compra,
        ?string $fecha_hora_ingreso,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ) {
        $existe = LotesData::get_resumen_lotes(id_lote: $id_lote);
        if (!$existe) {
            return ApiResponse::error('El lote que intenta editar no existe.');
        }

        LotesData::actualizar_lote(
            id_lote: $id_lote,
            descripcion: $descripcion,
            serie_factura_compra: $serie_factura_compra,
            numero_factura_compra: $numero_factura_compra,
            fecha_hora_ingreso: $fecha_hora_ingreso,
            id_empleado: $id_empleado,
            nombre_empleado: $nombre_empleado,
        );

        return ApiResponse::success(
            LotesData::get_resumen_lotes(id_lote: $id_lote),
            'Lote actualizado correctamente',
        );
    }

    /**
     * Desactivar (soft delete) un lote. Cambia estado a Inactivo y registra
     * la accion en cambios_log para trazabilidad.
     */
    public static function eliminar_lote(
        int $id_lote,
        ?int $id_empleado = null,
        ?string $nombre_empleado = null
    ) {
        $existe = LotesData::get_resumen_lotes(id_lote: $id_lote);
        if (!$existe) {
            return ApiResponse::error('El lote que intenta eliminar no existe.');
        }

        LotesData::eliminar_lote(
            id_lote: $id_lote,
            id_empleado: $id_empleado,
            nombre_empleado: $nombre_empleado,
        );

        return ApiResponse::success(
            LotesData::get_resumen_lotes(id_lote: $id_lote),
            'Lote eliminado correctamente',
        );
    }
}
