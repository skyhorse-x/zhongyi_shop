<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->comment('管理员ID');
            $table->string('admin_name', 100)->comment('管理员名称');
            $table->string('module', 50)->comment('操作模块');
            $table->string('action', 100)->comment('操作动作');
            $table->string('method', 10)->comment('请求方法');
            $table->string('url', 500)->comment('请求URL');
            $table->text('params')->nullable()->comment('请求参数');
            $table->string('ip', 45)->comment('IP地址');
            $table->string('user_agent', 500)->nullable()->comment('浏览器标识');
            $table->integer('response_code')->nullable()->comment('响应状态码');
            $table->text('response_data')->nullable()->comment('响应数据摘要');
            $table->integer('duration_ms')->comment('执行耗时(ms)');
            $table->tinyInteger('status')->default(1)->comment('状态：1成功 0失败');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->timestamps();

            $table->index('admin_id', 'idx_admin_id');
            $table->index('module', 'idx_module');
            $table->index('action', 'idx_action');
            $table->index('created_at', 'idx_created_at');
            $table->index(['module', 'action'], 'idx_module_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_operation_logs');
    }
};
