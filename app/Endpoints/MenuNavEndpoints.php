
<?php

use App\Controllers\MenuNavController;
use Illuminate\Support\Facades\Route;


Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('menu-nav')->controller(MenuNavController::class)->group(function () {
        Route::get('', 'get_menu_navegacion');
    });
});
