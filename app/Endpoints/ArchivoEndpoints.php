<?php

use App\Controllers\ArchivoController;
use Illuminate\Support\Facades\Route;

// Ruta pública para servir imágenes con CORS (sin auth — usada por react-pdf)
Route::get('/imagen-publica/{path}', [ArchivoController::class, 'serve_imagen'])
    ->where('path', '.*');

Route::middleware('auth.jwt.custom')->group(function () {
    Route::post('/archivos/upload', [ArchivoController::class, 'upload_archivo']);
    Route::get('/download-archivo', [ArchivoController::class, 'download_archivo']);
    Route::get('/descargar-externo', [ArchivoController::class, 'descargar_externo']);
});
