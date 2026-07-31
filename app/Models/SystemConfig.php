<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemConfig extends Model
{
    protected $fillable = ['key', 'value', 'name', 'group_name', 'type', 'remark'];

    /**
     * 获取配置值
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = 'system_config_' . $key;
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $config = self::where('key', $key)->first();
            return $config ? $config->value : $default;
        });
    }

    /**
     * 清除配置缓存
     */
    public static function clearCache(): void
    {
        $configs = self::all();
        foreach ($configs as $config) {
            Cache::forget('system_config_' . $config->key);
        }
    }
}
