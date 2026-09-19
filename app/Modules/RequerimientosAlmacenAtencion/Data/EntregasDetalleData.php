<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenEntregaDetalle;
use Illuminate\Support\Facades\DB;

class EntregasDetalleData
{

    /**
     * Crear un detalle de entrega.
     */
    public static function crear_detalle_entrega(
        int $id_entrega,
        int $id_requerimiento_detalle,
        ?int $id_lote,
        float $cantidad_base,
        float $cantidad_lote,
        float $cantidad_requerimiento,
        float $costo = 0.0
    ) {
        return RequerimientoAlmacenEntregaDetalle::insertGetId([
            'id_requerimiento_almacen_entrega' => $id_entrega,
            'id_requerimiento_almacen_detalle' => $id_requerimiento_detalle,
            'id_lote_producto' => $id_lote,
            'cantidad_base' => $cantidad_base,
            'cantidad_lote' => $cantidad_lote,
            'cantidad_requerimiento' => $cantidad_requerimiento,
            'costo' => $costo,
        ]);
    }

    /**
     * Obtener los detalles de una entrega
     */
    public static function get_detalles_entrega(?int $id_entrega = null, ?int $id_detalle_entrega = null)
    {
        $sql = "
        SELECT
            raed.id AS id_entrega_detalle,
            raed.id_requerimiento_almacen_detalle,
            raed.id_lote_producto,
            lot.correlativo,
            lot.fecha_vencimiento,
            prod_lote.nombre AS producto,
            CASE WHEN lot.fecha_vencimiento IS NOT NULL THEN DATEDIFF(
                lot.fecha_vencimiento,
                CURRENT_DATE
            ) ELSE NULL
            END AS dias_para_vencer,
            CASE 
                WHEN prod_lote.es_perecible != 1 THEN 'N/A' 
                WHEN lot.fecha_vencimiento IS NULL THEN 'Sin fecha' 
                WHEN DATEDIFF(lot.fecha_vencimiento, CURRENT_DATE) < 0 THEN 'Vencido' 
                WHEN DATEDIFF(lot.fecha_vencimiento, CURRENT_DATE) <= prod_lote.dias_espera_vencimiento THEN 'Por vencer' 
                ELSE 'Vigente'
            END AS estado_vencimiento,
            raed.cantidad_base,
            raed.cantidad_lote,
            raed.cantidad_requerimiento,
            raed.costo,
            uni_lot.nombre as unidad_lote,
            uni_lot.abreviatura as unidad_lote_abv,
            uni_base_lote.nombre AS unidad_base,
            uni_base_lote.abreviatura AS unidad_base_abv
        FROM
            requerimiento_almacen_entrega_detalle raed
        LEFT JOIN lote_producto lot ON
            lot.id = raed.id_lote_producto
        LEFT JOIN producto prod_lote ON
            prod_lote.id = lot.id_producto
        LEFT JOIN unidad_medida uni_base_lote ON
            uni_base_lote.id = prod_lote.id_unidad_medida_base
        LEFT JOIN unidad_medida uni_lot ON
            uni_lot.id = lot.id_unidad_medida
        WHERE 1 = 1
        ";

        $params = [];

        if ($id_detalle_entrega) {
            $sql .= ' AND raed.id = :id_detalle_entrega';
            $params['id_detalle_entrega'] = $id_detalle_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_entrega) {
            $sql .= ' AND raed.id_requerimiento_almacen_entrega = :id_entrega';
            $params['id_entrega'] = $id_entrega;
        }

        $sql .= ' ORDER BY lot.correlativo DESC;';

        return DB::select($sql, $params);
    }

    /**
     * Obtener un detalle de entrega específico
     */
    public static function get_detalle_entrega_by_id(int $id_detalle_entrega)
    {
        return self::get_detalles_entrega(id_detalle_entrega: $id_detalle_entrega);
    }
}
