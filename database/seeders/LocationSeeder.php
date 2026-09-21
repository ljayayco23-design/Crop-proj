<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Province;
use App\Models\City;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $province = Province::firstOrCreate(['name' => 'Negros Occidental']);

        $cities = [
            // Cities
            'Bacolod', 'Bago', 'Cadiz', 'Escalante', 'Himamaylan', 'Kabankalan',
            'La Carlota', 'Sagay', 'San Carlos', 'Silay', 'Sipalay', 'Talisay', 'Victorias',
            // Municipalities
            'Binalbagan', 'Calatrava', 'Candoni', 'Cauayan', 'Don Salvador Benedicto',
            'Enrique B. Magalona', 'Hinigaran', 'Hinoba-an', 'Ilog', 'Isabela',
            'La Castellana', 'Manapla', 'Moises Padilla', 'Murcia', 'Pontevedra',
            'Pulupandan', 'San Enrique', 'Toboso', 'Valladolid',
        ];

        foreach ($cities as $cityName) {
            City::firstOrCreate(['province_id' => $province->id, 'name' => $cityName]);
        }
    }
}