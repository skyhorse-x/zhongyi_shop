<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnalysisAppeal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * AI 诊断申诉
 */
class AppealController extends Controller
{
    public function index(Request $request)
    {
        $appeals = AnalysisAppeal::where('user_id', Auth::id())
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $appeals]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'analysis_id'  => 'nullable|integer|exists:analysis,id',
            'task_no'      => 'nullable|string|max:64',
            'reason'       => 'required|string|max:200',
            'description'  => 'required|string|max:2000',
            'attachments'  => 'nullable|array',
        ]);
        $data['user_id'] = Auth::id();
        $data['status'] = 'pending';

        $appeal = AnalysisAppeal::create($data);
        return response()->json(['code' => 0, 'message' => '申诉已提交', 'data' => $appeal]);
    }

    public function show(int $id)
    {
        $appeal = AnalysisAppeal::where('user_id', Auth::id())->findOrFail($id);
        return response()->json(['code' => 0, 'message' => 'ok', 'data' => $appeal]);
    }
}
