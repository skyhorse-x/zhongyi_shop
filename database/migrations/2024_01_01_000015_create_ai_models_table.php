<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('模型名称');
            $table->string('provider', 50)->comment('提供商:doubao deepseek openai');
            $table->string('model', 50)->comment('模型标识');
            $table->string('api_url', 500)->comment('API地址');
            $table->string('api_key', 255)->comment('API密钥');
            $table->string('type', 20)->comment('类型:vision视觉 chat文本');
            $table->string('analysis_type', 50)->nullable()->comment('分析类型(JSON)');
            $table->text('prompt')->nullable()->comment('默认Prompt');
            $table->decimal('tokens_price', 10, 6)->default(0)->comment('每Token价格(元)');
            $table->integer('timeout')->default(30)->comment('超时时间(秒)');
            $table->tinyInteger('retry_times')->default(3)->comment('重试次数');
            $table->tinyInteger('is_enabled')->default(1)->comment('是否启用:0否 1是');
            $table->integer('sort_order')->default(0)->comment('排序(优先级)');
            $table->timestamps();
            $table->index('is_enabled');
            $table->index('type');
            $table->index('analysis_type');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_models');
    }
};
