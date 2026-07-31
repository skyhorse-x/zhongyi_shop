<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'commission_no',
        'promoter_id',
        'user_id',
        'order_id',
        'amount',
        'rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:2',
            'status' => 'integer',
        ];
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(Promoter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
