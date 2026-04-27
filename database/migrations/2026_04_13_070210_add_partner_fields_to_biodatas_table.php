<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biodatas', function (Blueprint $table) {
            $table->string('partner_complexion')->nullable();
            $table->string('partner_marital_status')->nullable();
            $table->string('partner_occupation')->nullable();
            $table->string('partner_financial_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('biodatas', function (Blueprint $table) {
            $table->dropColumn([
                'partner_complexion',
                'partner_marital_status',
                'partner_occupation',
                'partner_financial_status'
            ]);
        });
    }
};
