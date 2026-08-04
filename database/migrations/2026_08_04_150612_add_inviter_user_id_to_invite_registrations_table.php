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
        Schema::table('invite_registrations', function (Blueprint $table) {
            $table->foreignId('inviter_user_id')->nullable()->after('id')->comment('邀请人用户ID（任意用户）');
            $table->foreign('inviter_user_id')->references('id')->on('users')->nullOnDelete();
            
            // 将 promoter_id 改为可空（向后兼容）
            $table->foreignId('promoter_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invite_registrations', function (Blueprint $table) {
            $table->dropColumn('inviter_user_id');
            $table->foreignId('promoter_id')->nullable(false)->change();
        });
    }
};
