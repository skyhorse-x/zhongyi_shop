<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$baseUrl = 'http://localhost:8000';

echo "=== 验证舌诊图片上传和显示 ===\n\n";

// Create admin token
$admin = \App\Models\Admin::first();
$token = $admin->createToken('verify_test')->plainTextToken;

// Get latest health archives
$ch = curl_init($baseUrl . '/api/v1/admin/health-archives?page=1&per_page=5');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['code'] === 0) {
    $archives = $result['data']['data'];
    echo "获取到 " . count($archives) . " 条记录\n\n";
    
    $found = false;
    foreach ($archives as $index => $archive) {
        echo "记录 #" . ($index + 1) . ":\n";
        echo "- ID: {$archive['id']}\n";
        echo "- 用户: {$archive['user']['username']}\n";
        echo "- 类型: {$archive['type']}\n";
        
        if (isset($archive['task'])) {
            echo "- 任务号: {$archive['task']['task_no']}\n";
            $imageUrls = $archive['task']['image_urls'] ?? [];
            echo "- 图片数量: " . count($imageUrls) . "\n";
            if (count($imageUrls) > 0) {
                echo "- 第一张图片: " . $imageUrls[0] . "\n";
                $found = true;
            }
        }
        echo "\n";
    }
    
    if ($found) {
        echo "✅ 图片显示正常！\n";
    } else {
        echo "❌ 没有找到图片\n";
    }
} else {
    echo "获取失败: " . $result['message'] . "\n";
}
