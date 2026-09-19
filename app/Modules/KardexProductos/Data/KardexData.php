<?php

namespace App\Modules\KardexProductos\Data;

use Illuminate\Support\Facades\DB;

class KardexData
{
    /**
     * Listar movimientos de kardex por almacén y periodo.
     */
    public static function get_resumen_kardex(int $id_almacen, int $mes, int $yearcito)
    {
        $sql = '
        SELECT 
            k.id AS id_kardex,
            
            -- producto
            lp.id_producto,
            p.nombre AS producto,
            p.es_auditable,
            
            -- categoria
            p.id_categoria,
            cat.nombre as categoria,
            
            -- datos del lote
            k.id_lote_producto,
            lp.correlativo as correlativo_lote,
            lp.contenido_por_presentacion,
            
            -- datos del activo
            k.id_activo_fijo,
            act.correlativo as correlativo_activo_fijo,
            
            -- unidad base del producto
            p.id_unidad_medida_base,
            um_base.nombre as unidad_medida_base,
            um_base.abreviatura as unidad_medida_base_abv,
            
            -- unidad del lote, si aplica
            lp.id_unidad_medida as id_unidad_medida_lote,
            um_lote.nombre as unidad_medida_lote,
            um_lote.abreviatura as unidad_medida_lote_abv,
            
            -- datos del movimiento
            k.tipo_movimiento,
            k.tipo_origen,
            k.descripcion,
            
            -- stocks
            k.stock_anterior,
            k.stock_anterior_base,
            
            -- lo que se movio
            k.cantidad_movimiento,
            k.cantidad_movimiento_base,
            
            -- el resultado
            k.stock_resultante,
            k.stock_resultante_base,
            
            -- costos promedio
            p.moneda,
            k.costo_promedio_base, 
            k.costo_promedio_por_presentacion, 
            k.subtotal_promedio, 
            
            -- costos reales - todo en soles
            (lp.costo_por_unidad / lp.contenido_por_presentacion) as costo_por_unidad_base, -- un par
            lp.costo_por_unidad, -- una docenta
            lp.costo_por_unidad * k.cantidad_movimiento as subtotal, -- 
            
            k.created_at
        FROM
            kardex_producto k
        -- Se mantiene LEFT JOIN porque el movimiento es de uno u otro
        LEFT JOIN lote_producto lp ON lp.id = k.id_lote_producto
        LEFT JOIN activo_fijo act ON act.id = k.id_activo_fijo

        -- CORRECCIÓN: Evalúa correctamente el ID del producto que corresponda
        INNER JOIN producto p ON p.id = COALESCE(lp.id_producto, act.id_producto)

        INNER JOIN categoria cat on cat.id = p.id_categoria
        INNER JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON um_lote.id = lp.id_unidad_medida
        WHERE
            k.id_almacen = :id_almacen AND
            MONTH(k.created_at) = :mes AND
            YEAR(k.created_at) = :yearcito
        ORDER BY k.created_at DESC
        ';

        return DB::select($sql, [
            'id_almacen' => $id_almacen,
            'mes' => $mes,
            'yearcito' => $yearcito
        ]);
    }
}
