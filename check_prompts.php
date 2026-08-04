<?php
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== 当前提示词 ===" . PHP_EOL;
$prompts = \App\Models\Prompt::all();
foreach ($prompts as $prompt) {
    echo "类型: " . $prompt->type . PHP_EOL;
    echo "名称: " . $prompt->name . PHP_EOL;
    echo "内容: " . substr($prompt->prompt, 0, 200) . "..." . PHP_EOL;
    echo "---" . PHP_EOL;
}
