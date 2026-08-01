<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 给 users 加「注册赠送标记」字段 + 默认配置
     * 用于防止同一用户被重复赠送注册奖励
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'user_registered_granted')) {
                $table->boolean('user_registered_granted')
                    ->default(false)
                    ->after('user_type')
                    ->comment('是否已发放过注册试用次数（防重复）');
            }
        });

        // 默认配置：免费用户基础试用次数 = 3
        if (!\App\Models\SystemConfig::where('key', 'user_free_analysis_times')->exists()) {
            \App\Models\SystemConfig::create([
                'key'   => 'user_free_analysis_times',
                'value' => '3',
                'description' => '免费用户注册时获得的基础分析次数（0 = 不赠送）',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'user_registered_granted')) {
                $table->dropColumn('user_registered_granted');
            }
        });
        \App\Models\SystemConfig::where('key', 'user_free_analysis_times')->delete();
    }
};
