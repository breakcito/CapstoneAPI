<?php

namespace App\Modules\Empleados;

use App\Modules\Empleados\Data\EmpleadosData;
use App\Services\EmpleadosService as EmpleadosServiceGlobal;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

class EmpleadosService
{
    /**
     * Listar empleados
     */
    public static function get_empleados()
    {
        $empleados = EmpleadosData::get_empleados();

        return ApiResponse::success($empleados);
    }

    /**
     * Registrar un nuevo empleado
     */
    public static function crear_empleado(
        string $nombre,
        string $apellido,
        ?string $dni = null,
        bool $es_contratista = false,
        ?UploadedFile $foto = null
    ) {
        $response = EmpleadosServiceGlobal::crear_empleado(
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            es_contratista: $es_contratista,
            foto: $foto,
            return_object: false
        );

        if ($response['success']) {
            $id = (int) $response['data'];
            $new_empleado = EmpleadosData::get_empleados(id_empleado: $id);

            return ApiResponse::success(
                $new_empleado,
                'Empleado registrado correctamente'
            );
        }

        return $response;
    }

    /**
     * Actualizar empleado
     */
    public static function actualizar_empleado(
        int $id_empleado,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?bool $es_contratista = null
    ) {
        return EmpleadosServiceGlobal::actualizar_empleado(
            id_empleado: $id_empleado,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            es_contratista: $es_contratista
        );
    }

    /**
     * Eliminar empleado
     */
    public static function eliminar_empleado(int $id_empleado)
    {
        return EmpleadosServiceGlobal::eliminar_empleado(id_empleado: $id_empleado);
    }

    /**
     * Actualizar foto
     */
    public static function actualizar_foto(int $id_empleado, ?UploadedFile $foto = null)
    {
        return EmpleadosServiceGlobal::actualizar_foto(id_empleado: $id_empleado, nueva_foto: $foto);
    }
}
