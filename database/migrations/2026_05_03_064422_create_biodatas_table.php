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

            // সাধারণ তথ্য (General Information)
           $table->unsignedBigInteger('biodata_no')->nullable()->unique();
            $table->string('name')->nullable();
            $table->enum('type', ['Male', 'Female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('nationality')->default('বাংলাদেশী')->nullable();

            // Draft Save Logic এর জন্য
            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])->default('draft');
            $table->integer('current_step')->default(1);

            // ১. ঠিকানা (Address)
            $table->string('permanent_country')->default('বাংলাদেশ')->nullable();
            $table->string('permanent_division_id')->nullable();
            $table->string('permanent_district_id')->nullable();
            $table->string('permanent_upazila_id')->nullable();
            $table->string('permanent_union_id')->nullable();
            $table->text('permanent_home_details')->nullable();

            $table->string('present_country')->default('বাংলাদেশ')->nullable();
            $table->string('present_division_id')->nullable();
            $table->string('present_district_id')->nullable();
            $table->string('present_upazila_id')->nullable();
            $table->string('present_union_id')->nullable();
            $table->text('present_home_details')->nullable();

            $table->text('grew_up_details')->nullable();

            // ২. শিক্ষাগত যোগ্যতা (Educational Qualification)
            $table->string('edu_medium')->nullable();
            $table->string('edu_highest_qual')->nullable();

            // জেনারেল / আলিয়া / স্নাতক / ডক্টরেট ফিল্ডস
            $table->string('edu_below_ssc_class')->nullable();
            $table->string('edu_ssc_year')->nullable();
            $table->string('edu_ssc_group')->nullable();
            $table->string('edu_ssc_result')->nullable();
            $table->string('edu_hsc_ongoing_year')->nullable();
            $table->string('edu_hsc_year')->nullable();
            $table->string('edu_hsc_group')->nullable();
            $table->string('edu_hsc_result')->nullable();
            $table->string('edu_after_ssc_medium')->nullable();

            $table->text('edu_diploma_subject')->nullable();
            $table->text('edu_diploma_institute')->nullable();
            $table->string('edu_diploma_ongoing_year')->nullable();
            $table->string('edu_diploma_year')->nullable();

            $table->text('edu_bachelor_subject')->nullable();
            $table->text('edu_bachelor_institute')->nullable();
            $table->string('edu_bachelor_ongoing_year')->nullable();
            $table->string('edu_bachelor_year')->nullable();
            $table->string('edu_bachelor_result')->nullable();

            $table->text('edu_master_subject')->nullable();
            $table->text('edu_master_institute')->nullable();
            $table->string('edu_master_year')->nullable();
            $table->string('edu_master_result')->nullable();

            $table->text('edu_doctorate_subject')->nullable();
            $table->text('edu_doctorate_institute')->nullable();
            $table->string('edu_doctorate_year')->nullable();

            // কওমি ফিল্ডস
            $table->text('edu_qawmi_madrasa')->nullable();
            $table->string('edu_qawmi_year')->nullable();
            $table->string('edu_qawmi_result')->nullable();
            $table->text('edu_takmil_madrasa')->nullable();
            $table->string('edu_takmil_year')->nullable();
            $table->string('edu_takmil_result')->nullable();

            // গ্লোবাল শিক্ষাগত ফিল্ডস
            $table->text('edu_other_details')->nullable();
            $table->json('edu_deeni_titles')->nullable();

            // ৩. পারিবারিক তথ্য (Family Information)
            $table->text('father_name')->nullable(); // Changed to text
            $table->string('father_status')->nullable();
            $table->text('father_occupation')->nullable();
            $table->text('mother_name')->nullable(); // Changed to text
            $table->string('mother_status')->nullable();
            $table->text('mother_occupation')->nullable();
            $table->integer('brothers_count')->default(0);
            $table->text('brothers_details')->nullable();
            $table->integer('sisters_count')->default(0);
            $table->text('sisters_details')->nullable();
            $table->string('family_financial_status')->nullable();
            $table->text('uncles_details')->nullable();
            $table->text('family_asset_details')->nullable();
            $table->text('family_islamic_details')->nullable();
            $table->text('family_home_type')->nullable(); // Changed to text

            // ৪. ব্যক্তিগত তথ্য (Personal Information)
            $table->string('skin_tone')->nullable();
            $table->integer('height_inches')->nullable()->index();
            $table->string('weight')->nullable();
            $table->string('blood_group')->nullable();

            // কমন ও লিঙ্গভিত্তিক ফিল্ডস
            $table->text('outdoor_dressup_details')->nullable();
            $table->text('niqab_details')->nullable(); // Female only
            $table->string('niqab_started_from')->nullable(); // Female only

            $table->text('from_when_kept_beard_details')->nullable(); // Male only
            $table->text('wear_clothes_above_anckle')->nullable(); // Male only

            $table->text('started_prayer_details')->nullable();
            $table->string('missed_prayers')->nullable();
            $table->text('mahram_nonmahram_details')->nullable();
            $table->text('can_recite_quran_details')->nullable();
            $table->string('mazhab')->nullable();

            $table->string('watch_movies_or_listen_songs')->nullable();
            $table->text('any_disease_details')->nullable();
            $table->text('deen_effort')->nullable(); // Changed to text
            $table->text('mazar_belief')->nullable();
            $table->text('islamic_books_read')->nullable();
            $table->text('favorite_scholars')->nullable();

            $table->text('special_category')->nullable(); // Changed to text
            $table->text('hobby_and_wish_details')->nullable();

            $table->string('candidate_mobile_number')->nullable();
            $table->text('candidate_photo')->nullable(); // Changed to text

            // Special Gender Fields
            $table->integer('number_of_children')->default(0);
            $table->text('cause_of_divorce')->nullable();

            // ৫. পেশাগত তথ্য (Occupational Information)
            $table->string('occupation_category')->nullable();
            $table->text('occupation_details')->nullable();
            $table->string('monthly_income')->nullable();

            // ৬. বিবাহ সম্পর্কিত তথ্য (Marriage Related)
            $table->string('marital_status')->nullable();

            // Common
            $table->string('guardians_consent')->nullable();
            $table->text('view_on_marriage')->nullable();

            // Male
            $table->text('capable_of_keeping_wife_in_veil')->nullable(); // Changed to text
            $table->text('allow_wife_to_study')->nullable(); // Changed to text
            $table->text('allow_wifes_job')->nullable(); // Changed to text
            $table->text('where_live_after_marriage')->nullable(); // Changed to text
            $table->text('want_dowry')->nullable(); // Changed to text

            // Female
            $table->text('want_to_work_after_marriage')->nullable(); // Changed to text
            $table->text('want_to_study_after_marriage')->nullable(); // Changed to text
            $table->text('continue_working_after_marriage')->nullable(); // Changed to text

            // ৭. প্রত্যাশিত জীবনসঙ্গী (Expected Life Partner)
            $table->integer('partner_min_age')->nullable();
            $table->integer('partner_max_age')->nullable();
            $table->string('partner_district')->nullable();
            $table->integer('partner_min_height_inches')->nullable();
            $table->integer('partner_max_height_inches')->nullable();
            $table->text('partner_education_details')->nullable();
            $table->text('partner_qualities_details')->nullable();

            // ৮. যোগাযোগ ও ৯. অঙ্গীকারনামা
            $table->string('guardian_mobile')->nullable();
            $table->string('guardian_relationship')->nullable();
            $table->boolean('is_truthful')->default(false);
            $table->string('contact_email')->nullable();

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
