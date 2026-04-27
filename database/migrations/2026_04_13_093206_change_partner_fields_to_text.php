<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('biodatas', function (Blueprint $table) {
            $table->text('partner_complexion')->nullable()->change();
            $table->text('partner_district')->nullable()->change();
            $table->text('partner_marital_status')->nullable()->change();
            $table->text('partner_financial_status')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biodatas', function (Blueprint $table) {
            $table->string('partner_complexion')->nullable()->change();
            $table->string('partner_district')->nullable()->change();
            $table->string('partner_marital_status')->nullable()->change();
            $table->string('partner_financial_status')->nullable()->change();
        });
    }
};
