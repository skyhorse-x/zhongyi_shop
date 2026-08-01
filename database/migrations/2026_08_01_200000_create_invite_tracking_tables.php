<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 推广点击 / 注册记录表
     * 用于追踪邀请链接的访问、注册，以及反作弊
     */
    public function up(): void
    {
        Schema::create('invite_clicks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promoter_id')->comment('推广员 ID');
            $table->string('invite_code', 20)->comment('邀请码');
            $table->ipAddress('ip')->comment('访问者 IP');
            $table->string('user_agent', 500)->comment('User-Agent');
            $table->string('device_type', 20)->default('unknown')->comment('设备类型: mobile/desktop/tablet/unknown');
            $table->string('device_model', 100)->default('')->comment('设备型号');
            $table->string('browser', 100)->default('')->comment('浏览器');
            $table->string('os', 50)->default('default')->default('')->comment('操作系统');
            $table->boolean('is_duplicate_ip')->default(false)->comment('是否重复 IP（同一 IP 多次点击）');
            $table->boolean('is_suspicious')->default(false)->comment('是否可疑（UA 异常、机器人等）');
            $table->string('fingerprint', 64)->default('')->comment('浏览器指纹（哈希）');
            $table->timestamp('clicked_at');
            $table->index(['promoter_id', 'clicked_at']);
            $table->index(['ip', 'invite_code']);
        });

        Schema::create('invite_registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promoter_id')->comment('推广员 ID');
            $table->unsignedBigInteger('user_id')->comment('注册用户 ID');
            $table->string('invite_code', 20)->comment('邀请码');
            $table->ipAddress('ip')->comment('注册 IP');
            $table->string('user_agent', 500)->comment('注册时 UA');
            $table->string('device_type', 20)->comment('设备类型');
            $table->string('device_model', 100)->default('')->comment('设备型号');
            $table->string('browser', 100)->default('')->comment('浏览器');
            $table->string('os', 50)->default('')->comment('操作系统');
            $table->string('fingerprint', 64)->default('')->comment('指纹');
            $table->boolean('is_fraud')->default(false)->comment('是否被标记为作弊');
            $table->string('fraud_reason', 200)->default('')->comment('作弊原因');
            $table->unsignedTinyInteger('risk_score')->default(0)->comment('风险分数 0-100');
            $table->timestamps();

            $table->unique(['user_id']);
            $table->index(['promoter_id', 'created_at']);
            $table->index(['ip', 'invite_code']);
            $table->index(['is_fraud', 'created_at']);
        });

        // 推广员增加反作弊相关字段
        Schema::table('promoters', function (Blueprint $table) {
            $table->unsignedInteger('fraud_count')->default(0)->comment('被标记作弊次数');
            $table->boolean('is_banned')->default(false)->comment('是否被禁止推广');
            $table->timestamp('banned_at')->nullable()->comment('封禁时间');
        });

        // 默认反作弊配置
        if (!\App\Models\SystemConfig::where('key', 'invite_fraud_rules')->exists()) {
            \App\Models\SystemConfig::create([
                'key'         => 'invite_fraud_rules',
                'value'   => json_encode([
                    'max_per_ip_per_day'      => 5,        // 同一 IP 每日最多有效邀请
                    'min_ua_length'           => 10,       // UA 最短长度（太短是机器人）
                    'bot_keywords'            => ['bot', 'crawler', 'spider', 'curl', 'wget', 'python', 'java'],
                    'risk_score_threshold'    => 60,       // 风险分 >= 这个值标记作弊
                ]),
                'description' => '推广反作弊规则（JSON）',
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invite_clicks');
        Schema::dropIfExists('invite_registrations');
        Schema::table('promoters', function (Blueprint $table) {
            $table->dropColumn(['fraud_count', 'is_banned', 'banned_at']);
        });
        \App\Models\SystemConfig::where('key', 'invite_fraud_rules')->delete();
    }
};
