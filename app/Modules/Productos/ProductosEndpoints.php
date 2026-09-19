<?php

namespace App\Modules\Productos;

use App\Modules\Productos\Controller\ProductosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Módulo Inventario - Catálogo de Productos
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('productos')->group(function () {

        Route::controller(ProductosController::class)->group(function () {
            Route::get('/', 'get_productos');
            Route::post('/', 'crear_producto');
            Route::put('/{id_producto}', 'actualizar_producto');
            Route::delete('/{id_producto}', 'eliminar_producto');
        });
    });
});
