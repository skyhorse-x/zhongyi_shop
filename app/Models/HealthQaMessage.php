<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthQaMessage extends Model
{
    protected $fillable = [
        'session_id',
        'role',
        'content',
        'tokens',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(HealthQaSession::class);
    }
}
