<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biodata_views', function (Blueprint $table) {
            $table->id();
            // ইউজার লগইন করা থাকলে আইডি বসবে, না থাকলে null থাকবে
            $table->foreignId('viewer_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('biodata_id')->constrained('biodatas')->onDelete('cascade');

            // গেস্ট ট্র্যাকিংয়ের জন্য
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biodata_views');
    }
};
