<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->decimal('height', 5, 2)->nullable()->comment('身高');
            $table->decimal('weight', 5, 2)->nullable()->comment('体重');
            $table->string('blood_type', 10)->nullable()->comment('血型');
            $table->json('medical_history')->nullable()->comment('病史');
            $table->json('allergies')->nullable()->comment('过敏史');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
