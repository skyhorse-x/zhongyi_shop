<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiModel extends Model
{
    protected $table = 'ai_models';
    protected $fillable = ['name', 'provider', 'model', 'api_url', 'api_key', 'type', 'analysis_type', 'prompt', 'tokens_price', 'timeout', 'retry_times', 'is_enabled', 'sort_order'];
}
