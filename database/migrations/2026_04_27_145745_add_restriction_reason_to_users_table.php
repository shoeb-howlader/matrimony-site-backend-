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
        // রেস্ট্রিকশনের কারণ সেভ করার জন্য নতুন কলাম
        $table->text('restriction_reason')->nullable()->after('restriction_expires_at');
    });
}

public function down()
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropColumn('restriction_reason');
    });
}
};
