<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 用户 token
$user = App\Models\User::where('mobile', '13947427806')->first();
if ($user) {
    $userToken = $user->createToken('e2e-user')->plainTextToken;
    echo "USER_TOKEN=$userToken\n";
    echo "USER_ID={$user->id}\n";
    echo "USER_BALANCE={$user->balance}\n";
    echo "USER_TIMES={$user->analysis_times}\n";
}

// 管理员 token
$admin = App\Models\Admin::where('username', 'admin')->first();
if ($admin) {
    $adminToken = $admin->createToken('e2e-admin')->plainTextToken;
    echo "ADMIN_TOKEN=$adminToken\n";
    echo "ADMIN_ID={$admin->id}\n";
    echo "ADMIN_NAME={$admin->name}\n";
}
