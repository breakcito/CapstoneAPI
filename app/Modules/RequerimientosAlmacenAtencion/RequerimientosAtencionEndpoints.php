<?php

use App\Modules\RequerimientosAlmacenAtencion\Controller\AtencionController;
use App\Modules\RequerimientosAlmacenAtencion\Controller\EntregaController;
use App\Modules\RequerimientosAlmacenAtencion\Controller\SolicitudesController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('requerimientos-atencion')->group(function () {

        Route::controller(AtencionController::class)->group(function () {
            // Registro de requerimientos desde atención
            Route::post('/', 'crear_requerimiento');
            Route::get('/requerimientos', 'get_requerimientos');
            Route::get('/detalles-by-requerimiento', 'get_detalles_requerimiento');
            Route::put('/save-decision-detalle', 'update_estado_detalle_requerimiento');
            Route::get('/trazabilidad', 'get_trazabilidad');
            Route::post('/evidencias', 'subir_evidencias');
            Route::put('/{id}', 'editar_requerimiento');
        });

        // Entregas (Despacho, Stock, Lotes)
        Route::controller(EntregaController::class)->group(function () {
            Route::post('/save-entrega', 'crear_entrega');
            Route::get('/entregas', 'get_historial_entregas');
        });

        // Solicitudes (Logística)
        Route::controller(SolicitudesController::class)->group(function () {
            Route::post('/save-solicitud-logistica', 'registrar_solicitud');
            Route::get('/solicitudes-logistica', 'get_historial_solicitudes');
        });
    });
});
