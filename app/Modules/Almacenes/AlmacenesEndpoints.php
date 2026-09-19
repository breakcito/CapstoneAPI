
<?php

use App\Modules\Almacenes\Controller\AbastecimientoController;
use App\Modules\Almacenes\Controller\AlmacenesController;
use App\Modules\Almacenes\Controller\ResponsablesController;
use App\Modules\Almacenes\Controller\VecinosController;
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

        // Responsables
        Route::prefix('responsables')->controller(ResponsablesController::class)->group(function () {
            // Obtener historial de responsables de un almacen
            Route::get('/{id_almacen}', 'get_historial_responsables');

            // Asignar un nuevo responsable de almacen
            Route::post('/', 'nuevo_responsable');

            // Inactivar un responsable de almacen
            Route::post('/inactivar', 'inactivar_responsable');
        });

        // Abastecimiento de minas
        Route::prefix('abastecimiento-minas')->controller(AbastecimientoController::class)->group(function () {
            // Listar las minas que abstece un almacen
            Route::get('/{id_almacen}', 'get_minas_abastecidas');

            // Asignar nueva mina por abastecer
            Route::post('/', 'nueva_mina_por_abastecer');

            // Dejar de abastecer a una mina
            Route::delete('/{id_almacen_mina}', 'eliminar_abastecimiento_mina');

            // Listar las minas disponibles para abastecer
            Route::get('/minas/{id_almacen}', 'get_minas');
        });

        // Vecinos
        Route::prefix('vecinos')->controller(VecinosController::class)->group(function () {
            // Listar vecinos de un almacen
            Route::get('/{id_almacen}', 'get_vecinos');

            // Listar almacenes disponibles para ser vecinos de un almacen
            Route::get('/disponibles/{id_almacen}', 'get_almacenes_disponibles_vecinos');

            // Asignar un nuevo vecino
            Route::post('/', 'agregar_vecino');

            // Eliminar un vecino
            Route::delete('/{id_almacen_vecino}', 'eliminar_vecino');
        });
    });
});
