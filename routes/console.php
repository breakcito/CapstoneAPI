<?php

use App\Console\Commands\ProcesarContratosJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Procesar diariamente los vencimientos de contratos Vigentes y la activación
// de los Pendientes cuya fecha_inicio ya comenzó.
Schedule::command(ProcesarContratosJob::class)->dailyAt('00:05');
