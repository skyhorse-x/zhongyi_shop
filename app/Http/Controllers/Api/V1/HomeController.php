<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisTask;
use App\Models\AnalysisReport;
use App\Models\User;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * 获取首页滚动活动数据（仅真实用户数据）
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function activity()
    {
        // 获取最近的分析活动（最多20条，仅已支付且有评分的）
        $activities = AnalysisReport::with('user')
            ->where('is_paid', true)
            ->whereNotNull('health_score')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($report) {
                $typeName = match($report->type) {
                    'tongue' => '舌诊分析',
                    'face' => '面部分析',
                    'palm' => '手相分析',
                    'eye' => '眼部分析',
                    'constitution' => '体质测试',
                    default => 'AI分析',
                };

                // 脱敏用户名
                $username = $report->user->nickname ?? $report->user->username ?? '用户';
                $maskedName = $this->maskUsername($username);

                return [
                    'id' => $report->id,
                    'username' => $maskedName,
                    'type' => $report->type,
                    'type_name' => $typeName,
                    'health_score' => $report->health_score,
                    'credits' => 1, // 每次分析消耗1积分
                    'created_at' => $report->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'code' => 0,
            'data' => $activities,
        ]);
    }

    /**
     * 用户名脱敏
     */
    private function maskUsername(string $username): string
    {
        $len = mb_strlen($username);
        if ($len <= 1) {
            return '*';
        }
        if ($len === 2) {
            return mb_substr($username, 0, 1) . '*';
        }
        return mb_substr($username, 0, 1) . str_repeat('*', $len - 2) . mb_substr($username, -1, 1);
    }
}
