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
        Schema::table('customer_service_messages', function (Blueprint $table) {
            // 删除多余的 msg_type 列（controller 使用的是 message_type）
            if (Schema::hasColumn('customer_service_messages', 'msg_type')) {
                $table->dropColumn('msg_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_service_messages', 'msg_type')) {
                $table->string('msg_type', 20)->default('text')->after('content')->comment('消息类型');
            }
        });
    }
};
