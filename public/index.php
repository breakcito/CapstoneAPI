<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

header("X-Dbg-Before: " . strlen((string) ob_get_contents()));
// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$resp = $app->handleRequest(Request::capture());
header("X-Dbg-After: " . strlen((string) ob_get_contents()));
file_put_contents("/tmp/dbg_buffer.txt", (string) ob_get_contents());
