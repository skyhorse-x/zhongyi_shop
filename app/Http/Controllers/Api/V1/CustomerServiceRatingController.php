<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerServiceRating;
use App\Models\CustomerServiceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 客服会话评价（用户端）
 */
class CustomerServiceRatingController extends Controller
{
    public function store(Request $request, string $sessionNo)
    {
        $data = $request->validate([
            'score'    => 'required|integer|min:1|max:5',
            'attitude' => 'required|in:good,normal,bad',
            'solved'   => 'required|in:yes,partial,no',
            'comment'  => 'nullable|string|max:500',
            'tags'     => 'nullable|array',
        ]);

        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', Auth::id())
            ->where('status', 2) // 必须是已结束
            ->firstOrFail();

        if ($session->rated) {
            return response()->json(['code' => 1, 'message' => '该会话已评价', 'data' => null], 400);
        }

        $rating = CustomerServiceRating::create(array_merge($data, [
            'session_no' => $sessionNo,
            'user_id'    => Auth::id(),
            'admin_id'   => $session->admin_id,
        ]));

        $session->update([
            'satisfaction_score' => $data['score'],
            'rated'              => true,
        ]);

        return response()->json(['code' => 0, 'message' => '评价成功', 'data' => $rating]);
    }
}
