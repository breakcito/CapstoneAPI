<?php

namespace App\Services;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento;
use App\Shared\Responses\ApiResponse;

class KardexProductosService
{
    /**
     * Metodo generico para realizar un registro en el kardex
     */
    public static function registrar_kardex(
        KardexTipoMovimiento $tipo_movimiento,
        KardexOrigenMovimiento|string $tipo_origen,
        string $descripcion,
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        ?int $id_lote = null,
        ?int $id_almacen = null,
        ?float $stock_anterior = null,
        ?float $stock_anterior_base = null,
        ?float $costo = null,
        ?string $created_at = null
    ) {
        if ($id_almacen === null && $id_lote !== null) {
            $lote = LotesProductosData::get_lote_dinamico_by_id(
                id_lote: $id_lote,
                columnas: ['id_almacen', 'costo_por_unidad']
            );
            if ($lote) {
                $id_almacen = (int) $lote['id_almacen'];
                if ($costo === null && isset($lote['costo_por_unidad'])) {
                    $costo = $cantidad_movimiento * (float) $lote['costo_por_unidad'];
                }
            }
        }

        $id_almacen = $id_almacen ?? 0;

        $idKardex = KardexProductosData::registrar_kardex(
            id_almacen: $id_almacen,
            tipo_movimiento: $tipo_movimiento,
            tipo_origen: $tipo_origen,
            descripcion: $descripcion,
            cantidad_movimiento: $cantidad_movimiento,
            cantidad_movimiento_base: $cantidad_movimiento_base,
            nuevo_stock: $nuevo_stock,
            nuevo_stock_base: $nuevo_stock_base,
            id_lote: $id_lote,
            stock_anterior: $stock_anterior,
            stock_anterior_base: $stock_anterior_base,
            costo: $costo,
            created_at: $created_at
        );

        return ApiResponse::success($idKardex);
    }
}
