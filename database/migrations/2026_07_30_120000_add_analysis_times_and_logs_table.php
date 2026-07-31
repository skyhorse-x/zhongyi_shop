<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 用户表：剩余分析次数
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'analysis_times')) {
                $table->integer('analysis_times')->default(0)->after('is_promoter')->comment('剩余分析次数');
            }
        });

        // 2. 分析次数流水表（审计 + 统计）
        Schema::create('user_analysis_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('用户');
            $table->integer('change')->comment('变动次数：+ 增加 / - 减少');
            $table->integer('before')->comment('变动前');
            $table->integer('after')->comment('变动后');
            $table->string('type', 30)->comment('类型：purchase/use/refund/reward/admin');
            $table->string('remark', 200)->default('')->comment('备注（订单号/分析类型等）');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_analysis_logs');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'analysis_times')) {
                $table->dropColumn('analysis_times');
            }
        });
    }
};
