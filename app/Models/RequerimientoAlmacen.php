<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class RequerimientoAlmacen extends Model
{
    protected $table = 'requerimiento_almacen';

    public $timestamps = false;

    protected $fillable = [
        'id_contratista_solicitante', // el contratista que solicita - opc
        'id_empleado_registro',  // el almacenero que registra el requerimiento 
        'id_almacen', // el almacen que recibe el requerimiento
        //
        'correlativo',
        'numero_correlativo',
        //
        'observacion',
        'evidencias',
        'fecha_solicitud',
        //
        'created_at',
        'estado',
    ];

    /**
     * Obtiene los requerimientos de almacen
     */
    public static function get_requerimientos(
        ?int $id_requerimiento = null,
        ?int $id_almacen_destino = null,
        ?string $mes = null,
        ?string $yearcito = null
    ) {
        $sql = '
        SELECT
            ra.id AS id_requerimiento,
            --
            ra.id_almacen_destino,
            alm.nombre AS almacen_destino,
            --
            ra.id_contratista_solicitante,
            ra.id_empleado_registro,
            -- Solicitante: si es contratista, muestra su nombre desde
            -- ctr (unido por id_contratista_solicitante). Si no, el
            -- solicitante ES el empleado que registro (mismo registro).
            CASE
                WHEN ra.id_contratista_solicitante IS NOT NULL THEN CONCAT(ctr.nombre, " ", ctr.apellido)
                ELSE CONCAT(empr.nombre, " ", empr.apellido)
            END AS solicitante,
            CONCAT(empr.nombre, " ", empr.apellido) AS empleado_registro,
            --
            ra.correlativo,
            ra.evidencias,
            ra.observacion,
            ra.fecha_solicitud,
            ra.estado,
            ra.created_at,
            -- Progreso general de atencion: promedio del porcentaje de
            -- cada detalle. Si no hay detalles, 0. Esto permite que la
            -- pagina principal muestre el avance real de cada
            -- requerimiento en la lista.
            COALESCE((
                SELECT ROUND(AVG(
                    CASE
                        WHEN rad.cantidad_solicitada_base IS NULL OR rad.cantidad_solicitada_base = 0
                            THEN 0
                        ELSE LEAST(
                            100,
                            ROUND(
                                (
                                    COALESCE((
                                        SELECT SUM(entd.cantidad_base)
                                        FROM requerimiento_almacen_entrega_detalle entd
                                        INNER JOIN requerimiento_almacen_entrega rae
                                            ON rae.id = entd.id_requerimiento_almacen_entrega
                                        WHERE entd.id_requerimiento_almacen_detalle = rad.id
                                          AND rae.estado = :estado_entregado_3
                                    ), 0) / rad.cantidad_solicitada_base
                                ) * 100,
                                2
                            )
                        )
                    END
                ), 0)
                FROM requerimiento_almacen_detalle rad
                WHERE rad.id_requerimiento_almacen = ra.id
            ), 0) AS porcentaje_progreso_general
        FROM
            requerimiento_almacen ra
        INNER JOIN almacen alm ON alm.id = ra.id_almacen_destino
        LEFT JOIN empleado ctr ON ctr.id = ra.id_contratista_solicitante
        INNER JOIN empleado empr ON empr.id = ra.id_empleado_registro
        WHERE 1=1
        ';

        $params = [
            'estado_entregado_3' => 'Entregado',
        ];

        if ($id_requerimiento !== null) {
            $sql .= ' AND ra.id = :id_requerimiento';
            $params['id_requerimiento'] = $id_requerimiento;

            return DB::selectOne($sql, $params);
        }

        if ($id_almacen_destino !== null) {
            $sql .= ' AND ra.id_almacen_destino = :id_almacen_destino';
            $params['id_almacen_destino'] = $id_almacen_destino;
        }

        if ($mes && $yearcito) {
            // Filtra por la fecha REAL del negocio (fecha_solicitud), no por
            // la fecha automatica de insercion (created_at). Esto permite
            // que al cargar data retroactiva, los pedidos aparezcan en su mes
            // REAL aunque se hayan ingresado al sistema en otro mes.
            // Fallback a created_at solo si fecha_solicitud es NULL.
            $sql .= ' AND MONTH(COALESCE(ra.fecha_solicitud, ra.created_at)) = :mes';
            $sql .= ' AND YEAR(COALESCE(ra.fecha_solicitud, ra.created_at)) = :yearcito';
            $params['mes'] = $mes;
            $params['yearcito'] = $yearcito;
        }

        $sql .= ' 
        ORDER BY 
        	ra.created_at DESC
        ';

        return DB::select($sql, $params);
    }
}
