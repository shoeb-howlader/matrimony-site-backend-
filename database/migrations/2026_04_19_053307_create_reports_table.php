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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // যে ইউজার অভিযোগ করছে তার আইডি
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // যার বিরুদ্ধে অভিযোগ তার বায়োডাটা নম্বর
            $table->string('biodata_no');

            // অভিযোগের কারণ (dropdown থেকে আসা)
            $table->string('reason');

            // অভিযোগের বিস্তারিত
            $table->text('description');

            // প্রমাণস্বরূপ ছবির পাথ (nullable কারণ ছবি নাও থাকতে পারে)
            $table->string('attachment')->nullable();

            // অভিযোগের স্ট্যাটাস (pending, reviewed, resolved)
            $table->enum('status', ['pending', 'reviewed', 'resolved'])->default('pending');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
