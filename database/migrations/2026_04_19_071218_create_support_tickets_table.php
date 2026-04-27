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
    Schema::create('support_tickets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        // ক্যাটাগরি (payment_issue, biodata_report, etc.)
        $table->string('category');

        // বায়োডাটা নম্বর (শুধুমাত্র বায়োডাটা রিপোর্টের ক্ষেত্রে প্রযোজ্য, তাই nullable)
        $table->string('biodata_no')->nullable();

        $table->string('subject'); // অভিযোগ বা সাপোর্টের বিষয়
        $table->text('message'); // বিস্তারিত
        $table->string('attachment')->nullable(); // ছবি

        // স্ট্যাটাস
        $table->enum('status', ['pending', 'replied', 'resolved'])->default('pending');

        // 🔴 অ্যাডমিনের উত্তরের জন্য কলাম 🔴
        $table->text('admin_reply')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
