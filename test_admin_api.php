<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$baseUrl = 'http://localhost:8000';

echo "=== 测试后台健康档案API ===\n\n";

// Create a token for admin directly
$admin = \App\Models\Admin::first();
$token = $admin->createToken('admin_test')->plainTextToken;
echo "Admin Token: " . substr($token, 0, 20) . "...\n\n";

// Get health archives
echo "调用后台健康档案API...\n";
$ch = curl_init($baseUrl . '/api/v1/admin/health-archives?page=1&per_page=3');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$response = curl_exec($ch);
$archivesResult = json_decode($response, true);

if ($archivesResult['code'] === 0) {
    $archives = $archivesResult['data']['data'];
    echo "获取到 " . count($archives) . " 条记录\n\n";
    
    foreach ($archives as $index => $archive) {
        echo "记录 #" . ($index + 1) . ":\n";
        echo "- ID: {$archive['id']}\n";
        echo "- 用户: {$archive['user']['username']}\n";
        echo "- 类型: {$archive['type']}\n";
        
        if (isset($archive['task'])) {
            echo "- 任务号: {$archive['task']['task_no']}\n";
            $imageUrls = $archive['task']['image_urls'] ?? [];
            echo "- 图片URLs: " . json_encode($imageUrls) . "\n";
            if (is_array($imageUrls) && count($imageUrls) > 0) {
                echo "- 第一张图片: " . $imageUrls[0] . "\n";
            }
        } else {
            echo "- 任务: 无\n";
        }
        echo "\n";
    }
} else {
    echo "获取失败: " . $archivesResult['message'] . "\n";
    echo "响应: " . $response . "\n";
}
