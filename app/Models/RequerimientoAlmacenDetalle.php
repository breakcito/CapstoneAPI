<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacenDetalle extends Model
{
    protected $table = 'requerimiento_almacen_detalle';

    public $timestamps = false;

    protected $fillable = [
        'id_requerimiento_almacen',
        'id_producto', // Cable - Centimetros
        'id_unidad_medida', // Metros
        'con_magnitud', // FALSE|0 por default - disponible solo cuando ambas unidades de medida son universales
        'cantidad_items', // 0 por default: 4 cables
        'valor_magnitud', // (segun la unidad de medida del requerimiento)de 2 metros por cada cable
        'valor_magnitud_base', // 200cm por cada cable
        //
        'cantidad_solicitada', // cantidad de items * valor de magnitud: 8 metros
        'contenido_por_presentacion', // 100cm en 1 metro
        'cantidad_solicitada_base', // cantidad solicitada segun la unidad de medida base: 800cm
        'comentario',
        'estado',
    ];

    /**
     * Obtiene los detalles de un requerimiento de almacen
     */
    public static function get_detalles(
        ?int $id_detalle = null,
        ?int $id_requerimiento = null,
    ) {
        // 1. Definimos la base de la consulta (sin WHERE ni ORDER BY aún)
        $sql = '
        SELECT 
            rad.id AS id_requerimiento_almacen_detalle,
            
            pr.id AS id_producto,
            pr.nombre AS producto,
            pr.stock_minimo_base,
            pr.tipo_producto,
            
            -- unidad base y cantidades en base a esa unidad base del producto
            pr.id_unidad_medida_base,
            unib.abreviatura AS unidad_medida_base_abv,
            rad.contenido_por_presentacion, -- cuantas unidades base hay en una unidad del detalle del requerimiento
            rad.cantidad_solicitada_base,
           (
                SELECT SUM(entd.cantidad_base)
                FROM requerimiento_almacen_entrega_detalle entd
                WHERE entd.id_requerimiento_almacen_detalle = rad.id
            ) as cantidad_entregada_base,
            
            -- unidad del requerimiento y cantidades en base a esa unidad
            rad.id_unidad_medida as id_unidad_medida_req, 
            uni.abreviatura AS unidad_medida_req_abv,
            rad.cantidad_solicitada,
            (
                SELECT SUM(entd.cantidad_requerimiento)
                FROM requerimiento_almacen_entrega_detalle entd
                WHERE entd.id_requerimiento_almacen_detalle = rad.id
            ) as cantidad_entregada,
            
            -- stock disponible de ese producto del almacen que atendera el requerimiento
            (
                SELECT SUM(lot.stock_actual_base)
                FROM lote_producto lot
                WHERE
                    lot.id_almacen = alm.id AND
                    lot.id_producto = pr.id AND 
                    lot.estado = "Activo" AND 
                    lot.stock_actual_base > 0 AND
                    (lot.fecha_vencimiento > NOW() OR lot.fecha_vencimiento IS NULL)
			) as stock_disponible_base,
            
            -- comentario al registrar 
            rad.comentario,

            -- campos de magnitud por ítem (smart calc con magnitud)
            rad.con_magnitud,
            rad.cantidad_items,
            rad.valor_magnitud,
            rad.valor_magnitud_base,

            rad.estado
        FROM
            requerimiento_almacen_detalle rad
        INNER JOIN producto pr ON pr.id = rad.id_producto
        INNER JOIN unidad_medida unib ON unib.id = pr.id_unidad_medida_base
        INNER JOIN unidad_medida uni ON uni.id = rad.id_unidad_medida
        
        INNER JOIN requerimiento_almacen req on req.id = rad.id_requerimiento_almacen
        INNER JOIN almacen alm on alm.id = req.id_almacen_destino
        WHERE 1=1
        ';

        $params = [];

        if ($id_detalle !== null) {
            $sql .= ' AND rad.id = :id_detalle';
            $params['id_detalle'] = $id_detalle;
            return DB::selectOne($sql, $params);
        }

        if ($id_requerimiento !== null) {
            $sql .= ' AND rad.id_requerimiento_almacen = :id_requerimiento';
            $params['id_requerimiento'] = $id_requerimiento;
        }

        $sql .= ' ORDER BY pr.nombre';

        return DB::select($sql, $params);
    }
}
