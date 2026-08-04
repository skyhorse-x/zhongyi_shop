<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 退款流水
 *
 * 记录每笔退款的详细信息
 */
class RefundLog extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'refund_id',
        'order_no',
        'refund_no',
        'transaction_id',
        'channel_refund_id',
        'pay_type',
        'order_amount',
        'refund_amount',
        'reason',
        'remark',
        'status',
        'operator_id',
        'request_data',
        'response_data',
        'error_message',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'order_amount'  => 'decimal:2',
            'refund_amount' => 'decimal:2',
            'status'        => 'integer',
            'request_data'  => 'array',
            'response_data' => 'array',
            'refunded_at'   => 'datetime',
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

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'operator_id');
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

    /** 状态文案 */
    public function getStatusNameAttribute(): string
    {
        return match ($this->status) {
            0 => '待审核',
            1 => '已批准',
            2 => '已拒绝',
            3 => '退款中',
            4 => '退款成功',
            5 => '退款失败',
            default => '未知',
        };
    }

    /** 状态颜色 */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            0 => 'warning',
            1 => 'primary',
            2 => 'danger',
            3 => 'info',
            4 => 'success',
            5 => 'danger',
            default => 'info',
        };
    }
}
