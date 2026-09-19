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
        //
        TipoMovimiento $tipo_movimiento,
        OrigenMovimiento $tipo_origen,
        string $descripcion,
        //
        float $cantidad_movimiento,
        float $cantidad_movimiento_base,
        //
        float $nuevo_stock,
        float $nuevo_stock_base,
        //
        ?int $id_lote = null,
        ?int $id_activo_fijo = null,
        ?int $id_origen = null,
        ?string $tabla_origen = null,
        //
        ?float $stock_anterior = null,
        ?float $stock_anterior_base = null,
        //
        ?float $costo_promedio_base = null,
        ?float $costo_promedio_por_presentacion = null,
        ?float $subtotal_promedio = null,
        //
        ?string $created_at = null
    ) {
        return KardexProducto::insertGetId([
            'id_almacen' => $id_almacen,
            //
            'id_lote_producto' => $id_lote,
            'id_activo_fijo' => $id_activo_fijo,
            //
            'id_origen' => $id_origen,
            'tabla_origen' => $tabla_origen,
            //
            'tipo_movimiento' => $tipo_movimiento->value,
            'tipo_origen' => $tipo_origen->value,
            'descripcion' => $descripcion,
            //
            'stock_anterior' => $stock_anterior,
            'stock_anterior_base' => $stock_anterior_base,
            //
            'cantidad_movimiento' => $cantidad_movimiento,
            'cantidad_movimiento_base' => $cantidad_movimiento_base,
            //
            'stock_resultante' => $nuevo_stock,
            'stock_resultante_base' => $nuevo_stock_base,
            //
            'costo_promedio_base' => $costo_promedio_base,
            'costo_promedio_por_presentacion' => $costo_promedio_por_presentacion,
            'subtotal_promedio' => $subtotal_promedio,
            //
            'created_at' => $created_at ?? now(),
        ]);
    }
}
