<?php

namespace App\Modules\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Data\AlmacenesData;
use App\Modules\Almacenes\Data\ResponsablesData;

class AlmacenesService
{
    /**
     * Listar almacenes.
     */
    public static function get_almacenes()
    {
        $almacenes = AlmacenesData::get_almacenes();

        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un almacen
     */
    public static function crear_almacen(
        string $nombre,
        ?int $id_departamento = null,
        ?int $id_provincia = null,
        ?int $id_distrito = null,
        ?string $direccion = null,
        ?int $id_empleado_responsable = null
    ) {
        if (AlmacenesData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id_almacen = AlmacenesData::crear_almacen(
            nombre: $nombre,
            id_departamento: $id_departamento,
            id_provincia: $id_provincia,
            id_distrito: $id_distrito,
            direccion: $direccion
        );

        if ($id_empleado_responsable !== null) {
            ResponsablesData::nuevo_responsable(
                id_almacen: $id_almacen,
                id_empleado: $id_empleado_responsable,
                fecha_inicio: date('Y-m-d')
            );
        }

        $nuevoAlmacen = AlmacenesData::get_almacen_by_id($id_almacen);

        return ApiResponse::success($nuevoAlmacen, 'Almacén creado correctamente');
    }

    /**
     * Actualizar un almacén
     */
    public static function actualizar_almacen(
        int $id_almacen,
        string $nombre,
        ?int $id_departamento = null,
        ?int $id_provincia = null,
        ?int $id_distrito = null,
        ?string $direccion = null
    ) {
        if (AlmacenesData::verificar_nombre_duplicado($nombre, $id_almacen)) {
            return ApiResponse::error('Ya existe otro almacén con este nombre.');
        }

        AlmacenesData::actualizar_almacen(
            id_almacen: $id_almacen,
            nombre: $nombre,
            id_departamento: $id_departamento,
            id_provincia: $id_provincia,
            id_distrito: $id_distrito,
            direccion: $direccion
        );

        $almacenActualizado = AlmacenesData::get_almacen_by_id($id_almacen);

        return ApiResponse::success($almacenActualizado, 'Almacén actualizado correctamente');
    }

    /**
     * Eliminar (inactivar) un almacén
     */
    public static function eliminar_almacen(int $id_almacen)
    {
        AlmacenesData::eliminar_almacen($id_almacen);

        return ApiResponse::success(null, 'Almacén inactivado correctamente');
    }
}
