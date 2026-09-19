<?php

use App\Controllers\AuxController;
use App\Controllers\HealthCheckController;
use Illuminate\Support\Facades\Route;

// Mantiene los contenedores despiertos y valida que la base de datos responde.
Route::get('/health', [HealthCheckController::class, 'check']);

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('aux')->group(function () {
        Route::controller(AuxController::class)->group(function () {
            // almacenes
            Route::get('/almacenes', 'get_almacenes');

            // lotes disponibles de un almacen
            Route::get('/lotes', 'get_lotes_disponibles');

            // empleados
            Route::get('/empleados', 'get_empleados');
            Route::post('/empleados', 'crear_empleado');

            // roles
            Route::get('/roles-disponibles', 'get_roles_disponibles');

            // unidades de medida
            Route::get('/unidades-medida', 'get_unidades_medida');
            Route::post('/unidades-medida', 'crear_unidad_medida');

            // empresas
            Route::get('/empresas', 'get_empresas');

            // productos
            Route::get('/productos', 'get_productos');
            Route::post('/productos', 'crear_producto');

            // contratistas
            Route::get('/contratistas', 'get_contratistas');
            Route::post('/contratistas', 'crear_contratista');

            // lotes de mineral
            Route::get('/lotes-mineral', 'get_lotes_mineral');

            // ubicación geográfica del Perú (catálogos de solo lectura)
            Route::get('/departamentos', 'get_departamentos');
            Route::get('/provincias', 'get_provincias');
            Route::get('/distritos', 'get_distritos');
        });
    });
});
