<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HealthQaMessage;
use App\Models\HealthQaSession;
use App\Services\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class QaController extends Controller
{
    protected AiService $aiService;

    public function __construct(AiService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * 创建问答会话
     */
    public function createSession(Request $request)
    {
        $session = HealthQaSession::create([
            'session_no' => 'QA' . date('Ymd') . substr(md5(uniqid()), 0, 8),
            'user_id' => $request->user()->id,
            'title' => '新问答',
            'status' => 1,
        ]);

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => [
                'session_no' => $session->session_no,
                'title' => $session->title,
                'created_at' => $session->created_at,
            ],
        ]);
    }

    /**
     * 获取会话列表
     */
    public function sessions(Request $request)
    {
        $sessions = HealthQaSession::where('user_id', $request->user()->id)
            ->orderBy('updated_at', 'desc')
            ->paginate($request->get('limit', 10));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $sessions,
        ]);
    }

    /**
     * 发送消息
     */
    public function sendMessage(Request $request, string $sessionNo)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:1000',
        ]);

        $session = HealthQaSession::where('session_no', $sessionNo)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$session) {
            return response()->json([
                'code' => 404,
                'message' => '会话不存在',
            ], 404);
        }

        try {
            // 保存用户消息
            HealthQaMessage::create([
                'session_id' => $session->id,
                'role' => 'user',
                'content' => $validated['content'],
            ]);

            // 获取历史消息
            $history = $session->messages()
                ->orderBy('id', 'desc')
                ->limit(10)
                ->get()
                ->reverse()
                ->map(fn ($msg) => ['role' => $msg->role, 'content' => $msg->content])
                ->toArray();

            // 调用AI获取回复
            $aiContent = $this->aiService->chat($validated['content'], $history);

            // 保存AI回复
            $aiMessage = HealthQaMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $aiContent,
                'tokens' => mb_strlen($aiContent),
            ]);

            // 更新会话标题（取第一条消息）
            if ($session->messages()->count() === 1) {
                $session->update(['title' => mb_substr($validated['content'], 0, 20)]);
            }

            // 更新会话时间
            $session->touch();

            return response()->json([
                'code' => 0,
                'message' => '发送成功',
                'data' => [
                    'message_id' => $aiMessage->id,
                    'role' => 'assistant',
                    'content' => $aiContent,
                    'tokens' => $aiMessage->tokens,
                    'created_at' => $aiMessage->created_at,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('QA message send failed', [
                'session_no' => $sessionNo,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'code' => 500,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取消息列表
     */
    public function messages(Request $request, string $sessionNo)
    {
        $session = HealthQaSession::where('session_no', $sessionNo)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$session) {
            return response()->json([
                'code' => 404,
                'message' => '会话不存在',
            ], 404);
        }

        $messages = $session->messages()
            ->orderBy('id', 'asc')
            ->paginate($request->get('limit', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $messages,
        ]);
    }
}
