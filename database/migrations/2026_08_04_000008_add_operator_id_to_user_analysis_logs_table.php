<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_analysis_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('user_analysis_logs', 'operator_id')) {
                $table->foreignId('operator_id')->nullable()->after('type')->constrained('admins')->comment('操作管理员ID');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_analysis_logs', function (Blueprint $table) {
            if (Schema::hasColumn('user_analysis_logs', 'operator_id')) {
                $table->dropForeign(['operator_id']);
                $table->dropColumn('operator_id');
            }
        });
    }
};
