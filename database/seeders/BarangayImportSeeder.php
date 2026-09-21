<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use Illuminate\Database\Seeder;

class BarangayImportSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/barangays.csv');

        if (!file_exists($path)) {
            $this->command->error("CSV not found at $path");
            return;
        }

        // Adjust this if your province name in the `provinces` table is spelled differently
        $province = Province::firstOrCreate(['name' => 'Negros Occidental']);

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle); // skip header row

        $cityCache = [];
        $created = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            [$cityName, $barangayName] = $row;

            if (!isset($cityCache[$cityName])) {
                $cityCache[$cityName] = City::firstOrCreate(
                    ['name' => $cityName, 'province_id' => $province->id]
                );
            }

            $city = $cityCache[$cityName];

            $exists = Barangay::where('city_id', $city->id)
                ->where('name', $barangayName)
                ->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            Barangay::create([
                'city_id' => $city->id,
                'name' => $barangayName,
            ]);

            $created++;
        }

        fclose($handle);

        $this->command->info("Barangays created: $created, skipped (duplicates): $skipped");
        $this->command->info('Cities involved: ' . count($cityCache));
    }
}