<?php

namespace App\Data;

use App\Models\KardexProducto;
use App\Shared\Enums\Kardex\KardexOrigenMovimiento as OrigenMovimiento;
use App\Shared\Enums\Kardex\KardexTipoMovimiento as TipoMovimiento;

class KardexProductosData
{
    /**
     * Metodo generico para realizar un registro en el kardex
     */
    public static function registrar_kardex(
        int $id_almacen,
        TipoMovimiento $tipo_movimiento,
        OrigenMovimiento|string $tipo_origen,
        string $descripcion,
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        float $nuevo_stock,
        float $nuevo_stock_base,
        ?int $id_lote = null,
        ?float $stock_anterior = null,
        ?float $stock_anterior_base = null,
        ?float $costo = null,
        ?string $created_at = null
    ): int {
        $origenStr = $tipo_origen instanceof OrigenMovimiento ? $tipo_origen->value : (string) $tipo_origen;

        return KardexProducto::insertGetId([
            'id_almacen' => $id_almacen,
            'id_lote_producto' => $id_lote,
            'tipo_movimiento' => $tipo_movimiento->value,
            'tipo_origen' => $origenStr,
            'descripcion' => $descripcion,
            'stock_anterior' => $stock_anterior ?? 0,
            'stock_anterior_base' => $stock_anterior_base ?? 0,
            'cantidad_movimiento' => $cantidad_movimiento,
            'cantidad_movimiento_base' => $cantidad_movimiento_base,
            'stock_resultante' => $nuevo_stock,
            'stock_resultante_base' => $nuevo_stock_base,
            'costo' => $costo ?? 0.0,
            'created_at' => $created_at ?? now(),
        ]);
    }
}
