<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $fillable = [
        'refund_no', 'order_no', 'user_id', 'amount', 'refund_amount',
        'reason', 'description', 'status', 'pay_type', 'transaction_id',
        'refund_transaction_id', 'response', 'admin_note', 'processed_by',
        'processed_at', 'refunded_at',
    ];

    protected $casts = [
        'response' => 'array',
        'processed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_no', 'order_no');
    }

    public function processor()
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }

    public function isDone(): bool
    {
        return in_array($this->status, ['success', 'failed', 'cancelled'], true);
    }
}
