<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xianyu_products', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->comment('商品名称');
            $table->string('link', 500)->comment('闲鱼商品链接');
            $table->decimal('amount', 10, 2)->default(0)->comment('售价(元)');
            $table->integer('times')->default(0)->comment('赠送分析次数');
            $table->string('description', 255)->nullable()->comment('商品说明');
            $table->integer('sort_order')->default(0)->comment('排序权重');
            $table->tinyInteger('is_enabled')->default(1)->comment('是否启用:0否 1是');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xianyu_products');
    }
};
