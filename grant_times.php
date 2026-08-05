<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$user = \App\Models\User::where('username', 'browsertest_e1e374')->first();

if (!$user) {
    echo "User not found\n";
    exit(1);
}

// Grant 10 analysis times
$user->analysis_times = 10;
$user->save();

echo "Granted 10 analysis times to {$user->username}\n";
echo "Current times: {$user->analysis_times}\n";
