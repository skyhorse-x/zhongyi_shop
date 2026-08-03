<?php
// Test: write a fresh version of the file
$file = 'd:\Desktop\项目\中医商城\app\Http\Controllers\Api\V1\Admin\CustomerServiceManageController.php';
$content = file_get_contents($file);

// Save to backup
file_put_contents($file . '.bak', $content);

// Try parsing the file
echo "=== Original parse attempt ===\n";
$output = [];
exec("D:\\php-8.4\\php.exe -l " . escapeshellarg($file) . " 2>&1", $output, $ret);
print_r($output);

// Now write a minimal version
$minimal = <<<'PHP'
<?php
namespace App\Http\Controllers\Api\V1\Admin;
use App\Http\Controllers\Controller;
class TestCtl extends Controller {
    public function test() {
        $data = [];
        $data['admin_id'] = 1;
        return $data;
    }
}
PHP;
file_put_contents('d:\Desktop\项目\中医商城\test_minimal.php', $minimal);
echo "\n=== Minimal parse attempt ===\n";
$output = [];
exec("D:\\php-8.4\\php.exe -l " . escapeshellarg('d:\Desktop\项目\中医商城\test_minimal.php') . " 2>&1", $output, $ret);
print_r($output);
unlink('d:\Desktop\项目\中医商城\test_minimal.php');
