<?php

namespace App\Services;

use App\Data\ContratistasData;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

class ContratistasService
{
    /**
     * Listar contratistas
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
     */
    public static function crear_contratista(
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?UploadedFile $foto = null,
        ?bool $return_object = false
    ) {
        if (!empty($dni) && ContratistasData::ya_existe(dni: $dni)) {
            return ApiResponse::error('Ya existe un contratista registrado con este DNI.');
        }

        $url_foto = null;
        if ($foto && $foto->isValid()) {
            $archivo = ArchivoHelper::guardarArchivos('fotos-empleados', [$foto])[0] ?? null;
            if (!empty($archivo) && isset($archivo['url'])) {
                $url_foto = $archivo['url'];
            }
        }

        $id = ContratistasData::crear_contratista(
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            url_foto: $url_foto
        );

        if ($return_object) {
            $nuevoContratista = ContratistasData::get_contratistas(id_contratista: $id);

            return ApiResponse::success(
                $nuevoContratista,
                'Contratista registrado correctamente'
            );
        }

        return ApiResponse::success($id, 'Contratista registrado correctamente');
    }

    /**
     * Actualizar un contratista
     */
    public static function actualizar_contratista(
        int $id_contratista,
        string $nombre,
        string $apellido,
        ?string $dni = null
    ) {
        $actual = ContratistasData::get_contratistas(id_contratista: $id_contratista);
        if (!$actual) {
            return ApiResponse::error('Contratista no encontrado.');
        }

        if (!empty($dni) && \App\Data\EmpleadosData::ya_existe(dni: $dni, excluir_id: $id_contratista)) {
            return ApiResponse::error('Ya existe otro contratista con este DNI.');
        }

        ContratistasData::actualizar_contratista(
            id_contratista: $id_contratista,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni
        );

        $actualizado = ContratistasData::get_contratistas(id_contratista: $id_contratista);

        return ApiResponse::success($actualizado, 'Contratista actualizado correctamente.');
    }

    /**
     * Actualizar foto de contratista
     */
    public static function actualizar_foto(int $id_contratista, ?UploadedFile $foto = null)
    {
        return EmpleadosService::actualizar_foto(id_empleado: $id_contratista, nueva_foto: $foto);
    }

    /**
     * Borrado logico de un contratista
     */
    public static function eliminar_contratista(int $id_contratista)
    {
        $actual = ContratistasData::get_contratistas(id_contratista: $id_contratista);
        if (!$actual) {
            return ApiResponse::error('Contratista no encontrado.');
        }

        $ok = ContratistasData::eliminar_contratista(id_contratista: $id_contratista);
        if (!$ok) {
            return ApiResponse::error('No se pudo eliminar el contratista.');
        }

        return ApiResponse::success(null, 'Contratista eliminado correctamente.');
    }
}
