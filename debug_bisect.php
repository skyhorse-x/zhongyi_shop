<?php
$file = 'd:\Desktop\项目\中医商城\app\Http\Controllers\Api\V1\Admin\CustomerServiceManageController.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);

// Lines 1-14 fails. Let me get a complete hex dump of lines 1-15
echo "=== Hex of lines 1-15 ===\n";
for ($i = 0; $i < 15; $i++) {
    echo "L" . ($i+1) . " (hex): " . bin2hex($lines[$i]) . "\n";
    echo "L" . ($i+1) . " (text): [" . $lines[$i] . "]\n";
}

// Now let me try a clean re-write
echo "\n=== Testing if writing back the content fixes the issue ===\n";
file_put_contents($file, $content);
exec("D:\\php-8.4\\php.exe -l " . escapeshellarg($file) . " 2>&1", $output, $ret);
print_r($output);

// Try a totally new file with same content but normalized
echo "\n=== Testing normalized content ===\n";
$cleanContent = str_replace(["\r\n", "\r"], "\n", $content);
$testFile = tempnam(sys_get_temp_dir(), 'clean');
file_put_contents($testFile, $cleanContent);
$output = [];
exec("D:\\php-8.4\\php.exe -l " . escapeshellarg($testFile) . " 2>&1", $output, $ret);
print_r($output);
unlink($testFile);
