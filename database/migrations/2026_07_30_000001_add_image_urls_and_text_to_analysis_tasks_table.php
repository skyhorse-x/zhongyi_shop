<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_tasks', function (Blueprint $table) {
            $table->json('image_urls')->nullable()->after('image_url')->comment('多张图片URL');
            $table->text('text')->nullable()->after('image_urls')->comment('用户输入的描述文本');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_tasks', function (Blueprint $table) {
            $table->dropColumn(['image_urls', 'text']);
        });
    }
};
