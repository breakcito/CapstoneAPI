<?php

namespace App\Services;

use App\Shared\Responses\ApiResponse;
use App\Data\MenuNavData;

class MenuNavService
{
    /**
     * Construye el árbol de navegación. Cada nivel expone su `path` y
     * `es_desplegable` para que el frontend decida si es hoja (Link directo)
     * o contenedor (expandible). No se concatenan paths: la URL es
     * `/${path}` siempre al nivel del nodo hoja.
     */
    public static function get_menu_navegacion(int $idRol): array
    {
        $menus = MenuNavData::get_menus_by_rol($idRol);
        if (empty($menus)) return ApiResponse::success([]);

        $idsMenus = array_column($menus, 'id_menu');
        $todosLosSubmenus = MenuNavData::get_submenus_by_rol_and_menus($idRol, $idsMenus);
        $idsSubmenus = array_column($todosLosSubmenus, 'id_submenu');
        $todosLosModulos = !empty($idsSubmenus)
            ? MenuNavData::get_modulos_by_rol_and_submenus($idRol, $idsSubmenus)
            : [];

        $modulosAgrupados = [];
        foreach ($todosLosModulos as $mod) {
            $modulosAgrupados[$mod->id_submenu][] = $mod;
        }

        $submenusAgrupados = [];
        foreach ($todosLosSubmenus as $sub) {
            $submenusAgrupados[$sub->id_menu][] = $sub;
        }

        $estructura = [];
        foreach ($menus as $menu) {
            $submenusData = [];
            $misSubmenus = $submenusAgrupados[$menu->id_menu] ?? [];

            foreach ($misSubmenus as $sub) {
                $misModulos = $modulosAgrupados[$sub->id_submenu] ?? [];
                $submenusData[] = [
                    'id_submenu'    => (int) $sub->id_submenu,
                    'nombre'        => $sub->nombre,
                    // Si es contenedor, path es irrelevante: lo dejamos en null
                    'path'          => $sub->es_desplegable ? null : $sub->path,
                    'es_desplegable'=> (bool) $sub->es_desplegable,
                    'modulos'       => array_map(fn($m) => [
                        'id_modulo' => (int) $m->id_modulo,
                        'nombre'    => $m->nombre,
                        'path'      => $m->path,
                        'es_desplegable' => (bool) $m->es_desplegable,
                    ], $misModulos),
                ];
            }

            $estructura[] = [
                'id_menu'       => (int) $menu->id_menu,
                'nombre'        => $menu->nombre,
                // Si es contenedor, path es irrelevante: lo dejamos en null
                'path'          => $menu->es_desplegable ? null : $menu->path,
                'es_desplegable'=> (bool) $menu->es_desplegable,
                'submenus'      => $submenusData,
            ];
        }

        return ApiResponse::success($estructura);
    }
}