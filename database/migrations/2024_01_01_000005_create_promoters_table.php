<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promoters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique()->comment('用户ID');
            $table->string('invite_code', 20)->unique()->comment('推广码');
            $table->tinyInteger('level')->default(1)->comment('等级:1初级 2高级');
            $table->decimal('commission_rate', 5, 2)->default(15.00)->comment('佣金比例(%)');
            $table->tinyInteger('status')->default(1)->comment('状态:1正常 0禁用');
            $table->integer('total_invite')->default(0)->comment('累计邀请人数');
            $table->integer('total_consume')->default(0)->comment('累计消费人数');
            $table->decimal('total_commission', 12, 2)->default(0)->comment('累计佣金');
            $table->decimal('frozen_commission', 12, 2)->default(0)->comment('冻结佣金');
            $table->decimal('withdrawn_commission', 12, 2)->default(0)->comment('已提现佣金');
            $table->timestamp('activated_at')->nullable()->comment('开通时间');
            $table->timestamps();
            
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promoters');
    }
};
