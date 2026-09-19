<?php

namespace App\Modules\Almacenes\Data;

use App\Models\AlmacenVecino;
use Illuminate\Support\Facades\DB;

class VecinosData
{
    public static function get_vecinos(int $id_almacen)
    {
        $sql = "
        SELECT
            av.id AS id_almacen_vecino,
            a.id AS id_almacen,
            a.nombre,
            a.descripcion
        FROM
            almacen_vecino av
        INNER JOIN almacen a ON a.id = IF(av.id_almacen_a = :id_almacen, av.id_almacen_b, av.id_almacen_a)
        WHERE
            (av.id_almacen_a = :id_almacen_1 OR av.id_almacen_b = :id_almacen_2) AND
            a.estado = 'Activo'
        ORDER BY a.nombre ASC
        ";

        return DB::select($sql, [
            'id_almacen' => $id_almacen,
            'id_almacen_1' => $id_almacen,
            'id_almacen_2' => $id_almacen,
        ]);
    }

    public static function get_almacenes_disponibles_vecinos(int $id_almacen)
    {
        $sql = "
        SELECT
            a.id AS id_almacen,
            a.nombre,
            a.descripcion
        FROM
            almacen a
        WHERE
            a.id != :id_almacen AND
            a.estado = 'Activo' AND
            a.es_principal = 0 AND
            a.id NOT IN (
                SELECT id_almacen_b FROM almacen_vecino WHERE id_almacen_a = :id_almacen_1
                UNION
                SELECT id_almacen_a FROM almacen_vecino WHERE id_almacen_b = :id_almacen_2
            )
        ORDER BY a.nombre ASC
        ";

        return DB::select($sql, [
            'id_almacen' => $id_almacen,
            'id_almacen_1' => $id_almacen,
            'id_almacen_2' => $id_almacen,
        ]);
    }

    public static function verificar_vecino(int $id_almacen_a, int $id_almacen_b)
    {
        $id_min = min($id_almacen_a, $id_almacen_b);
        $id_max = max($id_almacen_a, $id_almacen_b);

        return AlmacenVecino::where('id_almacen_a', $id_min)
            ->where('id_almacen_b', $id_max)
            ->exists();
    }

    public static function agregar_vecino(int $id_almacen_a, int $id_almacen_b)
    {
        $id_min = min($id_almacen_a, $id_almacen_b);
        $id_max = max($id_almacen_a, $id_almacen_b);

        return AlmacenVecino::insertGetId([
            'id_almacen_a' => $id_min,
            'id_almacen_b' => $id_max,
        ]);
    }

    public static function get_vecino_by_id(int $id_almacen_vecino)
    {
        return DB::table('almacen_vecino')->where('id', $id_almacen_vecino)->first();
    }

    public static function eliminar_vecino(int $id_almacen_vecino)
    {
        AlmacenVecino::where('id', $id_almacen_vecino)->delete();
    }
}
