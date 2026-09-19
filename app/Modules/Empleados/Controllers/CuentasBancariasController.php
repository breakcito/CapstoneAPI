<?php

namespace App\Modules\Empleados\Controllers;

use App\Modules\Empleados\Services\CuentasBancariasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CuentasBancariasController
{
    /**
     * Obtener listado de cuentas bancarias de un empleado.
     */
    public function get_cuentas_bancarias(int $id_empleado): JsonResponse
    {
        return response()->json(CuentasBancariasService::get_cuentas_bancarias($id_empleado));
    }

    /**
     * Crear una nueva cuenta bancaria para un empleado.
     */
    public function crear_cuenta_bancaria(Request $request): JsonResponse
    {
        $request->validate([
            'id_empleado' => 'required|integer',
            'id_banco' => 'required|integer',
            'tipo_cuenta_bancaria' => 'nullable|string|max:64',
            'moneda' => 'required|string',
            'numero_cuenta' => 'required|string|max:50',
            'cci' => 'nullable|string|max:50',
        ]);

        return response()->json(CuentasBancariasService::crear_cuenta_bancaria(
            (int) $request->id_empleado,
            (int) $request->id_banco,
            $request->tipo_cuenta_bancaria,
            $request->moneda,
            $request->numero_cuenta,
            $request->cci
        ));
    }

    public function actualizar_cuenta_bancaria(Request $request, int $id_cuenta_bancaria): JsonResponse
    {
        $request->validate([
            'id_banco' => 'required|integer',
            'tipo_cuenta_bancaria' => 'nullable|string|max:64',
            'moneda' => 'required|string',
            'numero_cuenta' => 'required|string|max:50',
            'cci' => 'nullable|string|max:50',
        ]);

        return response()->json(CuentasBancariasService::actualizar_cuenta_bancaria(
            $id_cuenta_bancaria,
            (int) $request->id_banco,
            $request->tipo_cuenta_bancaria,
            $request->moneda,
            $request->numero_cuenta,
            $request->cci
        ));
    }
}
