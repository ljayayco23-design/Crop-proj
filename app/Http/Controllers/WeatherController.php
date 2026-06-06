<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class WeatherController extends Controller
{
    public function index()
    {
        // 🔑 Hardcoded API Key
        $apiKey = env('WEATHER_API_KEY');
        $city   = "Sagay City";
        $url    = "http://api.weatherapi.com/v1/forecast.json";

        // Fetch data using Laravel's HTTP client
        $response = Http::get($url, [
            'key'    => $apiKey,
            'q'      => $city,
            'days'   => 1,
            'aqi'    => 'no',
            'alerts' => 'yes'
        ]);

        // If the HTTP request fails entirely (e.g., timeout, bad DNS)
        if ($response->failed()) {
            return view('farmer.weather')->with('error', 'Unable to fetch weather data. The API request failed.');
        }

        $data = $response->json();

        // If the API returns a specific error (like an invalid key)
        if (isset($data['error'])) {
            return view('farmer.weather')->with('error', 'API Error: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        // Parse successful data
        $current   = $data['current'];
        $forecast  = $data['forecast']['forecastday'][0]['day'];

        $temp      = round($current['temp_c']);
        $condition = $current['condition']['text'];
        $humidity  = $current['humidity'];
        $wind      = round($current['wind_kph']);
        $rain      = $forecast['daily_chance_of_rain'];

        // Advanced Rice Farmer Risk Logic
        $alerts    = [];
        $riskLevel = "Low";
        $riskColor = "success";

        if ($rain > 70 || $humidity > 88) {
            $alerts[] = "🔴 HIGH RISK: Sheath Blight & Rice Blast likely. Drain fields and apply fungicide if needed.";
            $riskLevel = "High";
            $riskColor = "danger";
        } elseif ($rain > 50 || $humidity > 82) {
            $alerts[] = "🟡 MEDIUM RISK: Brown Spot & Fungal diseases possible. Increase scouting.";
            $riskLevel = "Medium";
            $riskColor = "warning";
        }

        if ($temp > 34) {
            $alerts[] = "🔥 Heat stress warning — Water early morning or late afternoon.";
        }
        if ($wind > 25) {
            $alerts[] = "💨 Strong winds — Check for lodging risk on tall varieties.";
        }
        if ($humidity > 85 && $rain < 30) {
            $alerts[] = "🦠 High humidity — Brown Planthopper risk rising. Scout fields today.";
        }

        if (empty($alerts)) {
            $alerts[] = "✅ Good weather conditions for rice today. Continue normal practices.";
        }

        return view('farmer.weather', compact(
            'temp', 'condition', 'humidity', 'wind', 'rain', 'alerts', 'riskLevel', 'riskColor'
        ));
    }
}