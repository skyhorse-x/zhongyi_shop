<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 增强客服系统：常用话术、系统消息、余额不足记录
     */
    public function up(): void
    {
        // 客服常用话术表
        if (!Schema::hasTable('customer_service_phrases')) {
            Schema::create('customer_service_phrases', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('admin_id')->default(0)->comment('客服管理员ID(0表示公共话术)');
                $table->string('title', 100)->default('')->comment('话术标题');
                $table->text('content')->comment('话术内容');
                $table->string('category', 50)->default('common')->comment('分类:common常见问题 greeting问候语 promotion推广');
                $table->unsignedInteger('sort_order')->default(0)->comment('排序');
                $table->boolean('is_public')->default(false)->comment('是否公共话术');
                $table->boolean('is_enabled')->default(true)->comment('是否启用');
                $table->timestamps();
                
                $table->index('admin_id');
                $table->index('category');
                $table->index('is_public');
            });
        }

        // 系统消息表（后台发送给用户的通知）
        if (!Schema::hasTable('system_messages')) {
            Schema::create('system_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->comment('接收用户ID(0表示广播所有用户)');
                $table->string('title', 200)->default('')->comment('消息标题');
                $table->text('content')->comment('消息内容');
                $table->string('type', 30)->default('notice')->comment('类型:notice通知 activity活动 system系统更新 balance余额提醒');
                $table->string('target_url', 500)->default('')->comment('跳转链接');
                $table->boolean('is_read')->default(false)->comment('是否已读');
                $table->timestamp('read_at')->nullable()->comment('阅读时间');
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('type');
                $table->index('is_read');
            });
        }

        // 余额不足消息记录表
        if (!Schema::hasTable('balance_insufficient_logs')) {
            Schema::create('balance_insufficient_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->comment('用户ID');
                $table->unsignedBigInteger('session_id')->nullable()->comment('关联会话ID');
                $table->decimal('current_balance', 10, 2)->default(0)->comment('当前余额');
                $table->decimal('required_amount', 10, 2)->default(0)->comment('所需金额');
                $table->string('action_type', 50)->comment('操作类型:analysis分析 constitution体质测试 qa问答');
                $table->boolean('is_notified')->default(false)->comment('是否已发送通知');
                $table->text('message')->nullable()->comment('提示消息内容');
                $table->timestamps();
                
                $table->index('user_id');
                $table->index('action_type');
                $table->index('is_notified');
            });
        }

        // 客服会话表增加欢迎消息字段
        if (Schema::hasTable('customer_service_sessions') && !Schema::hasColumn('customer_service_sessions', 'welcome_sent')) {
            Schema::table('customer_service_sessions', function (Blueprint $table) {
                $table->boolean('welcome_sent')->default(false)->comment('欢迎消息是否已发送')->after('status');
            });
        }

        // 客服配置表（存储欢迎消息等配置）
        if (!Schema::hasTable('customer_service_configs')) {
            Schema::create('customer_service_configs', function (Blueprint $table) {
                $table->id();
                $table->string('key', 50)->unique()->comment('配置键');
                $table->text('value')->nullable()->comment('配置值');
                $table->string('name', 100)->default('')->comment('配置名称');
                $table->string('remark', 255)->default('')->comment('备注');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_service_phrases');
        Schema::dropIfExists('system_messages');
        Schema::dropIfExists('balance_insufficient_logs');
        Schema::dropIfExists('customer_service_configs');
        
        if (Schema::hasTable('customer_service_sessions') && Schema::hasColumn('customer_service_sessions', 'welcome_sent')) {
            Schema::table('customer_service_sessions', function (Blueprint $table) {
                $table->dropColumn('welcome_sent');
            });
        }
    }
};
