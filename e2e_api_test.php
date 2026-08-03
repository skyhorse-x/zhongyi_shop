<?php
/**
 * 端到端 API 接口安全测试
 * 测试目标：找出在已登录状态下错误返回 401/重定向登录的接口
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Admin;
use App\Models\Promoter;
use App\Models\CustomerServiceSession;
use App\Models\CustomerServiceMessage;
use App\Models\AnalysisTask;
use App\Models\ConstitutionQuestion;

$userToken = $argv[1] ?? null;
$adminToken = $argv[2] ?? null;
$baseUrl = 'http://127.0.0.1:8000';

if (!$userToken || !$adminToken) {
    echo "Usage: php e2e_api_test.php <user_token> <admin_token>\n";
    exit(1);
}

// 测试结果收集
$results = [];
$stats = ['total' => 0, 'ok' => 0, 'warn' => 0, 'fail' => 0];

/**
 * 调用 API 并记录结果
 */
function callApi($method, $url, $token, $body = null, $label = '') {
    global $results, $stats;

    $stats['total']++;

    $ch = curl_init();
    $headers = [
        "Authorization: Bearer $token",
        "Accept: application/json",
        "Content-Type: application/json",
    ];
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    }

    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    $data = json_decode($resp, true);
    $code = $data['code'] ?? null;
    $message = $data['message'] ?? '';

    // 关键判定：是否错误地返回 401
    $isUnauth = ($httpCode == 401 || $code == 401);

    $status = 'OK';
    if ($isUnauth) {
        $status = 'FAIL';  // 错误地要求登录
        $stats['fail']++;
    } elseif ($httpCode >= 500) {
        $status = 'WARN';  // 服务端错误
        $stats['warn']++;
    } elseif ($httpCode >= 400) {
        // 4xx 业务错误不算 fail（比如参数验证、限速等）
        $status = 'BIZ';
    } else {
        $stats['ok']++;
    }

    $results[] = [
        'method' => $method,
        'url' => $url,
        'http' => $httpCode,
        'code' => $code,
        'message' => mb_substr($message, 0, 60),
        'status' => $status,
        'label' => $label,
    ];

    return $data;
}

echo "============== USER 端接口 ==============\n\n";

// 用户相关
callApi('GET', "$baseUrl/api/v1/user/info", $userToken, null, '用户信息');
callApi('GET', "$baseUrl/api/v1/user/orders", $userToken, null, '我的订单');
callApi('GET', "$baseUrl/api/v1/user/balance-logs", $userToken, null, '余额明细');

// 分析相关
callApi('GET', "$baseUrl/api/v1/analysis/config", $userToken, null, '分析配置');
callApi('GET', "$baseUrl/api/v1/analysis/history", $userToken, null, '分析历史');

// 体质测试
callApi('GET', "$baseUrl/api/v1/constitution/questions", $userToken, null, '体质问题列表');

// 健康问答
callApi('GET', "$baseUrl/api/v1/qa/sessions", $userToken, null, '问答会话列表');

// 客服
callApi('GET', "$baseUrl/api/v1/customer-service/session", $userToken, null, '获取/创建客服会话');
callApi('GET', "$baseUrl/api/v1/customer-service/sessions", $userToken, null, '客服会话列表');

// 系统消息
callApi('GET', "$baseUrl/api/v1/system-messages", $userToken, null, '系统消息列表');
callApi('GET', "$baseUrl/api/v1/system-messages/unread-count", $userToken, null, '未读消息数');

// 用户反馈
callApi('GET', "$baseUrl/api/v1/feedback", $userToken, null, '反馈列表');

// 申诉
callApi('GET', "$baseUrl/api/v1/appeals", $userToken, null, '申诉列表');

// 退款
callApi('GET', "$baseUrl/api/v1/refunds", $userToken, null, '退款列表');

// 次数包
callApi('GET', "$baseUrl/api/v1/packages", $userToken, null, '次数包列表');

// 健康档案
callApi('GET', "$baseUrl/api/v1/health/history", $userToken, null, '健康档案');
callApi('GET', "$baseUrl/api/v1/health/trend", $userToken, null, '健康趋势');
callApi('GET', "$baseUrl/api/v1/health/constitution", $userToken, null, '健康体质');

// 支付
callApi('GET', "$baseUrl/api/v1/payment/methods", $userToken, null, '支付方式');

// 推广员
callApi('GET', "$baseUrl/api/v1/promoter/info", $userToken, null, '推广员信息');
callApi('GET', "$baseUrl/api/v1/promoter/commissions", $userToken, null, '佣金明细');
callApi('GET', "$baseUrl/api/v1/promoter/withdraw-history", $userToken, null, '提现历史');
callApi('GET', "$baseUrl/api/v1/promoter/invite-records", $userToken, null, '邀请记录');
callApi('GET', "$baseUrl/api/v1/promoter/invite-clicks", $userToken, null, '邀请点击');
callApi('GET', "$baseUrl/api/v1/promoter/poster", $userToken, null, '推广海报');

// 文章
callApi('GET', "$baseUrl/api/v1/articles", $userToken, null, '文章列表');
callApi('GET', "$baseUrl/api/v1/articles/1", $userToken, null, '文章详情');

// 公开接口（应不要求登录）
callApi('GET', "$baseUrl/api/v1/invite-marquee", $userToken, null, '邀请滚动播报');

echo "\n\n============== ADMIN 端接口 ==============\n\n";

callApi('GET', "$baseUrl/api/v1/admin/auth/info", $adminToken, null, '管理员信息');
callApi('GET', "$baseUrl/api/v1/admin/dashboard", $adminToken, null, '后台数据概览');
callApi('GET', "$baseUrl/api/v1/admin/users", $adminToken, null, '用户列表');
callApi('GET', "$baseUrl/api/v1/admin/orders", $adminToken, null, '订单列表');
callApi('GET', "$baseUrl/api/v1/admin/promoters", $adminToken, null, '推广员列表');
callApi('GET', "$baseUrl/api/v1/admin/withdraws", $adminToken, null, '提现列表');
callApi('GET', "$baseUrl/api/v1/admin/ai/models", $adminToken, null, 'AI 模型');
callApi('GET', "$baseUrl/api/v1/admin/ai/logs", $adminToken, null, 'AI 日志');
callApi('GET', "$baseUrl/api/v1/admin/configs", $adminToken, null, '系统配置');
callApi('GET', "$baseUrl/api/v1/admin/packages", $adminToken, null, '次数包管理');
callApi('GET', "$baseUrl/api/v1/admin/constitution/questions", $adminToken, null, '体质题目管理');
callApi('GET', "$baseUrl/api/v1/admin/articles", $adminToken, null, '文章管理');
callApi('GET', "$baseUrl/api/v1/admin/promoters/invite-records", $adminToken, null, '推广员邀请记录');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/statistics", $adminToken, null, '客服统计');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/sessions", $adminToken, null, '客服会话');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/phrases", $adminToken, null, '常用话术');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/system-messages", $adminToken, null, '客服系统消息');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/balance-insufficient-logs", $adminToken, null, '余额不足日志');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/balance-insufficient-stats", $adminToken, null, '余额不足统计');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/configs", $adminToken, null, '客服配置');
callApi('GET', "$baseUrl/api/v1/admin/refunds", $adminToken, null, '退款列表');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/ratings", $adminToken, null, '客服评价');
callApi('GET', "$baseUrl/api/v1/admin/customer-service/ratings-stats", $adminToken, null, '客服评价统计');
callApi('GET', "$baseUrl/api/v1/admin/risk/rules", $adminToken, null, '风控规则');
callApi('GET', "$baseUrl/api/v1/admin/risk/events", $adminToken, null, '风控事件');
callApi('GET', "$baseUrl/api/v1/admin/risk/blacklists", $adminToken, null, '风控黑名单');
callApi('GET', "$baseUrl/api/v1/admin/risk/statistics", $adminToken, null, '风控统计');
callApi('GET', "$baseUrl/api/v1/admin/feedback", $adminToken, null, '用户反馈');
callApi('GET', "$baseUrl/api/v1/admin/appeals", $adminToken, null, 'AI 申诉');

// BI 数据
callApi('GET', "$baseUrl/api/v1/admin/analytics/overview", $adminToken, null, 'BI 概览');
callApi('GET', "$baseUrl/api/v1/admin/analytics/funnel", $adminToken, null, 'BI 漏斗');
callApi('GET', "$baseUrl/api/v1/admin/analytics/retention", $adminToken, null, 'BI 留存');
callApi('GET', "$baseUrl/api/v1/admin/analytics/revenue", $adminToken, null, 'BI 收入');
callApi('GET', "$baseUrl/api/v1/admin/analytics/user-growth", $adminToken, null, 'BI 用户增长');
callApi('GET', "$baseUrl/api/v1/admin/analytics/top-promoters", $adminToken, null, 'BI 顶级推广员');
callApi('GET', "$baseUrl/api/v1/admin/analytics/analysis-distribution", $adminToken, null, 'BI 分析分布');
callApi('GET', "$baseUrl/api/v1/admin/analytics/refund-rate", $adminToken, null, 'BI 退款率');
callApi('GET', "$baseUrl/api/v1/admin/analytics/package-sales", $adminToken, null, 'BI 套餐销售');
callApi('GET', "$baseUrl/api/v1/admin/analytics/promotion-conversion", $adminToken, null, 'BI 推广转化');
callApi('GET', "$baseUrl/api/v1/admin/analytics/satisfaction", $adminToken, null, 'BI 满意度');

// 输出结果
echo "\n\n========== 测试结果汇总 ==========\n";
printf("%-50s %-6s %-6s %-10s %s\n", '接口', 'HTTP', 'Code', '状态', '消息');
echo str_repeat('-', 120) . "\n";

$failList = [];
$warnList = [];
foreach ($results as $r) {
    $color = '';
    if ($r['status'] === 'FAIL') {
        $color = '[FAIL]';
        $failList[] = $r;
    } elseif ($r['status'] === 'WARN') {
        $color = '[WARN]';
        $warnList[] = $r;
    } elseif ($r['status'] === 'BIZ') {
        $color = '[BIZ]';
    } else {
        $color = '[ OK ]';
    }
    $urlShort = str_replace($baseUrl, '', $r['url']);
    $method = str_pad($r['method'], 5);
    $urlShort = str_pad($urlShort, 38);
    $http = str_pad((string) $r['http'], 5);
    $code = str_pad((string) ($r['code'] ?? '-'), 5);
    printf("%s %s %s  %s  %s  %s\n", $method, $urlShort, $http, $code, $color, $r['message']);
}

echo "\n========== 统计 ==========\n";
echo "总调用: {$stats['total']}\n";
echo "成功: {$stats['ok']}\n";
echo "业务错误(4xx): " . ($stats['total'] - $stats['ok'] - $stats['warn'] - $stats['fail']) . "\n";
echo "服务错误(5xx): {$stats['warn']}\n";
echo "误判未登录(401): {$stats['fail']}\n";

if ($failList) {
    echo "\n========== 【已登录却被要求登录】的接口（需修复） ==========\n";
    foreach ($failList as $r) {
        echo "  - {$r['method']} {$r['url']}\n";
        echo "    原因: HTTP {$r['http']}, code={$r['code']}, msg={$r['message']}\n";
    }
}

if ($warnList) {
    echo "\n========== 【500 错误】接口（需排查） ==========\n";
    foreach ($warnList as $r) {
        echo "  - {$r['method']} {$r['url']}\n";
        echo "    原因: HTTP {$r['http']}, code={$r['code']}, msg={$r['message']}\n";
    }
}
