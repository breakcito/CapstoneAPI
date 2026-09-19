
<?php

use App\Modules\Login\LoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('login')->controller(LoginController::class)->group(function () {
    Route::post('/', 'login');
});
