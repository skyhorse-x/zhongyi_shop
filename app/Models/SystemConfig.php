<?php

namespace App\Models;

use App\Services\SystemConfigService;
use Illuminate\Database\Eloquent\Model;

/**
 * 系统配置 Eloquent 模型
 *
 * ⚠️ 业务代码应优先通过 SystemConfigService::get()/set() 读写配置
 *    该服务已集成文件缓存 + 敏感字段加密
 *
 * 本类仅保留纯 DB 操作（用于数据迁移、Seeder、Admin 后台等）
 */
class SystemConfig extends Model
{
    protected $fillable = ['key', 'value', 'name', 'group_name', 'type', 'remark'];

    /**
     * 获取配置值（已弃用，请使用 SystemConfigService::get）
     *
     * @deprecated 业务代码请使用 SystemConfigService::get($key)
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        return SystemConfigService::get($key, $default);
    }

    /**
     * 清除配置缓存（已弃用，请使用 SystemConfigService::flush）
     *
     * @deprecated 业务代码请使用 SystemConfigService::flush()
     */
    public static function clearCache(): void
    {
        SystemConfigService::flush();
    }
}
