<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_qa_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->comment('会话ID');
            $table->string('role', 20)->comment('角色:user用户 assistantAI');
            $table->text('content')->comment('消息内容');
            $table->integer('tokens')->default(0)->comment('Token消耗');
            $table->timestamps();
            
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_qa_messages');
    }
};
