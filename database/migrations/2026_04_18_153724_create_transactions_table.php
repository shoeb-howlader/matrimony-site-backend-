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
    Schema::create('transactions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->foreignId('connection_package_id')->nullable()->constrained()->onDelete('set null'); // কোন প্যাকেজ কিনছে
        $table->string('transaction_id')->unique(); // পেমেন্ট গেটওয়ের ট্রানজেকশন আইডি
        $table->decimal('amount', 8, 2);
        $table->integer('connections_added'); // কত কানেকশন পেল
        $table->enum('status', ['pending', 'success', 'failed', 'canceled'])->default('pending');
        $table->string('payment_method')->nullable(); // bKash, SSLCommerz etc.
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
