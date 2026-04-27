<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // status কলামের পরে নতুন কলামটি বসবে (আপনি চাইলে after অংশটি বাদও দিতে পারেন)
            $table->timestamp('restriction_expires_at')->nullable()->after('status');
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('restriction_expires_at');
        });
    }
};
