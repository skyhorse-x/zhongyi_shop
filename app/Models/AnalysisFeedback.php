<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisFeedback extends Model
{
    protected $table = 'analysis_feedback';

    protected $fillable = [
        'user_id',
        'task_id',
        'type',
        'rating',
    ];

    /**
     * 关联用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联分析任务
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(AnalysisTask::class);
    }
}
