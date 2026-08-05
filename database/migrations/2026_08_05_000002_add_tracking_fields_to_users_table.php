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
        Schema::table('users', function (Blueprint $table) {
            $table->string('register_ip', 45)->nullable()->after('invite_code')->comment('注册IP');
            $table->timestamp('last_login_at')->nullable()->after('register_ip')->comment('最后登录时间');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at')->comment('最后登录IP');
            $table->timestamp('last_visit_at')->nullable()->after('last_login_ip')->comment('最后访问时间');
            $table->string('last_visit_ip', 45)->nullable()->after('last_visit_at')->comment('最后访问IP');

            $table->index('last_login_at', 'idx_last_login_at');
            $table->index('register_ip', 'idx_register_ip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'register_ip',
                'last_login_at',
                'last_login_ip',
                'last_visit_at',
                'last_visit_ip',
            ]);
        });
    }
};
