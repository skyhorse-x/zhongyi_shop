<?php

namespace App\Support;

use App\Models\SystemConfig;

/**
 * 站点配置解析（推广链接 / 分享链接统一入口）
 *
 * 优先级：
 * 1. SystemConfig 表 site_url（后台【系统设置 → 基本设置 → 网站域名】）
 * 2. .env FRONTEND_URL（部署期固定）
 * 3. http://localhost:5173（开发默认）
 *
 * 自动去掉尾部斜杠，避免拼接出 // 路径
 */
final class Site
{
    public const DEFAULT_URL = 'http://localhost:5173';
    public const CONFIG_KEY  = 'site_url';
    public const ENV_KEY     = 'FRONTEND_URL';

    /**
     * 当前站点根 URL（不带尾部斜杠）
     */
    public static function url(): string
    {
        $fromDb   = SystemConfig::getValue(self::CONFIG_KEY, '');
        $fromEnv  = env(self::ENV_KEY, '');

        $raw = $fromDb !== '' ? $fromDb : ($fromEnv !== '' ? $fromEnv : self::DEFAULT_URL);

        return rtrim(trim($raw), '/');
    }

    /**
     * 推广员邀请链接：?code=xxx
     */
    public static function inviteLink(string $inviteCode): string
    {
        return self::url() . '?code=' . rawurlencode($inviteCode);
    }

    /**
     * 文章/海报/任意 path 的分享链接
     */
    public static function shareLink(string $path = '/'): string
    {
        $base = self::url();
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}
