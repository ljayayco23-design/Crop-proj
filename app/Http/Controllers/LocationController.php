<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\City;
use App\Models\Barangay;

class LocationController extends Controller
{
    public function provinces()
    {
        return Province::orderBy('name')->get(['id', 'name']);
    }

    public function cities($provinceId)
    {
        return City::where('province_id', $provinceId)->orderBy('name')->get(['id', 'name']);
    }

    public function barangays($cityId)
    {
        return Barangay::where('city_id', $cityId)->orderBy('name')->get(['id', 'name']);
    }
}