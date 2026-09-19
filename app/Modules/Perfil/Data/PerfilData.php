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
            emp.nombre,
            emp.apellido,
            emp.dni,
            emp.ruc,
            emp.carnet_extranjeria,
            emp.pasaporte,
            emp.fecha_nacimiento,
            emp.url_foto,
            emp.es_contratista,
            mn.nombre as mina_nombre,
            rol.nombre as nombre_rol,
            IFNULL(car.nombre, car_contrato.nombre) AS nombre_cargo,
            IFNULL(are.nombre, are_contrato.nombre) AS nombre_area,
            IFNULL(em.razon_social, em_contrato.razon_social) AS empresa_nombre
        FROM usuario usu
        INNER JOIN empleado emp ON emp.id = usu.id_empleado
        INNER JOIN rol ON rol.id = usu.id_rol
        LEFT JOIN cargo car ON car.id = emp.id_cargo
        LEFT JOIN area are ON are.id = car.id_area
        LEFT JOIN empresa em ON em.id = emp.id_empresa
        LEFT JOIN contrato_trabajo ct ON ct.id = emp.id_contrato_vigente
        LEFT JOIN cargo car_contrato ON car_contrato.id = ct.id_cargo
        LEFT JOIN area are_contrato ON are_contrato.id = car_contrato.id_area
        LEFT JOIN empresa em_contrato ON em_contrato.id = ct.id_empresa
        LEFT JOIN mina mn ON mn.id = emp.id_mina
        WHERE usu.id = :id_usuario
        ';

        return DB::selectOne($sql, ['id_usuario' => $id_usuario]);
    }
}
