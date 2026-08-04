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
     * 获取消息列表
     */
    public function messages(Request $request, $sessionNo)
    {
        $user = $request->user();

        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $messages = CustomerServiceMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        // 清除用户未读
        $session->user_unread = 0;
        $session->save();

        // 标记管理员发送的消息为已读
        CustomerServiceMessage::where('session_id', $session->id)
            ->where('sender_type', 'admin')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $messages,
        ]);
    }

    /**
     * 发送消息
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
}
