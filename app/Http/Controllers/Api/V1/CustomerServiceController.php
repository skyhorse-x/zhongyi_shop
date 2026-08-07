<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerServiceSession;
use App\Models\CustomerServiceMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerServiceController extends Controller
{
    /**
     * 获取或创建客服会话
     */
    public function getOrCreateSession(Request $request)
    {
        $user = $request->user();

        // 查找进行中的会话
        $session = CustomerServiceSession::where('user_id', $user->id)
            ->where('status', '<', 2)
            ->orderBy('created_at', 'desc')
            ->first();

        $isNew = false;

        if (!$session) {
            $session = CustomerServiceSession::create([
                'session_no' => CustomerServiceSession::generateSessionNo(),
                'user_id' => $user->id,
                'title' => '客服咨询',
                'status' => 0,
                'ip_address' => $request->ip(),
                'browser_info' => $request->userAgent(),
                'is_online' => true,
                'last_active_at' => now(),
            ]);
            $isNew = true;

            // 自动发送欢迎消息
            $session->sendWelcomeMessage();
        } else {
            // 更新在线状态和客户端信息
            $session->updateOnlineStatus($request->ip(), $request->userAgent());
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $session,
            'is_new' => $isNew,
        ]);
    }

    /**
     * 获取会话列表（用户端）
     */
    public function sessions(Request $request)
    {
        $user = $request->user();
        
        $sessions = CustomerServiceSession::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->paginate(20);
        
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $sessions,
        ]);
    }

    /**
     * 获取消息列表（支持增量获取）
     */
    public function messages(Request $request, $sessionNo)
    {
        $user = $request->user();

        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $query = CustomerServiceMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc');

        // 增量获取：只获取 after_id 之后的消息
        if ($request->has('after_id') && $request->input('after_id') > 0) {
            $afterId = (int) $request->input('after_id');
            $query->where('id', '>', $afterId);
            // 增量模式：不分页，直接返回所有新消息
            $messages = $query->get();
        } else {
            // 全量获取：分页
            $messages = $query->paginate(50);

            // 清除用户未读（仅全量获取时）
            $session->user_unread = 0;
            $session->save();

            // 标记管理员发送的消息为已读
            CustomerServiceMessage::where('session_id', $session->id)
                ->where('sender_type', 'admin')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $messages,
        ]);
    }

    /**
     * 发送消息（支持关键词自动回复）
     */
    public function sendMessage(Request $request, $sessionNo)
    {
        $request->validate([
            'content' => 'required_without:file|max:5000',
        ]);
        
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        if ($session->status == 2) {
            return response()->json([
                'code' => 400,
                'message' => '会话已结束',
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            // 创建消息
            $message = CustomerServiceMessage::create([
                'session_id' => $session->id,
                'sender_id' => $user->id,
                'sender_type' => 'user',
                'content' => $request->input('content', ''),
                'message_type' => 'text',
            ]);

            // 更新会话
            $session->message_count += 1;
            $session->admin_unread += 1;
            $session->last_message_at = now();
            $session->is_online = true;
            $session->last_active_at = now();
            if ($session->status == 0) {
                $session->status = 1; // 改为服务中
            }
            $session->save();
            
            // 检查关键词自动回复
            $this->checkAndAutoReply($session, $request->input('content', ''));
            
            DB::commit();
            
            return response()->json([
                'code' => 0,
                'message' => '发送成功',
                'data' => $message,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => 500,
                'message' => '发送失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 检查并执行关键词自动回复
     */
    private function checkAndAutoReply(CustomerServiceSession $session, string $userContent): void
    {
        // 获取所有启用的关键词触发话术
        $phrases = \App\Models\CustomerServicePhrase::where('is_enabled', true)
            ->where('is_auto_reply', true)
            ->where('trigger_type', 'keyword')
            ->get();

        foreach ($phrases as $phrase) {
            $keywords = $phrase->keywords_array;
            if (empty($keywords)) {
                continue;
            }

            // 检查用户消息是否包含任一关键词
            foreach ($keywords as $keyword) {
                if (empty($keyword)) {
                    continue;
                }
                // 模糊匹配：用户消息包含关键词即触发
                if (mb_stripos($userContent, $keyword) !== false) {
                    // 创建自动回复消息
                    CustomerServiceMessage::create([
                        'session_id' => $session->id,
                        'sender_id' => 0, // 系统自动回复
                        'sender_type' => 'admin',
                        'content' => $phrase->content,
                        'message_type' => 'text',
                        'is_auto_reply' => true,
                        'read_at' => now(), // 自动回复默认已读
                    ]);

                    // 更新会话
                    $session->message_count += 1;
                    $session->user_unread += 1;
                    $session->last_message_at = now();
                    $session->save();

                    // 只触发第一个匹配的话术
                    return;
                }
            }
        }
    }

    /**
     * 上传图片
     */
    public function uploadImage(Request $request, $sessionNo)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);
        
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        if ($session->status == 2) {
            return response()->json([
                'code' => 400,
                'message' => '会话已结束',
            ], 400);
        }
        
        try {
            // 保存图片
            $file = $request->file('image');
            $path = $file->store('customer-service', 'public');
            $url = \Storage::url($path);
            
            DB::beginTransaction();
            
            // 创建消息
            $message = CustomerServiceMessage::create([
                'session_id' => $session->id,
                'sender_id' => $user->id,
                'sender_type' => 'user',
                'content' => '',
                'message_type' => 'image',
                'file_url' => $url,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
            
            // 更新会话
            $session->message_count += 1;
            $session->admin_unread += 1;
            $session->last_message_at = now();
            if ($session->status == 0) {
                $session->status = 1;
            }
            $session->save();
            
            DB::commit();
            
            return response()->json([
                'code' => 0,
                'message' => '上传成功',
                'data' => $message,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => 500,
                'message' => '上传失败: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 关闭会话
     */
    public function closeSession(Request $request, $sessionNo)
    {
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $session->status = 2;
        $session->closed_at = now();
        $session->save();
        
        return response()->json([
            'code' => 0,
            'message' => '会话已关闭',
        ]);
    }

    /**
     * 心跳上报（用户端定期调用以维持在线状态）
     */
    public function heartbeat(Request $request, $sessionNo)
    {
        $user = $request->user();

        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->first();

        if (!$session || $session->status == 2) {
            return response()->json([
                'code' => 400,
                'message' => '会话不存在或已结束',
            ], 400);
        }

        // 更新在线状态
        $session->updateOnlineStatus($request->ip(), $request->userAgent());

        return response()->json([
            'code' => 0,
            'message' => 'success',
        ]);
    }

    /**
     * 用户离开页面时标记为离线（不关闭会话）
     */
    public function markOffline(Request $request, $sessionNo)
    {
        $user = $request->user();

        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->first();

        if (!$session || $session->status == 2) {
            return response()->json([
                'code' => 400,
                'message' => '会话不存在或已结束',
            ], 400);
        }

        // 仅标记离线，不关闭会话
        $session->is_online = false;
        $session->save();

        return response()->json([
            'code' => 0,
            'message' => '已标记离线',
        ]);
    }

    /**
     * 标记消息为已读
     */
    public function markAsRead(Request $request, $sessionNo)
    {
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        // 标记所有对方发送的消息为已读
        CustomerServiceMessage::where('session_id', $session->id)
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        
        // 清除未读数
        $session->user_unread = 0;
        $session->save();
        
        return response()->json([
            'code' => 0,
            'message' => '已标记为已读',
        ]);
    }

    /**
     * 管理员标记用户消息为已读（管理员查看会话时调用）
     */
    public function adminMarkRead(Request $request, $sessionNo)
    {
        // 查找会话（管理员可以查看任何会话）
        $session = CustomerServiceSession::where('session_no', $sessionNo)->firstOrFail();
        
        // 标记所有用户发送的消息为已读（添加 read_at 时间戳）
        CustomerServiceMessage::where('session_id', $session->id)
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
        
        // 清除管理员未读数
        $session->admin_unread = 0;
        $session->save();
        
        return response()->json([
            'code' => 0,
            'message' => '已标记为已读',
        ]);
    }

    /**
     * 获取消息已读状态（用于前端同步已读状态）
     */
    public function readStatus(Request $request, $sessionNo)
    {
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        // 获取用户发送的消息的已读状态
        $readStatus = CustomerServiceMessage::where('session_id', $session->id)
            ->where('sender_type', 'user')
            ->whereNotNull('read_at')
            ->pluck('read_at', 'id')
            ->map(function ($readAt) {
                return $readAt->toDateTimeString();
            });
        
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $readStatus,
        ]);
    }

    /**
     * 删除消息（软删除，仅自己可见）
     */
    public function deleteMessage(Request $request, $sessionNo, $messageId)
    {
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $message = CustomerServiceMessage::where('id', $messageId)
            ->where('session_id', $session->id)
            ->firstOrFail();
        
        // 只能删除自己发送的消息
        if ($message->sender_id !== $user->id || $message->sender_type !== 'user') {
            return response()->json([
                'code' => 403,
                'message' => '只能删除自己发送的消息',
            ], 403);
        }
        
        $message->is_deleted = true;
        $message->deleted_at = now();
        $message->deleted_by = $user->id;
        $message->save();
        
        return response()->json([
            'code' => 0,
            'message' => '消息已删除',
        ]);
    }

    /**
     * 撤回消息（2分钟内可撤回）
     */
    public function recallMessage(Request $request, $sessionNo, $messageId)
    {
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $message = CustomerServiceMessage::where('id', $messageId)
            ->where('session_id', $session->id)
            ->firstOrFail();
        
        // 只能撤回自己发送的消息
        if ($message->sender_id !== $user->id || $message->sender_type !== 'user') {
            return response()->json([
                'code' => 403,
                'message' => '只能撤回自己发送的消息',
            ], 403);
        }
        
        // 检查是否在2分钟内
        $createdAt = $message->created_at;
        if (now()->diffInMinutes($createdAt) > 2) {
            return response()->json([
                'code' => 400,
                'message' => '消息发送超过2分钟，无法撤回',
            ], 400);
        }
        
        $message->is_recalled = true;
        $message->recalled_at = now();
        $message->save();
        
        return response()->json([
            'code' => 0,
            'message' => '消息已撤回',
        ]);
    }

    /**
     * 引用消息发送
     */
    public function replyMessage(Request $request, $sessionNo)
    {
        $request->validate([
            'content' => 'required|max:5000',
            'reply_to_id' => 'required|integer',
        ]);
        
        $user = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        if ($session->status == 2) {
            return response()->json([
                'code' => 400,
                'message' => '会话已结束',
            ], 400);
        }
        
        // 验证引用的消息是否存在
        $replyToMessage = CustomerServiceMessage::where('id', $request->reply_to_id)
            ->where('session_id', $session->id)
            ->firstOrFail();
        
        DB::beginTransaction();
        try {
            // 创建消息
            $message = CustomerServiceMessage::create([
                'session_id' => $session->id,
                'sender_id' => $user->id,
                'sender_type' => 'user',
                'content' => $request->input('content'),
                'message_type' => 'text',
                'reply_to_id' => $request->reply_to_id,
            ]);

            // 更新会话
            $session->message_count += 1;
            $session->admin_unread += 1;
            $session->last_message_at = now();
            $session->is_online = true;
            $session->last_active_at = now();
            if ($session->status == 0) {
                $session->status = 1;
            }
            $session->save();
            
            DB::commit();
            
            // 加载引用消息信息
            $message->load('replyTo');
            
            return response()->json([
                'code' => 0,
                'message' => '发送成功',
                'data' => $message,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code' => 500,
                'message' => '发送失败: ' . $e->getMessage(),
            ], 500);
        }
    }
}
