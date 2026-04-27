<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // ইমেইলের পরে নতুন কলামগুলো যুক্ত হবে
            $table->string('mobile')->nullable()->after('email');
           // $table->string('role')->default('user')->after('mobile'); // 'user' বা 'admin'
            $table->string('status')->default('active')->after('role'); // 'active' বা 'banned'
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // রোলব্যাক করলে যেন কলামগুলো মুছে যায়
            $table->dropColumn(['mobile', 'status']);
        });
    }
};
