<?php

namespace App\Modules\Cuentas;

use App\Modules\Cuentas\Data\CuentasData;
use App\Shared\Enums\_Generic\EstadoBase;
use App\Shared\Responses\ApiResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CuentasService
{
    public static function get_cuentas(): array|object
    {
        return ApiResponse::success(CuentasData::get_cuentas());
    }

    /**
     * Crear una nueva cuenta de usuario
     */
    public static function crear_cuenta(
        int $id_rol,
        int $id_empleado,
        string $username,
        string $password
    ) {
        $ya_existe = CuentasData::ya_existe($username);

        if ($ya_existe) {
            return ApiResponse::error('El nombre de usuario ya está en uso.');
        }

        return DB::transaction(function () use ($id_rol, $id_empleado, $username, $password) {
            $id_usuario = CuentasData::insert_usuario(
                $id_rol,
                $id_empleado,
                $username,
                Hash::make($password)
            );

            $creado = CuentasData::get_cuentas(id_usuario: $id_usuario);

            return ApiResponse::success($creado, 'Cuenta creada correctamente.');
        });
    }

    /**
     * Actualizar datos de la cuenta (incluyendo contraseña opcional)
     */
    public static function actualizar_cuenta(
        int $id_usuario,
        int $id_rol,
        string $username,
        ?string $password = null,
        ?EstadoBase $estado = null
    ) {

        $ya_existe = CuentasData::ya_existe($username, $id_usuario);
        if ($ya_existe) {
            return ApiResponse::error('El nombre de usuario ya está en uso.');
        }

        $updateData = [
            'id_rol' => $id_rol,
            'username' => $username
        ];

        if (!empty($password)) {
            $updateData['password'] = Hash::make($password);
        }

        if (!empty($estado)) {
            $updateData['estado'] = $estado->value;
        }

        CuentasData::update_usuario($id_usuario, $updateData);

        return ApiResponse::success(null, 'Cuenta actualizada correctamente.');
    }
}
