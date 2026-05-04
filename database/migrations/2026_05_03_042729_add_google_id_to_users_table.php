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
    Schema::table('users', function (Blueprint $table) {
        $table->string('google_id')->nullable()->unique()->after('email');
        $table->string('avatar')->nullable()->after('google_id');
        $table->string('password')->nullable()->change(); // গুগল লগিনের ক্ষেত্রে পাসওয়ার্ড নাও থাকতে পারে
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
