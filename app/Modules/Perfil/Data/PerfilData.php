<?php

namespace App\Modules\Perfil\Data;

use Illuminate\Support\Facades\DB;

class PerfilData
{
    /**
     * Obtener toda la información necesaria para el perfil del usuario logueado
     */
    public static function get_info_perfil(int $id_usuario)
    {
        $sql = '
        SELECT
            usu.id as id_usuario,
            usu.username,
            usu.id_rol,
            rol.nombre as nombre_rol,
            usu.id_empleado,
            emp.nombre,
            emp.apellido,
            CONCAT(emp.nombre, " ", emp.apellido) as nombre_completo,
            emp.dni,
            emp.url_foto,
            emp.es_contratista,
            emp.estado as estado_empleado,
            usu.estado as estado_usuario
        FROM usuario usu
        INNER JOIN empleado emp ON emp.id = usu.id_empleado
        INNER JOIN rol ON rol.id = usu.id_rol
        WHERE usu.id = :id_usuario
        ';

        $res = DB::selectOne($sql, ['id_usuario' => $id_usuario]);
        if ($res) {
            $row = (array) $res;
            $row['es_contratista'] = (bool) ($row['es_contratista'] ?? 0);
            if (!empty($row['url_foto']) && !str_starts_with($row['url_foto'], 'http')) {
                $row['url_foto'] = asset('storage/' . $row['url_foto']);
            }
            return (object) $row;
        }

        return null;
    }
}
