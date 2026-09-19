<?php

namespace App\Modules\Cuentas;

use Illuminate\Support\Facades\Route;

Route::prefix('cuentas')->group(function () {
    Route::get('/', [CuentasController::class, 'get_cuentas']);
    Route::get('/empleados-disponibles', [CuentasController::class, 'get_empleados_sin_cuenta']);
    Route::get('/roles', [CuentasController::class, 'get_roles_disponibles']);
    Route::post('/', [CuentasController::class, 'crear_cuenta']);
    Route::put('/{id_usuario}', [CuentasController::class, 'actualizar_cuenta']);
    Route::post('/foto/{id_empleado}', [CuentasController::class, 'actualizar_foto_empleado']);
});
