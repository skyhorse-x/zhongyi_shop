<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 客服会话表增加评价相关字段
     */
    public function up(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_service_sessions', 'rated')) {
                $table->boolean('rated')->default(false)->after('closed_at')->comment('是否已评价');
            }
            if (!Schema::hasColumn('customer_service_sessions', 'satisfaction_score')) {
                $table->unsignedTinyInteger('satisfaction_score')->nullable()->after('rated')->comment('满意度评分(1-5)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('customer_service_sessions', 'satisfaction_score')) {
                $table->dropColumn('satisfaction_score');
            }
            if (Schema::hasColumn('customer_service_sessions', 'rated')) {
                $table->dropColumn('rated');
            }
        });
    }
};
