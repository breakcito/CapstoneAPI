<?php
namespace App\Modules\System\Data;

use App\Models\Menu;
use App\Models\Submenu;
use App\Models\Modulo;
use App\Models\ModuloRol;

class MenuSystemData
{
    public static function arbol_completo(): array
    {
        $menus = Menu::orderBy('numero_orden')->get();
        $submenus = Submenu::orderBy('numero_orden')->get()->groupBy('id_menu');
        $modulos = Modulo::orderBy('numero_orden')->get()->groupBy('id_submenu');

        return $menus->map(function ($m) use ($submenus, $modulos) {
            $misSub = $submenus->get($m->id, collect())->map(function ($s) use ($modulos) {
                $misMod = $modulos->get($s->id, collect())->values();
                $s->modulos = $misMod;
                return $s;
            })->values();
            $m->submenus = $misSub;
            return $m;
        })->values()->toArray();
    }

    public static function path_en_uso(?string $path, ?int $excluir_id, string $excluir_tipo): bool
    {
        // Si el path es null (contenedor), no hay nada que comparar.
        if ($path === null || $path === '') return false;

        $qMenu = Menu::where('path', $path);
        $qSub = Submenu::where('path', $path);
        $qMod = Modulo::where('path', $path);

        if ($excluir_id !== null) {
            if ($excluir_tipo === 'menu')    $qMenu->where('id', '!=', $excluir_id);
            if ($excluir_tipo === 'submenu') $qSub->where('id', '!=', $excluir_id);
            if ($excluir_tipo === 'modulo')  $qMod->where('id', '!=', $excluir_id);
        }

        return $qMenu->exists() || $qSub->exists() || $qMod->exists();
    }

    // Menu
    public static function menu_tiene_submenus(int $id): bool
    {
        return Submenu::where('id_menu', $id)->where('estado', 'Activo')->exists();
    }

    public static function crear_menu(string $nombre, ?string $path, int $numero_orden, bool $es_desplegable): int
    {
        return Menu::insertGetId([
            'nombre' => $nombre,
            'path' => $path,
            'numero_orden' => $numero_orden,
            'es_desplegable' => $es_desplegable,
            'estado' => 'Activo',
        ]);
    }

    public static function editar_menu(int $id, string $nombre, ?string $path, int $numero_orden, bool $es_desplegable): bool
    {
        return Menu::where('id', $id)->update([
            'nombre' => $nombre,
            'path' => $path,
            'numero_orden' => $numero_orden,
            'es_desplegable' => $es_desplegable,
        ]) > 0;
    }

    public static function eliminar_menu(int $id): bool
    {
        return Menu::where('id', $id)->delete() > 0;
    }

    // Submenu
    public static function submenu_tiene_modulos(int $id): bool
    {
        return Modulo::where('id_submenu', $id)->where('estado', 'Activo')->exists();
    }

    public static function crear_submenu(int $id_menu, string $nombre, ?string $path, int $numero_orden, bool $es_desplegable): int
    {
        return Submenu::insertGetId([
            'id_menu' => $id_menu,
            'nombre' => $nombre,
            'path' => $path,
            'numero_orden' => $numero_orden,
            'es_desplegable' => $es_desplegable,
            'estado' => 'Activo',
        ]);
    }

    public static function editar_submenu(int $id, string $nombre, ?string $path, int $numero_orden, bool $es_desplegable): bool
    {
        return Submenu::where('id', $id)->update([
            'nombre' => $nombre,
            'path' => $path,
            'numero_orden' => $numero_orden,
            'es_desplegable' => $es_desplegable,
        ]) > 0;
    }

    public static function eliminar_submenu(int $id): bool
    {
        return Submenu::where('id', $id)->delete() > 0;
    }

    // Modulo
    public static function modulo_tiene_permisos(int $id): bool
    {
        return ModuloRol::where('id_modulo', $id)->exists();
    }

    public static function crear_modulo(int $id_submenu, string $nombre, string $path, int $numero_orden): int
    {
        return Modulo::insertGetId([
            'id_submenu' => $id_submenu,
            'nombre' => $nombre,
            'path' => $path,
            'numero_orden' => $numero_orden,
            'es_desplegable' => false,
            'estado' => 'Activo',
        ]);
    }

    public static function editar_modulo(int $id, string $nombre, string $path, int $numero_orden): bool
    {
        return Modulo::where('id', $id)->update([
            'nombre' => $nombre,
            'path' => $path,
            'numero_orden' => $numero_orden,
        ]) > 0;
    }

    public static function eliminar_modulo(int $id): bool
    {
        ModuloRol::where('id_modulo', $id)->delete();
        return Modulo::where('id', $id)->delete() > 0;
    }
}