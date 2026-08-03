<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * 管理后台 - 用户反馈管理
 */
class FeedbackController extends Controller
{
    public function __construct(protected NotificationService $notif) {}

    public function index(Request $request)
    {
        $list = Feedback::with(['user:id,nickname,mobile,avatar', 'replier:id,name'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderByRaw("FIELD(status,'pending','processing','replied','closed')")
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $list]);
    }

    public function show(int $id)
    {
        $f = Feedback::with(['user', 'replier'])->findOrFail($id);
        // 第一次查看时状态变为 processing
        if ($f->status === 'pending') {
            $f->update(['status' => 'processing']);
        }
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $f]);
    }

    public function reply(Request $request, int $id)
    {
        $data = $request->validate([
            'reply' => 'required|string|max:2000',
        ]);
        $f = Feedback::findOrFail($id);
        $f->update([
            'reply'      => $data['reply'],
            'status'     => 'replied',
            'replied_at' => now(),
            'replied_by' => $request->user('admin')?->id,
        ]);

        $this->notif->feedbackReplied($f->user_id, $data['reply']);

        return response()->json(['code' => 0, 'message' => '回复成功', 'data' => $f]);
    }

    public function close(Request $request, int $id)
    {
        $f = Feedback::findOrFail($id);
        $f->update(['status' => 'closed']);
        return response()->json(['code' => 0, 'message' => '已关闭', 'data' => $f]);
    }
}
