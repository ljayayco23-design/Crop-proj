<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FieldMapController extends Controller
{
public function index()
    {
        $apiKey = env('WEATHER_API_KEY');
        $city   = "Sagay City";
        $url    = "http://api.weatherapi.com/v1/forecast.json";

        // Fetch User Registration Map Data
        $userLat  = Auth::user()->latitude;
        $userLng  = Auth::user()->longitude;
        $farmName = Auth::user()->farm_name;
        $farmSize = Auth::user()->farm_size;

        $otherFarms = \App\Models\User::where('role', 'farmer')
            ->where('status', 'approved')
            ->where('id', '!=', auth()->id()) // Exclude myself
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('farm_name', 'farm_size', 'latitude', 'longitude', 'address') 
            ->get();

        // Evaluate user authorization scope to build view target destination path
        $viewTemplate = auth()->user()->role === 'technician' ? 'technician.field_map' : 'farmer.field_map';

        try {
            $response = Http::timeout(5)->get($url, [
                'key'    => $apiKey,
                'q'      => $city,
                'days'   => 1,
                'aqi'    => 'no',
                'alerts' => 'yes'
            ]);

            if ($response->failed()) throw new \Exception('API request failed');

            $data = $response->json();
            if (isset($data['error'])) throw new \Exception('API Error');

            $current   = $data['current'];
            $forecast  = $data['forecast']['forecastday'][0]['day'];

            $temp      = round($current['temp_c']);
            $condition = $current['condition']['text'];
            $humidity  = $current['humidity'];
            $wind      = round($current['wind_kph']);
            $rain      = $forecast['daily_chance_of_rain'];

            $alerts    = [];
            $riskLevel = "Low";
            $riskColor = "emerald";

            if ($rain > 70 || $humidity > 88) {
                $alerts[] = "🔴 HIGH RISK: Sheath Blight & Rice Blast likely. Drain fields.";
                $riskLevel = "High";
                $riskColor = "red";
            } elseif ($rain > 50 || $humidity > 82) {
                $alerts[] = "🟡 MEDIUM RISK: Brown Spot & Fungal diseases possible.";
                $riskLevel = "Medium";
                $riskColor = "yellow";
            }

            if ($temp > 34) $alerts[] = "🔥 Heat stress warning — Water early morning/late afternoon.";
            if ($wind > 25) $alerts[] = "💨 Strong winds — Check for lodging risk on tall varieties.";
            if (empty($alerts)) $alerts[] = "✅ Good weather conditions for rice today.";

            // Passing variables straight into the dynamic string path template
            return view($viewTemplate, compact(
                'temp', 'condition', 'humidity', 'wind', 'rain', 'alerts', 'riskLevel', 'riskColor', 'userLat', 'userLng', 'farmName', 'farmSize', 'otherFarms'
            ));

        } catch (\Exception $e) {
            // Apply the dynamic target destination in case of exception handling fallbacks as well
            return view($viewTemplate, compact('userLat', 'userLng', 'farmName', 'farmSize', 'otherFarms'))
                   ->with('error', 'Weather service is temporarily offline or timed out.');
        }
    }

    public function getWeather(Request $request)
    {
        $lat = $request->input('lat');
        $lon = $request->input('lon');
        $apiKey = env('WEATHER_API_KEY');
        $url    = "http://api.weatherapi.com/v1/forecast.json";

        if (!$lat || !$lon) return response()->json(['error' => 'Missing coordinates.'], 400);

        try {
            $response = Http::timeout(5)->get($url, [
                'key'    => $apiKey,
                'q'      => "{$lat},{$lon}",
                'days'   => 1,
                'aqi'    => 'no',
                'alerts' => 'yes'
            ]);

            if ($response->failed()) throw new \Exception('API request failed');

            $data = $response->json();
            if (isset($data['error'])) throw new \Exception('API Error');

            $current   = $data['current'];
            $forecast  = $data['forecast']['forecastday'][0]['day'];

            $temp      = round($current['temp_c']);
            $condition = $current['condition']['text'];
            $humidity  = $current['humidity'];
            $wind      = round($current['wind_kph']);
            $rain      = $forecast['daily_chance_of_rain'];

            $alerts    = [];
            $riskLevel = "Low";
            $riskColor = "emerald";

            if ($rain > 70 || $humidity > 88) {
                $alerts[] = "🔴 HIGH RISK: Sheath Blight & Rice Blast likely.";
                $riskLevel = "High";
                $riskColor = "red";
            } elseif ($rain > 50 || $humidity > 82) {
                $alerts[] = "🟡 MEDIUM RISK: Brown Spot & Fungal diseases possible.";
                $riskLevel = "Medium";
                $riskColor = "yellow";
            }

            if ($temp > 34) $alerts[] = "🔥 Heat stress warning — Water early morning/late afternoon.";
            if ($wind > 25) $alerts[] = "💨 Strong winds — Check for lodging risk on tall varieties.";
            if (empty($alerts)) $alerts[] = "✅ Good weather conditions for rice today.";

            return response()->json([
                'temp' => $temp, 'condition' => $condition, 'humidity' => $humidity,
                'wind' => $wind, 'rain' => $rain, 'alerts' => $alerts,
                'riskLevel' => $riskLevel, 'riskColor' => $riskColor
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Weather service offline.'], 500);
        }
    }

    public function syncLayers(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            if ($request->isMethod('post')) {
                $layers = $request->input('layers', []);

                DB::transaction(function () use ($userId, $layers) {
                    DB::table('map_layers')->where('user_id', $userId)->delete();

                    foreach ($layers as $layer) {
                        DB::table('map_layers')->insert([
                            'user_id'    => $userId,
                            'layer_id'   => $layer['id'] ?? uniqid(),
                            'type'       => $layer['type'] ?? 'Shape',
                            'geojson'    => is_array($layer['geojson']) || is_object($layer['geojson']) ? json_encode($layer['geojson']) : $layer['geojson'],
                            'properties' => is_array($layer['properties']) || is_object($layer['properties']) ? json_encode($layer['properties']) : $layer['properties'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                });

                return response()->json(['status' => 'success']);
            }

            if ($request->isMethod('get')) {
                $layers = DB::table('map_layers')->where('user_id', $userId)->get();

                $formattedLayers = $layers->map(function ($layer) {
                    $safeDecode = function($data) {
                        if (empty($data)) return [];
                        if (is_string($data)) {
                            $decoded = json_decode($data, true);
                            return (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
                        }
                        return $data; 
                    };

                    return [
                        'id'         => $layer->layer_id,
                        'type'       => $layer->type,
                        'geojson'    => $safeDecode($layer->geojson),
                        'properties' => $safeDecode($layer->properties),
                    ];
                });

                return response()->json($formattedLayers);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Server Crash',
                'message' => $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }

    public function triggerWeatherCron(Request $request)
    {
        if ($request->header('Authorization') !== 'Bearer ' . env('CRON_SECRET')) {
            return response()->json(['error' => 'Unauthorized request'], 401);
        }

        $apiKey = env('WEATHER_API_KEY');
        $alertsProcessed = 0;
        
        $response = Http::get("http://api.weatherapi.com/v1/forecast.json", [
            'key'    => $apiKey,
            'q'      => "Sagay City",
            'days'   => 3,
            'alerts' => 'yes'
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            if (isset($data['alerts']['alert']) && !empty($data['alerts']['alert'])) {
                foreach($data['alerts']['alert'] as $alert) {
                    $alertsProcessed++;
                }
            }
        }

        return response()->json([
            'status' => 'success', 
            'alerts_found' => $alertsProcessed,
            'message' => 'Automated weather sweep completed.'
        ]);
    }
}