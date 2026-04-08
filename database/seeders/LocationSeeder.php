<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Division;
use App\Models\District;
use App\Models\Upazila;
use App\Models\Union;
use Illuminate\Support\Facades\DB;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Union::truncate();
        Upazila::truncate();
        District::truncate();
        Division::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $load = function ($file) {
            $path = storage_path("app/data/$file");
            $contents = file_get_contents($path);
            $contents = str_replace("\xEF\xBB\xBF", '', $contents);
            $data = json_decode($contents, true);
            foreach ($data as $item) {
                if (isset($item['type']) && $item['type'] === 'table') {
                    return $item['data'];
                }
            }
            return [];
        };

        // Divisions
        $divisions = $load('divisions.json');
        $this->command->info("Divisions loaded: " . count($divisions));
        foreach ($divisions as $row) {
            Division::create([
                'id'      => $row['id'],
                'name'    => $row['name'],
                'bn_name' => $row['bn_name'],
            ]);
        }

        // Districts
        $districts = $load('districts.json');
        $this->command->info("Districts loaded: " . count($districts));
        foreach ($districts as $row) {
            District::create([
                'id'          => $row['id'],
                'division_id' => $row['division_id'],
                'name'        => $row['name'],
                'bn_name'     => $row['bn_name'],
                'lat'         => $row['lat'] ?? null,
                'lon'         => $row['lon'] ?? null,
            ]);
        }

        // Upazilas
        $upazilas = $load('upazilas.json');
        $this->command->info("Upazilas loaded: " . count($upazilas));
        foreach ($upazilas as $row) {
            Upazila::create([
                'id'          => $row['id'],
                'district_id' => $row['district_id'],
                'name'        => $row['name'],
                'bn_name'     => $row['bn_name'],
            ]);
        }

        // Unions
        $unions = $load('unions.json');
        $this->command->info("Unions loaded: " . count($unions));
        foreach ($unions as $row) {
            Union::create([
                'id'         => $row['id'],
                'upazila_id' => $row['upazilla_id'],
                'name'       => $row['name'],
                'bn_name'    => $row['bn_name'],
            ]);
        }
    }
}
