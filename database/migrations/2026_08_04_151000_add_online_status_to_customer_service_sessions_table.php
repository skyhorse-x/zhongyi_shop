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
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('status')->comment('用户是否在线');
            $table->timestamp('last_active_at')->nullable()->after('is_online')->comment('最后活跃时间');
            $table->string('ip_address', 45)->nullable()->after('last_active_at')->comment('用户IP地址');
            $table->text('browser_info')->nullable()->after('ip_address')->comment('浏览器信息(User-Agent)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_online', 'last_active_at', 'ip_address', 'browser_info']);
        });
    }
};
