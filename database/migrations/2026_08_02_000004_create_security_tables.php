<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 安全概念相关表：角色、权限、操作日志
     * 对应安全设计文档 docs/10-security.md
     */
    public function up(): void
    {
        // 角色表
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->comment('角色名称');
            $table->string('code', 50)->unique()->comment('角色编码:super_admin运营管理员 finance_admin客服');
            $table->string('description', 200)->default('')->comment('角色描述');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态:1正常 0禁用');
            $table->timestamps();
            
            $table->index('code');
            $table->index('status');
        });

        // 权限表
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->comment('权限名称');
            $table->string('code', 100)->unique()->comment('权限编码:dashboard user_view user_edit等');
            $table->string('module', 50)->default('')->comment('所属模块');
            $table->string('description', 200)->default('')->comment('权限描述');
            $table->timestamps();
            
            $table->index('module');
        });

        // 角色权限关联表
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->comment('角色ID');
            $table->unsignedBigInteger('permission_id')->comment('权限ID');
            $table->timestamps();
            
            $table->unique(['role_id', 'permission_id']);
            $table->index('role_id');
            $table->index('permission_id');
        });

        // 操作日志表
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('admin_id')->default(0)->comment('管理员ID(0表示系统操作)');
            $table->string('admin_name', 50)->default('')->comment('管理员名称');
            $table->string('action', 100)->comment('操作动作');
            $table->string('module', 50)->default('')->comment('操作模块');
            $table->string('target_type', 50)->default('')->comment('目标类型:user order withdraw等');
            $table->string('target_id', 50)->default('')->comment('目标ID');
            $table->json('data')->comment('操作数据');
            $table->string('ip', 50)->default('')->comment('操作IP');
            $table->string('user_agent', 500)->default('')->comment('浏览器UA');
            $table->string('method', 10)->default('')->comment('请求方法:GET POST PUT DELETE');
            $table->string('url', 500)->default('')->comment('请求URL');
            $table->timestamps();
            
            $table->index('admin_id');
            $table->index('action');
            $table->index('module');
            $table->index('target_type');
            $table->index('created_at');
        });

        // 用户登录日志表
        Schema::create('user_login_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->string('ip', 50)->default('')->comment('登录IP');
            $table->string('user_agent', 500)->default('')->comment('浏览器UA');
            $table->string('device', 100)->default('')->comment('设备类型');
            $table->string('location', 100)->default('')->comment('登录地点');
            $table->unsignedTinyInteger('status')->default(1)->comment('状态:1成功 0失败');
            $table->string('fail_reason', 200)->default('')->comment('失败原因');
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('ip');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_login_logs');
        Schema::dropIfExists('operation_logs');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
