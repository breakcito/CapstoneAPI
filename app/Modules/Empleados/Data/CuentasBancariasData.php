<?php

namespace App\Modules\Empleados\Data;

use App\Models\CuentaBancariaEmpleado;
use App\Shared\Enums\_Generic\EstadoBase;
use Illuminate\Support\Facades\DB;

class CuentasBancariasData
{
    /**
     * Obtener listado de cuentas bancarias de empleados.
     */
    public static function get_cuentas_bancarias(
        ?int $id_empleado = null,
        ?int $id_cuenta_bancaria = null
    ): array {
        $sql = '
        SELECT
            cn.id AS id_cuenta_bancaria,
            cn.id_banco,
            bc.nombre as banco,
            bc.abreviatura as banco_abv,
            cn.tipo_cuenta_bancaria,
            cn.moneda,
            cn.numero_cuenta,
            cn.cci,
            cn.estado
        FROM
            cuenta_bancaria_empleado cn
        INNER JOIN banco bc ON bc.id = cn.id_banco
        WHERE 1 = 1
        ';

        $params = [];
        if ($id_empleado !== null) {
            $sql .= ' AND cn.id_empleado = :id_empleado';
            $params['id_empleado'] = $id_empleado;
        }

        if ($id_cuenta_bancaria !== null) {
            $sql .= ' AND cn.id = :id_cuenta_bancaria';
            $params['id_cuenta_bancaria'] = $id_cuenta_bancaria;
            return (array) DB::selectOne($sql, $params);
        }

        $sql .= ' ORDER BY cn.moneda, cn.numero_cuenta;';
        return DB::select($sql, $params);
    }

    /**
     * Obtener una cuenta bancaria por su ID.
     */
    public static function get_cuenta_bancaria_by_id(int $id_cuenta_bancaria): array
    {
        return self::get_cuentas_bancarias(id_cuenta_bancaria: $id_cuenta_bancaria);
    }

    /**
     * Obtener el id del empleado de una cuenta bancaria.
     */
    public static function get_empleado_id_by_cuenta(int $id_cuenta_bancaria): ?int
    {
        $cuenta = CuentaBancariaEmpleado::find($id_cuenta_bancaria);
        return $cuenta ? (int) $cuenta->id_empleado : null;
    }

    /**
     * Registrar una cuenta bancaria en base de datos.
     */
    public static function crear_cuenta_bancaria(
        int $id_empleado,
        int $id_banco,
        ?string $tipoCuentaBancaria,
        string $moneda,
        string $numeroCuenta,
        ?string $cci
    ): int {
        return CuentaBancariaEmpleado::insertGetId([
            'id_empleado' => $id_empleado,
            'id_banco' => $id_banco,
            'tipo_cuenta_bancaria' => $tipoCuentaBancaria,
            'moneda' => $moneda,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
            'estado' => EstadoBase::Activo->value
        ]);
    }

    /**
     * Actualizar una cuenta bancaria.
     */
    public static function actualizar_cuenta_bancaria(
        int $id_cuenta_bancaria,
        int $id_banco,
        ?string $tipoCuentaBancaria,
        string $moneda,
        string $numeroCuenta,
        ?string $cci
    ): bool {
        return CuentaBancariaEmpleado::where('id', $id_cuenta_bancaria)->update([
            'id_banco' => $id_banco,
            'tipo_cuenta_bancaria' => $tipoCuentaBancaria,
            'moneda' => $moneda,
            'numero_cuenta' => $numeroCuenta,
            'cci' => $cci,
        ]) > 0;
    }

    /**
     * Verificar si una cuenta bancaria ya existe para el empleado.
     */
    public static function existe_cuenta_bancaria(
        int $id_empleado,
        int $id_banco,
        string $numero_cuenta,
        ?int $excluir_id = null
    ): bool {
        $query = CuentaBancariaEmpleado::where('id_empleado', $id_empleado)
            ->where('id_banco', $id_banco)
            ->where('numero_cuenta', $numero_cuenta);

        if ($excluir_id !== null) {
            $query->where('id', '!=', $excluir_id);
        }

        return $query->exists();
    }
}
