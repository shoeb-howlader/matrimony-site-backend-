<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('user_restriction_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->integer('restricted_days'); // কতদিনের জন্য রেস্ট্রিক্ট করা হয়েছিল
        $table->text('reason')->nullable(); // কী কারণে করা হয়েছিল
        $table->timestamp('expires_at')->nullable(); // কবে মেয়াদ শেষ হবে
        $table->timestamps(); // কখন রেস্ট্রিক্ট করা হয়েছে (created_at)
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_restriction_logs');
    }
};
