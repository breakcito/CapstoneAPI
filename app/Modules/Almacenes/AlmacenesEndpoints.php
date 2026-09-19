
<?php

use App\Modules\Almacenes\Controller\AlmacenesController;
use App\Modules\Almacenes\Controller\ResponsablesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Endpoints para la vista de almacenes
|--------------------------------------------------------------------------
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('almacenes')->controller(AlmacenesController::class)->group(function () {

        // Listar un resumen de todos los almacenes
        Route::get('/', 'get_almacenes');

        // Crear un nuevo almacén.
        Route::post('/', 'crear_almacen');
        Route::put('/{id_almacen}', 'actualizar_almacen');
        Route::delete('/{id_almacen}', 'eliminar_almacen');

        // Responsables
        Route::prefix('responsables')->controller(ResponsablesController::class)->group(function () {
            // Obtener historial de responsables de un almacen
            Route::get('/{id_almacen}', 'get_historial_responsables');

            // Asignar un nuevo responsable de almacen
            Route::post('/', 'nuevo_responsable');

            // Inactivar un responsable de almacen
            Route::post('/inactivar', 'inactivar_responsable');
        });
    });
});
