<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / debug mode via the "artisan down"
| command, we'll need to load this file so that the maintenance mode can
| be handled gracefully. This allows us to prevent any migrations from
| running while the maintenance mode is enabled.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We'll just need to load it into our application so we
| can utilize the awesome libraries we've installed with Composer. We'll
| require it here so we don't have to worry about manually loading any of
| our classes later on.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the request using the
| application's HTTP kernel. Then, we will send the response back to the
| client's browser, allowing them to enjoy the cool application we have
| prepared for them.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
