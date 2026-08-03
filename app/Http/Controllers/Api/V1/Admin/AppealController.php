<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisAppeal;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 管理后台 - AI 申诉审核
 */
class AppealController extends Controller
{
    public function __construct(protected NotificationService $notif) {}

    public function index(Request $request)
    {
        $appeals = AnalysisAppeal::with(['user:id,nickname,mobile', 'analysis:id,task_no,type', 'auditor:id,name'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->orderByRaw("FIELD(status,'pending','approved','rejected')")
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $appeals]);
    }

    public function show(int $id)
    {
        $appeal = AnalysisAppeal::with(['user', 'analysis', 'auditor'])->findOrFail($id);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $appeal]);
    }

    public function audit(Request $request, int $id)
    {
        $data = $request->validate([
            'result'     => 'required|in:approved,rejected',
            'audit_note' => 'nullable|string|max:500',
        ]);
        $appeal = AnalysisAppeal::findOrFail($id);

        $appeal->update([
            'status'     => $data['result'],
            'audit_note' => $data['audit_note'] ?? null,
            'audited_by' => $request->user('admin')?->id,
            'audited_at' => now(),
        ]);

        // 通知用户
        $this->notif->appealResult(
            $appeal->user_id,
            $data['result'] === 'approved',
            $data['audit_note'] ?? ''
        );

        return response()->json(['code' => 0, 'message' => '审核完成', 'data' => $appeal]);
    }
}
