<?php

namespace App\Data;

use App\Models\Empleado;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Obtener listado de empleados con su cuenta y rol si la tiene
     */
    public static function get_empleados(
        ?int $id_empleado = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?int $id_almacen_excluyente = null,
        ?bool $con_cuenta = null,
        ?bool $es_contratista = null
    ) {
        $query = DB::table('empleado as emp')
            ->selectRaw('
                emp.id as id_empleado,
                emp.nombre,
                emp.apellido,
                CONCAT(emp.nombre, " ", emp.apellido) as nombre_completo,
                emp.dni,
                emp.url_foto,
                emp.es_contratista,
                emp.estado,
                u.id as id_usuario,
                u.username,
                u.id_rol,
                r.nombre as nombre_rol,
                u.estado as estado_usuario
            ')
            ->leftJoin('usuario as u', 'u.id_empleado', '=', 'emp.id')
            ->leftJoin('rol as r', 'r.id', '=', 'u.id_rol');

        if ($estado !== null) {
            $query->where('emp.estado', $estado->value);
        }

        if ($es_contratista !== null) {
            $query->where('emp.es_contratista', $es_contratista ? 1 : 0);
        }

        if ($id_empleado !== null) {
            $query->where('emp.id', $id_empleado);
            $row = $query->first();
            if ($row) {
                $row = (array) $row;
                $row['es_contratista'] = (bool) $row['es_contratista'];
                if ($row['url_foto'] && !str_starts_with($row['url_foto'], 'http')) {
                    $row['url_foto'] = asset('storage/' . $row['url_foto']);
                }
                return $row;
            }
            return null;
        }

        // filtro por empleados ya asignados a un almacén
        if ($id_almacen_excluyente !== null) {
            $query->whereNotExists(function ($subquery) use ($id_almacen_excluyente) {
                $subquery->select(DB::raw(1))
                    ->from('responsable_almacen as res')
                    ->whereColumn('res.id_empleado', 'emp.id')
                    ->where('res.id_almacen', $id_almacen_excluyente)
                    ->where('res.estado', EstadoBase::Activo->value);
            });
        }

        // filtro listar solo empleados con/sin cuenta
        if ($con_cuenta !== null) {
            if ($con_cuenta === false) {
                $query->whereNull('u.id');
            } else {
                $query->whereNotNull('u.id');
            }
        }

        $query->orderBy('emp.apellido', 'asc')->orderBy('emp.nombre', 'asc');

        return $query->get()->map(function ($row) {
            $row = (array) $row;
            $row['es_contratista'] = (bool) $row['es_contratista'];
            if ($row['url_foto'] && !str_starts_with($row['url_foto'], 'http')) {
                $row['url_foto'] = asset('storage/' . $row['url_foto']);
            }
            return $row;
        })->toArray();
    }

    /**
     * Verificar si ya existe un empleado con el mismo documento
     */
    public static function ya_existe(?string $dni = null, ?int $excluir_id = null): bool
    {
        if (empty($dni)) {
            return false;
        }

        return Empleado::query()
            ->where('dni', $dni)
            ->where('estado', '!=', EstadoBase::Inactivo->value)
            ->when($excluir_id !== null, fn($q) => $q->where('id', '!=', $excluir_id))
            ->exists();
    }

    /**
     * Crear un nuevo empleado
     */
    public static function crear_empleado(
        string $nombre,
        string $apellido,
        ?string $dni = null,
        bool $es_contratista = false,
        ?string $url_foto = null
    ): int {
        return Empleado::insertGetId([
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
            'es_contratista' => $es_contratista ? 1 : 0,
            'url_foto' => $url_foto,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Metodo para consultar datos dinamicos de uno o varios empleados a la vez
     * @param int|array<int> $id_empleado
     * @param array<string> $columnas
     * @return array<mixed>|null
     */
    public static function get_empleado_dinamico_by_id(int|array $id_empleado, array $columnas): ?array
    {
        $esArray = is_array($id_empleado);
        $ids = $esArray ? $id_empleado : [$id_empleado];
        if (!in_array('id as id_empleado', $columnas, true) && !in_array('id', $columnas, true)) {
            $columnas[] = 'id as id_empleado';
        }
        $query = Empleado::whereIn('id', $ids)->get($columnas);
        if ($esArray) {
            return $query->toArray();
        }

        return $query->first()?->toArray();
    }

    /**
     * Actualizar foto de un empleado
     */
    public static function actualizar_foto(int $id_empleado, ?string $url_foto = null): int
    {
        return Empleado::where('id', $id_empleado)->update([
            'url_foto' => $url_foto,
        ]);
    }

    /**
     * Actualizar campos editables de un empleado
     */
    public static function actualizar_empleado(
        int $id_empleado,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?bool $es_contratista = null
    ): bool {
        $data = [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'dni' => $dni,
        ];

        if ($es_contratista !== null) {
            $data['es_contratista'] = $es_contratista ? 1 : 0;
        }

        return Empleado::where('id', $id_empleado)->update($data) >= 0;
    }

    /**
     * Borrado logico de un empleado (cambia estado a Inactivo).
     */
    public static function eliminar_empleado(int $id_empleado): bool
    {
        return Empleado::where('id', $id_empleado)
            ->where('estado', '!=', EstadoBase::Inactivo->value)
            ->update(['estado' => EstadoBase::Inactivo->value]) > 0;
    }
}
