<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户分析次数变动流水
 *
 * 记录每次分析次数的增加/减少，用于审计、统计、客诉处理
 */
class UserAnalysisLog extends Model
{
    public $timestamps = true;

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
            'change' => 'integer',
            'before' => 'integer',
            'after'  => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Admin::class, 'operator_id');
    }

    /** 类型文案映射（用户友好名称） */
    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            'recharge'     => '官方充值',
            'use'          => '分析消费',
            'refund'       => '退款返还',
            'reward'       => '系统奖励',
            'admin_deduct' => '系统扣减',
            'register_grant' => '注册赠送',
            'purchase'     => '购买',
            default        => $this->type,
        };
    }

    /** 后台显示用的类型文案（管理员可见） */
    public function getAdminTypeNameAttribute(): string
    {
        return match ($this->type) {
            'recharge'     => '后台充值',
            'use'          => '分析消费',
            'refund'       => '退款返还',
            'reward'       => '系统奖励',
            'admin_deduct' => '后台扣减',
            'register_grant' => '注册赠送',
            'purchase'     => '购买',
            default        => $this->type,
        };
    }
}
