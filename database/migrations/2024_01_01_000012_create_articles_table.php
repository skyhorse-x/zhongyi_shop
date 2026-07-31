<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200)->comment('标题');
            $table->string('cover', 500)->nullable()->comment('封面图');
            $table->string('summary', 500)->nullable()->comment('摘要');
            $table->longText('content')->comment('内容');
            $table->string('category', 50)->nullable()->comment('分类');
            $table->integer('views')->default(0)->comment('浏览量');
            $table->tinyInteger('status')->default(1)->comment('状态:1发布 0草稿');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
            
            $table->index('status');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
