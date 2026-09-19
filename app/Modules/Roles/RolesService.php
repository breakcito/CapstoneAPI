<?php

namespace App\Modules\Roles;

use App\Shared\Responses\ApiResponse;
use App\Modules\Roles\Data\RolesData;
use App\Modules\Roles\Data\PermisosData;
use Illuminate\Support\Facades\DB;

class RolesService
{
    public static function get_roles()
    {
        $roles = RolesData::get_roles();
        return ApiResponse::success($roles);
    }

    public static function get_estructura_permisos()
    {
        $estructura = PermisosData::get_estructura_permisos();
        return ApiResponse::success($estructura);
    }

    /**
     * Crea un rol y asigna sus permisos (multi-nivel). Los permisos llegan
     * como array de {tipo, id}. Si el backend rechaza alguno por
     * es_desplegable=true, se hace rollback y se devuelve el error.
     */
    public static function crear_rol(array $data)
    {
        try {
            return DB::transaction(function () use ($data) {
                $id_rol = RolesData::crear_rol([
                    'nombre' => $data['nombre'],
                    'descripcion' => $data['descripcion'] ?? null,
                    'estado' => 'Activo',
                ]);

                foreach ($data['permisos'] as $permiso) {
                    PermisosData::asignar_permiso_a_rol(
                        $id_rol,
                        (int) $permiso['id'],
                        $permiso['tipo'],
                    );
                }

                $nuevoRol = RolesData::get_rol_by_id($id_rol);
                return ApiResponse::success($nuevoRol, 'Rol creado correctamente con sus permisos.');
            });
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::error('Ocurrió un error al registrar el rol: ' . $e->getMessage());
        }
    }

    public static function get_permisos_rol(int $id_rol)
    {
        $permisos = PermisosData::get_permisos_por_rol($id_rol);
        return ApiResponse::success($permisos);
    }

    /**
     * Actualiza permisos por diff (multi-nivel).
     */
    public static function actualizar_permisos_rol(int $id_rol, array $permisos_nuevos)
    {
        try {
            return DB::transaction(function () use ($id_rol, $permisos_nuevos) {
                $actuales = PermisosData::get_permisos_por_rol($id_rol);

                $keyOf = fn($p) => $p['tipo'] . ':' . $p['id'];
                $setActual = array_map($keyOf, $actuales);
                $setNuevo  = array_map($keyOf, $permisos_nuevos);

                $agregar  = array_diff($setNuevo, $setActual);
                $eliminar = array_diff($setActual, $setNuevo);

                foreach ($agregar as $k) {
                    [$tipo, $id] = explode(':', $k);
                    PermisosData::asignar_permiso_a_rol($id_rol, (int) $id, $tipo);
                }

                foreach ($eliminar as $k) {
                    [$tipo, $id] = explode(':', $k);
                    PermisosData::eliminar_permiso_de_rol($id_rol, (int) $id, $tipo);
                }

                return ApiResponse::success(null, 'Permisos actualizados correctamente.');
            });
        } catch (\DomainException $e) {
            return ApiResponse::error($e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar los permisos: ' . $e->getMessage());
        }
    }
}