<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique()->comment('配置键');
            $table->text('value')->nullable()->comment('配置值');
            $table->string('name', 100)->nullable()->comment('配置名称');
            $table->string('group_name', 50)->nullable()->comment('配置分组');
            $table->string('type', 20)->default('text')->comment('类型:text number select json');
            $table->string('remark', 500)->nullable()->comment('备注');
            $table->timestamps();
            $table->index('group_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
