<?php

use App\Modules\Indicadores\Controller\IndicadoresController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Modulo Business Intelligence (BI) - Dashboard de Indicadores
|--------------------------------------------------------------------------
|
| Endpoints READ-ONLY que consultan vistas SQL especializadas
| (v_bi_kpis_principales, v_bi_stock_valorizado, v_bi_top_productos,
| v_bi_rotacion_mensual, v_bi_alertas_stock, v_bi_tap_por_almacen).
|
| Las vistas se crean con `CapstoneAPI/sql/vistas_bi.sql` directamente en
| el VPS (no usamos migrations de Laravel). El Service no accede a SQL:
| delega todo en Data.
|
*/

Route::middleware('auth.jwt.custom')->group(function () {
    Route::prefix('indicadores')->controller(IndicadoresController::class)->group(function () {
        // KPIs principales (cabezera del dashboard)
        Route::get('/generales', 'get_generales');

        // Stock valorizado por almacen
        Route::get('/stock-valorizado', 'get_stock_valorizado');

        // Top productos mas despachados
        Route::get('/top-productos', 'get_top_productos');

        // Rotacion mensual (ingresos vs salidas)
        Route::get('/rotacion-mensual', 'get_rotacion_mensual');

        // Alertas de stock critico/bajo
        Route::get('/alertas-stock', 'get_alertas_stock');

        // TAP por almacen (cumplimiento del TDA segmentado)
        Route::get('/tap-por-almacen', 'get_tap_por_almacen');

        // Tendencias mensuales para sparklines (ultimos 6 meses)
        Route::get('/tendencias-mensuales', 'get_tendencias_mensuales');
    });
});