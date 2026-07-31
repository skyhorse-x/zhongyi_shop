<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promoter extends Model
{
    protected $fillable = [
        'user_id',
        'invite_code',
        'level',
        'commission_rate',
        'status',
        'total_invite',
        'total_consume',
        'total_commission',
        'frozen_commission',
        'withdrawn_commission',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'commission_rate' => 'decimal:2',
            'status' => 'integer',
            'total_invite' => 'integer',
            'total_consume' => 'integer',
            'total_commission' => 'decimal:2',
            'frozen_commission' => 'decimal:2',
            'withdrawn_commission' => 'decimal:2',
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
