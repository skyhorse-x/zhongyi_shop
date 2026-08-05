<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$count = \App\Models\User::count();
echo "Total users: {$count}\n";

if ($count > 0) {
    $user = \App\Models\User::first();
    echo "First user: {$user->username}\n";
    echo "User ID: {$user->id}\n";
}
