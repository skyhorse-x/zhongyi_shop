<?php

namespace App\Services;

use App\Models\AnalysisTask;
use App\Models\Commission;
use App\Models\Order;
use App\Models\ProductPackage;
use App\Models\Promoter;
use App\Models\Refund;
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
            'today_revenue'     => (float) Order::where('status', 1)->whereDate('paid_at', $today)->sum('amount'),
            'today_orders'      => Order::where('status', 1)->whereDate('paid_at', $today)->count(),
            'today_new_users'   => User::whereDate('created_at', $today)->count(),
            'today_active'      => $this->activeUsersInDay($today),

            // 昨日对比
            'yesterday_revenue' => (float) Order::where('status', 1)->whereDate('paid_at', $yesterday)->sum('amount'),
            'yesterday_orders'  => Order::where('status', 1)->whereDate('paid_at', $yesterday)->count(),

            // 本月
            'month_revenue'     => (float) Order::where('status', 1)->where('paid_at', '>=', $thisMonth)->sum('amount'),
            'month_new_users'   => User::where('created_at', '>=', $thisMonth)->count(),

            // 累计
            'total_users'       => User::count(),
            'total_paying'      => (int) Order::where('status', 1)->distinct('user_id')->count('user_id'),
            'total_promoters'   => Promoter::where('status', 1)->count(),
            'total_revenue'     => (float) Order::where('status', 1)->sum('amount'),
            'total_commission'  => (float) Commission::where('status', 1)->sum('amount'),
            'pending_withdraw'  => (float) Withdraw::where('status', 0)->sum('amount'),
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
            'today_revenue'    => (float) Order::where('status', 1)->whereDate('paid_at', $yesterday)->sum('amount'),
            'today_orders'     => Order::where('status', 1)->whereDate('paid_at', $yesterday)->count(),
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
        $loggedIn   = $this->activeUsersSince($since);
        $viewed     = AnalysisTask::where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
        $paid       = Order::where('status', 1)->where('created_at', '>=', $since)->distinct('user_id')->count('user_id');
        $repaid     = Order::where('status', 1)
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

            $d1 = $this->activeUsersOnDate(Carbon::parse($date)->addDay());
            $d7 = $this->activeUsersOnDate(Carbon::parse($date)->addDays(7));

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
        $rows = Order::where('status', 1)
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
        $rows = Promoter::with('user:id,nickname,mobile,avatar')
            ->orderByDesc('total_commission')
            ->limit($limit)
            ->get(['id', 'user_id', 'total_commission', 'frozen_commission', 'withdrawn_commission', 'invite_code']);

        return $rows->map(function ($p) {
            $available = (float) $p->total_commission - (float) $p->frozen_commission - (float) $p->withdrawn_commission;
            return [
                'id'                    => $p->id,
                'user_id'               => $p->user_id,
                'invite_code'           => $p->invite_code,
                'total_commission'      => (float) $p->total_commission,
                'available_commission'  => $available,
                'user'                  => $p->user,
            ];
        })->toArray();
    }

    /**
     * AI 使用分布（按 type）
     */
    public function analysisTypeDistribution(int $days = 30): array
    {
        return AnalysisTask::where('created_at', '>=', Carbon::now()->subDays($days))
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();
    }

    // ============ 扩展指标（P1）============

    /**
     * 退款率
     *
     * 统计周期内有已支付订单 + 成功退款订单数
     * 注意：order 表只有 0=待支付/1=已支付/2=已取消 三个状态，
     *       退款状态走独立的 refunds 表（status = 'success'）
     */
    public function refundRate(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $totalOrders = Order::where('status', 1)
            ->where('paid_at', '>=', $since)
            ->count();

        // 通过联表 refunds 找成功的退款
        $refundOrderNos = Refund::where('status', 'success')
            ->where('created_at', '>=', $since)
            ->pluck('order_no')
            ->unique();

        $refundOrders = $refundOrderNos->count();
        $refundAmount = (float) Order::whereIn('order_no', $refundOrderNos)->sum('amount');

        return [
            'total_orders'    => $totalOrders,
            'refund_orders'   => $refundOrders,
            'refund_rate'     => $this->pct($refundOrders, $totalOrders),
            'refund_amount'   => $refundAmount,
            'today_refund'    => Refund::where('status', 'success')
                ->whereDate('refunded_at', Carbon::today())
                ->count(),
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
            ->join('product_packages', 'product_packages.id', '=', 'orders.relation_id')
            ->selectRaw('product_packages.id, product_packages.name, count(*) as sales, sum(orders.amount) as revenue')
            ->groupBy('product_packages.id', 'product_packages.name')
            ->orderByDesc('revenue')
            ->get();

        return [
            'total_sales'   => (int) $rows->sum('sales'),
            'total_revenue' => (float) $rows->sum('revenue'),
            'items'         => $rows->toArray(),
        ];
    }

    /**
     * 推广转化率
     *
     * "已付费"定义：被邀请用户在注册后产生了已支付订单（status = 1）
     */
    public function promotionConversion(int $days = 30): array
    {
        $since = Carbon::now()->subDays($days);

        $totalInvited = \App\Models\InviteRegistration::where('created_at', '>=', $since)->count();

        // 通过联表 orders 来判断是否付费（status = 1）
        $paidInvited = \App\Models\InviteRegistration::where('invite_registrations.created_at', '>=', $since)
            ->join('orders', function ($join) {
                $join->on('orders.user_id', '=', 'invite_registrations.user_id')
                    ->where('orders.status', '=', 1);
            })
            ->distinct('invite_registrations.user_id')
            ->count('invite_registrations.user_id');

        $totalCommission = (float) Commission::where('created_at', '>=', $since)->sum('amount');

        return [
            'total_invited'   => $totalInvited,
            'paid_invited'    => $paidInvited,
            'conversion_rate' => $this->pct($paidInvited, $totalInvited),
            'total_commission' => $totalCommission,
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
     * 活跃用户数（按天）
     * 数据源：Sanctum 的 personal_access_tokens.last_used_at
     * 仅统计 user 类型的 token（排除 admin）
     */
    protected function activeUsersInDay(Carbon $date): int
    {
        return (int) DB::table('personal_access_tokens')
            ->where('tokenable_type', \App\Models\User::class)
            ->whereDate('last_used_at', $date)
            ->distinct('tokenable_id')
            ->count('tokenable_id');
    }

    /**
     * 某段时间内的活跃用户数
     */
    protected function activeUsersSince(Carbon $since): int
    {
        return (int) DB::table('personal_access_tokens')
            ->where('tokenable_type', \App\Models\User::class)
            ->where('last_used_at', '>=', $since)
            ->distinct('tokenable_id')
            ->count('tokenable_id');
    }

    /**
     * 指定日期当天活跃用户数
     */
    protected function activeUsersOnDate(Carbon $date): int
    {
        return $this->activeUsersInDay($date);
    }

    protected function pct(int $a, int $b): float
    {
        return $b > 0 ? round($a / $b * 100, 1) : 0.0;
    }
}
