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
        float $stock_inicial,
        float $contenido_por_presentacion,
        ?string $fecha_hora_ingreso = null,
        ?string $fecha_vencimiento = null,
        ?string $comprobante_compra = null,
        ?float $costo_por_unidad = null
    ) {
        $new_lote_response = LotesProductosService::crear_lote(
            id_producto: $id_producto,
            id_unidad_medida: $id_unidad_medida,
            id_almacen: $id_almacen,
            contenido_por_presentacion: $contenido_por_presentacion,
            stock_inicial: $stock_inicial,
            fecha_hora_ingreso: $fecha_hora_ingreso,
            fecha_vencimiento: $fecha_vencimiento,
            comprobante_compra: $comprobante_compra,
            costo_por_unidad: $costo_por_unidad
        );

        $id_lote = $new_lote_response['data']['id_lote'] ?? null;
        return ApiResponse::success(
            $id_lote ? LotesData::get_lote_by_id(id_lote: (int) $id_lote) : null,
            'Lote registrado correctamente'
        );
    }

    public static function ajustar_stock(int $id_lote, float $nuevo_stock_base, ?string $motivo = null)
    {
        return DB::transaction(function () use ($id_lote, $nuevo_stock_base, $motivo) {
            $lote = LotesProductosData::get_lote_dinamico_by_id(id_lote: $id_lote, columnas: ['id_almacen', 'stock_actual_base', 'contenido_por_presentacion', 'stock_actual', 'costo_por_unidad']);
            if (!$lote) {
                return ApiResponse::error('Lote no encontrado');
            }

            $stock_anterior_base = (float) $lote['stock_actual_base'];
            if ($stock_anterior_base == $nuevo_stock_base) {
                return ApiResponse::error('El nuevo stock es igual al actual');
            }

            $diferencia_base = $nuevo_stock_base - $stock_anterior_base;
            $tipo_movimiento = $diferencia_base > 0 ? KardexTipoMovimiento::Ingreso : KardexTipoMovimiento::Salida;

            $contenido = (float) ($lote['contenido_por_presentacion'] ?? 1);
            $nuevo_stock = $contenido > 0 ? round($nuevo_stock_base / $contenido, 4) : $nuevo_stock_base;
            $cantidad_movimiento = abs($nuevo_stock - (float)$lote['stock_actual']);

            LotesProductosData::update_stock(
                id_lote: $id_lote,
                nuevo_stock: $nuevo_stock,
                nuevo_stock_base: $nuevo_stock_base
            );

            $costo_unitario = (float) ($lote['costo_por_unidad'] ?? 0);

            \App\Services\KardexProductosService::registrar_kardex(
                tipo_movimiento: $tipo_movimiento,
                tipo_origen: KardexOrigenMovimiento::AjusteStock->value,
                descripcion: $motivo ?? 'Ajuste de stock manual',
                cantidad_movimiento: $cantidad_movimiento,
                cantidad_movimiento_base: abs($diferencia_base),
                nuevo_stock: $nuevo_stock,
                nuevo_stock_base: $nuevo_stock_base,
                id_lote: $id_lote,
                id_almacen: (int) $lote['id_almacen'],
                stock_anterior: (float) $lote['stock_actual'],
                stock_anterior_base: $stock_anterior_base,
                costo: $cantidad_movimiento * $costo_unitario
            );

            return ApiResponse::success(LotesData::get_lote_by_id(id_lote: $id_lote), 'Stock del lote ajustado correctamente');
        });
    }

    /**
     * Actualizar campos de un lote.
     */
    public static function actualizar_lote(
        int $id_lote,
        ?string $comprobante_compra,
        ?string $fecha_hora_ingreso,
        ?float $costo_por_unidad = null
    ) {
        $existe = LotesData::get_resumen_lotes(id_lote: $id_lote);
        if (!$existe) {
            return ApiResponse::error('El lote que intenta editar no existe.');
        }

        LotesData::actualizar_lote(
            id_lote: $id_lote,
            comprobante_compra: $comprobante_compra,
            fecha_hora_ingreso: $fecha_hora_ingreso,
            costo_por_unidad: $costo_por_unidad
        );

        return ApiResponse::success(
            LotesData::get_resumen_lotes(id_lote: $id_lote),
            'Lote actualizado correctamente'
        );
    }

    /**
     * Desactivar (soft delete) un lote.
     */
    public static function eliminar_lote(int $id_lote) {
        $existe = LotesData::get_resumen_lotes(id_lote: $id_lote);
        if (!$existe) {
            return ApiResponse::error('El lote que intenta eliminar no existe.');
        }

        LotesData::eliminar_lote(id_lote: $id_lote);

        return ApiResponse::success(
            LotesData::get_resumen_lotes(id_lote: $id_lote),
            'Lote eliminado correctamente'
        );
    }
}
