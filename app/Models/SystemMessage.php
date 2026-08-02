<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'content',
        'type',
        'target_url',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * 所属用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 标记为已读
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * 作用域：未读消息
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * 作用域：按类型筛选
     */
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
