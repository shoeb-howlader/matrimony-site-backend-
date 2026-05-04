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
    Schema::table('biodata_preferences', function (Blueprint $table) {
        $table->text('note')->nullable()->after('type');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biodata_preferences', function (Blueprint $table) {
            //
        });
    }
};
