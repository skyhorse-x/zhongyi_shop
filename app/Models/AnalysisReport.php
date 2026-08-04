<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisReport extends Model
{
    public $timestamps = true;

    protected $fillable = [
        'task_id',
        'user_id',
        'type',
        'health_score',
        'tongue_color',
        'tongue_shape',
        'tongue_coating',
        'sublingual_vein',
        'tongue_analysis',
        'face_color',
        'lip_color',
        'eye_analysis',
        'skin_analysis',
        'face_analysis',
        'constitution_type',
        'constitution_analysis',
        'life_advice',
        'diet_advice',
        'exercise_advice',
        'precautions',
        'summary',
        'content',
        'is_paid',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'health_score' => 'integer',
            'content' => 'array',
            'is_paid' => 'boolean',
            'viewed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(AnalysisTask::class, 'task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
