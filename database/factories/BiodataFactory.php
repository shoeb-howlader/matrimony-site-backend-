<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\Union;
use Illuminate\Database\Eloquent\Factories\Factory;

class BiodataFactory extends Factory
{
    public function definition(): array
    {
        // ডাটাবেস থেকে র্যান্ডম একটি বিভাগ নেওয়া
        $division = Division::inRandomOrder()->first();
        $district = District::where('division_id', $division->id)->inRandomOrder()->first();
        $upazila = Upazila::where('district_id', $district->id)->inRandomOrder()->first();
        $union = Union::where('upazila_id', $upazila->id)->inRandomOrder()->first();

        // ৪ ফুট (৪৮ ইঞ্চি) থেকে ৭ ফুট (৮৪ ইঞ্চি) এর মধ্যে র‍্যান্ডম ইঞ্চি
        $totalInches = $this->faker->numberBetween(48, 84);

        // শিক্ষাগত যোগ্যতা ও মাধ্যম লজিক
        $eduMediums = ['জেনারেল', 'কওমি', 'আলিয়া'];
        $medium = $this->faker->randomElement($eduMediums);

        $highestQual = '';
        if (in_array($medium, ['জেনারেল', 'আলিয়া'])) {
            $highestQual = $this->faker->randomElement(['এস এস সি / দাখিল', 'এইচ.এস.সি / আলিম', 'স্নাতক / স্নাতক (সম্মান)', 'স্নাতকোত্তর / কামিল']);
        } else {
            $highestQual = $this->faker->randomElement(['ইবতিদাইয়্যাহ', 'মুতাওয়াসসিতাহ', 'সানাবিয়া উলইয়া', 'ফযীলত', 'তাকমীল']);
        }

        // দ্বীনি যোগ্যতা (Array হিসেবে সেভ হবে কারণ মাইগ্রেশনে JSON দেওয়া আছে)
        $deeniQuals = ['হাফেজ', 'মাওলানা', 'মুফতি', 'মুফাসসির', 'আদিব', 'ক্বারী'];
        $selectedDeeniTitles = $this->faker->randomElements($deeniQuals, $this->faker->numberBetween(0, 3));

        $financialStatuses = ["উচ্চবিত্ত", "উচ্চ মধ্যবিত্ত", "মধ্যবিত্ত", "নিম্ন মধ্যবিত্ত", "নিম্নবিত্ত"];
        $special_categories = ["প্রতিবন্ধী", "বন্ধ্যা", "নওমুসলিম", "এতিম", "২য় স্ত্রী হতে আগ্রহী", "তাবলীগ"];

        return [
            'user_id' => User::factory(),
            'biodata_no' => 'OD-' . $this->faker->unique()->numberBetween(10000, 99999),
            'type' => $this->faker->randomElement(['Male', 'Female']),
            'date_of_birth' => $this->faker->date('Y-m-d', '2008-01-01'), // ২০০৮ এর আগের যেকোনো তারিখ
            'nationality' => 'বাংলাদেশী',
            'status' => 'approved',
            'current_step' => 10,

            // ১. বর্তমান ঠিকানা
            'present_country' => 'বাংলাদেশ',
            'present_division_id'   => $division->id,
            'present_district_id'   => $district->id,
            'present_upazila_id'    => $upazila->id,
            'present_union_id'      => $union ? $union->id : null,
            'present_home_details'  => $this->faker->streetAddress(),

            // ১. স্থায়ী ঠিকানা
            'permanent_country' => 'বাংলাদেশ',
            'permanent_division_id' => $division->id,
            'permanent_district_id' => $district->id,
            'permanent_upazila_id'  => $upazila->id,
            'permanent_union_id'    => $union ? $union->id : null,
            'permanent_home_details'=> $this->faker->streetAddress(),

            'grew_up_details' => $this->faker->city() . ', বাংলাদেশ',

            // ২. শিক্ষাগত যোগ্যতা (নতুন মাইগ্রেশন অনুযায়ী)
            'edu_medium' => $medium,
            'edu_highest_qual' => $highestQual,
            'edu_ssc_year' => $this->faker->numberBetween(2010, 2020),
            'edu_ssc_group' => $this->faker->randomElement(['বিজ্ঞান', 'মানবিক', 'ব্যবসায় শিক্ষা']),
            'edu_ssc_result' => $this->faker->randomElement(['A+', 'A', 'A-', 'B']),

           'edu_deeni_titles' => json_encode($selectedDeeniTitles, JSON_UNESCAPED_UNICODE),

            // ৩. ব্যক্তিগত তথ্য
            'marital_status' => $this->faker->randomElement(['অবিবাহিত', 'বিবাহিত', 'ডিভোর্সড', 'বিপত্নীক']),
            'skin_tone' => $this->faker->randomElement(['কালো', 'শ্যামলা', 'উজ্জ্বল শ্যামলা', 'ফর্সা', 'উজ্জ্বল ফর্সা']),
            'height_inches' => $totalInches,
            'mazhab' => $this->faker->randomElement(['হানাফি', 'মালিকি', 'শাফিঈ', 'হাম্বলি', 'আহলে হাদীস / সালাফি']),

            // পেশা এবং পরিবার
            'occupation_category' => $this->faker->randomElement([
                "ইমাম", "মাদ্রাসা শিক্ষক", "শিক্ষক", "ইঞ্জিনিয়ার", "ব্যবসায়ী",
                "সরকারী চাকুরী", "বেসরকারী চাকুরী", "ফ্রিল্যান্সার", "ডাক্তার",
                "MBBS/BDS শিক্ষার্থী", "শিক্ষার্থী", "প্রবাসী", "অন্যান্য", "পেশা নেই"
            ]),
            'special_category' => implode(',', $this->faker->randomElements($special_categories, $this->faker->numberBetween(0, 2))),
            'family_financial_status' => $this->faker->randomElement($financialStatuses),
        ];
    }
}
