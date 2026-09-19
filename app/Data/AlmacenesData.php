<?php

namespace App\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class AlmacenesData
{

    /**
     * obtener la lista simple de almacenes activos con filtros opcionales.
     */
    public static function get_almacenes(
        ?int $id_almacen = null,
        ?int $id_empleado_responsable = null,
    ) {
        $query = DB::table('almacen as alm')
            ->select(
                'alm.id as id_almacen',
                'alm.nombre',
                'alm.direccion',
                'alm.id_departamento',
                'alm.id_provincia',
                'alm.id_distrito',
                'd.nombre as departamento_nombre',
                'p.nombre as provincia_nombre',
                'di.nombre as distrito_nombre',
            )
            ->leftJoin('departamento as d', 'd.id', '=', 'alm.id_departamento')
            ->leftJoin('provincia as p', 'p.id', '=', 'alm.id_provincia')
            ->leftJoin('distrito as di', 'di.id', '=', 'alm.id_distrito')
            ->where('alm.estado', EstadoBase::Activo->value)
            ->distinct();

        // filtro por id de almacen
        if ($id_almacen !== null) {
            $query->where('alm.id', $id_almacen);
            return $query->get()->toArray()[0] ?? [];
        }

        // si recibimos el id del responsable
        if ($id_empleado_responsable !== null) {
            $query->join('responsable_almacen as res', 'res.id_almacen', '=', 'alm.id')
                ->where('res.estado', EstadoBase::Activo->value)
                ->where('res.id_empleado', $id_empleado_responsable);
        }

        // Primero ordenamos por es_principal (1 antes que 0) y luego por nombre
        return $query->orderBy('alm.nombre', 'asc')
            ->get()
            ->toArray();
    }
}
