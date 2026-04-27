<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // ENUM আপডেট করার জন্য Raw SQL ব্যবহার করা সবচেয়ে নিরাপদ
        DB::statement("ALTER TABLE biodatas MODIFY COLUMN status ENUM('incomplete', 'draft', 'pending', 'approved', 'rejected', 'edited') DEFAULT 'incomplete'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE biodatas MODIFY COLUMN status ENUM('draft', 'pending', 'approved', 'rejected') DEFAULT 'draft'");
    }
};
