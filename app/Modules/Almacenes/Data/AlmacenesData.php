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
     * - para_carbon: true|false|null. Si es null, NO se aplica filtro (se
     *   devuelven tanto de logistica como de carbon). El caller decide.
     *
     * Devuelve tambien los datos de ubicacion (departamento/provincia/distrito
     * y direccion) cuando estan registrados.
     */
    public static function get_almacenes(?int $id_almacen = null, ?bool $para_carbon = null)
    {
        $sql = '
        SELECT
            a.id AS id_almacen,
            a.nombre,
            a.descripcion,
            a.es_principal,
            a.para_carbon,
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
            ) AS responsables,
            (
                SELECT
                    COUNT(*)
                FROM almacen_mina am
                WHERE
                    am.id_almacen = a.id
            ) AS minas_count -- a cuantas minas abastece
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

        if ($para_carbon !== null) {
            $sql .= ' AND a.para_carbon = :para_carbon';
            $params['para_carbon'] = $para_carbon ? 1 : 0;
        }

        $sql .= ' ORDER BY a.es_principal DESC, a.nombre ASC';

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
     *
     * - para_carbon: lo establece el caller (la vista de logistica pasa false,
     *   la vista de carbon pasa true).
     * - id_departamento / id_provincia / id_distrito / direccion: opcionales.
     *   Si se pasan, quedan persistidos. La cascada geografica es responsabilidad
     *   del caller (no se valida aqui que la provincia pertenezca al depto, etc).
     */
    public static function crear_almacen(
        string $nombre,
        ?string $descripcion = null,
        bool $es_principal = false,
        bool $para_carbon = false,
        ?int $id_departamento = null,
        ?int $id_provincia = null,
        ?int $id_distrito = null,
        ?string $direccion = null,
    ) {
        return Almacen::insertGetId([
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'es_principal' => $es_principal,
            'para_carbon' => $para_carbon,
            'id_departamento' => $id_departamento,
            'id_provincia' => $id_provincia,
            'id_distrito' => $id_distrito,
            'direccion' => $direccion,
            'estado' => EstadoBase::Activo->value,
        ]);
    }

    /**
     * Verificar si ya existe un almacen activo o inactivo con el mismo nombre
     */
    public static function verificar_nombre_duplicado(string $nombre)
    {
        return Almacen::where('nombre', $nombre)
            ->where('estado', [EstadoBase::Activo->value, EstadoBase::Inactivo->value])
            ->exists();
    }
}
