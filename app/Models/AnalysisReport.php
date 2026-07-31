<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisReport extends Model
{
    protected $fillable = [
        'task_id',
        'user_id',
        'type',
        'health_score',
        'tongue_color',
        'tongue_shape',
        'tongue_coating',
        'face_color',
        'lip_color',
        'eye_condition',
        'skin_condition',
        'summary',
        'detail',
    ];

    protected function casts(): array
    {
        return [
            'health_score' => 'integer',
            'detail' => 'array',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AnalysisTask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
