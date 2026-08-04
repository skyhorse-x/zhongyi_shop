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
            if (!Schema::hasColumn('customer_service_messages', 'msg_type')) {
                $table->string('msg_type', 20)->default('text')->after('content')->comment('消息类型:text文本 image图片 file文件');
            }
            if (!Schema::hasColumn('customer_service_messages', 'file_url')) {
                $table->string('file_url', 500)->default('')->after('msg_type')->comment('文件URL');
            }
            if (!Schema::hasColumn('customer_service_messages', 'file_name')) {
                $table->string('file_name', 255)->default('')->after('file_url')->comment('文件名称');
            }
            if (!Schema::hasColumn('customer_service_messages', 'file_size')) {
                $table->unsignedInteger('file_size')->default(0)->after('file_name')->comment('文件大小(字节)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_messages', function (Blueprint $table) {
            $table->dropColumn(['msg_type', 'file_url', 'file_name', 'file_size']);
        });
    }
};
