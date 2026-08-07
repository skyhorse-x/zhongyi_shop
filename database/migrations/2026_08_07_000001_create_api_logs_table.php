<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 创建API请求日志表
     */
    public function up(): void
    {
        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10)->comment('请求方法');
            $table->text('url')->comment('请求URL');
            $table->string('route_name', 255)->nullable()->comment('路由名称');
            $table->string('module', 50)->default('other')->comment('模块');
            $table->json('request_headers')->nullable()->comment('请求头');
            $table->json('request_params')->nullable()->comment('请求参数');
            $table->text('request_body')->nullable()->comment('请求体');
            $table->integer('response_status')->comment('响应状态码');
            $table->json('response_headers')->nullable()->comment('响应头');
            $table->text('response_body')->nullable()->comment('响应体');
            $table->boolean('success')->default(true)->comment('是否成功');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->unsignedInteger('duration_ms')->default(0)->comment('耗时(毫秒)');
            $table->string('ip', 45)->nullable()->comment('IP地址');
            $table->string('user_agent', 500)->nullable()->comment('用户代理');
            $table->unsignedInteger('user_id')->nullable()->comment('用户ID');
            $table->string('user_type', 20)->nullable()->comment('用户类型:admin/user');
            $table->string('token', 20)->nullable()->comment('令牌标识');
            $table->timestamp('requested_at')->comment('请求时间');

            // 索引
            $table->index('module', 'idx_module');
            $table->index('response_status', 'idx_status');
            $table->index('requested_at', 'idx_requested_at');
            $table->index('user_id', 'idx_user_id');
            $table->index(['module', 'requested_at'], 'idx_module_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_logs');
    }
};
