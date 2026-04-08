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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group'); // যেমন: 'occupation', 'mazhab', 'skin_tone'
        $table->string('value'); // ডাটাবেসে যা সেভ হবে (English/Bangla)
        $table->string('label'); // ফ্রন্টএন্ডে যা দেখাবে (Bangla)
        $table->integer('order')->default(0); // ক্রমানুসারে সাজানোর জন্য
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
