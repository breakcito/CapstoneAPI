<?php

use App\Modules\KardexProductos\Controller\KardexController;
use Illuminate\Support\Facades\Route;


Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('kardex-productos')->controller(KardexController::class)->group(function () {
        Route::get('/', 'get_resumen_kardex');
    });
});
