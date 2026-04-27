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
        // ডিফল্ট ০ দেওয়া হয়েছে যাতে নতুন ইউজারের শুরুতে কোনো কানেকশন না থাকে
        $table->integer('total_connections')->default(0)->after('email');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('total_connections');
    });
}
};
