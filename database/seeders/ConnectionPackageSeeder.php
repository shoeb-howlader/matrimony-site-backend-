<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConnectionPackage;

class ConnectionPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'বেসিক প্যাকেজ',
                'connection_count' => 1,
                'price' => 100,
                'discount_price' => null,
                'badge_text' => null,
                'is_active' => true,
            ],
            [
                'name' => 'স্ট্যান্ডার্ড প্যাকেজ',
                'connection_count' => 5,
                'price' => 500,
                'discount_price' => 450, // অফার প্রাইস
                'badge_text' => 'জনপ্রিয়',
                'is_active' => true,
            ],
            [
                'name' => 'প্রিমিয়াম প্যাকেজ',
                'connection_count' => 10,
                'price' => 1000,
                'discount_price' => 800,
                'badge_text' => 'সেরা ভ্যালু',
                'is_active' => true,
            ],
        ];

        foreach ($packages as $package) {
            // updateOrCreate ব্যবহার করা নিরাপদ, এতে ডুপ্লিকেট হবে না
            ConnectionPackage::updateOrCreate(
                ['name' => $package['name']],
                $package
            );
        }
    }
}
