<?php

use App\Modules\System\Controller\UnidadesMedidaSystemController;
use App\Modules\System\Controller\ConversionesSystemController;
use App\Modules\System\Controller\MenuSystemController;
use App\Modules\System\Controller\ArchivosSystemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->prefix('system')->group(function () {

    Route::controller(UnidadesMedidaSystemController::class)->group(function () {
        Route::get('/unidades-medida', 'listar');
        Route::post('/unidades-medida', 'crear');
        Route::put('/unidades-medida/{id}', 'editar');
        Route::delete('/unidades-medida/{id}', 'eliminar');
    });

    Route::controller(ConversionesSystemController::class)->group(function () {
        Route::get('/conversiones', 'listar');
        Route::post('/conversiones', 'crear');
        Route::put('/conversiones/{id}', 'editar');
        Route::delete('/conversiones/{id}', 'eliminar');
    });

    Route::controller(MenuSystemController::class)->group(function () {
        Route::get('/menu-arbol', 'arbol');

        Route::post('/menu', 'crear_menu');
        Route::put('/menu/{id}', 'editar_menu');
        Route::delete('/menu/{id}', 'eliminar_menu');

        Route::post('/submenu', 'crear_submenu');
        Route::put('/submenu/{id}', 'editar_submenu');
        Route::delete('/submenu/{id}', 'eliminar_submenu');

        Route::post('/modulo', 'crear_modulo');
        Route::put('/modulo/{id}', 'editar_modulo');
        Route::delete('/modulo/{id}', 'eliminar_modulo');
    });

    Route::controller(ArchivosSystemController::class)->group(function () {
        Route::get('/archivos', 'listar');
        Route::get('/archivos/descargar', 'descargar');
        Route::put('/archivos/renombrar', 'renombrar');
        Route::delete('/archivos', 'eliminar');
    });
});