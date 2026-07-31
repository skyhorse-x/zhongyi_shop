<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->string('commission_no', 32)->unique()->comment('佣金编号');
            $table->unsignedBigInteger('promoter_id')->comment('推广员ID');
            $table->unsignedBigInteger('user_id')->comment('消费用户ID');
            $table->unsignedBigInteger('order_id')->comment('订单ID');
            $table->decimal('amount', 10, 2)->comment('佣金金额');
            $table->decimal('rate', 5, 2)->comment('佣金比例');
            $table->tinyInteger('status')->default(1)->comment('状态:1有效 0无效');
            $table->timestamps();
            
            $table->index('promoter_id');
            $table->index('user_id');

            // 外键约束
            $table->foreign('promoter_id')->references('id')->on('promoters')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
