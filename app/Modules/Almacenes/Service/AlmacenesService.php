<?php

namespace App\Modules\Almacenes\Service;

use App\Shared\Responses\ApiResponse;
use App\Modules\Almacenes\Data\AlmacenesData;

class AlmacenesService
{
    /**
     * Listar almacenes.
     *
     * @param ?bool $para_carbon Si se pasa true|false filtra por ese tipo.
     *   Si es null, NO se aplica filtro y se devuelven tanto logistica como
     *   carbon. La vista /almacenes con tabs pasa true|false segun el tab.
     */
    public static function get_almacenes(?bool $para_carbon = null)
    {
        $almacenes = AlmacenesData::get_almacenes(para_carbon: $para_carbon);

        return ApiResponse::success($almacenes);
    }

    /**
     * Crear un almacen (logistica o carbon segun $para_carbon).
     *
     * La geografia (departamento/provincia/distrito) es opcional; el caller
     * debe encargarse de pasar los ids en cascada coherente.
     */
    public static function crear_almacen(
        string $nombre,
        bool $es_principal,
        bool $para_carbon = false,
        ?string $descripcion = null,
        ?int $id_departamento = null,
        ?int $id_provincia = null,
        ?int $id_distrito = null,
        ?string $direccion = null
    ) {
        if (AlmacenesData::verificar_nombre_duplicado($nombre)) {
            return ApiResponse::error('Ya existe un almacén con este nombre.');
        }

        $id_almacen = AlmacenesData::crear_almacen(
            nombre: $nombre,
            descripcion: $descripcion,
            es_principal: $es_principal,
            para_carbon: $para_carbon,
            id_departamento: $id_departamento,
            id_provincia: $id_provincia,
            id_distrito: $id_distrito,
            direccion: $direccion
        );
        $nuevoAlmacen = AlmacenesData::get_almacen_by_id($id_almacen);

        return ApiResponse::success($nuevoAlmacen, 'Almacén creado correctamente');
    }
}
