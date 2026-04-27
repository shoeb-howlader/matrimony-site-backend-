<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // যে ইউজার পছন্দ করেছে
            $table->foreignId('biodata_id')->constrained('biodatas')->onDelete('cascade'); // যে বায়োডাটা পছন্দ করা হয়েছে
            $table->timestamps();

            // একজন ইউজার একটি বায়োডাটা একবারই ফেভারিট করতে পারবে
            $table->unique(['user_id', 'biodata_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
