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
    Schema::create('biodata_deletion_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); // কোন ইউজার
        $table->string('biodata_no'); // ডিলিট করা বায়োডাটা নম্বর
        $table->string('reason');    // ডিলিট করার কারণ
        $table->text('feedback')->nullable(); // ইউজারের বিস্তারিত মতামত
        $table->timestamps(); // কখন ডিলিট হলো তা জানার জন্য
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biodata_deletion_logs');
    }
};
