<?php

namespace App\Services;

use App\Data\KardexProductosData;
use App\Data\LotesProductosData;
use App\Data\ActivosFijosData;
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
        KardexOrigenMovimiento $tipo_origen,
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
        //
        ?string $created_at = null
    ) {
        $id_almacen = 0;
        // Consultar el costo promedio del producto del lote
        $costo_promedio_base = $costo_promedio_base ?? LotesProductosData::get_costo_promedio_producto($id_lote);
        $costo_promedio_por_presentacion = $costo_promedio_base;
        $subtotal_promedio = $costo_promedio_base;

        // si el registro es por un lote, obtenemos su almacen
        if ($id_lote != null) {
            $lote = LotesProductosData::get_lote_dinamico_by_id(
                id_lote: $id_lote,
                columnas: ['id_almacen', 'contenido_por_presentacion']
            );
            $id_almacen = $lote['id_almacen'];
            $costo_promedio_por_presentacion = $lote['contenido_por_presentacion'] * $costo_promedio_base;
            $subtotal_promedio = $costo_promedio_base * $cantidad_movimiento_base;
        }
        // si es por un activo fijo, obtenemos su almacen
        else if ($id_activo_fijo != null) {
            $activo = ActivosFijosData::get_activo_by_id(id_activo: $id_activo_fijo, columnas: ['id_almacen']);
            $id_almacen = $activo['id_almacen'] ?? 0;
        }


        return ApiResponse::success(KardexProductosData::registrar_kardex(
            id_almacen: $id_almacen,
            //
            id_lote: $id_lote,
            id_activo_fijo: $id_activo_fijo,
            //
            tipo_movimiento: $tipo_movimiento,
            tipo_origen: $tipo_origen,
            descripcion: $descripcion,
            //
            cantidad_movimiento: $cantidad_movimiento,
            cantidad_movimiento_base: $cantidad_movimiento_base,
            //
            nuevo_stock: $nuevo_stock,
            nuevo_stock_base: $nuevo_stock_base,
            //
            id_origen: $id_origen,
            tabla_origen: $tabla_origen,
            //
            stock_anterior: $stock_anterior,
            stock_anterior_base: $stock_anterior_base,
            //
            costo_promedio_base: $costo_promedio_base,
            costo_promedio_por_presentacion: $costo_promedio_por_presentacion,
            subtotal_promedio: $subtotal_promedio,
            //
            created_at: $created_at
        ));
    }
}
