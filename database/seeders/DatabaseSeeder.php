<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Biodata;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create a specific test user first
        $user = User::factory()->create([
            'name' => 'Shoeb',
            'email' => 'test@example.com',
        ]);

       $this->call([
        LocationSeeder::class, // আপনার আগের লোকেশন সিডার
        SettingSeeder::class,  // নতুন সেটিং সিডার
        BiodataSeeder::class,  // বায়োডাটা জেনারেটর
    ]);
    }
}
