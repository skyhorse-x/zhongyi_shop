<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 用户余额：与 analysis_times 同模式（users 表存当前值 + 流水表存审计）
     */
    public function up(): void
    {
        // 1. users 表加 balance 字段
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'balance')) {
                $table->decimal('balance', 10, 2)->default(0)->after('analysis_times')->comment('账户余额（元）');
            }
        });

        // 2. 余额变动流水表（审计 + 统计 + 客服查询）
        Schema::create('user_balance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->comment('用户');
            $table->decimal('change', 10, 2)->comment('变动金额：+ 增加 / - 扣减');
            $table->decimal('before', 10, 2)->comment('变动前余额');
            $table->decimal('after', 10, 2)->comment('变动后余额');
            // 类型：recharge 后台充值 / consume 消费 / refund 退款 / reward 奖励 / admin_deduct 后台扣减
            $table->string('type', 30)->comment('类型：recharge/consume/refund/reward/admin_deduct');
            $table->string('remark', 200)->default('')->comment('备注（订单号/管理员ID等）');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID（管理员）');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_balance_logs');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'balance')) {
                $table->dropColumn('balance');
            }
        });
    }
};
