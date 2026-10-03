<?php

namespace App\Modules\RequerimientosAlmacenAtencion\Data;

use App\Models\RequerimientoAlmacenEntrega;
use App\Shared\Enums\RequerimientoAlmacen\EstadoRequerimientoEntrega;
use App\Shared\Helpers\CorrelativoHelper;
use Illuminate\Support\Facades\DB;

class EntregasData
{

    /**
     * Obtener el historial de entregas en base al detalle de un requerimiento
     */
    public static function get_historial_entregas(?int $id_requerimiento = null, ?int $id_entrega = null)
    {
        // Tanto el que entrega como el que recibe son empleados de la
        // tabla empleado (los contratistas con es_contratista=1 tambien
        // viven alli). Por eso hacemos JOIN solo a empleado dos veces.
        //
        // IMPORTANTE: `id_requerimiento_almacen` e `id_empleado_entrega`
        // deben ir en el SELECT porque `EntregaService::anular_entrega`
        // los lee del row para conocer a que requerimiento pertenece la
        // entrega y para registrar el log de trazabilidad.
        $sql = '
        SELECT
            ent.id AS id_requerimiento_almacen_entrega,
            ent.id_requerimiento_almacen,
            ent.id_empleado_entrega,
            CONCAT(emp_ent.nombre," ",emp_ent.apellido) AS empleado_entrega,
            CONCAT(emp_rec.nombre," ",emp_rec.apellido) AS empleado_recibe,
            CASE WHEN emp_rec.es_contratista = 1 THEN 1 ELSE 0 END AS receptor_es_contratista,
            ent.correlativo,
            ent.fecha_hora_entrega,
            ent.observacion,
            ent.evidencias,
            ent.created_at,
            ent.estado
        FROM
            requerimiento_almacen_entrega ent
        LEFT JOIN empleado emp_ent ON
            emp_ent.id = ent.id_empleado_entrega
        LEFT JOIN empleado emp_rec ON
            emp_rec.id = ent.id_empleado_recibe
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_entrega) {
            $sql .= ' AND ent.id = :id_entrega';
            $params['id_entrega'] = $id_entrega;
            return DB::selectOne($sql, $params);
        }

        if ($id_requerimiento) {
            $sql .= ' AND ent.id_requerimiento_almacen = :id_requerimiento';
            $params['id_requerimiento'] = $id_requerimiento;
        }

        $sql .= ' ORDER BY ent.correlativo DESC;';

        return DB::select($sql, $params);
    }

    /**
     * Obtener una entrega
     */
    public static function get_entrega_by_id(int $id_entrega)
    {
        return self::get_historial_entregas(id_entrega: $id_entrega);
    }

    /**
     * Obtener el nuevo correlativo para un entrega en
     * base al almacen de destino
     */
    public static function get_nuevo_correlativo()
    {
        return CorrelativoHelper::generar(
            prefijo: 'ENT',
            tabla: 'requerimiento_almacen_entrega',
            // alias: 'ent',
            // queryModifier: function ($query) {
            //     // conectamos las entregas con los requerimientos para filtrar por almacen
            //     $query->join(
            //         'requerimiento_almacen as req',
            //         'req.id',
            //         '=',
            //         'ent.id_requerimiento_almacen'
            //     );
            // },
            // filtros: ['req.id_almacen_destino' => $id_almacen_destino],
        );
    }

    /**
     * Crear una nueva entrega.
     *
     * Modelo dual: tanto el que entrega como el que recibe se guardan
     * con su id de empleado. Si el receptor es un contratista, tambien
     * va aqui (los contratistas viven en la tabla empleado con
     * es_contratista = 1).
     */
    public static function crear_entrega(
        int $id_requerimiento,
        int $id_empleado_entrega,
        ?int $id_empleado_recibe,
        string $correlativo,
        int $numero_correlativo,
        string $fecha_hora_entrega,
        ?string $observacion = null,
        ?array $evidencias = null,
    ) {
        return RequerimientoAlmacenEntrega::insertGetId([
            'id_requerimiento_almacen' => $id_requerimiento,
            'id_empleado_entrega' => $id_empleado_entrega,
            'id_empleado_recibe' => $id_empleado_recibe,
            'correlativo' => $correlativo,
            'numero_correlativo' => $numero_correlativo,
            'fecha_hora_entrega' => $fecha_hora_entrega,
            'observacion' => $observacion,
            'evidencias' => $evidencias ? json_encode($evidencias) : null,
            'created_at' => now(),
            'estado' => EstadoRequerimientoEntrega::Entregado->value
        ]);
    }

    /**
     * Marcar una entrega como Anulada. Solo si esta actualmente Entregado.
     * Devuelve true si actualizo, false si no (porque ya estaba anulada o no existe).
     */
    public static function anular_entrega(int $id_entrega): bool
    {
        return (bool) RequerimientoAlmacenEntrega::where('id', $id_entrega)
            ->where('estado', EstadoRequerimientoEntrega::Entregado->value)
            ->update([
                'estado' => EstadoRequerimientoEntrega::Anulado->value,
            ]);
    }

    /**
     * Obtener los detalles de una entrega con los datos necesarios para
     * reintegrar stock al lote correspondiente (id_lote, cantidades).
     */
    public static function get_detalles_para_reintegrar(int $id_entrega): array
    {
        $sql = '
        SELECT
            raed.id AS id_entrega_detalle,
            raed.id_requerimiento_almacen_detalle,
            raed.id_lote_producto,
            raed.cantidad_base,
            raed.cantidad_lote,
            raed.cantidad_requerimiento
        FROM requerimiento_almacen_entrega_detalle raed
        WHERE raed.id_requerimiento_almacen_entrega = :id_entrega
        ';
        return DB::select($sql, ['id_entrega' => $id_entrega]);
    }
}
