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
    Schema::create('biodatas', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('biodata_no')->unique();
        $table->string('name')->nullable();
        $table->enum('type', ['Male', 'Female']); // Using 'type' for Nuxt compatibility
        $table->date('date_of_birth')->nullable();
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

        // ১. ঠিকানা (Address) [cite: 15]
        $table->string('permanent_division_id')->nullable();
        $table->string('permanent_district_id')->nullable();
        $table->string('permanent_upazila_id')->nullable();
        $table->string('permanent_union_id')->nullable();
        $table->string('present_division_id')->nullable();
        $table->string('present_district_id')->nullable();
        $table->string('present_upazila_id')->nullable();
        $table->string('present_union_id')->nullable();
        $table->string('grew_up_district_id')->nullable();

        // ২. শিক্ষাগত যোগ্যতা (Educational Qualification) [cite: 17]
        $table->string('education_method')->nullable();
        $table->string('highest_degree')->nullable();
        $table->year('ssc_passing_year')->nullable(); // Changed to year
        $table->string('ssc_board')->nullable();
        $table->string('ssc_group')->nullable();
        $table->string('ssc_result')->nullable();
       // এখানে after() সরিয়ে দিন, কারণ height_inches নিচে আছে
        $table->string('edu_media')->nullable();
        $table->string('deeni_qualification')->nullable();
        //(Repeat for HSC)
        $table->year('hsc_passing_year')->nullable(); // Changed to year
        $table->string('hsc_board')->nullable();
        $table->string('hsc_group')->nullable();
        $table->string('hsc_result')->nullable();
        // (Repeat for Graduation)
        $table->year('graduation_passing_year')->nullable(); // Changed to year
        $table->string('graduation_university')->nullable();
        $table->string('graduation_subject')->nullable();
        $table->string('graduation_result')->nullable();
        // (Repeat for Masters)
        $table->year('masters_passing_year')->nullable(); // Changed to year
        $table->string('masters_university')->nullable();
        $table->string('masters_subject')->nullable();
        $table->string('masters_result')->nullable();

        // ৩. পারিবারিক তথ্য (Family Information) [cite: 22]
        $table->string('father_name')->nullable();
        $table->string('father_status')->nullable();
        $table->string('father_occupation')->nullable();
        $table->string('mother_name')->nullable();
        $table->string('mother_status')->nullable();
        $table->string('mother_occupation')->nullable();
        $table->integer('brothers_count')->default(0);
        $table->text('brothers_details')->nullable();
        $table->integer('sisters_count')->default(0);
        $table->text('sisters_details')->nullable();
        $table->string('family_financial_status')->nullable();
        $table->text('uncles_details')->nullable();
        $table->text('family_asset_details')->nullable();
        $table->text('family_islamic_details')->nullable();

        // ৪. ব্যক্তিগত তথ্য (Personal Information) [cite: 23]
        $table->date('birth_date')->nullable();
        $table->string('skin_tone')->nullable();
        $table->integer('height_inches')->nullable()->index(); // ৪' ২" হলে ৫০ সেভ হবে
        $table->string('weight')->nullable();
        $table->string('blood_group')->nullable();
        $table->text('outdoor_dressup_details')->nullable();
        $table->text('niqab_details')->nullable();
        $table->text('started_prayer_details')->nullable();
        $table->text('mahram_nonmahram_details')->nullable();
        $table->text('can_recite_quran_details')->nullable();
        $table->text('any_disease_details')->nullable();
        $table->text('hobby_and_wish_details')->nullable();
        // Special Gender Fields [cite: 23]
        $table->text('from_when_kept_beard_details')->nullable();
        $table->text('wear_clothes_above_anckle')->nullable();
        $table->integer('number_of_children')->default(0);
        $table->text('cause_of_divorce')->nullable();
        $table->string('mazhab'); // ফিকহ অনুসরণ


        // বায়োডাটা special ক্যাটাগরি (একাধিক হতে পারে, তাই স্ট্রিং বা JSON হিসেবে সেভ হবে)
        $table->string('special_category')->nullable();

        // ৫. পেশাগত তথ্য (Occupational Information) [cite: 24]
        $table->string('occupation_category')->nullable();
        $table->text('occupation_details')->nullable();
        $table->string('monthly_income')->nullable();


        // ৬. বিবাহ সম্পর্কিত তথ্য (Marriage Related) [cite: 25]
        $table->string('marital_status')->nullable();
        $table->text('view_on_marriage')->nullable();
        $table->text('where_live_after_marriage')->nullable();
        $table->text('want_dowry')->nullable();
        // Special Gender Fields [cite: 25]
        $table->text('capable_of_keeping_wife_in_veil')->nullable();
        $table->text('allow_wifes_job')->nullable();

        // ৭. প্রত্যাশিত জীবনসঙ্গী (Expected Life Partner) [cite: 26]
        $table->integer('partner_min_age')->nullable(); // Changed to integer
        $table->integer('partner_max_age')->nullable(); // Changed to integer
        $table->string('partner_district')->nullable();
        $table->integer('partner_min_height_inches')->nullable(); // Changed to integer
        $table->integer('partner_max_height_inches')->nullable(); // Changed to integer
        $table->text('partner_education_details')->nullable();
        $table->text('partner_qualities_details')->nullable();

        // ৮. যোগাযোগ ও ৯. অঙ্গীকারনামা [cite: 29, 27]
        $table->string('guardian_mobile')->nullable();
        $table->string('guardian_relationship')->nullable();
        $table->boolean('is_truthful')->default(false);

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biodatas');
    }
};
