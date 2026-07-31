<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('constitution_questions', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->comment('题目分类');
            $table->string('question', 200)->comment('题目内容');
            $table->string('type', 20)->default('single')->comment('题目类型');
            $table->json('options')->comment('选项');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->tinyInteger('is_enabled')->default(1)->comment('是否启用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constitution_questions');
    }
};
