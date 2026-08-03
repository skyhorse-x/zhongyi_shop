<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 风控规则定义表
 * 存储可动态配置的风控规则（如：1 小时内同 IP 注册超过 5 次则拒绝）
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('risk_rules', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->comment('规则唯一编码')->unique();
            $table->string('name')->comment('规则名称');
            $table->enum('type', ['register', 'login', 'payment', 'promotion', 'analysis', 'withdraw'])
                ->comment('适用场景');
            $table->enum('action', ['allow', 'deny', 'review'])->default('deny')
                ->comment('命中后动作：放行/拒绝/人工审核');
            $table->json('conditions')->comment('JSON 条件，如 {"window":3600,"max_count":5,"dimension":"ip"}');
            $table->tinyInteger('priority')->default(100)->comment('优先级，越小越先执行');
            $table->boolean('enabled')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['type', 'enabled']);
        });

        // 风控事件表 - 记录每次风控检查的命中情况
        Schema::create('risk_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rule_code', 64)->nullable()->comment('命中的规则 code');
            $table->enum('type', ['register', 'login', 'payment', 'promotion', 'analysis', 'withdraw']);
            $table->enum('action', ['allow', 'deny', 'review']);
            $table->enum('risk_level', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->json('context')->comment('触发上下文：IP、UA、金额等');
            $table->ipAddress('ip')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index('rule_code');
        });

        // 黑名单表 - 永久封禁的 IP / 手机号 / 设备
        Schema::create('risk_blacklists', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['ip', 'mobile', 'device', 'user_id'])->comment('黑名单类型');
            $table->string('value', 128)->comment('具体值');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->comment('到期时间，null=永久');
            $table->timestamps();

            $table->unique(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_blacklists');
        Schema::dropIfExists('risk_events');
        Schema::dropIfExists('risk_rules');
    }
};
