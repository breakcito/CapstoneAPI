<?php

use App\Modules\Perfil\PerfilController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('perfil')->controller(PerfilController::class)->group(function () {
        Route::get('/', 'get_perfil');
    });
});
