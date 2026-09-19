<?php

namespace App\Services;

use App\Data\ContratistasData;
use App\Data\EmpleadosData;
use App\Models\Empleado;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ContratistasService
{
    /**
     * Listar contratistas con su mina y labores asignadas
     */
    public static function get_contratistas(
        ?int $id_mina = null,
        ?int $id_contratista = null
    ) {
        $contratistas = ContratistasData::get_contratistas(id_mina: $id_mina, id_contratista: $id_contratista);

        return ApiResponse::success($contratistas);
    }

    /**
     * Registrar un nuevo contratista
     * @param array $ids_labor IDs de las labores a asignar como activas desde hoy
     */
    public static function crear_contratista(
        int $id_mina,
        string $nombre,
        string $apellido,
        array $ids_labor = [],
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $genero = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?UploadedFile $foto = null,
        ?bool $return_object = false
    ) {
        if (ContratistasData::ya_existe(dni: $dni, ruc: $ruc, carnet_extranjeria: $carnet_extranjeria, pasaporte: $pasaporte)) {
            return ApiResponse::error('Ya existe un contratista registrado con estos documentos.');
        }

        $url_foto = null;
        if ($foto && $foto->isValid()) {
            $archivo = ArchivoHelper::guardarArchivos('fotos-contratistas', [$foto])[0];
            if (!empty($archivo)) {
                $url_foto = $archivo['url'];
            }
        }

        return DB::transaction(function () use ($id_mina, $nombre, $apellido, $dni, $ruc, $carnet_extranjeria, $pasaporte, $fecha_nacimiento, $genero, $direccion, $telefono, $email, $url_foto, $ids_labor, $return_object) {
            $id = ContratistasData::crear_contratista(
                id_mina: $id_mina,
                nombre: $nombre,
                apellido: $apellido,
                dni: $dni,
                ruc: $ruc,
                carnet_extranjeria: $carnet_extranjeria,
                pasaporte: $pasaporte,
                fecha_nacimiento: $fecha_nacimiento,
                url_foto: $url_foto,
                genero: $genero,
                direccion: $direccion,
                telefono: $telefono,
                email: $email
            );

            ContratistasData::asignar_labor($id, $ids_labor);

            if ($return_object) {
                $nuevoContratista = ContratistasData::get_contratistas(id_contratista: $id);

                return ApiResponse::success(
                    $nuevoContratista,
                    'Contratista registrado correctamente'
                );
            }

            return ApiResponse::success($id, 'Contratista registrado correctamente');
        });
    }

    /**
     * Actualizar un contratista (datos personales + contacto).
     * La foto va por el endpoint dedicado `/contratistas/{id}/foto`.
     */
    public static function actualizar_contratista(
        int $id_contratista,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $genero = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?int $idEmpleadoLog = null,
        ?string $nombreEmpleadoLog = null,
    ) {
        $actual = ContratistasData::get_contratistas(id_contratista: $id_contratista);
        if (! $actual) {
            return ApiResponse::error('Contratista no encontrado.');
        }

        ContratistasData::actualizar_contratista(
            id_contratista: $id_contratista,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            fecha_nacimiento: $fecha_nacimiento,
            genero: $genero,
            direccion: $direccion,
            telefono: $telefono,
            email: $email,
            idEmpleadoLog: $idEmpleadoLog,
            nombreEmpleadoLog: $nombreEmpleadoLog,
        );

        // Usamos el SELECT del modulo para que la respuesta tenga la MISMA
        // forma que el listado (`GET /contratistas`). La version global de
        // este Data NO incluye `estado`, `labores_asignadas` ni
        // `tipo_contrato_vigente`, y el frontend reemplaza la fila del
        // listado con esta respuesta: si faltan, el badge de estado queda
        // vacio y se pierden las labores hasta recargar la pagina.
        $actualizado = \App\Modules\Contratistas\Data\ContratistasData::get_contratistas(
            id_contratista: $id_contratista
        );

        return ApiResponse::success($actualizado, 'Contratista actualizado correctamente.');
    }

    /**
     * Borrado logico de un contratista (cambia estado a Inactivo).
     */
    public static function eliminar_contratista(int $id_contratista)
    {
        $actual = ContratistasData::get_contratistas(id_contratista: $id_contratista);
        if (! $actual) {
            return ApiResponse::error('Contratista no encontrado.');
        }

        $ok = EmpleadosData::eliminar_empleado(id_empleado: $id_contratista);
        if (! $ok) {
            return ApiResponse::error('No se pudo eliminar el contratista.');
        }

        return ApiResponse::success(null, 'Contratista eliminado correctamente.');
    }
}
