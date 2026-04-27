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
    Schema::create('connection_packages', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // প্যাকেজের নাম (উদা: বেসিক, সিলভার, গোল্ড)
        $table->integer('connection_count'); // এই প্যাকেজে কতটি কানেকশন পাবে
        $table->decimal('price', 8, 2); // প্যাকেজের দাম
        $table->decimal('discount_price', 8, 2)->nullable(); // যদি কোনো অফার থাকে (ঐচ্ছিক)
        $table->string('badge_text')->nullable(); // উদা: "জনপ্রিয়" বা "সেরা ভ্যালু" (ঐচ্ছিক)
        $table->boolean('is_active')->default(true); // প্যাকেজটি কি এখন কেনা যাবে?
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('connection_packages');
    }
};
