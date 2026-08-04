<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 支付交易流水表：记录每笔支付渠道的交易详情
     */
    public function up(): void
    {
        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('关联订单ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('order_no', 64)->comment('订单号');
            $table->string('transaction_id', 128)->nullable()->comment('渠道交易号');
            $table->string('refund_no', 64)->nullable()->comment('退款单号');
            $table->string('pay_type', 20)->comment('支付渠道：wechat/alipay/balance');
            $table->decimal('amount', 10, 2)->comment('交易金额');
            $table->tinyInteger('action')->default(0)->comment('类型：0=支付 1=退款');
            $table->tinyInteger('status')->default(0)->comment('状态：0=待处理 1=成功 2=失败 3=处理中');
            $table->json('request_data')->nullable()->comment('请求参数');
            $table->json('response_data')->nullable()->comment('渠道响应');
            $table->string('error_message', 500)->nullable()->comment('错误信息');
            $table->timestamp('paid_at')->nullable()->comment('支付/退款完成时间');
            $table->timestamps();

            // 索引
            $table->index('order_id');
            $table->index('user_id');
            $table->index('order_no');
            $table->index('transaction_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
    }
};
