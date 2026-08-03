<?php

namespace App\Services;

use App\Models\Analysis;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Withdraw;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 数据分析 / BI 服务
 *
 * 提供：
 *   - 概览看板（GMV、DAU、付费率、转化率等核心指标）
 *   - 漏斗（注册→首单→复购）
 *   - 留存（次日/7日/30日）
 *   - 趋势（多日数据）
 *   - 排行榜
 */
class AnalyticsService
{
    /**
     * 运营总览（用于首页 dashboard）
     */
    public function overview(): array
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $thisMonth = Carbon::now()->startOfMonth();

        return [
            // 今日
            'today_revenue'     => Order::where('status', 2)->whereDate('paid_at', $today)->sum('amount'),
            'today_orders'      => Order::where('status', 2)->whereDate('paid_at', $today)->count(),
            'today_new_users'   => User::whereDate('created_at', $today)->count(),
            'today_active'      => $this->activeUsersInDay($today),

            // 昨日对比
            'yesterday_revenue' => Order::where('status', 2)->whereDate('paid_at', $yesterday)->sum('amount'),
            'yesterday_orders'  => Order::where('status', 2)->whereDate('paid_at', $yesterday)->count(),

            // 本月
            'month_revenue'     => Order::where('status', 2)->where('paid_at', '>=', $thisMonth)->sum('amount'),
            'month_new_users'   => User::where('created_at', '>=', $thisMonth)->count(),

            // 累计
            'total_users'       => User::count(),
            'total_paying'      => User::has('orders', '>', 0)->count(),
            'total_promoters'   => Promoter::where('status', 1)->count(),
            'total_revenue'     => Order::where('status', 2)->sum('amount'),
            'total_commission'  => Commission::where('status', 1)->sum('amount'),
            'pending_withdraw'  => Withdraw::where('status', 0)->sum('amount'),
        ];
    }

    /**
     * 计算指标环比
     */
    public function overviewWithComparison(): array
    {
        $today = $this->overview();
        $yesterday = $this->overviewYesterday();

        $calc = function ($today, $yesterday) {
            if (!$yesterday) return null;
            return round(($today - $yesterday) / max($yesterday, 0.01) * 100, 1);
        };

        return [
            'today'        => $today,
            'yesterday'    => $yesterday,
            'growth'       => [
                'revenue'    => $calc($today['today_revenue'], $yesterday['today_revenue']),
                'orders'     => $calc($today['today_orders'], $yesterday['today_orders']),
                'new_users'  => $calc($today['today_new_users'], $yesterday['today_new_users']),
            ],
        ];
    }

    protected function overviewYesterday(): array
    {
        $yesterday = Carbon::yesterday();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();

        return [
            'today_revenue'    => Order::where('status', 2)->whereDate('paid_at', $yesterday)->sum('amount'),
            'today_orders'     => Order::where('status', 2)->whereDate('paid_at', $yesterday)->count(),
            'today_new_users'  => User::whereDate('created_at', $yesterday)->count(),
        ];
    }

    /**
     * 用户转化漏斗
     */
    public function funnel(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $registered = User::where('created_at', '>=', $since)->count();
        $loggedIn   = User::where('last_login_at', '>=', $since)->count();
        $viewed     = Analysis::where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
        $paid       = Order::where('status', 2)->where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
        $repaid     = Order::where('status', 2)
            ->where('created_at', '>=', $since)
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('count(*) > 1')
            ->get()->count();

        return [
            'days'        => $days,
            'steps'       => [
                ['name' => '注册', 'count' => $registered],
                ['name' => '登录', 'count' => $loggedIn],
                ['name' => '使用 AI', 'count' => $viewed],
                ['name' => '付费', 'count' => $paid],
                ['name' => '复购', 'count' => $repaid],
            ],
            'conversion'  => [
                'register_to_login'  => $this->pct($loggedIn, $registered),
                'login_to_use'       => $this->pct($viewed, $loggedIn),
                'use_to_pay'         => $this->pct($paid, $viewed),
                'pay_to_repay'       => $this->pct($repaid, $paid),
            ],
        ];
    }

    /**
     * 留存率
     */
    public function retention(int $cohortDays = 7): array
    {
        $cohorts = [];
        for ($i = $cohortDays - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $registered = User::whereDate('created_at', $date)->pluck('id')->toArray();
            if (empty($registered)) {
                $cohorts[] = ['date' => $date, 'registered' => 0, 'd1' => 0, 'd7' => 0];
                continue;
            }

            $d1 = User::whereIn('id', $registered)
                ->whereBetween('last_login_at', [
                    Carbon::parse($date)->addDay(),
                    Carbon::parse($date)->addDay()->endOfDay(),
                ])->count();

            $d7 = User::whereIn('id', $registered)
                ->whereBetween('last_login_at', [
                    Carbon::parse($date)->addDays(7),
                    Carbon::parse($date)->addDays(7)->endOfDay(),
                ])->count();

            $cohorts[] = [
                'date'       => $date,
                'registered' => count($registered),
                'd1'         => $this->pct($d1, count($registered)),
                'd7'         => $this->pct($d7, count($registered)),
            ];
        }
        return $cohorts;
    }

    /**
     * 收入趋势（按天）
     */
    public function revenueTrend(int $days = 30): array
    {
        $rows = Order::where('status', 2)
            ->where('paid_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('DATE(paid_at) as date, sum(amount) as revenue, count(*) as orders')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $map = $rows->keyBy('date');
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $row = $map[$date] ?? null;
            $series[] = [
                'date'    => $date,
                'revenue' => $row ? (float) $row->revenue : 0,
                'orders'  => $row ? (int) $row->orders : 0,
            ];
        }
        return $series;
    }

    /**
     * 用户增长趋势
     */
    public function userGrowthTrend(int $days = 30): array
    {
        $rows = User::where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()->keyBy('date');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $series[] = [
                'date'  => $date,
                'count' => isset($rows[$date]) ? (int) $rows[$date]->count : 0,
            ];
        }
        return $series;
    }

    /**
     * 推广员 TOP 排行（按佣金）
     */
    public function topPromoters(int $limit = 10): array
    {
        return Promoter::with('user:id,nickname,mobile,avatar')
            ->orderByDesc('total_commission')
            ->limit($limit)
            ->get(['id', 'user_id', 'total_commission', 'available_commission', 'invite_code'])
            ->toArray();
    }

    /**
     * AI 使用分布（按 type）
     */
    public function analysisTypeDistribution(int $days = 30): array
    {
        return Analysis::where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    // ============ 扩展指标（P1）============

    /**
     * 退款率
     */
    public function refundRate(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $totalOrders = Order::where('status', '>=', 1)
            ->where('paid_at', '>=', $since)
            ->count();
        $refundOrders = Order::where('status', 3)
            ->where('paid_at', '>=', $since)
            ->count();
        $refundAmount = Order::where('status', 3)
            ->where('paid_at', '>=', $since)
            ->sum('amount');

        return [
            'total_orders'    => $totalOrders,
            'refund_orders'   => $refundOrders,
            'refund_rate'     => $this->pct($refundOrders, $totalOrders),
            'refund_amount'   => (float) $refundAmount,
            'today_refund'    => Order::where('status', 3)->whereDate('updated_at', Carbon::today())->count(),
        ];
    }

    /**
     * 套餐销量
     */
    public function packageSales(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $rows = Order::where('orders.status', 1)
            ->where('orders.type', 'package')
            ->where('orders.paid_at', '>=', $since)
            ->join('packages', 'packages.id', '=', 'orders.relation_id')
            ->selectRaw('packages.id, packages.name, count(*) as sales, sum(orders.amount) as revenue')
            ->groupBy('packages.id', 'packages.name')
            ->orderByDesc('revenue')
            ->get();

        return [
            'total_sales'   => $rows->sum('sales'),
            'total_revenue' => (float) $rows->sum('revenue'),
            'items'         => $rows->toArray(),
        ];
    }

    /**
     * 推广转化率
     */
    public function promotionConversion(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $totalInvited = \App\Models\InviteRegistration::where('created_at', '>=', $since)->count();
        $paidInvited = \App\Models\InviteRegistration::where('created_at', '>=', $since)
            ->where('is_paid', true)->count();
        $totalCommission = Commission::where('created_at', '>=', $since)->sum('amount');

        return [
            'total_invited'   => $totalInvited,
            'paid_invited'    => $paidInvited,
            'conversion_rate' => $this->pct($paidInvited, $totalInvited),
            'total_commission' => (float) $totalCommission,
        ];
    }

    /**
     * 客服满意度
     */
    public function customerServiceSatisfaction(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $total = \App\Models\CustomerServiceSession::where('created_at', '>=', $since)->count();
        $rated = \App\Models\CustomerServiceSession::where('created_at', '>=', $since)
            ->where('rated', true)->count();
        $avgScore = (float) \App\Models\CustomerServiceSession::where('created_at', '>=', $since)
            ->whereNotNull('satisfaction_score')->avg('satisfaction_score');

        return [
            'total_sessions'   => $total,
            'rated_sessions'   => $rated,
            'rating_rate'      => $this->pct($rated, $total),
            'avg_score'        => round($avgScore, 2),
        ];
    }

    /**
     * 活跃用户数
     */
    protected function activeUsersInDay(Carbon $date): int
    {
        return User::whereDate('last_login_at', $date)->count();
    }

    protected function pct(int $a, int $b): float
    {
        return $b > 0 ? round($a / $b * 100, 1) : 0.0;
    }
}
