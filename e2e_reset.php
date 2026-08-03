<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::where('mobile', '13947427806')->first();
if ($user) {
    $user->password = \Illuminate\Support\Facades\Hash::make('123456');
    $user->nickname = 'test';
    $user->balance = 100.00;
    $user->save();
    echo "OK: id={$user->id} mobile={$user->mobile} nickname={$user->nickname} balance={$user->balance} times={$user->analysis_times}\n";
}

// 给所有用户赠送 3 次分析次数（如果还没赠送）
$u = App\Models\User::where('mobile', '13947427806')->first();
if ($u->analysis_times < 5) {
    $u->analysis_times = 5;
    $u->save();
    echo "Boosted analysis_times to 5\n";
}
