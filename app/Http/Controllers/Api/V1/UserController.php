<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * 获取用户信息
     */
    public function info(Request $request)
    {
        $user = $request->user();
        $user->load(['profile', 'promoter']);

        // 是否享受过注册赠送（用于前端展示"新人礼"标签）
        $grantedTimes = (int) \App\Models\SystemConfig::getValue('user_free_analysis_times', 3);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => array_merge($user->toArray(), [
                'is_new_user_gift' => (bool) $user->user_registered_granted && (int) $user->analysis_times > 0,
                'gift_times'      => $user->user_registered_granted ? $grantedTimes : 0,
            ]),
        ]);
    }

    /**
     * 更新用户信息
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'nickname' => 'sometimes|string|max:50',
            'avatar' => 'sometimes|string|url',
            'gender' => 'sometimes|in:0,1,2',
            'birthday' => 'sometimes|date',
        ]);

        $user->update($validated);

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $user,
        ]);
    }

    /**
     * 获取当前用户的订单列表
     */
    public function orders(Request $request)
    {
        $query = \App\Models\Order::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        // 按状态筛选
        if ($request->filled('status')) {
            $query->where('status', (int) $request->get('status'));
        }

        $paginator = $query->paginate($request->get('per_page', 10));

        // 附加可读字段
        $paginator->getCollection()->transform(function ($order) {
            $order->type_name = $this->getOrderTypeName($order->type);
            $order->item_count = 1;
            // 关联商品名称
            $order->item_name = $this->getOrderItemName($order);
            $order->item_cover = null;
            return $order;
        });

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $paginator,
        ]);
    }

    /**
     * 获取订单详情
     */
    public function orderDetail(Request $request, string $orderNo)
    {
        $order = \App\Models\Order::where('user_id', $request->user()->id)
            ->where('order_no', $orderNo)
            ->first();

        if (!$order) {
            return response()->json([
                'code' => 404,
                'message' => '订单不存在',
            ], 404);
        }

        $order->type_name = $this->getOrderTypeName($order->type);
        $order->item_count = 1;
        $order->item_name = $this->getOrderItemName($order);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $order,
        ]);
    }

    /**
     * 取消订单
     */
    public function cancelOrder(Request $request, string $orderNo)
    {
        $order = \App\Models\Order::where('user_id', $request->user()->id)
            ->where('order_no', $orderNo)
            ->where('status', 0)
            ->first();

        if (!$order) {
            return response()->json([
                'code' => 404,
                'message' => '订单不存在或不可取消',
            ], 404);
        }

        $order->update([
            'status' => 2,
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'code' => 0,
            'message' => '订单已取消',
            'data' => $order,
        ]);
    }

    /**
     * 当前用户余额明细
     */
    public function balanceLogs(Request $request)
    {
        $user = $request->user();
        $logs = \App\Models\UserBalanceLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'balance' => (float) $user->balance,
                'logs'    => $logs,
            ],
        ]);
    }

    private function getOrderTypeName(string $type): string
    {
        // 优先从数据库配置读取，配置缺失时回退到默认值
        $typeMap = [
            'analysis' => \App\Models\SystemConfig::where('key', 'order_type_analysis_name')->value('value') ?: 'AI 智能分析',
            'package' => \App\Models\SystemConfig::where('key', 'order_type_package_name')->value('value') ?: '次数套餐',
            'constitution' => \App\Models\SystemConfig::where('key', 'order_type_constitution_name')->value('value') ?: '体质测试',
        ];
        return $typeMap[$type] ?? $type;
    }

    private function getOrderItemName($order): string
    {
        if ($order->type === 'package') {
            $pkg = \App\Models\ProductPackage::find($order->relation_id);
            return $pkg ? $pkg->name : '次数套餐';
        }
        if ($order->type === 'analysis') {
            $task = \App\Models\AnalysisTask::where('task_no', $order->relation_id)->first();
            if ($task) {
                // 优先从数据库配置读取分析类型名称
                $typeMap = [
                    'tongue' => \App\Models\SystemConfig::where('key', 'analysis_type_tongue_name')->value('value') ?: '舌象分析',
                    'face' => \App\Models\SystemConfig::where('key', 'analysis_type_face_name')->value('value') ?: '面象分析',
                    'constitution' => \App\Models\SystemConfig::where('key', 'analysis_type_constitution_name')->value('value') ?: '体质分析',
                ];
                return $typeMap[$task->type] ?? 'AI 分析报告';
            }
        }
        return \App\Models\SystemConfig::where('key', 'order_default_item_name')->value('value') ?: '虚拟商品';
    }
}
