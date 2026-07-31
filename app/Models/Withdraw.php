<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdraw extends Model
{
    protected $fillable = [
        'withdraw_no',
        'user_id',
        'promoter_id',
        'amount',
        'pay_type',
        'pay_account',
        'status',
        'remark',
        'audit_remark',
        'audited_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => 'integer',
            'audited_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }
}
