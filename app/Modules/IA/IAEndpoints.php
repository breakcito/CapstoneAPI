<?php

use App\Modules\IA\Controller\IAController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modulo IA - Chat de analytics
|--------------------------------------------------------------------------
|
| Endpoint de chat en streaming (SSE) y descarga de reportes .xlsx.
|
| El SQL que ejecuta el modelo esta confinado a solo lectura por SqlGuard:
| tres capas (comando de lectura, lista negra, tope de filas). El modelo
| propone la consulta, el servidor la ejecuta y arma el archivo.
|
| El historial NO se persiste: el frontend reenvia la conversacion completa
| en cada request, asi que este controller es stateless.
|
*/

Route::middleware('auth.jwt.custom')->prefix('ia')->controller(IAController::class)->group(function () {
    // Chat en streaming. Response: text/event-stream.
    Route::post('/chat/stream', 'chatStream');

    // Descarga del Excel generado por el modelo.
    Route::get('/reporte/{uuid}', 'descargarReporte');
});
