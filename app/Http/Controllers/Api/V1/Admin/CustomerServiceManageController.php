<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerServicePhrase;
use App\Models\CustomerServiceConfig;
use App\Models\CustomerServiceSession;
use App\Models\SystemMessage;
use App\Models\BalanceInsufficientLog;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerServiceManageController extends Controller
{
    /**
     * ===== 常用话术管理 =====
     */

    /**
     * 获取常用话术列表
     */
    public function phrases(Request $request)
    {
        $query = CustomerServicePhrase::query()
            ->where('is_enabled', true);

        // 筛选：公共话术或当前客服的话术
        $adminId = $request->user()->id;
        $query->where(function ($q) use ($adminId) {
            $q->where('is_public', true)
              ->orWhere('admin_id', $adminId);
        });

        // 按分类筛选
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // 搜索关键词
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('title', 'like', "%{$keyword}%")
                  ->orWhere('content', 'like', "%{$keyword}%");
            });
        }

        $phrases = $query->orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['code' => 0, 'data' => $phrases]);
    }

    /**
     * 创建常用话术
     */
    public function phraseStore(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'content' => 'required|string',
            'category' => 'nullable|string|in:common,greeting,promotion',
            'sort_order' => 'nullable|integer',
            'is_public' => 'nullable|boolean',
        ]);

        $data['admin_id'] = $request->user()->id;
        $data['category'] = $data['category'] ?? 'common';
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_public'] = $data['is_public'] ?? false;

        $phrase = CustomerServicePhrase::create($data);

        return response()->json(['code' => 0, 'message' => '创建成功', 'data' => $phrase]);
    }

    /**
     * 更新常用话术
     */
    public function phraseUpdate(Request $request, $id)
    {
        $phrase = CustomerServicePhrase::findOrFail($id);

        // 检查权限：只能修改自己的话术（公共话术需要超级管理员）
        if (!$phrase->is_public && $phrase->admin_id !== $request->user()->id) {
            return response()->json(['code' => 403, 'message' => '无权修改'], 403);
        }

        $data = $request->validate([
            'title' => 'nullable|string|max:100',
            'content' => 'nullable|string',
            'category' => 'nullable|string|in:common,greeting,promotion',
            'sort_order' => 'nullable|integer',
            'is_enabled' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $phrase->update(array_filter($data, fn($v) => !is_null($v)));

        return response()->json(['code' => 0, 'message' => '更新成功', 'data' => $phrase]);
    }

    /**
     * 删除常用话术
     */
    public function phraseDestroy(Request $request, $id)
    {
        $phrase = CustomerServicePhrase::findOrFail($id);

        // 检查权限
        if (!$phrase->is_public && $phrase->admin_id !== $request->user()->id) {
            return response()->json(['code' => 403, 'message' => '无权删除'], 403);
        }

        $phrase->delete();

        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    /**
     * 切换话术自动回复状态（支持多选）
     */
    public function toggleAutoReply(Request $request, $id)
    {
        $phrase = CustomerServicePhrase::findOrFail($id);

        // 检查权限
        if (!$phrase->is_public && $phrase->admin_id !== $request->user()->id) {
            return response()->json(['code' => 403, 'message' => '无权操作'], 403);
        }

        // 切换自动回复状态（支持多个）
        $phrase->is_auto_reply = !$phrase->is_auto_reply;
        $phrase->save();

        return response()->json(['code' => 0, 'message' => '操作成功', 'data' => $phrase]);
    }

    /**
     * 获取当前自动回复话术ID列表
     */
    public function getAutoReplyPhraseIds(Request $request)
    {
        $adminId = $request->user()->id;
        $phraseIds = CustomerServicePhrase::where('is_auto_reply', true)
            ->where(function ($q) use ($adminId) {
                $q->where('is_public', true)
                  ->orWhere('admin_id', $adminId);
            })
            ->pluck('id')
            ->toArray();

        return response()->json(['code' => 0, 'data' => $phraseIds]);
    }

    /**
     * ===== 系统消息管理 =====
     */

    /**
     * 获取系统消息列表
     */
    public function systemMessages(Request $request)
    {
        $query = SystemMessage::query()->with('user');

        // 按类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // 按用户筛选
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $messages = $query->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['code' => 0, 'data' => $messages]);
    }

    /**
     * 发送系统消息给用户
     */
    public function sendSystemMessage(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|min:0',
            'title' => 'required|string|max:200',
            'content' => 'required|string',
            'type' => 'nullable|string|in:notice,activity,system,balance',
            'target_url' => 'nullable|string|max:500',
        ]);

        $data['type'] = $data['type'] ?? 'notice';

        // 广播给所有用户
        if ($data['user_id'] == 0) {
            $userIds = User::pluck('id');
            foreach ($userIds as $userId) {
                SystemMessage::create([
                    'user_id' => $userId,
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'type' => $data['type'],
                    'target_url' => $data['target_url'] ?? '',
                ]);
            }
            $count = count($userIds);
        } else {
            // 发送给指定用户
            SystemMessage::create($data);
            $count = 1;
        }

        return response()->json(['code' => 0, 'message' => "已发送给 {$count} 位用户"]);
    }

    /**
     * 删除系统消息
     */
    public function systemMessageDestroy($id)
    {
        SystemMessage::findOrFail($id)->delete();
        return response()->json(['code' => 0, 'message' => '删除成功']);
    }

    /**
     * ===== 余额不足记录 =====
     */

    /**
     * 获取余额不足记录
     */
    public function balanceInsufficientLogs(Request $request)
    {
        $query = BalanceInsufficientLog::query()->with('user');

        // 按操作类型筛选
        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        // 按通知状态筛选
        if ($request->filled('is_notified')) {
            $query->where('is_notified', $request->is_notified);
        }

        $logs = $query->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['code' => 0, 'data' => $logs]);
    }

    /**
     * 统计余额不足记录
     */
    public function balanceInsufficientStats()
    {
        $stats = [
            'total_count' => BalanceInsufficientLog::count(),
            'today_count' => BalanceInsufficientLog::whereDate('created_at', today())->count(),
            'unnotified_count' => BalanceInsufficientLog::notNotified()->count(),
            'by_action_type' => BalanceInsufficientLog::selectRaw('action_type, count(*) as count')
                ->groupBy('action_type')
                ->pluck('count', 'action_type'),
        ];

        return response()->json(['code' => 0, 'data' => $stats]);
    }

    /**
     * ===== 客服配置 =====
     */

    /**
     * 获取客服配置
     */
    public function configs()
    {
        $welcomeMessage = CustomerServiceConfig::getValue('welcome_message', CustomerServiceSession::WELCOME_MESSAGE);
        $autoWelcome = CustomerServiceConfig::getValue('auto_welcome', 'true');
        $autoReplyPhraseIds = CustomerServiceConfig::getValue('auto_reply_phrase_ids', '[]');
        $autoCloseOnLeave = CustomerServiceConfig::getValue('auto_close_on_leave', 'true');

        // 解析JSON数组
        $phraseIds = json_decode($autoReplyPhraseIds, true) ?? [];
        if (!is_array($phraseIds)) {
            $phraseIds = [];
        }

        return response()->json([
            'code' => 0,
            'data' => [
                'welcome_message' => $welcomeMessage,
                'auto_welcome' => $autoWelcome === 'true',
                'auto_reply_phrase_ids' => $phraseIds,
                'auto_close_on_leave' => $autoCloseOnLeave === 'true',
            ],
        ]);
    }

    /**
     * 更新客服配置
     */
    public function configUpdate(Request $request)
    {
        $data = $request->validate([
            'welcome_message' => 'nullable|string|max:1000',
            'auto_welcome' => 'nullable|boolean',
            'auto_reply_phrase_ids' => 'nullable|array',
            'auto_reply_phrase_ids.*' => 'integer',
            'auto_close_on_leave' => 'nullable|boolean',
        ]);

        if (isset($data['welcome_message'])) {
            CustomerServiceConfig::setValue('welcome_message', $data['welcome_message'], '欢迎消息', '用户进入客服时自动发送的欢迎消息');
        }

        if (isset($data['auto_welcome'])) {
            CustomerServiceConfig::setValue('auto_welcome', $data['auto_welcome'] ? 'true' : 'false', '自动欢迎', '是否自动发送欢迎消息');
        }

        if (isset($data['auto_reply_phrase_ids'])) {
            // 验证所有话术是否存在且启用
            $phraseIds = array_filter($data['auto_reply_phrase_ids'], fn($id) => $id > 0);
            if (!empty($phraseIds)) {
                $existingCount = CustomerServicePhrase::whereIn('id', $phraseIds)
                    ->where('is_enabled', true)
                    ->count();
                if ($existingCount !== count($phraseIds)) {
                    return response()->json(['code' => 400, 'message' => '部分话术不存在或已禁用'], 400);
                }
            }
            // 存储为JSON数组
            CustomerServiceConfig::setValue('auto_reply_phrase_ids', json_encode(array_values($phraseIds)), '自动回复话术ID列表', '设置后用户发送消息时将自动回复这些话术内容');
        }

        if (isset($data['auto_close_on_leave'])) {
            CustomerServiceConfig::setValue('auto_close_on_leave', $data['auto_close_on_leave'] ? 'true' : 'false', '离开自动关闭', '用户离开客服页面时自动关闭会话');
        }

        return response()->json(['code' => 0, 'message' => '保存成功']);
    }

    /**
     * ===== 发送系统消息到客服会话 =====
     */

    /**
     * 在客服会话中发送系统消息
     */
    public function sendSessionSystemMessage(Request $request, $sessionNo)
    {
        $data = $request->validate([
            'content' => 'required|string',
            'msg_type' => 'nullable|string|in:text,image',
        ]);

        $session = CustomerServiceSession::where('session_no', $sessionNo)->firstOrFail();

        // 创建系统消息记录
        $message = $session->messages()->create([
            'sender_id' => 0,
            'sender_type' => 'system',
            'content' => $data['content'],
            'message_type' => $data['msg_type'] ?? 'text',
        ]);

        // 更新会话
        $session->increment('message_count');
        $session->update(['last_message_at' => now()]);

        return response()->json(['code' => 0, 'message' => '发送成功', 'data' => $message]);
    }
}
