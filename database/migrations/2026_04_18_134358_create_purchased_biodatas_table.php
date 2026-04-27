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
    Schema::create('purchased_biodatas', function (Blueprint $table) {
        $table->id();

        // ইউজার এবং বায়োডাটার ফরেন কি (Foreign Key)
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('biodata_id')->constrained()->onDelete('cascade');

        $table->timestamps();

        // 🔴 ইউনিক ইন্ডেক্স: ইউজার আইডি এবং বায়োডাটা আইডির কম্বিনেশন ইউনিক হবে
        $table->unique(['user_id', 'biodata_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchased_biodatas');
    }
};
