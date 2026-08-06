<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerServiceSession;
use App\Models\CustomerServiceMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CustomerServiceController extends Controller
{
    /**
     * 获取会话列表（客服端）
     */
    public function sessions(Request $request)
    {
        $status = $request->input('status', '');
        $keyword = $request->input('keyword', '');
        $updatedAfter = $request->input('updated_after', '');

        // 自动标记超过5分钟无活动的用户为离线
        $this->autoMarkOffline();

        $query = CustomerServiceSession::with('user');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('session_no', 'like', "%{$keyword}%")
                  ->orWhere('title', 'like', "%{$keyword}%")
                  ->orWhereHas('user', function ($uq) use ($keyword) {
                      $uq->where('nickname', 'like', "%{$keyword}%")
                         ->orWhere('mobile', 'like', "%{$keyword}%");
                  });
            });
        }

        // 支持按更新时间过滤（用于轮询检测新消息）
        if ($updatedAfter) {
            $query->where('updated_at', '>', $updatedAfter);
        }

        $sessions = $query->orderBy('updated_at', 'desc')->paginate(20);

        // 为每条会话添加简化的浏览器信息
        $sessions->getCollection()->transform(function ($session) {
            $session->browser_short = $session->browser_short;
            $session->is_actually_online = $session->is_actually_online;
            return $session;
        });

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $sessions,
        ]);
    }

    /**
     * 自动标记超过5分钟无活动的用户为离线
     */
    private function autoMarkOffline(): void
    {
        CustomerServiceSession::where('is_online', true)
            ->where('last_active_at', '<', now()->subMinutes(5))
            ->update(['is_online' => false]);
    }

    /**
     * 获取消息列表（客服端，支持增量获取）
     */
    public function messages(Request $request, string $sessionNo)
    {
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->firstOrFail();

        $query = CustomerServiceMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc');

        // 增量获取：只获取 after_id 之后的消息
        if ($request->has('after_id') && $request->input('after_id') > 0) {
            $afterId = (int) $request->input('after_id');
            $query->where('id', '>', $afterId);
            $messages = $query->get();
        } else {
            $messages = $query->paginate(50);

            // 清除客服未读
            $session->admin_unread = 0;
            $session->save();

            // 标记用户发送的消息为已读
            CustomerServiceMessage::where('session_id', $session->id)
                ->where('sender_type', 'user')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $messages,
        ]);
    }
    }

    /**
     * 客服发送消息
     */
    public function sendMessage(Request $request, string $sessionNo)
    {
        $request->validate([
            'content' => 'required_without:image|max:5000',
        ]);
        
        $admin = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
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
                'sender_id' => $admin->id,
                'sender_type' => 'admin',
                'content' => $request->input('content', ''),
                'msg_type' => 'text',
                'is_auto_reply' => $request->input('is_auto_reply', false),
            ]);
            
            // 更新会话
            $session->admin_id = $admin->id;
            $session->message_count += 1;
            $session->user_unread += 1;
            $session->last_message_at = now();
            if ($session->status == 0) {
                $session->status = 1;
            }
            $session->save();
            
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
     * 客服上传图片
     */
    public function uploadImage(Request $request, string $sessionNo)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,gif,webp|max:5120',
        ]);
        
        $admin = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->firstOrFail();
        
        if ($session->status == 2) {
            return response()->json([
                'code' => 400,
                'message' => '会话已结束',
            ], 400);
        }
        
        try {
            $file = $request->file('image');
            $path = $file->store('customer-service', 'public');
            $url = Storage::url($path);
            
            DB::beginTransaction();
            
            $message = CustomerServiceMessage::create([
                'session_id' => $session->id,
                'sender_id' => $admin->id,
                'sender_type' => 'admin',
                'content' => '',
                'message_type' => 'image',
                'file_url' => $url,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
            ]);
            
            $session->staff_id = $admin->id;
            $session->message_count += 1;
            $session->user_unread += 1;
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
    public function closeSession(Request $request, string $sessionNo)
    {
        $session = CustomerServiceSession::where('session_no', $sessionNo)
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
     * 获取会话统计
     */
    public function statistics()
    {
        // 自动标记超过5分钟无活动的用户为离线
        $this->autoMarkOffline();

        $stats = [
            'waiting' => CustomerServiceSession::where('status', 0)->count(),
            'active' => CustomerServiceSession::where('status', 1)->count(),
            'closed' => CustomerServiceSession::where('status', 2)->count(),
            'total' => CustomerServiceSession::count(),
            'online' => CustomerServiceSession::where('is_online', true)->where('status', '<', 2)->count(),
        ];

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $stats,
        ]);
    }

    /**
     * 删除消息（软删除，管理员可删除任何消息）
     */
    public function deleteMessage(Request $request, $sessionNo, $messageId)
    {
        $admin = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)->firstOrFail();
        
        $message = CustomerServiceMessage::where('id', $messageId)
            ->where('session_id', $session->id)
            ->firstOrFail();
        
        $message->is_deleted = true;
        $message->deleted_at = now();
        $message->deleted_by = $admin->id;
        $message->save();
        
        return response()->json([
            'code' => 0,
            'message' => '消息已删除',
        ]);
    }

    /**
     * 撤回消息（管理员可撤回自己发送的消息，2分钟内）
     */
    public function recallMessage(Request $request, $sessionNo, $messageId)
    {
        $admin = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)->firstOrFail();
        
        $message = CustomerServiceMessage::where('id', $messageId)
            ->where('session_id', $session->id)
            ->firstOrFail();
        
        // 只能撤回自己发送的消息
        if ($message->sender_id !== $admin->id || $message->sender_type !== 'admin') {
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
     * 引用消息发送（管理员端）
     */
    public function replyMessage(Request $request, $sessionNo)
    {
        $request->validate([
            'content' => 'required|max:5000',
            'reply_to_id' => 'required|integer',
        ]);
        
        $admin = $request->user();
        
        $session = CustomerServiceSession::where('session_no', $sessionNo)
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
                'sender_id' => $admin->id,
                'sender_type' => 'admin',
                'content' => $request->input('content'),
                'msg_type' => 'text',
                'reply_to_id' => $request->reply_to_id,
            ]);

            // 更新会话
            $session->admin_id = $admin->id;
            $session->message_count += 1;
            $session->user_unread += 1;
            $session->last_message_at = now();
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
