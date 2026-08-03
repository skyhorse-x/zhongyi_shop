<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 黑名单
 */
class RiskBlacklist extends Model
{
    protected $fillable = [
        'type', 'value', 'reason', 'created_by', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
