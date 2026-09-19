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
            CASE
                WHEN ra.id_empleado_solicitante IS NOT NULL THEN CONCAT(emp.nombre, " ", emp.apellido)
                WHEN ra.id_contratista_solicitante IS NOT NULL THEN CONCAT(ctr.nombre, " ", ctr.apellido)
                ELSE NULL
            END AS solicitante,
            CONCAT(empr.nombre, " ", empr.apellido) AS empleado_registro,
            --
            ra.correlativo,
            ra.evidencias,
            ra.observacion,
            ra.fecha_solicitud,
            ra.estado,
            ra.created_at
        FROM
            requerimiento_almacen ra
        INNER JOIN almacen alm ON alm.id = ra.id_almacen_destino
        LEFT JOIN empleado ctr ON ctr.id = ra.id_contratista_solicitante
        INNER JOIN empleado empr ON empr.id = ra.id_empleado_registro
        WHERE 1=1
        ';

        $params = [];

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
            $sql .= ' AND MONTH(ra.created_at) = :mes AND YEAR(ra.created_at) = :yearcito';
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
