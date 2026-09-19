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
        int $id_cargo,
        string $nombre,
        string $apellido,
        bool $con_contrato = false,
        ?int $id_contrato_vigente = null,
        ?string $genero = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?UploadedFile $foto = null,
        ?int $id_empresa = null
    ) {
        $response = EmpleadosServiceGlobal::crear_empleado(
            id_cargo: $id_cargo,
            nombre: $nombre,
            apellido: $apellido,
            con_contrato: $con_contrato,
            id_contrato_vigente: $id_contrato_vigente,
            genero: $genero,
            dni: $dni,
            ruc: $ruc,
            carnet_extranjeria: $carnet_extranjeria,
            pasaporte: $pasaporte,
            fecha_nacimiento: $fecha_nacimiento,
            direccion: $direccion,
            telefono: $telefono,
            email: $email,
            foto: $foto,
            id_empresa: $id_empresa
        );

        if ($response['success']) {
            $id = $response['data'];
            $new_empleado = EmpleadosData::get_empleados(id_empleado: $id);

            return ApiResponse::success(
                $new_empleado,
                'Empleado registrado correctamente'
            );
        }

        return $response;
    }
}
