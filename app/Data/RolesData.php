<?php

namespace App\Data;

use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class RolesData
{
    /**
     * Metodo generico para obtener roles del sistema
     */
    public static function get_roles(
        ?int $id_rol = null,
        ?EstadoBase $estado = null,
    ) {
        $sql = '
        SELECT
            r.id as id_rol,
            r.nombre,
            r.descripcion
        FROM rol r
        WHERE 1 = 1
        ';

        $params = [];

        if ($id_rol !== null) {
            $sql .= " AND r.id = :id_rol";
            $params['id_rol'] = $id_rol;
            return DB::selectOne($sql, $params);
        }

        if ($estado !== null) {
            $sql .= " AND r.estado = :estado";
            $params['estado'] = $estado->value;
        }

        $sql .= " ORDER BY r.nombre;";
        return DB::select($sql, $params);
    }
}
