<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;

/**
 * 管理后台 - 退款管理
 */
class RefundController extends Controller
{
    public function __construct(protected RefundService $refund) {}

    public function index(Request $request)
    {
        $list = Refund::with(['user:id,nickname,mobile', 'order:id,order_no,amount,pay_type,type', 'processor:id,name'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->reason, fn ($q) => $q->where('reason', $request->reason))
            ->orderByRaw("FIELD(status,'pending','processing','success','failed','cancelled')")
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $list]);
    }

    public function show(int $id)
    {
        $r = Refund::with(['user', 'order', 'processor'])->findOrFail($id);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $r]);
    }

    public function approve(Request $request, int $id)
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);
        $r = Refund::findOrFail($id);
        $ok = $this->refund->approve($r, $request->user('admin')?->id, $data['note'] ?? null);
        if (!$ok) {
            return response()->json(['code' => 1, 'message' => '渠道退款失败', 'data' => null], 500);
        }
        return response()->json(['code' => 0, 'message' => '退款成功', 'data' => $r->fresh()]);
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);
        $r = Refund::findOrFail($id);
        $this->refund->reject($r, $request->user('admin')?->id, $data['note'] ?? null);
        return response()->json(['code' => 0, 'message' => '已驳回', 'data' => $r]);
    }
}
