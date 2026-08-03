<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 用户端 - 退款申请
 */
class RefundController extends Controller
{
    public function __construct(protected RefundService $refund) {}

    public function index(Request $request)
    {
        $list = Refund::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $list]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_no'    => 'required|string|exists:orders,order_no',
            'reason'      => 'required|in:user_request,service_failure,duplicate_payment,other',
            'description' => 'nullable|string|max:500',
        ]);
        $r = $this->refund->apply(Auth::id(), $data['order_no'], $data['reason'], $data['description'] ?? null);
        return response()->json(['code' => 0, 'message' => '退款申请已提交', 'data' => $r]);
    }

    public function show(int $id)
    {
        $r = Refund::where('user_id', Auth::id())->findOrFail($id);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $r]);
    }
}
