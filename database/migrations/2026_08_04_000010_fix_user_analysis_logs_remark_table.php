<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 修复 user_analysis_logs 表的 remark 字段长度
        if (Schema::hasTable('user_analysis_logs')) {
            Schema::table('user_analysis_logs', function (Blueprint $table) {
                $table->string('remark', 500)->nullable()->change();
            });
        }

        // 修复 ai_logs 表的 model_id 字段允许为空
        if (Schema::hasTable('ai_logs')) {
            Schema::table('ai_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('model_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_analysis_logs')) {
            Schema::table('user_analysis_logs', function (Blueprint $table) {
                $table->string('remark', 255)->nullable()->change();
            });
        }

        if (Schema::hasTable('ai_logs')) {
            Schema::table('ai_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('model_id')->nullable(false)->change();
            });
        }
    }
};
