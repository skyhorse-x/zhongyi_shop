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
     * 获取配置值（自动解密）
     */
    public static function get(string $key, $default = null)
    {
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

        return $value;
    }

    /**
     * 设置配置值（自动加密）
     */
    public static function set(string $key, $value): void
    {
        // 敏感配置加密存储
        if (in_array($key, self::$encryptedKeys) && !empty($value)) {
            $value = Crypt::encryptString($value);
        }

        SystemConfig::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
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
     * 获取分组配置
     */
    public static function getByGroup(string $group): array
    {
        $configs = SystemConfig::where('group_name', $group)->get();
        $result = [];

        foreach ($configs as $config) {
            $value = $config->value;

            // 解密敏感配置
            if (in_array($config->key, self::$encryptedKeys) && !empty($value)) {
                try {
                    $value = Crypt::decryptString($value);
                } catch (\Exception $e) {
                    $value = '';
                }
            }

            $result[$config->key] = $value;
        }

        return $result;
    }

    /**
     * 获取所有配置（按分组）
     */
    public static function getAllGrouped(): array
    {
        $configs = SystemConfig::all();
        $grouped = [];

        foreach ($configs as $config) {
            $value = $config->value;

            // 解密敏感配置
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
}
