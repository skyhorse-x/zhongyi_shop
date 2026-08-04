<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBalanceLog;
use App\Models\PaymentLog;
use App\Models\RefundLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserBalanceController extends Controller
{
    /**
     * GET /api/v1/admin/user-balances
     * 用户余额列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->select('id', 'username', 'email', 'mobile', 'balance', 'analysis_times', 'created_at')
            ->withCount(['balanceLogs as total_recharge' => function ($q) {
                $q->where('type', 'recharge')->select(DB::raw('SUM(`change`)'));
            }]);

        // 搜索
        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('username', 'like', "%{$keyword}%")
                    ->orWhere('mobile', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%");
            });
        }

        // 余额范围
        if ($minBalance = $request->input('min_balance')) {
            $query->where('balance', '>=', $minBalance);
        }
        if ($maxBalance = $request->input('max_balance')) {
            $query->where('balance', '<=', $maxBalance);
        }

        $query->orderBy('balance', 'desc');

        $perPage = $request->input('per_page', 20);
        $users = $query->paginate($perPage);

        return response()->json([
            'code' => 0,
            'data' => $users,
        ]);
    }

    /**
     * GET /api/v1/admin/user-balances/{userId}/logs
     * 用户余额变动流水
     */
    public function logs(int $userId, Request $request): JsonResponse
    {
        $query = UserBalanceLog::with('operator')
            ->where('user_id', $userId)
            ->orderBy('id', 'desc');

        // 类型筛选
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage = $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'code' => 0,
            'data' => $logs,
        ]);
    }

    /**
     * GET /api/v1/admin/payment-logs
     * 支付流水列表
     */
    public function paymentLogs(Request $request): JsonResponse
    {
        $query = PaymentLog::with(['user:id,username,mobile', 'order:id,order_no,type'])
            ->orderBy('id', 'desc');

        // 搜索
        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', "%{$keyword}%")
                    ->orWhere('transaction_id', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($uq) use ($keyword) {
                        $uq->where('username', 'like', "%{$keyword}%")
                            ->orWhere('mobile', 'like', "%{$keyword}%");
                    });
            });
        }

        // 支付渠道
        if ($payType = $request->input('pay_type')) {
            $query->where('pay_type', $payType);
        }

        // 操作类型
        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        // 状态
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // 日期范围
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $perPage = $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'code' => 0,
            'data' => $logs,
        ]);
    }

    /**
     * GET /api/v1/admin/refund-logs
     * 退款流水列表
     */
    public function refundLogs(Request $request): JsonResponse
    {
        $query = RefundLog::with(['user:id,username,mobile', 'order:id,order_no', 'operator:id,name'])
            ->orderBy('id', 'desc');

        // 搜索
        if ($keyword = $request->input('keyword')) {
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', "%{$keyword}%")
                    ->orWhere('refund_no', 'like', "%{$keyword}%")
                    ->orWhere('transaction_id', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($uq) use ($keyword) {
                        $uq->where('username', 'like', "%{$keyword}%")
                            ->orWhere('mobile', 'like', "%{$keyword}%");
                    });
            });
        }

        // 支付渠道
        if ($payType = $request->input('pay_type')) {
            $query->where('pay_type', $payType);
        }

        // 状态
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // 日期范围
        if ($startDate = $request->input('start_date')) {
            $query->whereDate('created_at', '>=', $startDate);
        }
        if ($endDate = $request->input('end_date')) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        $perPage = $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        return response()->json([
            'code' => 0,
            'data' => $logs,
        ]);
    }
}
