<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $metadata = [
            // ১. গাত্রবর্ণ
            'skin_tone' => ['কালো', 'শ্যামলা', 'উজ্জ্বল শ্যামলা', 'ফর্সা', 'উজ্জ্বল ফর্সা'],

            // ২. ফিকহ (মাজহাব)
            'mazhab' => ['হানাফি', 'মালিকি', 'শাফিঈ', 'হাম্বলি', 'আহলে হাদীস / সালাফি'],

            // ৩. পড়াশোনার মাধ্যম
            'edu_media' => ['জেনারেল', 'কওমি', 'আলিয়া'],

            // ৪. দ্বীনি শিক্ষাগত যোগ্যতা
            'deeni_qualification' => ['হাফেজ', 'মাওলানা', 'মুফতি', 'মুফাসসির', 'আদিব', 'ক্বারী'],

            // ৫. পেশা
            'occupation' => [
                "ইমাম", "মাদ্রাসা শিক্ষক", "শিক্ষক", "ইঞ্জিনিয়ার", "ব্যবসায়ী",
                "সরকারী চাকুরী", "বেসরকারী চাকুরী", "ফ্রিল্যান্সার", "ডাক্তার",
                "MBBS/BDS শিক্ষার্থী", "শিক্ষার্থী", "প্রবাসী", "অন্যান্য", "পেশা নেই"
            ],

            // ৬. অর্থনৈতিক অবস্থা
            'financial_status' => ["উচ্চবিত্ত", "উচ্চ মধ্যবিত্ত", "মধ্যবিত্ত", "নিম্ন মধ্যবিত্ত", "নিম্নবিত্ত"],

            // ৭. বিশেষ ক্যাটাগরি
            'special_category' => ["প্রতিবন্ধী", "বন্ধ্যা", "নওমুসলিম", "এতিম", "২য় স্ত্রী হতে আগ্রহী", "তাবলীগ"],

            // ৮. বৈবাহিক অবস্থা
            'marital_status' => ["অবিবাহিত", "বিবাহিত", "ডিভোর্সড", "বিপত্নীক"]
        ];

        foreach ($metadata as $group => $items) {
            foreach ($items as $index => $item) {
                Setting::updateOrCreate(
                    ['group' => $group, 'value' => $item], // যদি অলরেডি থাকে তবে নতুন করে তৈরি করবে না
                    ['label' => $item, 'order' => $index]
                );
            }
        }

        $this->command->info('Settings seeded successfully!');
    }
}
