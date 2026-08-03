<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 风控事件记录
 */
class RiskEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'rule_code', 'type', 'action', 'risk_level',
        'context', 'ip', 'note', 'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];
}
