<?php

namespace App\Support;

use App\Models\SystemConfig;

/**
 * 站点配置解析（推广链接 / 分享链接统一入口）
 *
 * 优先级：
 * 1. SystemConfig 表 site_url（后台【系统设置 → 基本设置 → 网站域名】）
 * 2. config('site.url')（即 .env SITE_URL，部署期固定）
 * 3. http://localhost:5173（开发默认）
 *
 * 自动去掉尾部斜杠，避免拼接出 // 路径
 *
 * 注意：本类不使用 env() 直接读取，避免 config:cache 后返回 null
 */
final class Site
{
    public const DEFAULT_URL = 'http://localhost:5173';
    public const CONFIG_KEY  = 'site_url';

    /**
     * 当前站点根 URL（不带尾部斜杠）
     */
    public static function url(): string
    {
        // 1. 数据库配置（后台可改）
        $fromDb = SystemConfig::getValue(self::CONFIG_KEY, '');
        if ($fromDb !== '') {
            return rtrim(trim($fromDb), '/');
        }

        // 2. config() 读取（config/site.php 中定义）
        $fromConfig = config('site.url', '');
        if (!empty($fromConfig)) {
            return rtrim(trim($fromConfig), '/');
        }

        // 3. 默认值
        return self::DEFAULT_URL;
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
