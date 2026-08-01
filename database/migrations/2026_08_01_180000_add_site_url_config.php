<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemConfig;

return new class extends Migration
{
    /**
     * 站点域名（推广链接 / 分享链接统一使用此域名）
     * 默认留空，由 Site 静态类 fallback 到 .env FRONTEND_URL
     */
    public function up(): void
    {
        if (!SystemConfig::where('key', 'site_url')->exists()) {
            SystemConfig::create([
                'key'         => 'site_url',
                'value'       => env('FRONTEND_URL', 'http://localhost:5173'),
                'description' => '站点根域名（推广链接/分享链接前缀），需 http:// 或 https:// 开头，不带尾部斜杠',
            ]);
        }
    }

    public function down(): void
    {
        SystemConfig::where('key', 'site_url')->delete();
    }
};
