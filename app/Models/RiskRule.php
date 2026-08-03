<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 风控规则
 */
class RiskRule extends Model
{
    protected $fillable = [
        'code', 'name', 'type', 'action', 'conditions',
        'priority', 'enabled', 'description',
    ];

    protected $casts = [
        'conditions' => 'array',
        'enabled' => 'boolean',
        'priority' => 'integer',
    ];
}
