<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table) {
            // 使 task_id 可为空（兼容不创建任务的直接分析）
            $table->unsignedBigInteger('task_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('task_id')->nullable(false)->change();
        });
    }
};
