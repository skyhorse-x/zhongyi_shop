<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$baseUrl = 'http://localhost:8000';

echo "=== 舌诊功能测试 ===\n\n";

// Step 1: Login
echo "1. 登录用户...\n";
$loginData = [
    'account' => 'browsertest_e1e374',
    'password' => 'password123',
];

$ch = curl_init($baseUrl . '/api/v1/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($loginData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$response = curl_exec($ch);
$loginResult = json_decode($response, true);

if ($loginResult['code'] !== 0) {
    echo "登录失败: " . $loginResult['message'] . "\n";
    exit(1);
}

$token = $loginResult['data']['token'];
echo "   登录成功，Token: " . substr($token, 0, 20) . "...\n\n";

// Step 2: Upload image
echo "2. 上传舌苔图片...\n";
$imagePath = 'D:\Desktop\舌苔1.jpg.jpeg';

if (!file_exists($imagePath)) {
    echo "   图片文件不存在: {$imagePath}\n";
    exit(1);
}

$cfile = new CURLFile($imagePath, 'image/jpeg', 'tongue.jpg');
$uploadData = ['image' => $cfile];

$ch = curl_init($baseUrl . '/api/v1/analysis/upload-image');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $uploadData);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
$response = curl_exec($ch);
$uploadResult = json_decode($response, true);

if ($uploadResult['code'] !== 0) {
    echo "   上传失败: " . $uploadResult['message'] . "\n";
    exit(1);
}

$imageUrl = $uploadResult['data']['image_url'];
echo "   上传成功，图片URL: {$imageUrl}\n\n";

// Step 3: Submit tongue diagnosis task
echo "3. 提交舌诊分析任务...\n";
$taskData = [
    'type' => 'tongue',
    'gender' => 1,
    'age' => 30,
    'image_urls' => [$imageUrl],
    'text' => '舌苔薄白，舌质淡红',
];

$ch = curl_init($baseUrl . '/api/v1/analysis/submit');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($taskData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$response = curl_exec($ch);
$taskResult = json_decode($response, true);

if ($taskResult['code'] !== 0) {
    echo "   提交失败: " . $taskResult['message'] . "\n";
    exit(1);
}

$taskNo = $taskResult['data']['task_no'];
echo "   任务提交成功，任务号: {$taskNo}\n\n";

// Step 4: Check task status
echo "4. 查询任务状态...\n";
sleep(5); // Wait for processing

$ch = curl_init($baseUrl . '/api/v1/analysis/task/' . $taskNo);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);
$response = curl_exec($ch);
$statusResult = json_decode($response, true);

if ($statusResult['code'] === 0) {
    $task = $statusResult['data'];
    $statusText = match($task['status']) {
        0 => '等待中',
        1 => '处理中',
        2 => '已完成',
        3 => '失败',
        default => '未知'
    };
    echo "   任务状态: {$statusText}\n";
    echo "   图片URLs: " . json_encode($task['image_urls'] ?? []) . "\n";
    echo "   图片URL: " . ($task['image_url'] ?? '无') . "\n\n";
} else {
    echo "   查询失败: " . ($statusResult['message'] ?? '未知错误') . "\n\n";
}

// Step 5: Check admin health archives
echo "5. 检查后台健康档案...\n";

// Login as admin using same endpoint
$adminLoginData = [
    'account' => 'admin',
    'password' => 'admin123',
];

$ch = curl_init($baseUrl . '/api/v1/auth/login');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($adminLoginData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$response = curl_exec($ch);
$adminLoginResult = json_decode($response, true);

if ($adminLoginResult['code'] !== 0) {
    echo "   管理员登录失败: " . $adminLoginResult['message'] . "\n";
    echo "   响应: " . $response . "\n";
} else {
    $adminToken = $adminLoginResult['data']['token'];
    echo "   管理员登录成功\n";
    
    // Get health archives
    $ch = curl_init($baseUrl . '/api/v1/admin/health-archives?page=1&per_page=5');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $adminToken
    ]);
    $response = curl_exec($ch);
    $archivesResult = json_decode($response, true);
    
    if ($archivesResult['code'] === 0) {
        $archives = $archivesResult['data']['data'];
        echo "   获取到 " . count($archives) . " 条健康档案记录\n\n";
        
        foreach ($archives as $index => $archive) {
            echo "   记录 #" . ($index + 1) . ":\n";
            echo "   - ID: {$archive['id']}\n";
            echo "   - 用户: {$archive['user']['username']}\n";
            echo "   - 类型: {$archive['type']}\n";
            
            if (isset($archive['task'])) {
                echo "   - 任务号: {$archive['task']['task_no']}\n";
                $imageUrls = $archive['task']['image_urls'] ?? [];
                echo "   - 图片URLs数量: " . count($imageUrls) . "\n";
                if (count($imageUrls) > 0) {
                    echo "   - 第一张图片: " . $imageUrls[0] . "\n";
                    echo "   - 图片是否可访问: " . (filter_var($imageUrls[0], FILTER_VALIDATE_URL) ? '是' : '否') . "\n";
                }
            } else {
                echo "   - 任务数据: 无\n";
            }
            echo "\n";
        }
    } else {
        echo "   获取健康档案失败: " . $archivesResult['message'] . "\n";
    }
}

echo "=== 测试完成 ===\n";
