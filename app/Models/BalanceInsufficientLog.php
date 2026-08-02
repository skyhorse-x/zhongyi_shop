<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BalanceInsufficientLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'current_balance',
        'required_amount',
        'action_type',
        'is_notified',
        'message',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'required_amount' => 'decimal:2',
        'is_notified' => 'boolean',
    ];

    /**
     * 所属用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联会话
     */
    public function session()
    {
        return $this->belongsTo(CustomerServiceSession::class, 'session_id');
    }

    /**
     * 作用域：未通知的记录
     */
    public function scopeNotNotified($query)
    {
        return $query->where('is_notified', false);
    }

    /**
     * 标记为已通知
     */
    public function markNotified(): void
    {
        $this->update(['is_notified' => true]);
    }
}
