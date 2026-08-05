<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 检查是否存在 error_msg 旧字段，如果存在则重命名为 error_message
        if (Schema::hasColumn('analysis_tasks', 'error_msg') && !Schema::hasColumn('analysis_tasks', 'error_message')) {
            Schema::table('analysis_tasks', function (Blueprint $table) {
                $table->renameColumn('error_msg', 'error_message');
            });
        }

        // 如果两个字段都存在，将 error_msg 数据合并到 error_message，然后删除 error_msg
        if (Schema::hasColumn('analysis_tasks', 'error_msg') && Schema::hasColumn('analysis_tasks', 'error_message')) {
            // 将 error_msg 的数据更新到 error_message（如果 error_message 为空）
            \DB::statement('UPDATE analysis_tasks SET error_message = error_msg WHERE error_message IS NULL AND error_msg IS NOT NULL');
            
            Schema::table('analysis_tasks', function (Blueprint $table) {
                $table->dropColumn('error_msg');
            });
        }

        // 确保 error_message 字段类型为 TEXT（容纳更多内容）
        if (Schema::hasColumn('analysis_tasks', 'error_message')) {
            Schema::table('analysis_tasks', function (Blueprint $table) {
                $table->text('error_message')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // 回滚时恢复 error_msg 字段
        if (Schema::hasColumn('analysis_tasks', 'error_message') && !Schema::hasColumn('analysis_tasks', 'error_msg')) {
            Schema::table('analysis_tasks', function (Blueprint $table) {
                $table->string('error_msg', 500)->nullable()->after('status')->comment('错误信息');
            });
            
            \DB::statement('UPDATE analysis_tasks SET error_msg = LEFT(error_message, 500) WHERE error_message IS NOT NULL');
            
            Schema::table('analysis_tasks', function (Blueprint $table) {
                $table->dropColumn('error_message');
            });
        }
    }
};
