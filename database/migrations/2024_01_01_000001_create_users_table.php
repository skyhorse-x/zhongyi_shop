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
        // 扩展默认users表
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'nickname')) {
                $table->string('nickname')->default('用户')->comment('昵称')->after('id');
            }
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile', 20)->nullable()->unique()->comment('手机号')->after('nickname');
            }
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->comment('头像')->after('password');
            }
            if (!Schema::hasColumn('users', 'gender')) {
                $table->tinyInteger('gender')->default(0)->comment('性别:0未知 1男 2女')->after('avatar');
            }
            if (!Schema::hasColumn('users', 'birthday')) {
                $table->date('birthday')->nullable()->comment('生日')->after('gender');
            }
            if (!Schema::hasColumn('users', 'is_promoter')) {
                $table->tinyInteger('is_promoter')->default(0)->comment('是否推广员')->after('birthday');
            }
            if (!Schema::hasColumn('users', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->comment('推荐人ID')->after('is_promoter');
            }

            // 添加外键约束
            if (Schema::hasColumn('users', 'parent_id')) {
                $table->foreign('parent_id')->references('id')->on('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nickname',
                'mobile',
                'avatar',
                'gender',
                'birthday',
                'is_promoter',
                'parent_id',
            ]);
        });
    }
};
