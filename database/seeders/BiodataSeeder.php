<?php

namespace Database\Seeders;

use App\Models\Biodata;
use App\Models\User;
use Illuminate\Database\Seeder;



class BiodataSeeder extends Seeder
{
    public function run(): void
    {
        // ১. ১০ জন পুরুষ (Male) ইউজার এবং তাদের বায়োডাটা তৈরি
        User::factory(500)->create()->each(function ($user) {
            Biodata::factory()->create([
                'user_id' => $user->id,
                'type' => 'Male',
                'status' => 'approved',
            ]);
        });

        // ২. ১০ জন মহিলা (Female) ইউজার এবং তাদের বায়োডাটা তৈরি
        User::factory(500)->create()->each(function ($user) {
            Biodata::factory()->create([
                'user_id' => $user->id,
                'type' => 'Female',
                'status' => 'approved',
            ]);
        });

        $this->command->info('1000 Biodatas seeded successfully with proper location IDs!');
    }
}
