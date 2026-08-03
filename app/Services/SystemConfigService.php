<?php

namespace App\Services;

use App\Models\SystemConfig;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SystemConfigService
{
    /**
     * 需要加密存储的配置键
     */
    protected static array $encryptedKeys = [
        'llm_api_key',
        'wechat_secret',
        'wechat_pay_key',
    ];

    /**
     * 获取配置值（自动解密 + 走 Redis 缓存）
     */
    public static function get(string $key, $default = null)
    {
        $value = CacheService::namespace('sys:config')->get($key, null);
        if ($value !== null) {
            return $value;
        }

        $config = SystemConfig::where('key', $key)->first();
        if (!$config) {
            return $default;
        }

        $value = $config->value;

        // 解密敏感配置
        if (in_array($key, self::$encryptedKeys) && !empty($value)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Exception $e) {
                Log::error("配置解密失败: {$key}");
                return $default;
            }
        }

        // 缓存到 Redis（敏感配置不缓存明文，缓存空串占位避免重复查 DB）
        CacheService::namespace('sys:config')->put(
            $key,
            in_array($key, self::$encryptedKeys) ? '__ENCRYPTED__' : $value
        );

        return $value;
    }

    /**
     * 设置配置值（自动加密 + 失效缓存）
     */
    public static function set(string $key, $value): void
    {
        $isEncrypted = in_array($key, self::$encryptedKeys) && !empty($value);
        if ($isEncrypted) {
            $value = Crypt::encryptString($value);
        }

        SystemConfig::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        // 失效缓存
        CacheService::namespace('sys:config')->forget($key);
    }

    /**
     * 批量设置配置
     */
    public static function setBatch(array $configs): void
    {
        foreach ($configs as $key => $value) {
            self::set($key, $value);
        }
    }

    /**
     * 获取分组配置（带缓存）
     */
    public static function getByGroup(string $group): array
    {
        $cacheKey = "group:{$group}";
        $cached = CacheService::namespace('sys:config')->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $configs = SystemConfig::where('group_name', $group)->get();
        $result = [];

        foreach ($configs as $config) {
            $value = $config->value;

            if (in_array($config->key, self::$encryptedKeys) && !empty($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    $value = '';
                }
            }

            $result[$config->key] = $value;
        }

        CacheService::namespace('sys:config')->put($cacheKey, $result);
        return $result;
    }

    /**
     * 获取所有配置（按分组）- 不缓存（管理后台用）
     */
    public static function getAllGrouped(): array
    {
        $configs = SystemConfig::all();
        $grouped = [];

        foreach ($configs as $config) {
            $value = $config->value;

            if (in_array($config->key, self::$encryptedKeys) && !empty($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    $value = '';
                }
            }

            $grouped[$config->group_name][] = [
                'key' => $config->key,
                'value' => $value,
                'name' => $config->name,
                'type' => $config->type,
                'remark' => $config->remark,
            ];
        }

        return $grouped;
    }

    /**
     * 失效整个 sys:config 命名空间
     */
    public static function flush(): void
    {
        CacheService::namespace('sys:config')->flush();
    }
}
