<?php

namespace App\Data;

use Illuminate\Support\Facades\DB;

class MenuNavData
{
    /**
     * Menus visibles para el rol. UNION ALL entre permiso directo (id_menu en
     * modulo_rol) y permiso derivado (modulos hijos con permiso id_modulo).
     */
    public static function get_menus_by_rol(int $id_rol): array
    {
        $sql = '
        (SELECT m.id AS id_menu, m.nombre, m.path, m.numero_orden, m.es_desplegable
         FROM menu m
         INNER JOIN modulo_rol mr ON mr.id_menu = m.id AND mr.id_rol = :rol_a
         WHERE m.estado = "Activo")
        UNION
        (SELECT DISTINCT m.id AS id_menu, m.nombre, m.path, m.numero_orden, m.es_desplegable
         FROM menu m
         INNER JOIN submenu s ON s.id_menu = m.id AND s.estado = "Activo"
         INNER JOIN modulo md ON md.id_submenu = s.id AND md.estado = "Activo"
         INNER JOIN modulo_rol mr ON mr.id_modulo = md.id AND mr.id_rol = :rol_b
         WHERE m.estado = "Activo")
        ORDER BY numero_orden ASC;
        ';

        return DB::select($sql, ['rol_a' => $id_rol, 'rol_b' => $id_rol]);
    }

    /**
     * Submenus visibles para el rol, filtrados por los menus dados. UNION ALL
     * entre permiso directo (id_submenu en modulo_rol) y permiso derivado.
     */
    public static function get_submenus_by_rol_and_menus(int $id_rol, array $ids_menu): array
    {
        if (empty($ids_menu)) return [];
        $placeholders = implode(',', array_fill(0, count($ids_menu), '?'));

        $sql = "
        (SELECT s.id AS id_submenu, s.id_menu, s.nombre, s.path, s.numero_orden, s.es_desplegable
         FROM submenu s
         INNER JOIN modulo_rol mr ON mr.id_submenu = s.id AND mr.id_rol = ?
         WHERE s.estado = 'Activo' AND s.id_menu IN ($placeholders))
        UNION
        (SELECT DISTINCT s.id AS id_submenu, s.id_menu, s.nombre, s.path, s.numero_orden, s.es_desplegable
         FROM submenu s
         INNER JOIN modulo md ON md.id_submenu = s.id AND md.estado = 'Activo'
         INNER JOIN modulo_rol mr ON mr.id_modulo = md.id AND mr.id_rol = ?
         WHERE s.estado = 'Activo' AND s.id_menu IN ($placeholders))
        ORDER BY numero_orden ASC;
        ";

        return DB::select($sql, array_merge([$id_rol], $ids_menu, [$id_rol], $ids_menu));
    }

    /**
     * Modulos visibles para el rol. Los modulos son siempre hojas, sin UNION.
     */
    public static function get_modulos_by_rol_and_submenus(int $id_rol, array $ids_submenu): array
    {
        if (empty($ids_submenu)) return [];
        $placeholders = implode(',', array_fill(0, count($ids_submenu), '?'));

        $sql = "
        SELECT DISTINCT
          md.id AS id_modulo, md.id_submenu, md.nombre, md.path,
          md.numero_orden, md.es_desplegable
        FROM modulo md
        INNER JOIN modulo_rol mr ON mr.id_modulo = md.id AND mr.id_rol = ?
        WHERE md.estado = 'Activo' AND md.id_submenu IN ($placeholders)
        ORDER BY md.numero_orden ASC;
        ";

        return DB::select($sql, array_merge([$id_rol], $ids_submenu));
    }
}