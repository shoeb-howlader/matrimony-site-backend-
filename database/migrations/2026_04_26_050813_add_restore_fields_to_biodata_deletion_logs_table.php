<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('biodata_deletion_logs', function (Blueprint $table) {
            $table->timestamp('restored_at')->nullable()->after('feedback');
            $table->text('admin_note')->nullable()->after('restored_at');
        });
    }

    public function down()
    {
        Schema::table('biodata_deletion_logs', function (Blueprint $table) {
            $table->dropColumn(['restored_at', 'admin_note']);
        });
    }
};
