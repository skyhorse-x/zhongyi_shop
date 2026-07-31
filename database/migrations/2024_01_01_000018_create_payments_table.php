<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->comment('订单编号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('pay_type', 20)->comment('支付方式');
            $table->decimal('amount', 10, 2)->comment('支付金额');
            $table->tinyInteger('status')->default(0)->comment('状态:0待支付 1支付中 2成功 3失败');
            $table->string('trade_no', 100)->nullable()->comment('第三方流水号');
            $table->json('pay_response')->nullable()->comment('支付响应');
            $table->json('notify_response')->nullable()->comment('回调响应');
            $table->dateTime('paid_at')->nullable()->comment('支付完成时间');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('order_no');
            $table->index('user_id');
            $table->index('trade_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
