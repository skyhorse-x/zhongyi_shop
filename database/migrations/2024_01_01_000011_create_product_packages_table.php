<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('套餐名称');
            $table->string('type', 20)->comment('类型:tongue舌诊 face面诊 all全部');
            $table->integer('times')->comment('次数');
            $table->integer('days')->comment('有效期(天)');
            $table->decimal('price', 10, 2)->comment('价格');
            $table->decimal('original_price', 10, 2)->default(0)->comment('原价');
            $table->tinyInteger('is_recommend')->default(0)->comment('是否推荐');
            $table->tinyInteger('is_enabled')->default(1)->comment('是否启用');
            $table->integer('sort_order')->default(0)->comment('排序');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_packages');
    }
};
