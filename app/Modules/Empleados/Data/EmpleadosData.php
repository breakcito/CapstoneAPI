<?php

namespace App\Modules\Empleados\Data;

use Illuminate\Support\Facades\DB;

class EmpleadosData
{
    /**
     * Listar empleados con su cuenta y rol asociado si existe.
     */
    public static function get_empleados(?int $id_empleado = null)
    {
        $sql = '
        SELECT
            e.id AS id_empleado,
            e.nombre,
            e.apellido,
            CONCAT(e.nombre, " ", e.apellido) AS nombre_completo,
            e.dni,
            e.url_foto,
            e.es_contratista,
            e.estado,
            u.id AS id_usuario,
            u.username,
            u.id_rol,
            r.nombre AS nombre_rol,
            u.estado AS estado_usuario
        FROM
            empleado e
        LEFT JOIN usuario u ON u.id_empleado = e.id
        LEFT JOIN rol r ON r.id = u.id_rol
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_empleado) {
            $sql .= ' AND e.id = :id_empleado';
            $params['id_empleado'] = $id_empleado;

            $res = DB::selectOne($sql, $params);
            if (!$res) {
                return (object) [];
            }
            $row = (array) $res;
            $row['es_contratista'] = (bool) ($row['es_contratista'] ?? 0);
            if (!empty($row['url_foto']) && !str_starts_with($row['url_foto'], 'http')) {
                $row['url_foto'] = asset('storage/' . $row['url_foto']);
            }
            return (object) $row;
        }

        $sql .= ' ORDER BY e.apellido ASC, e.nombre ASC';

        return collect(DB::select($sql, $params))
            ->map(function ($row) {
                $row = (array) $row;
                $row['es_contratista'] = (bool) ($row['es_contratista'] ?? 0);
                if (!empty($row['url_foto']) && !str_starts_with($row['url_foto'], 'http')) {
                    $row['url_foto'] = asset('storage/' . $row['url_foto']);
                }
                return $row;
            })
            ->toArray();
    }
}
