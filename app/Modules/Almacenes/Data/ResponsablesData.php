<?php

namespace App\Modules\Almacenes\Data;

use App\Models\ResponsableAlmacen;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ResponsablesData
{
    /**
     * Obtener el historial de responsables de un almacen
     */
    public static function get_historial_responsables(?int $id_almacen = null, ?int $id_responsable = null)
    {
        $sql = '
        SELECT
            ra.id AS id_responsable_almacen,
            ra.id_empleado,
            CONCAT(emp.nombre, " ", emp.apellido) as nombre_completo,
            emp.url_foto,
            emp.dni,
            ra.fecha_inicio,
            ra.fecha_fin,
            ra.estado
        FROM
            responsable_almacen ra
        INNER JOIN empleado emp ON emp.id = ra.id_empleado
        WHERE
            1 = 1
        ';

        $params = [];

        // Si se quiere obtener por id solo retornamos uno
        if ($id_responsable != null) {
            $sql .= ' AND ra.id = :id_responsable_almacen';
            $params['id_responsable_almacen'] = $id_responsable;
            return DB::selectOne($sql, $params);
        }

        if ($id_almacen != null) {
            $sql .= ' AND ra.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY ra.fecha_inicio DESC';

        return DB::select($sql, $params);
    }

    /**
     * Inactivar un responsable de almacen
     */
    public static function inactivar_responsable(int $id_responsable, string $fecha_fin)
    {
        return ResponsableAlmacen::where('id', $id_responsable)
            ->update([
                'fecha_fin' => $fecha_fin,
                'estado' => EstadoBase::Inactivo->value,
            ]);
    }

    /**
     * Obtener los datos de un responsable de almacen
     */
    public static function get_responsable_by_id(int $id_responsable)
    {
        return self::get_historial_responsables(id_responsable: $id_responsable);
    }

    /**
     * Asignar la fecha de fin de responsabilidad de los responsables de un almacen
     */
    public static function update_fecha_fin_responsabilidad(int $id_almacen, string $fecha_fin)
    {
        ResponsableAlmacen::where('id_almacen', $id_almacen)
            ->where('estado', EstadoBase::Activo->value) // solo responsables activos
            ->update([
                'fecha_fin' => $fecha_fin, // fecha final
                'estado' => EstadoBase::Inactivo->value, // se inactiva
            ]);
    }

    /**
     * Asignar un nuevo responsable de almacen
     */
    public static function nuevo_responsable(
        int $id_almacen,
        int $id_empleado,
        string $fecha_inicio
    ) {
        return ResponsableAlmacen::insertGetId([
            'id_almacen' => $id_almacen,
            'id_empleado' => $id_empleado,
            'fecha_inicio' => $fecha_inicio,
            'fecha_fin' => null,
            'estado' => EstadoBase::Activo->value,
        ]);
    }
}
