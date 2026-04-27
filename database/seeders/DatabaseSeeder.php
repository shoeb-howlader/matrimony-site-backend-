<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Biodata;
use Illuminate\Database\Seeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\SettingSeeder;
use Database\Seeders\BiodataSeeder;
use Database\Seeders\ConnectionPackageSeeder;

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
        ConnectionPackageSeeder::class,
    ]);
    }
}
