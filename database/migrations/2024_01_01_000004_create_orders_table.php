<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->unique()->comment('订单编号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('type', 20)->comment('类型:analysis分析 package次数包');
            $table->string('relation_id', 32)->comment('关联ID');
            $table->decimal('amount', 10, 2)->comment('金额');
            $table->string('pay_type', 20)->comment('支付方式:wechat alipay');
            $table->tinyInteger('status')->default(0)->comment('状态:0待支付 1已支付 2已取消 3已退款');
            $table->string('transaction_id', 64)->nullable()->comment('第三方支付流水号');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');

            // 外键约束
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
