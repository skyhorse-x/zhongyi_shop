<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 支付交易流水
 *
 * 记录每笔支付渠道的交易详情，包括支付和退款
 */
class PaymentLog extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'order_no',
        'transaction_id',
        'refund_no',
        'pay_type',
        'amount',
        'action',
        'status',
        'request_data',
        'response_data',
        'error_message',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'        => 'decimal:2',
            'action'        => 'integer',
            'status'        => 'integer',
            'request_data'  => 'array',
            'response_data' => 'array',
            'paid_at'       => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** 支付渠道文案 */
    public function getPayTypeNameAttribute(): string
    {
        return match ($this->pay_type) {
            'wechat'  => '微信支付',
            'alipay'  => '支付宝',
            'balance' => '余额支付',
            default   => $this->pay_type,
        };
    }

    /** 操作类型文案 */
    public function getActionNameAttribute(): string
    {
        return match ($this->action) {
            0 => '支付',
            1 => '退款',
            default => '未知',
        };
    }

    /** 状态文案 */
    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            0 => '待处理',
            1 => '成功',
            2 => '失败',
            3 => '处理中',
            default => '未知',
        };
    }

    /** 状态颜色 */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            0 => 'warning',
            1 => 'success',
            2 => 'danger',
            3 => 'info',
            default => 'info',
        };
    }
}
