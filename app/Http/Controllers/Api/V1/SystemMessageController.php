<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemMessage;
use Illuminate\Http\Request;

class SystemMessageController extends Controller
{
    /**
     * 获取当前用户的系统消息列表
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = SystemMessage::where('user_id', $user->id);
        
        // 按类型筛选
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        
        // 按已读状态筛选
        if ($request->filled('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }
        
        $messages = $query->orderBy('id', 'desc')
            ->paginate($request->per_page ?? 20);
        
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $messages,
        ]);
    }

    /**
     * 获取未读消息数量
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();
        
        $count = SystemMessage::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();
        
        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * 标记消息为已读
     */
    public function markAsRead(Request $request, $id)
    {
        $user = $request->user();
        
        $message = SystemMessage::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        
        $message->markAsRead();
        
        return response()->json([
            'code' => 0,
            'message' => '已标记为已读',
        ]);
    }
}
