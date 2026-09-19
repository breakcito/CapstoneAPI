<?php

namespace App\Modules\Almacenes\Data;

use App\Models\AlmacenMina;
use Illuminate\Support\Facades\DB;

class AbastecimientoData
{
    /**
     * Listar las minas que abstece un almacen
     */
    public static function get_minas_abastecidas(?int $id_almacen = null, ?int $id_almacen_mina = null)
    {
        $sql = '
        SELECT
            am.id as id_almacen_mina,
            m.nombre,
            c.nombre AS concesion
        FROM
            almacen_mina am
        INNER JOIN mina m ON m.id = am.id_mina
        INNER JOIN concesion c ON c.id = m.id_concesion
        WHERE
            1 = 1
        ';

        $params = [];

        // Si solo se quiere obtener una asignacion
        if ($id_almacen_mina) {
            $sql .= ' AND am.id = :id_almacen_mina';
            $params['id_almacen_mina'] = $id_almacen_mina;

            return DB::selectOne($sql, $params);
        }
        if ($id_almacen) {
            $sql .= ' AND am.id_almacen = :id_almacen';
            $params['id_almacen'] = $id_almacen;
        }

        $sql .= ' ORDER BY m.nombre ASC';

        return DB::select($sql, $params);
    }

    /**
     * Verificar si la mina ya esta siendo abastecida por el almacen
     */
    public static function verificar_abastecimiento_mina(int $id_almacen, int $id_mina)
    {
        return AlmacenMina::where('id_almacen', $id_almacen)
            ->where('id_mina', $id_mina)
            ->exists();
    }

    /**
     * Asignar nueva mina por abastecer
     */
    public static function nueva_mina_por_abastecer(int $id_almacen, int $id_mina)
    {
        return AlmacenMina::insertGetId([
            'id_almacen' => $id_almacen,
            'id_mina' => $id_mina,
        ]);
    }

    /**
     * Obtener los datos de una mina abstecida
     */
    public static function get_mina_abastecida_by_id(int $id_almacen_mina)
    {
        return self::get_minas_abastecidas(id_almacen_mina: $id_almacen_mina);
    }

    /**
     * Dejar de abastecer a una mina
     */
    public static function eliminar_abastecimiento_mina(int $id_almacen_mina)
    {
        AlmacenMina::where('id', $id_almacen_mina)->delete();
    }

    /**
     * Listar todas las minas posibles para abastecer
     */
    public static function get_minas(int $id_almacen)
    {
        $sql = '
        SELECT DISTINCT
            min.id as id_mina,
            min.nombre,
            con.nombre as concesion
        FROM
            mina min
        INNER JOIN concesion con on con.id = min.id_concesion
        WHERE
            min.estado = "Activo" AND
            con.estado = "Activo" AND
            -- que no esten siendo abastecidas por el almacen
            min.id NOT IN (
                SELECT
                    alm.id_mina
                FROM almacen_mina alm
                WHERE 
                    alm.id_almacen = :id_almacen
            )
        ';

        return DB::select($sql, ['id_almacen' => $id_almacen]);
    }
}
