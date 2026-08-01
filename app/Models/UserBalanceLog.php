<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBalanceLog extends Model
{
    protected $fillable = [
        'user_id',
        'change',
        'before',
        'after',
        'type',
        'remark',
        'operator_id',
    ];

    protected function casts(): array
    {
        return [
            'change' => 'decimal:2',
            'before' => 'decimal:2',
            'after'  => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'operator_id');
    }

    /** 类型文案映射 */
    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'recharge'     => '后台充值',
            'consume'      => '消费扣减',
            'refund'       => '退款返还',
            'reward'       => '系统奖励',
            'admin_deduct' => '后台扣减',
            default        => $this->type,
        };
    }
}
