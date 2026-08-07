<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiLog extends Model
{
    public $timestamps = false;

    protected $table = 'api_logs';

    protected $fillable = [
        'method',
        'url',
        'route_name',
        'module',
        'request_headers',
        'request_params',
        'request_body',
        'response_status',
        'response_headers',
        'response_body',
        'success',
        'error_message',
        'duration_ms',
        'ip',
        'user_agent',
        'user_id',
        'user_type',
        'token',
        'requested_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'duration_ms' => 'integer',
        'response_status' => 'integer',
        'user_id' => 'integer',
        'requested_at' => 'datetime',
        'request_headers' => 'array',
        'request_params' => 'array',
        'response_headers' => 'array',
    ];
}
