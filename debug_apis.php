<?php
/**
 * 调试脚本：获取具体的错误堆栈
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$endpoints = [
    ['GET', '/api/v1/admin/customer-service/phrases', 'phrases'],
    ['GET', '/api/v1/admin/customer-service/system-messages', 'system-messages'],
    ['GET', '/api/v1/admin/auth/info', 'admin/auth/info'],
    ['GET', '/api/v1/admin/dashboard', 'admin/dashboard'],
];

$token = '35|S7OQelqBp6aTpdx92blk4mNmdrFb3cLihrWFAigW19e15415';

foreach ($endpoints as [$method, $path, $label]) {
    echo "\n========== $label ==========\n";
    $req = Illuminate\Http\Request::create($path, $method);
    $req->headers->set('Authorization', "Bearer $token");
    $req->headers->set('Accept', 'application/json');

    // 模拟通过应用处理请求
    try {
        $resp = $app->handle($req);
        echo "HTTP=" . $resp->getStatusCode() . "\n";
        echo $resp->getContent() . "\n";
    } catch (Throwable $e) {
        echo "EXCEPTION: " . get_class($e) . "\n";
        echo "MSG: " . $e->getMessage() . "\n";
        echo "FILE: " . $e->getFile() . ":" . $e->getLine() . "\n";
        echo "TRACE:\n" . $e->getTraceAsString() . "\n";
    }
}
