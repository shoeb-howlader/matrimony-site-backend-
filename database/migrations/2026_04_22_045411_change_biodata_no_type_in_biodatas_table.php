<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
    {
        $existingNumbers = [];
        $currentMax = 1000;

        // ডাটাবেজ থেকে সব বায়োডাটা আনা
        $biodatas = DB::table('biodatas')->whereNotNull('biodata_no')->orderBy('id')->get();

        foreach ($biodatas as $item) {
            // ড্যাশ বা অক্ষর সব বাদ দিয়ে শুধু সলিড সংখ্যা বের করা
            $onlyNumbers = preg_replace('/[^0-9]/', '', $item->biodata_no);

            if ($onlyNumbers !== '') {
                $num = (int) $onlyNumbers;

                // যদি এই নম্বরটি অলরেডি অন্য কাউকে দেওয়া হয়ে থাকে, তবে নতুন সর্বোচ্চ নম্বর দেওয়া
                if (in_array($num, $existingNumbers)) {
                    $num = $currentMax + 1;
                }

                // ট্র্যাক রাখা এবং ডাটাবেজ আপডেট করা
                $existingNumbers[] = $num;
                if ($num > $currentMax) {
                    $currentMax = $num;
                }

                DB::table('biodatas')->where('id', $item->id)->update(['biodata_no' => $num]);
            } else {
                DB::table('biodatas')->where('id', $item->id)->update(['biodata_no' => null]);
            }
        }

        // ৪. কলামের টাইপ পরিবর্তন করা (🔴 এখান থেকে unique() বাদ দেওয়া হয়েছে)
        Schema::table('biodatas', function (Blueprint $table) {
            $table->unsignedBigInteger('biodata_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('biodatas', function (Blueprint $table) {
            // প্রয়োজনে পুনরায় স্ট্রিং এ ফেরত যাওয়া
            $table->string('biodata_no')->nullable()->change();
        });

        // ইউনিক ইনডেক্স ড্রপ করার প্রয়োজন হতে পারে যদি ডাউন করার সময় ঝামেলা হয়
        // $table->dropUnique(['biodata_no']);
    }
};
