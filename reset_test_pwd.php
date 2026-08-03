<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::find(5);
echo 'old pwd: '.$user->password.PHP_EOL;
$user->password = Illuminate\Support\Facades\Hash::make('123456');
$user->save();
$fresh = App\Models\User::find(5);
echo 'new pwd: '.$fresh->password.PHP_EOL;
echo 'verify: '.(Illuminate\Support\Facades\Hash::check('123456', $fresh->password) ? 'OK' : 'FAIL').PHP_EOL;
