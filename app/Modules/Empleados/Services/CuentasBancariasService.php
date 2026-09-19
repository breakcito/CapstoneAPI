<?php

namespace App\Modules\Empleados\Services;

use App\Modules\Empleados\Data\CuentasBancariasData;
use App\Shared\Responses\ApiResponse;

class CuentasBancariasService
{
    /**
     * Obtener cuentas bancarias del empleado.
     */
    public static function get_cuentas_bancarias(int $idEmpleado): array
    {
        $data = CuentasBancariasData::get_cuentas_bancarias($idEmpleado);
        return ApiResponse::success($data, "Cuentas bancarias obtenidas correctamente");
    }

    /**
     * Registrar una cuenta bancaria para el empleado.
     */
    public static function crear_cuenta_bancaria(
        int $idEmpleado,
        int $idBanco,
        ?string $tipoCuentaBancaria,
        string $moneda,
        string $numeroCuenta,
        ?string $cci
    ): array {
        $existe = CuentasBancariasData::existe_cuenta_bancaria($idEmpleado, $idBanco, $numeroCuenta);

        if ($existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada para este empleado");
        }

        $id = CuentasBancariasData::crear_cuenta_bancaria(
            $idEmpleado,
            $idBanco,
            $tipoCuentaBancaria,
            $moneda,
            $numeroCuenta,
            $cci
        );
        $nuevaCuenta = CuentasBancariasData::get_cuenta_bancaria_by_id($id);
        return ApiResponse::success($nuevaCuenta, "Cuenta bancaria registrada correctamente");
    }

    public static function actualizar_cuenta_bancaria(
        int $idCuentaBancaria,
        int $idBanco,
        ?string $tipoCuentaBancaria,
        string $moneda,
        string $numeroCuenta,
        ?string $cci
    ): array {
        $id_empleado = CuentasBancariasData::get_empleado_id_by_cuenta($idCuentaBancaria);

        if ($id_empleado === null) {
            return ApiResponse::error("La cuenta bancaria no existe");
        }

        $existe = CuentasBancariasData::existe_cuenta_bancaria(
            $id_empleado,
            $idBanco,
            $numeroCuenta,
            excluir_id: $idCuentaBancaria
        );

        if ($existe) {
            return ApiResponse::error("Esta cuenta bancaria ya está registrada para este empleado");
        }

        CuentasBancariasData::actualizar_cuenta_bancaria(
            $idCuentaBancaria,
            $idBanco,
            $tipoCuentaBancaria,
            $moneda,
            $numeroCuenta,
            $cci
        );

        $cuentaActualizada = CuentasBancariasData::get_cuenta_bancaria_by_id($idCuentaBancaria);
        return ApiResponse::success($cuentaActualizada, "Cuenta bancaria actualizada correctamente");
    }
}
