<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 管理员操作日志
 */
class AdminOperationLog extends Model
{
    protected $fillable = [
        'admin_id',
        'admin_name',
        'module',
        'action',
        'method',
        'url',
        'params',
        'ip',
        'user_agent',
        'response_code',
        'response_data',
        'duration_ms',
        'status',
        'error_message',
    ];

    protected $casts = [
        'params' => 'array',
        'response_data' => 'array',
        'status' => 'integer',
        'duration_ms' => 'integer',
        'response_code' => 'integer',
    ];

    /**
     * 关联管理员
     */
    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * 作用域：按模块筛选
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * 作用域：按管理员筛选
     */
    public function scopeByAdmin($query, int $adminId)
    {
        return $query->where('admin_id', $adminId);
    }

    /**
     * 作用域：按时间范围筛选
     */
    public function scopeBetweenDates($query, string $start, string $end)
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * 作用域：按状态筛选
     */
    public function scopeByStatus($query, int $status)
    {
        return $query->where('status', $status);
    }
}
