<?php

use App\Modules\Empleados\Controllers\CuentasBancariasController;
use App\Modules\Empleados\EmpleadosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empleados')->group(function () {

        // --- Proceso: Gestión general de empleados ---
        Route::controller(EmpleadosController::class)->group(function () {
            Route::get('/', 'get_empleados');
            Route::post('/', 'crear_empleado');
            // Endpoint orquestador: crear empleado + contrato en una sola transacción
            Route::post('/con-contrato', 'crear_empleado_con_contrato');
            Route::put('/{id_empleado}', 'actualizar_empleado');
            Route::delete('/{id_empleado}', 'eliminar_empleado');
            Route::patch('/toggle-con-contrato', 'toggle_con_contrato');
            Route::post('/foto/{id_empleado}', 'actualizar_foto');
        });

        Route::prefix('cuentas-bancarias')->controller(CuentasBancariasController::class)->group(function () {
            Route::get('/{id_empleado}', 'get_cuentas_bancarias');
            Route::post('/', 'crear_cuenta_bancaria');
            Route::put('/{id_cuenta_bancaria}', 'actualizar_cuenta_bancaria');
        });
    });
});
