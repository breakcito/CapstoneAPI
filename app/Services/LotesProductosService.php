<?php

namespace App\Services;

use App\Data\LotesProductosData;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\DB;

class LotesProductosService
{
    /**
     * Obtener los lotes disponibles de un almacen.
     * @param array<int> $ids_productos
     */
    public static function get_lotes_disponibles(int $id_almacen, array $ids_productos)
    {
        $lotes = LotesProductosData::get_lotes_disponibles(
            id_almacen: $id_almacen,
            ids_productos: $ids_productos
        );

        return ApiResponse::success($lotes);
    }

    /**
     * Crear nuevo lote e insertar en Kardex.
     */
    public static function crear_lote(
        int $id_producto,
        int $id_unidad_medida,
        int $id_almacen,
        float $contenido_por_presentacion,
        float $stock_inicial,
        ?string $fecha_hora_ingreso = null,
        ?string $fecha_vencimiento = null,
        ?string $comprobante_compra = null,
        ?float $costo_por_unidad = 0.0
    ) {
        $correlativoData = LotesProductosData::get_nuevo_correlativo();
        $correlativo = (string) $correlativoData['correlativo'];
        $numeroCorrelativo = (int) $correlativoData['numero_correlativo'];

        return DB::transaction(function () use (
            $id_producto,
            $id_unidad_medida,
            $id_almacen,
            $correlativo,
            $numeroCorrelativo,
            $contenido_por_presentacion,
            $stock_inicial,
            $fecha_hora_ingreso,
            $fecha_vencimiento,
            $comprobante_compra,
            $costo_por_unidad
        ) {
            $id_lote = LotesProductosData::crear_lote(
                id_producto: $id_producto,
                id_unidad_medida: $id_unidad_medida,
                id_almacen: $id_almacen,
                correlativo: $correlativo,
                numero_correlativo: $numeroCorrelativo,
                contenido_por_presentacion: $contenido_por_presentacion,
                stock_inicial: $stock_inicial,
                fecha_hora_ingreso: $fecha_hora_ingreso,
                fecha_vencimiento: $fecha_vencimiento,
                comprobante_compra: $comprobante_compra,
                costo_por_unidad: $costo_por_unidad
            );

            $stock_base = $stock_inicial * $contenido_por_presentacion;
            $costoTotal = $stock_inicial * ($costo_por_unidad ?? 0.0);

            // Registro obligatorio en Kardex
            KardexProductosService::registrar_kardex(
                tipo_movimiento: KardexTipoMovimiento::Ingreso,
                tipo_origen: KardexOrigenMovimiento::NuevoLote,
                descripcion: "Ingreso inicial de lote {$correlativo}" . ($comprobante_compra ? " ({$comprobante_compra})" : ''),
                cantidad_movimiento: $stock_inicial,
                cantidad_movimiento_base: $stock_base,
                nuevo_stock: $stock_inicial,
                nuevo_stock_base: $stock_base,
                id_lote: $id_lote,
                id_almacen: $id_almacen,
                stock_anterior: 0,
                stock_anterior_base: 0,
                costo: $costoTotal,
                created_at: $fecha_hora_ingreso ?? now()->toDateTimeString()
            );

            return ApiResponse::success([
                'id_lote' => $id_lote,
                'correlativo' => $correlativo,
            ], 'Lote registrado correctamente e ingresado al Kardex.');
        });
    }

    /**
     * Descontar stock de un lote por entrega o salida física y registrar en Kardex.
     */
    public static function descontar_stock(
        int $id_lote,
        float $cantidad_lote,
        float $cantidad_base,
        string $tipo_origen,
        string $descripcion
    ): bool {
        $lote = LotesProductosData::get_lote_dinamico_by_id($id_lote, [
            'id_almacen',
            'stock_actual',
            'stock_actual_base',
            'costo_por_unidad',
        ]);

        if (!$lote) {
            return false;
        }

        $stockAnterior = (float) $lote['stock_actual'];
        $stockAnteriorBase = (float) $lote['stock_actual_base'];
        $costoUnitario = (float) ($lote['costo_por_unidad'] ?? 0);

        $nuevoStock = max(0, $stockAnterior - $cantidad_lote);
        $nuevoStockBase = max(0, $stockAnteriorBase - $cantidad_base);

        LotesProductosData::update_stock($id_lote, $nuevoStock, $nuevoStockBase);

        $costoMovimiento = $cantidad_lote * $costoUnitario;

        KardexProductosService::registrar_kardex(
            tipo_movimiento: KardexTipoMovimiento::Salida,
            tipo_origen: $tipo_origen,
            descripcion: $descripcion,
            cantidad_movimiento: $cantidad_lote,
            cantidad_movimiento_base: $cantidad_base,
            nuevo_stock: $nuevoStock,
            nuevo_stock_base: $nuevoStockBase,
            id_lote: $id_lote,
            id_almacen: (int) $lote['id_almacen'],
            stock_anterior: $stockAnterior,
            stock_anterior_base: $stockAnteriorBase,
            costo: $costoMovimiento
        );

        return true;
    }
}