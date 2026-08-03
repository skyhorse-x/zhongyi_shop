<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_tasks', function (Blueprint $table) {
            $table->tinyInteger('gender')->nullable()->after('type')->comment('性别:1男 2女');
            $table->unsignedTinyInteger('age')->nullable()->after('gender')->comment('年龄');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_tasks', function (Blueprint $table) {
            $table->dropColumn(['gender', 'age']);
        });
    }
};
