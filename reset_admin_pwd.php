<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = App\Models\Admin::where('username', 'admin')->first();
if ($admin) {
    $admin->password = Illuminate\Support\Facades\Hash::make('123456');
    $admin->save();
    echo 'admin id: '.$admin->id.PHP_EOL;
    echo 'verify: '.(Illuminate\Support\Facades\Hash::check('123456', $admin->fresh()->password) ? 'OK' : 'FAIL').PHP_EOL;
} else {
    echo 'admin not found'.PHP_EOL;
    print_r(App\Models\Admin::limit(3)->get(['id','username'])->toArray());
}
