<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 客服话术表增加触发类型和关键词字段
     */
    public function up(): void
    {
        Schema::table('customer_service_phrases', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_service_phrases', 'trigger_type')) {
                $table->string('trigger_type', 20)->default('manual')->after('is_auto_reply')->comment('触发类型:keyword关键词触发 manual手动触发');
            }
            if (!Schema::hasColumn('customer_service_phrases', 'keywords')) {
                $table->text('keywords')->nullable()->after('trigger_type')->comment('触发关键词(多个用逗号分隔)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_phrases', function (Blueprint $table) {
            if (Schema::hasColumn('customer_service_phrases', 'keywords')) {
                $table->dropColumn('keywords');
            }
            if (Schema::hasColumn('customer_service_phrases', 'trigger_type')) {
                $table->dropColumn('trigger_type');
            }
        });
    }
};
