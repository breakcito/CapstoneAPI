<?php

namespace App\Services;

use App\Data\EmpleadosData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Helpers\ArchivoHelper;
use App\Shared\Responses\ApiResponse;
use Illuminate\Http\UploadedFile;

class EmpleadosService
{
    /**
     * Listar empleados
     */
    public static function get_empleados(
        ?int $id_empleado = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?int $id_almacen_excluyente = null,
        ?bool $con_cuenta = null,
        ?bool $es_contratista = null
    ) {
        $empleados = EmpleadosData::get_empleados(
            id_empleado: $id_empleado,
            estado: $estado,
            id_almacen_excluyente: $id_almacen_excluyente,
            con_cuenta: $con_cuenta,
            es_contratista: $es_contratista
        );

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
        ?UploadedFile $foto = null,
        ?bool $return_object = false
    ) {
        if (!empty($dni) && EmpleadosData::ya_existe(dni: $dni)) {
            return ApiResponse::error('Ya existe un empleado registrado con el DNI proporcionado.');
        }

        $url_foto_str = null;
        if ($foto && $foto->isValid()) {
            $archivo = ArchivoHelper::guardarArchivos('fotos-empleados', [$foto])[0] ?? null;
            if ($archivo && isset($archivo['url'])) {
                $url_foto_str = $archivo['url'];
            }
        }

        $id = EmpleadosData::crear_empleado(
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            es_contratista: $es_contratista,
            url_foto: $url_foto_str
        );

        if ($return_object) {
            $nuevoEmpleado = EmpleadosData::get_empleados(id_empleado: $id);

            return ApiResponse::success(
                $nuevoEmpleado,
                'Empleado registrado correctamente'
            );
        }

        return ApiResponse::success($id, 'Empleado registrado correctamente');
    }

    /**
     * Actualizar la foto del empleado
     */
    public static function actualizar_foto(int $id_empleado, ?UploadedFile $nueva_foto = null)
    {
        $emp = EmpleadosData::get_empleado_dinamico_by_id($id_empleado, ['url_foto']);
        $url_foto_old = ! empty($emp['url_foto']) ? $emp['url_foto'] : null;

        if (is_null($nueva_foto)) {
            if ($url_foto_old) {
                ArchivoHelper::eliminarArchivo($url_foto_old);
                EmpleadosData::actualizar_foto($id_empleado, null);

                return ApiResponse::success(null, 'Foto eliminada correctamente.');
            }

            return ApiResponse::success(null, 'No hay foto para eliminar.');
        }

        if ($url_foto_old) {
            ArchivoHelper::eliminarArchivo($url_foto_old);
        }

        $resultado = ArchivoHelper::guardarArchivos('fotos-empleados', [$nueva_foto]);
        $url_foto = $resultado[0]['url'] ?? null;

        if (empty($url_foto)) {
            return ApiResponse::error('Error al procesar el archivo.');
        }

        EmpleadosData::actualizar_foto(id_empleado: $id_empleado, url_foto: $url_foto);

        return ApiResponse::success($url_foto, 'Foto actualizada correctamente.');
    }

    /**
     * Actualizar un empleado
     */
    public static function actualizar_empleado(
        int $id_empleado,
        string $nombre,
        string $apellido,
        ?string $dni = null,
        ?bool $es_contratista = null
    ) {
        $actual = EmpleadosData::get_empleados(id_empleado: $id_empleado);
        if (!$actual) {
            return ApiResponse::error('Empleado no encontrado.');
        }

        if (!empty($dni) && EmpleadosData::ya_existe(dni: $dni, excluir_id: $id_empleado)) {
            return ApiResponse::error('Ya existe otro empleado registrado con el DNI proporcionado.');
        }

        EmpleadosData::actualizar_empleado(
            id_empleado: $id_empleado,
            nombre: $nombre,
            apellido: $apellido,
            dni: $dni,
            es_contratista: $es_contratista
        );

        $actualizado = EmpleadosData::get_empleados(id_empleado: $id_empleado);

        return ApiResponse::success($actualizado, 'Empleado actualizado correctamente.');
    }

    /**
     * Borrado logico de un empleado (cambia estado a Inactivo).
     */
    public static function eliminar_empleado(int $id_empleado)
    {
        $actual = EmpleadosData::get_empleados(id_empleado: $id_empleado);
        if (!$actual) {
            return ApiResponse::error('Empleado no encontrado.');
        }

        $ok = EmpleadosData::eliminar_empleado(id_empleado: $id_empleado);
        if (!$ok) {
            return ApiResponse::error('No se pudo eliminar el empleado.');
        }

        return ApiResponse::success(null, 'Empleado eliminado correctamente.');
    }
}
