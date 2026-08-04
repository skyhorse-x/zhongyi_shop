<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table) {
            $table->tinyInteger('gender')->default(0)->after('type')->comment('性别:0未知 1男 2女');
            $table->unsignedTinyInteger('age')->default(0)->after('gender')->comment('年龄');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table) {
            $table->dropColumn(['gender', 'age']);
        });
    }
};
