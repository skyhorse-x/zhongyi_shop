<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 补充 ai_logs 表的 request/response/duration 字段
     * 修复：AI 接口调用日志写入失败导致 500
     */
    public function up(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            // 长文本字段，使用 longText 兼容不同数据库
            if (!Schema::hasColumn('ai_logs', 'request')) {
                $table->longText('request')->nullable()->after('type')->comment('请求体 JSON');
            }
            if (!Schema::hasColumn('ai_logs', 'response')) {
                $table->longText('response')->nullable()->after('request')->comment('响应体 JSON');
            }
            if (!Schema::hasColumn('ai_logs', 'duration')) {
                $table->integer('duration')->default(0)->after('response')->comment('调用耗时(ms)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_logs', function (Blueprint $table) {
            if (Schema::hasColumn('ai_logs', 'request')) {
                $table->dropColumn('request');
            }
            if (Schema::hasColumn('ai_logs', 'response')) {
                $table->dropColumn('response');
            }
            if (Schema::hasColumn('ai_logs', 'duration')) {
                $table->dropColumn('duration');
            }
        });
    }
};
