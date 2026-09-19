<?php

namespace App\Data;

use App\Models\Empleado;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class ContratistasData
{
    /**
     * Listar contratistas
     */
    public static function get_contratistas(
        ?int $id_mina = null,
        ?int $id_contratista = null
    ) {
        return EmpleadosData::get_empleados(
            id_empleado: $id_contratista,
            es_contratista: true
        );
    }

    /**
     * Crear un nuevo contratista
     */
    public static function crear_contratista(
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?string $url_foto = null
    ): int {
        return EmpleadosData::crear_empleado(
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            es_contratista: true,
            url_foto: $url_foto
        );
    }

    public static function ya_existe(?string $dni = null): bool
    {
        return EmpleadosData::ya_existe(dni: $dni);
    }

    public static function actualizar_contratista(
        int $id_contratista,
        string $nombre,
        string $apellido,
        ?string $dni = null
    ): bool {
        return EmpleadosData::actualizar_empleado(
            id_empleado: $id_contratista,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            es_contratista: true
        );
    }

    public static function eliminar_contratista(int $id_contratista): bool
    {
        return EmpleadosData::eliminar_empleado($id_contratista);
    }
}
