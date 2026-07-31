<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraws', function (Blueprint $table) {
            $table->id();
            $table->string('withdraw_no', 32)->unique()->comment('提现编号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('promoter_id')->comment('推广员ID');
            $table->decimal('amount', 10, 2)->comment('提现金额');
            $table->string('pay_type', 20)->comment('收款方式');
            $table->string('pay_account', 100)->comment('收款账号');
            $table->tinyInteger('status')->default(0)->comment('状态:0待审核 1已通过 2已拒绝 3已打款');
            $table->string('remark', 500)->nullable()->comment('用户备注');
            $table->string('audit_remark', 500)->nullable()->comment('审核备注');
            $table->timestamp('audited_at')->nullable()->comment('审核时间');
            $table->timestamp('paid_at')->nullable()->comment('打款时间');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');

            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('promoter_id')->references('id')->on('promoters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraws');
    }
};
