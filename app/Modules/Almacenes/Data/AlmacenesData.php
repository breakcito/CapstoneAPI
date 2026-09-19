<?php

namespace App\Modules\Almacenes\Data;

use App\Models\Almacen;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class AlmacenesData
{
    /**
     * Listar un resumen de los almacenes.
     *
     * Filtros:
     * - id_almacen: devuelve solo la fila correspondiente.
     */
    public static function get_almacenes(?int $id_almacen = null)
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.nombre,
            a.direccion,
            a.id_departamento,
            a.id_provincia,
            a.id_distrito,
            d.nombre  AS departamento_nombre,
            p.nombre  AS provincia_nombre,
            di.nombre AS distrito_nombre,
            a.estado,
            (
                SELECT
                    GROUP_CONCAT(CONCAT(emp.nombre, " ", emp.apellido) ORDER BY ra.id DESC SEPARATOR ", ")
                FROM responsable_almacen ra
                INNER JOIN empleado emp ON emp.id = ra.id_empleado
                WHERE
                    ra.id_almacen = a.id AND
                    ra.estado = "Activo"
            ) AS responsables
        FROM
            almacen a
        LEFT JOIN departamento d  ON d.id  = a.id_departamento
        LEFT JOIN provincia   p  ON p.id  = a.id_provincia
        LEFT JOIN distrito    di ON di.id = a.id_distrito
        WHERE
            1 = 1
        ';

        $params = [];
        if ($id_almacen !== null) {
            $sql .= ' AND a.id = :id_almacen';
            $params['id_almacen'] = $id_almacen;

            return DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY a.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Obtener datos de un almacen
     */
    public static function get_almacen_by_id(int $id_almacen)
    {
        return self::get_almacenes(id_almacen: $id_almacen);
    }

    /**
     * Helper para registrar un almacen.
     */
    public static function crear_almacen(
        string $nombre,
        ?int $id_departamento = null,
        ?int $id_provincia = null,
        ?int $id_distrito = null,
        ?string $direccion = null,
    ) {
        return Almacen::insertGetId([
            'nombre' => $nombre,
            'id_departamento' => $id_departamento,
            'id_provincia' => $id_provincia,
            'id_distrito' => $id_distrito,
            'direccion' => $direccion,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Actualizar un almacen
     */
    public static function actualizar_almacen(
        int $id_almacen,
        string $nombre,
        ?int $id_departamento = null,
        ?int $id_provincia = null,
        ?int $id_distrito = null,
        ?string $direccion = null,
    ): bool {
        return Almacen::where('id', $id_almacen)->update([
            'nombre' => $nombre,
            'id_departamento' => $id_departamento,
            'id_provincia' => $id_provincia,
            'id_distrito' => $id_distrito,
            'direccion' => $direccion,
        ]) >= 0;
    }

    /**
     * Inactivar/eliminar un almacen
     */
    public static function eliminar_almacen(int $id_almacen): bool
    {
        return Almacen::where('id', $id_almacen)->update([
            'estado' => EstadoBase::Inactivo->value,
        ]) > 0;
    }

    /**
     * Verificar si ya existe un almacen con el mismo nombre
     */
    public static function verificar_nombre_duplicado(string $nombre, ?int $id_almacen_excluir = null)
    {
        return Almacen::where('nombre', $nombre)
            ->when($id_almacen_excluir !== null, fn($q) => $q->where('id', '!=', $id_almacen_excluir))
            ->exists();
    }
}
