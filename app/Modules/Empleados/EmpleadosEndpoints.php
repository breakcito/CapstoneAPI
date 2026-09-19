<?php

use App\Modules\Empleados\EmpleadosController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('empleados')->group(function () {
        Route::controller(EmpleadosController::class)->group(function () {
            Route::get('/', 'get_empleados');
            Route::post('/', 'crear_empleado');
            Route::put('/{id_empleado}', 'actualizar_empleado');
            Route::delete('/{id_empleado}', 'eliminar_empleado');
            Route::post('/foto/{id_empleado}', 'actualizar_foto');
        });
    });
});
