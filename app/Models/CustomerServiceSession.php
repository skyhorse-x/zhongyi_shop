<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerServiceSession extends Model
{
    protected $fillable = [
        'session_no',
        'user_id',
        'admin_id',
        'title',
        'status',
        'message_count',
        'user_unread',
        'admin_unread',
        'last_message_at',
        'closed_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'message_count' => 'integer',
        'user_unread' => 'integer',
        'admin_unread' => 'integer',
        'last_message_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * 生成唯一会话编号
     */
    public static function generateSessionNo(): string
    {
        do {
            $sessionNo = 'CS' . date('Ymd') . strtoupper(Str::random(6));
        } while (self::where('session_no', $sessionNo)->exists());
        
        return $sessionNo;
    }

    /**
     * 用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 客服管理员
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * 消息列表
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CustomerServiceMessage::class, 'session_id');
    }

    /**
     * 获取最后一条消息
     */
    public function lastMessage()
    {
        return $this->messages()->latest()->first();
    }
}
