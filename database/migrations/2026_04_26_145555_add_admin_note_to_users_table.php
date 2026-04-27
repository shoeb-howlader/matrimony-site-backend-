<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // nullable() দেওয়া হয়েছে যাতে ডাটা ফাঁকা থাকলেও এরর না দেয়
            $table->text('admin_note')->nullable()->after('status'); // আপনি চাইলে after('অন্য_কলাম') দিতে পারেন
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('admin_note');
        });
    }
};
