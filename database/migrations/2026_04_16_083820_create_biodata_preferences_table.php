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
    Schema::create('biodata_preferences', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('biodata_id')->constrained('biodatas')->onDelete('cascade');

        // 🔴 ম্যাজিক কলাম: এটি ঠিক করবে অ্যাকশনটি কী ধরনের
        $table->enum('type', ['favorite', 'ignore']);

        $table->timestamps();

        // 🔴 সবচেয়ে জরুরি: একজন ইউজার একটি বায়োডাটার বিপরীতে একটি রেকর্ডই রাখতে পারবে
        $table->unique(['user_id', 'biodata_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biodata_preferences');
    }
};
