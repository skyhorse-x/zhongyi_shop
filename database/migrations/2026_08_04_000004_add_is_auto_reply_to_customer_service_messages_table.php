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
            $table->boolean('is_auto_reply')->default(false)->comment('是否为自动回复消息');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_service_messages', function (Blueprint $table) {
            $table->dropColumn('is_auto_reply');
        });
    }
};
