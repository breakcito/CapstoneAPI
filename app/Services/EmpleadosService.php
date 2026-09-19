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
     * Listar almacenes.
     */
    public static function get_empleados(
        ?int $id_empleado = null,
        ?EstadoBase $estado = EstadoBase::Activo,
        ?int $id_almacen_excluyente = null,
        ?int $id_mina_excluyente = null,
        ?bool $con_cuenta = null,
        ?bool $solo_con_contrato_vigente = null,
        ?string $fecha_fin_programacion = null,
        ?int $id_lugar = null,
        ?string $tipo_lugar = null
    ) {
        $empleados = EmpleadosData::get_empleados(
            id_empleado: $id_empleado,
            estado: $estado,
            id_almacen_excluyente: $id_almacen_excluyente,
            id_mina_excluyente: $id_mina_excluyente,
            con_cuenta: $con_cuenta,
            solo_con_contrato_vigente: $solo_con_contrato_vigente,
            fecha_fin_programacion: $fecha_fin_programacion,
            id_lugar: $id_lugar,
            tipo_lugar: $tipo_lugar
        );

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
        ?bool $return_object = false,
        ?int $id_empresa = null
    ) {
        if (EmpleadosData::ya_existe(dni: $dni, ruc: $ruc, carnet_extranjeria: $carnet_extranjeria, pasaporte: $pasaporte)) {
            return ApiResponse::error('Ya existe un empleado registrado con uno de los documentos proporcionados.');
        }

        $url_foto_str = null;
        if ($foto && $foto->isValid()) {
            $archivo = ArchivoHelper::guardarArchivos('fotos-empleados', [$foto])[0] ?? null;
            if ($archivo && isset($archivo['url'])) {
                $url_foto_str = $archivo['url'];
            }
        }

        $id = EmpleadosData::crear_empleado(
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
            url_foto: $url_foto_str,
            id_empresa: $id_empresa
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
     * Actualizar la foto del empleado asociado a una cuenta
     */
    public static function actualizar_foto(int $id_empleado, ?UploadedFile $nueva_foto = null)
    {
        $emp = EmpleadosData::get_empleado_dinamico_by_id($id_empleado, ['url_foto']);
        $url_foto_old = ! empty($emp['url_foto']) ? $emp['url_foto'] : null;

        // Caso: eliminar foto (sin nueva)
        if (is_null($nueva_foto)) {
            if ($url_foto_old) {
                ArchivoHelper::eliminarArchivo($url_foto_old);
                EmpleadosData::actualizar_foto($id_empleado, null);

                return ApiResponse::success(null, 'Foto eliminada correctamente.');
            }

            return ApiResponse::success(null, 'No hay foto para eliminar.');
        }

        // Caso: actualizar o agregar foto
        if ($url_foto_old) {
            ArchivoHelper::eliminarArchivo($url_foto_old);
        }

        $resultado = ArchivoHelper::guardarArchivos('perfiles', [$nueva_foto]);
        $url_foto = $resultado[0]['url'] ?? null;

        if (empty($url_foto)) {
            return ApiResponse::error('Error al procesar el archivo.');
        }

        EmpleadosData::actualizar_foto(id_empleado: $id_empleado, url_foto: $url_foto);

        return ApiResponse::success($url_foto, 'Foto actualizada correctamente.');
    }

    /**
     * Actualizar un empleado (no contratista).
     *
     * Si el empleado tiene contrato vigente, se omiten `id_cargo` e
     * `id_empresa` (se pasan `null`) para preservar la referencia que
     * mantiene el contrato. La edición de esos campos se hace desde
     * el módulo ContratosEmpleado.
     *
     * IMPORTANTE: para el SELECT de retorno usamos el módulo de
     * Empleados (`App\Modules\Empleados\Data\EmpleadosData`) porque
     * la versión global (`App\Data\EmpleadosData::get_empleados`)
     * NO incluye `nombre` ni `apellido` como columnas separadas, y
     * el frontend las espera para re-renderizar la fila del listado.
     */
    public static function actualizar_empleado(
        int $id_empleado,
        string $nombre,
        string $apellido,
        ?string $genero = null,
        ?string $dni = null,
        ?string $ruc = null,
        ?string $carnet_extranjeria = null,
        ?string $pasaporte = null,
        ?string $fecha_nacimiento = null,
        ?string $direccion = null,
        ?string $telefono = null,
        ?string $email = null,
        ?int $id_cargo = null,
        ?int $id_empresa = null,
        ?int $idEmpleadoLog = null,
        ?string $nombreEmpleadoLog = null,
    ) {
        $moduloData = \App\Modules\Empleados\Data\EmpleadosData::class;

        $actual = $moduloData::get_empleados(id_empleado: $id_empleado);

        if (! $actual) {
            return ApiResponse::error('Empleado no encontrado.');
        }

        $tieneContratoVigente = ! empty($actual->id_contrato_vigente);

        if ($tieneContratoVigente) {
            // El cargo y la empresa viven en el contrato. Pasar null
            // para que el Model NO los toque (preserva la referencia).
            $id_cargo = null;
            $id_empresa = null;
        }

        EmpleadosData::actualizar_empleado(
            id_empleado: $id_empleado,
            nombre: $nombre,
            apellido: $apellido,
            genero: $genero,
            dni: $dni,
            fecha_nacimiento: $fecha_nacimiento,
            direccion: $direccion,
            telefono: $telefono,
            email: $email,
            id_cargo: $id_cargo,
            id_empresa: $id_empresa,
            idEmpleadoLog: $idEmpleadoLog,
            nombreEmpleadoLog: $nombreEmpleadoLog,
        );

        // Usamos el SELECT del módulo para que la respuesta incluya
        // todos los campos que el frontend espera (nombre, apellido,
        // area, cargo, empresa, etc.).
        $actualizado = $moduloData::get_empleados(id_empleado: $id_empleado);

        return ApiResponse::success($actualizado, 'Empleado actualizado correctamente.');
    }

    /**
     * Borrado logico de un empleado (cambia estado a Inactivo).
     */
    public static function eliminar_empleado(int $id_empleado)
    {
        $moduloData = \App\Modules\Empleados\Data\EmpleadosData::class;

        $actual = $moduloData::get_empleados(id_empleado: $id_empleado);
        if (! $actual) {
            return ApiResponse::error('Empleado no encontrado.');
        }

        $ok = EmpleadosData::eliminar_empleado(id_empleado: $id_empleado);
        if (! $ok) {
            return ApiResponse::error('No se pudo eliminar el empleado.');
        }

        return ApiResponse::success(null, 'Empleado eliminado correctamente.');
    }
}
