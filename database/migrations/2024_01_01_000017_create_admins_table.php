<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('username', 50)->unique()->comment('用户名');
            $table->string('password', 255)->comment('密码(bcrypt)');
            $table->string('name', 50)->nullable()->comment('姓名');
            $table->string('email', 100)->nullable()->comment('邮箱');
            $table->string('avatar', 500)->nullable()->comment('头像');
            $table->unsignedInteger('role_id')->nullable()->comment('角色ID');
            $table->tinyInteger('status')->default(1)->comment('状态:1正常 0禁用');
            $table->dateTime('last_login_at')->nullable()->comment('最后登录时间');
            $table->string('last_login_ip', 50)->nullable()->comment('最后登录IP');
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
