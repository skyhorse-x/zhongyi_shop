<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 退款单表 + 邀请关系锁定字段
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_no', 64)->unique()->comment('退款单号');
            $table->string('order_no', 64)->comment('原订单号');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2)->comment('退款金额');
            $table->decimal('refund_amount', 10, 2)->comment('实际退款金额（可部分退款）');
            $table->enum('reason', ['user_request', 'admin_refund', 'order_timeout', 'service_failure', 'duplicate_payment', 'other'])->comment('退款原因');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'cancelled'])->default('pending');
            $table->enum('pay_type', ['wechat', 'alipay', 'balance'])->nullable();
            $table->string('transaction_id', 128)->nullable()->comment('支付流水号');
            $table->string('refund_transaction_id', 128)->nullable()->comment('退款流水号');
            $table->json('response')->nullable()->comment('渠道返回');
            $table->text('admin_note')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('order_no');
        });

        // 邀请关系锁定字段
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('parent_locked')->default(false)->after('parent_id')->comment('邀请关系是否锁定');
            $table->timestamp('parent_locked_at')->nullable()->after('parent_locked');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['parent_locked', 'parent_locked_at']);
        });
        Schema::dropIfExists('refunds');
    }
};
