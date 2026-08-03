<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 用户反馈
 */
class FeedbackController extends Controller
{
    public function __construct(protected NotificationService $notif) {}

    public function index(Request $request)
    {
        $feedbacks = Feedback::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $feedbacks]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'    => 'required|in:bug,suggestion,complaint,other',
            'title'   => 'required|string|max:200',
            'content' => 'required|string|max:2000',
            'images'  => 'nullable|array',
            'images.*' => 'string',
            'contact' => 'nullable|string|max:100',
        ]);
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';
        $feedback = Feedback::create($data);
        return response()->json(['code' => 0, 'message' => '反馈已提交，我们会尽快处理', 'data' => $feedback]);
    }

    public function show(int $id)
    {
        $feedback = Feedback::where('user_id', Auth::id())->findOrFail($id);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $feedback]);
    }
}
