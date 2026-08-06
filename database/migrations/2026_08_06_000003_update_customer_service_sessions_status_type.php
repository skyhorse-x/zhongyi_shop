<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * 修改客服会话表状态字段类型（string -> integer）
     * 0=等待中 1=服务中 2=已结束
     */
    public function up(): void
    {
        // 先更新现有数据
        \DB::table('customer_service_sessions')
            ->where('status', 'waiting')
            ->update(['status' => 0]);
        
        \DB::table('customer_service_sessions')
            ->where('status', 'active')
            ->update(['status' => 1]);
        
        \DB::table('customer_service_sessions')
            ->whereIn('status', ['resolved', 'closed'])
            ->update(['status' => 2]);

        // 修改字段类型
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(0)->comment('状态:0等待中 1服务中 2已结束')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_sessions', function (Blueprint $table) {
            $table->string('status', 20)->default('waiting')->comment('状态:waiting待接待 active进行中 resolved已解决 closed已关闭')->change();
        });
        
        // 还原数据
        \DB::table('customer_service_sessions')
            ->where('status', 0)
            ->update(['status' => 'waiting']);
        
        \DB::table('customer_service_sessions')
            ->where('status', 1)
            ->update(['status' => 'active']);
        
        \DB::table('customer_service_sessions')
            ->where('status', 2)
            ->update(['status' => 'closed']);
    }
};
