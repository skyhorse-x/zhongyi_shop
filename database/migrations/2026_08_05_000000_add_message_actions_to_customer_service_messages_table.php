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
            // 软删除
            $table->boolean('is_deleted')->default(false)->after('status')->comment('是否已删除');
            $table->timestamp('deleted_at')->nullable()->after('is_deleted')->comment('删除时间');
            $table->integer('deleted_by')->nullable()->after('deleted_at')->comment('删除人ID');

            // 撤回
            $table->boolean('is_recalled')->default(false)->after('deleted_by')->comment('是否已撤回');
            $table->timestamp('recalled_at')->nullable()->after('is_recalled')->comment('撤回时间');

            // 引用/回复
            $table->integer('reply_to_id')->nullable()->after('recalled_at')->comment('引用的消息ID');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_messages', function (Blueprint $table) {
            $table->dropColumn([
                'is_deleted',
                'deleted_at',
                'deleted_by',
                'is_recalled',
                'recalled_at',
                'reply_to_id',
            ]);
        });
    }
};
