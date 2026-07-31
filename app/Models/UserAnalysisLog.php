<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 用户分析次数变动流水
 *
 * 记录每次分析次数的增加/减少，用于审计、统计、客诉处理
 */
class UserAnalysisLog extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'change',
        'before',
        'after',
        'type',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'change' => 'integer',
            'before' => 'integer',
            'after'  => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
