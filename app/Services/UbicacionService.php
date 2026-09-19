<?php

namespace App\Services;

use App\Data\UbicacionData;
use App\Shared\Responses\ApiResponse;

class UbicacionService
{
    public static function get_departamentos(?int $id_departamento = null)
    {
        return ApiResponse::success(UbicacionData::get_departamentos(id_departamento: $id_departamento));
    }

    public static function get_provincias(?int $id_provincia = null, ?int $id_departamento = null)
    {
        return ApiResponse::success(UbicacionData::get_provincias(
            id_provincia: $id_provincia,
            id_departamento: $id_departamento
        ));
    }

    public static function get_distritos(?int $id_distrito = null, ?int $id_provincia = null, ?int $id_departamento = null)
    {
        return ApiResponse::success(UbicacionData::get_distritos(
            id_distrito: $id_distrito,
            id_provincia: $id_provincia,
            id_departamento: $id_departamento
        ));
    }
}