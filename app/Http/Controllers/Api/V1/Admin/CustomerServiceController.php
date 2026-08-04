<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerServiceSession;
use App\Models\CustomerServiceMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     * 获取消息列表（客服端）
     */
    public function messages(Request $request, $sessionNo)
    {
        $session = CustomerServiceSession::where('session_no', $sessionNo)
            ->firstOrFail();

        $messages = CustomerServiceMessage::where('session_id', $session->id)
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        // 清除客服未读
        $session->admin_unread = 0;
        $session->save();

        // 标记用户发送的消息为已读
        CustomerServiceMessage::where('session_id', $session->id)
            ->where('sender_type', 'user')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $messages,
        ]);
    }

    /**
     * 客服发送消息
     */
    public function sendMessage(Request $request, $sessionNo)
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
    public function uploadImage(Request $request, $sessionNo)
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
            $url = \Storage::url($path);
            
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
    public function closeSession(Request $request, $sessionNo)
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
}
