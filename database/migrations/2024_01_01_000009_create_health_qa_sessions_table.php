<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_qa_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_no', 32)->unique()->comment('会话编号');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('title', 100)->default('新问答')->comment('会话标题');
            $table->tinyInteger('status')->default(1)->comment('状态:1进行中 0已结束');
            $table->timestamps();
            
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_qa_sessions');
    }
};
