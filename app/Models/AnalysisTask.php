<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AnalysisTask extends Model
{
    protected $fillable = [
        'task_no',
        'user_id',
        'type',
        'gender',
        'age',
        'image_url',
        'image_urls',
        'image_md5',
        'text',
        'status',
        'model',
        'prompt',
        'tokens',
        'cost',
        'result',
        'error_msg',
        'is_paid',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'gender' => 'integer',
            'age' => 'integer',
            'tokens' => 'integer',
            'cost' => 'decimal:4',
            'result' => 'array',
            'image_urls' => 'array',
            'is_paid' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): HasOne
    {
        return $this->hasOne(AnalysisReport::class);
    }
}
