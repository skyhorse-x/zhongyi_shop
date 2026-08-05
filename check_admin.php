<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$admins = \App\Models\Admin::all();
echo "Total admins: " . $admins->count() . "\n";

foreach ($admins as $admin) {
    echo "ID: {$admin->id}, Username: {$admin->username}, Name: {$admin->name}\n";
}

// Check analysis routes
echo "\n=== Analysis Routes ===\n";
$routes = \Illuminate\Support\Facades\Route::getRoutes();
foreach ($routes as $route) {
    if (str_contains($route->uri(), 'analysis')) {
        echo $route->methods()[0] . " " . $route->uri() . "\n";
    }
}
