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
        Schema::table('analysis_reports', function (Blueprint $table) {
            $table->text('palm_analysis')->nullable()->after('face_analysis')->comment('手相分析内容');
            $table->string('life_line', 500)->nullable()->after('palm_analysis')->comment('生命线分析');
            $table->string('career_line', 500)->nullable()->after('life_line')->comment('事业线分析');
            $table->string('marriage_line', 500)->nullable()->after('career_line')->comment('婚姻线分析');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_reports', function (Blueprint $table) {
            $table->dropColumn(['palm_analysis', 'life_line', 'career_line', 'marriage_line']);
        });
    }
};
