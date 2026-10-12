<?php

/*
|--------------------------------------------------------------------------
| Bootstrap de las pruebas
|--------------------------------------------------------------------------
|
| Orden critico: los dobles de test de los modelos se declaran ANTES de que
| el autoloader de Composer pueda cargar los modelos reales. Un archivo no se
| autocarga si su clase ya esta declarada, asi que declarar aqui
| `App\Models\LoteProducto` desactiva de hecho el archivo original para
| todo el proceso de pruebas.
|
| Esto es deliberado y esta contenido en tests/: fuera de aqui (artisan,
| frankenphp, cola, phpstan) los modelos reales siguen mandando.
|
*/

require __DIR__.'/../vendor/autoload.php';

// Infraestructura de los dobles. `Tests\` no esta en el autoload de
// composer (autoload-dev esta vacio), asi que se carga a mano.
require_once __DIR__.'/Support/Fakes/Fila.php';
require_once __DIR__.'/Support/Fakes/TablaFalsa.php';
require_once __DIR__.'/Support/Fakes/ConsultaFalsa.php';
require_once __DIR__.'/Support/Fakes/ModeloFalso.php';
require_once __DIR__.'/Support/Fakes/DBFalsa.php';

// Ultimo: declara App\Models\*, que es lo que hay que ganar por delante.
require_once __DIR__.'/Support/Fakes/cargar_fakes.php';
