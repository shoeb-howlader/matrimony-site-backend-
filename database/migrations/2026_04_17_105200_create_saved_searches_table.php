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
    Schema::create('saved_searches', function (Blueprint $table) {
        $table->id();
        // কোন ইউজারের সেভ করা ফিল্টার তা ট্র্যাক করতে
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        // ফিল্টারের নাম (যেমন: 'ঢাকার পাত্রী')
        $table->string('name');
        // ফিল্টারের পুরো JSON ডাটা
        $table->json('filter_data');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
