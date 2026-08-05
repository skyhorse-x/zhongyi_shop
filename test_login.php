<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Generate unique invite code
function generateUniqueInviteCode(): string {
    do {
        $code = strtoupper(Str::random(8));
    } while (\App\Models\User::where('invite_code', $code)->exists());
    return $code;
}

// Create a test user for browser testing
$username = 'browsertest_' . substr(md5(uniqid()), 0, 6);
$password = 'password123';

$user = \App\Models\User::create([
    'name' => $username,
    'username' => $username,
    'nickname' => $username,
    'email' => $username . '@test.local',
    'password' => \Illuminate\Support\Facades\Hash::make($password),
    'invite_code' => generateUniqueInviteCode(),
]);

echo "Test user created:\n";
echo "Username: {$username}\n";
echo "Password: {$password}\n";
echo "User ID: {$user->id}\n";
