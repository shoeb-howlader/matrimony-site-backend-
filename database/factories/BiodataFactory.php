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
        // ডাটাবেস থেকে র্যান্ডম একটি বিভাগ নেওয়া
        $division = Division::inRandomOrder()->first();
        // ওই বিভাগের অধীনে একটি র্যান্ডম জেলা নেওয়া
        $district = District::where('division_id', $division->id)->inRandomOrder()->first();
        // ওই জেলার অধীনে একটি র্যান্ডম উপজেলা নেওয়া
        $upazila = Upazila::where('district_id', $district->id)->inRandomOrder()->first();
        // ওই উপজেলার অধীনে একটি র্যান্ডম ইউনিয়ন নেওয়া
        $union = Union::where('upazila_id', $upazila->id)->inRandomOrder()->first();
        // ৪ ফুট (৪৮ ইঞ্চি) থেকে ৭ ফুট (৮৪ ইঞ্চি) এর মধ্যে র‍্যান্ডম ইঞ্চি
        $totalInches = $this->faker->numberBetween(48, 84);
        // পড়াশোনার মাধ্যম (একাধিক হতে পারে, তাই র‍্যান্ডমলি ১-২টি নেওয়া হচ্ছে)
        $eduMedias = ['জেনারেল', 'কওমি', 'আলিয়া'];
        $selectedMedias = $this->faker->randomElements($eduMedias, $this->faker->numberBetween(1, 2));

        // দ্বীনি যোগ্যতা (একাধিক হতে পারে, তাই র‍্যান্ডমলি ০-৩টি নেওয়া হচ্ছে)
        $deeniQuals = ['হাফেজ', 'মাওলানা', 'মুফতি', 'মুফাসসির', 'আদিব', 'ক্বারী'];
        $selectedQuals = $this->faker->randomElements($deeniQuals, $this->faker->numberBetween(0, 3));
        $financialStatuses = ["উচ্চবিত্ত", "উচ্চ মধ্যবিত্ত", "মধ্যবিত্ত", "নিম্ন মধ্যবিত্ত", "নিম্নবিত্ত"];
        $special_categories = ["প্রতিবন্ধী", "বন্ধ্যা", "নওমুসলিম", "এতিম", "২য় স্ত্রী হতে আগ্রহী", "তাবলীগ"];


        return [
            // লোকেশন আইডিগুলো সেট করা
           'present_division_id'   => $division->id,
           'present_district_id'   => $district->id,
           'present_upazila_id'    => $upazila->id,
            'present_union_id'      => $union ? $union->id : null,

            // স্থায়ী ঠিকানা হিসেবেও একই লজিক বা আলাদা লজিক দেওয়া যায়
            'permanent_division_id' => $division->id,
             'permanent_district_id' => $district->id,
             'permanent_upazila_id'  => $upazila->id,
             'permanent_union_id'    => $union ? $union->id : null,
            'user_id' => User::factory(),
            'biodata_no' => 'OD-' . $this->faker->unique()->numberBetween(10000, 99999),
            'type' => $this->faker->randomElement(['Male', 'Female']),
            'birth_date' => $this->faker->date('Y-m-d', '2008-01-01'), // ২০০৮ এর আগের যেকোনো তারিখ

            // এই ফিল্ডটি ফ্রন্টএন্ড ফিল্টারের জন্য খুবই গুরুত্বপূরণ
            'marital_status' => $this->faker->randomElement(['অবিবাহিত', 'বিবাহিত', 'ডিভোর্সড', 'বিপত্নীক']),

            'skin_tone' => $this->faker->randomElement(['কালো', 'শ্যামলা', 'উজ্জ্বল শ্যামলা', 'ফর্সা', 'উজ্জ্বল ফর্সা']),


            'mazhab' => $this->faker->randomElement(['হানাফি', 'মালিকি', 'শাফিঈ', 'হাম্বলি', 'আহলে হাদীস / সালাফি']),
             // শিক্ষা সংক্রান্ত ডাটা (কমা দিয়ে আলাদা করা স্ট্রিং)
            'edu_media' => implode(',', $selectedMedias),
            'deeni_qualification' => count($selectedQuals) > 0 ? implode(',', $selectedQuals) : null,
            'status' => 'approved',
            'height_inches' => $totalInches,
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
