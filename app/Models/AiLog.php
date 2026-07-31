<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiLog extends Model
{
    protected $table = 'ai_logs';
    public $timestamps = false;
    protected $fillable = ['model_id', 'user_id', 'task_id', 'type', 'prompt_tokens', 'completion_tokens', 'total_tokens', 'cost', 'response_time', 'status', 'error', 'request', 'response', 'duration'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
