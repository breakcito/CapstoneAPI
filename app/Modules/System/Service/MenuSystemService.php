<?php
namespace App\Modules\System\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\System\Data\MenuSystemData;

class MenuSystemService
{

    public static function arbol()
    {
        return ApiResponse::success(MenuSystemData::arbol_completo());
    }


    public static function crear_menu(string $nombre, ?string $path, int $numero_orden, bool $es_desplegable)
    {

        if (MenuSystemData::path_en_uso($path, null, 'menu')) {
            return ApiResponse::error('Ya existe un nodo con ese path.');
        }
        $id = MenuSystemData::crear_menu($nombre, $path, $numero_orden, $es_desplegable);
        return ApiResponse::success(['id_menu' => $id], 'Menu creado.');
    }

    public static function editar_menu(int $id, string $nombre, ?string $path, int $numero_orden, bool $es_desplegable)
    {


        if (!$es_desplegable && MenuSystemData::menu_tiene_submenus($id)) {
            return ApiResponse::error('No se puede marcar como no-desplegable: el menu tiene submenus activos.');
        }
        if (MenuSystemData::path_en_uso($path, $id, 'menu')) {
            return ApiResponse::error('Ya existe otro nodo con ese path.');
        }

        MenuSystemData::editar_menu($id, $nombre, $path, $numero_orden, $es_desplegable);
        return ApiResponse::success(null, 'Menu actualizado.');
    }

    public static function eliminar_menu(int $id)
    {
        if (MenuSystemData::menu_tiene_submenus($id)) {
            return ApiResponse::error('No se puede eliminar: tiene submenus activos.');
        }
        MenuSystemData::eliminar_menu($id);
        return ApiResponse::success(null, 'Menu eliminado.');
    }

    public static function crear_submenu(int $id_menu, string $nombre, ?string $path, int $numero_orden, bool $es_desplegable)
    {

        if (MenuSystemData::path_en_uso($path, null, 'submenu')) {
            return ApiResponse::error('Ya existe un nodo con ese path.');
        }
        $id = MenuSystemData::crear_submenu($id_menu, $nombre, $path, $numero_orden, $es_desplegable);
        return ApiResponse::success(['id_submenu' => $id], 'Submenu creado.');
    }

    public static function editar_submenu(int $id, string $nombre, ?string $path, int $numero_orden, bool $es_desplegable)
    {

        if (!$es_desplegable && MenuSystemData::submenu_tiene_modulos($id)) {
            return ApiResponse::error('No se puede marcar como no-desplegable: tiene modulos activos.');
        }
        if (MenuSystemData::path_en_uso($path, $id, 'submenu')) {
            return ApiResponse::error('Ya existe otro nodo con ese path.');
        }

        MenuSystemData::editar_submenu($id, $nombre, $path, $numero_orden, $es_desplegable);
        return ApiResponse::success(null, 'Submenu actualizado.');
    }

    public static function eliminar_submenu(int $id)
    {
        if (MenuSystemData::submenu_tiene_modulos($id)) {
            return ApiResponse::error('No se puede eliminar: tiene modulos activos.');
        }
        MenuSystemData::eliminar_submenu($id);
        return ApiResponse::success(null, 'Submenu eliminado.');
    }

    public static function crear_modulo(int $id_submenu, string $nombre, string $path, int $numero_orden)
    {

        if (MenuSystemData::path_en_uso($path, null, 'modulo')) {
            return ApiResponse::error('Ya existe un nodo con ese path.');
        }
        $id = MenuSystemData::crear_modulo($id_submenu, $nombre, $path, $numero_orden);
        return ApiResponse::success(['id_modulo' => $id], 'Modulo creado.');
    }

    public static function editar_modulo(int $id, string $nombre, string $path, int $numero_orden)
    {

        if (MenuSystemData::path_en_uso($path, $id, 'modulo')) {
            return ApiResponse::error('Ya existe otro nodo con ese path.');
        }
        MenuSystemData::editar_modulo($id, $nombre, $path, $numero_orden);
        return ApiResponse::success(null, 'Modulo actualizado.');
    }

    public static function eliminar_modulo(int $id)
    {
        MenuSystemData::eliminar_modulo($id);
        return ApiResponse::success(null, 'Modulo eliminado.');
    }
}