<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 给 commissions 表加 withdraw_id 字段，精确关联到提现记录
     * 解决提现审核时按金额匹配冻结佣金可能误标的问题
     */
    public function up(): void
    {
        if (!Schema::hasColumn('commissions', 'withdraw_id')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->unsignedBigInteger('withdraw_id')->nullable()->after('order_id')->comment('关联的提现记录ID');
                $table->index('withdraw_id');
            });
        }

        // 统一 status 语义：0=冻结/待结算 1=已结算 2=已撤销
        // 注意：原 migration 中默认 1 与代码中 1=已结算 不冲突，但需保留 0/1/2 语义
    }

    public function down(): void
    {
        if (Schema::hasColumn('commissions', 'withdraw_id')) {
            Schema::table('commissions', function (Blueprint $table) {
                $table->dropIndex(['withdraw_id']);
                $table->dropColumn('withdraw_id');
            });
        }
    }
};
