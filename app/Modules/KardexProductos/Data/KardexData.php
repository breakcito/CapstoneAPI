<?php

namespace App\Modules\KardexProductos\Data;

use Illuminate\Support\Facades\DB;

class KardexData
{
    /**
     * Listar movimientos de kardex por almacén y periodo opcional.
     */
    public static function get_resumen_kardex(int $id_almacen, ?int $mes = null, ?int $yearcito = null)
    {
        $sql = '
        SELECT 
            k.id AS id_kardex,
            k.id_almacen,
            
            -- producto
            lp.id_producto,
            p.nombre AS producto,
            p.tipo_producto,
            
            -- datos del lote
            k.id_lote_producto,
            lp.correlativo as correlativo_lote,
            lp.contenido_por_presentacion,
            
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
            
            -- costos
            k.costo,
            lp.costo_por_unidad,
            lp.costo_por_unidad_base,
            
            k.created_at
        FROM
            kardex_producto k
        LEFT JOIN lote_producto lp ON lp.id = k.id_lote_producto
        LEFT JOIN producto p ON p.id = lp.id_producto
        LEFT JOIN unidad_medida um_base ON um_base.id = p.id_unidad_medida_base
        LEFT JOIN unidad_medida um_lote ON um_lote.id = lp.id_unidad_medida
        WHERE
            k.id_almacen = :id_almacen
        ';

        $params = ['id_almacen' => $id_almacen];

        if ($mes !== null && $yearcito !== null) {
            $sql .= ' AND MONTH(k.created_at) = :mes AND YEAR(k.created_at) = :yearcito';
            $params['mes'] = $mes;
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' ORDER BY k.created_at DESC';

        return DB::select($sql, $params);
    }
}
