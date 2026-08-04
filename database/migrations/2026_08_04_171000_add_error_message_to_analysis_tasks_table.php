<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_tasks', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('status')->comment('错误信息');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_tasks', function (Blueprint $table) {
            $table->dropColumn('error_message');
        });
    }
};
