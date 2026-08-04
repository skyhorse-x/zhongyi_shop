<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 退款流水表：记录每笔退款的详细信息
     */
    public function up(): void
    {
        Schema::create('refund_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->comment('关联订单ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->unsignedBigInteger('refund_id')->comment('退款单ID');
            $table->string('order_no', 64)->comment('订单号');
            $table->string('refund_no', 64)->comment('退款单号');
            $table->string('transaction_id', 128)->nullable()->comment('原支付渠道交易号');
            $table->string('channel_refund_id', 128)->nullable()->comment('渠道退款号');
            $table->string('pay_type', 20)->comment('原支付渠道：wechat/alipay/balance');
            $table->decimal('order_amount', 10, 2)->comment('订单金额');
            $table->decimal('refund_amount', 10, 2)->comment('退款金额');
            $table->string('reason', 500)->nullable()->comment('退款原因');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->tinyInteger('status')->default(0)->comment('状态：0=待审核 1=已批准 2=已拒绝 3=退款中 4=退款成功 5=退款失败');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人ID');
            $table->json('request_data')->nullable()->comment('退款请求参数');
            $table->json('response_data')->nullable()->comment('渠道响应');
            $table->string('error_message', 500)->nullable()->comment('错误信息');
            $table->timestamp('refunded_at')->nullable()->comment('退款完成时间');
            $table->timestamps();

            // 索引
            $table->index('order_id');
            $table->index('user_id');
            $table->index('refund_id');
            $table->index('order_no');
            $table->index('refund_no');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refund_logs');
    }
};
