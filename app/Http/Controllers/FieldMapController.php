<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Services\WeatherAlertService;

class FieldMapController extends Controller
{
public function index()
    {
        $apiKey = env('WEATHER_API_KEY');
        $city   = "Sagay City";
        $url    = "http://api.weatherapi.com/v1/forecast.json";

        $user = auth()->user()->load(['province', 'city', 'barangay']);
        // Fetch User Registration Map Data
        $userLat  = $user->latitude;
        $userLng  = $user->longitude;
        $farmName = $user->farm_name;
        $farmSize = $user->farm_size;

        // Fetch New Crop & Detection Additions
        $growthStage     = $user->growth_stage;
        $riceVariety     = $user->rice_variety;
        $userAddress = collect([
            optional($user->barangay)->name,
            optional($user->city)->name,
            optional($user->province)->name,
        ])->filter()->implode(', ');
        $latestDetection = \App\Models\TreatmentRecord::where('user_id', $user->id)->latest()->first();

        // Recent weather alerts for the "Recent Alerts" card (farmers only): active first, expired ones last.
        // Wrapped so the Field Map still loads if the alerts table doesn't exist yet.
        $recentAlerts = collect();
        if ($user->role === 'farmer') {
            try {
                $recentAlerts = \App\Models\WeatherAlert::where('user_id', $user->id)
                    ->where('issued_at', '>', now()->subDays(3))
                    ->orderByDesc('issued_at')
                    ->limit(12)
                    ->get()
                    ->map(function ($a) {
                        // Flag alerts whose validity window has passed (shown dimmed with an "Expired" tag).
                        $exp = $a->expires_at ? \Carbon\Carbon::parse($a->expires_at) : null;
                        $a->is_expired = $exp ? $exp->isPast() : false;
                        return $a;
                    })
                    // Active alerts first (newest first), then expired ones (newest first).
                    ->sortBy(fn ($a) => [
                        $a->is_expired ? 1 : 0,
                        -($a->issued_at ? \Carbon\Carbon::parse($a->issued_at)->timestamp : 0),
                    ])
                    ->take(5)
                    ->values();
            } catch (\Throwable $e) {
                $recentAlerts = collect();
            }
        }
        
        // Load the new additional farms column
$additionalFarmsJson = json_encode($user->additional_farms ?? []);


        // ================= AUTOMATIC FIELD STATUS (based on saved detection history) =================
        // Tune these any time — they control when a field's status label/pin color changes.
        $statusThresholds = [
            'healthy'    => 1,   // >= this many total detections -> "Healthy"
            'monitoring' => 25,  // >= this many total detections -> "Monitoring"
            'at_risk'    => 60,  // >= this many total detections -> "At Risk"
        ];
        $fieldStatusColors = [
            'No data'    => '#94a3b8',
            'Healthy'    => '#10b981',
            'Monitoring' => '#f59e0b',
            'At Risk'    => '#ef4444',
        ];

        $computeFieldStatus = function ($fieldKey) use ($user, $statusThresholds) {
            $query = DB::table('user_detections')->where('user_id', $user->id);
            if ($fieldKey === 'main') {
                $query->where(function ($q) {
                    $q->where('field_key', 'main')->orWhereNull('field_key');
                });
            } else {
                $query->where('field_key', $fieldKey);
            }
            $total = $query->count();

            if ($total <= 0) return 'No data';
            if ($total < $statusThresholds['monitoring']) return 'Healthy';
            if ($total < $statusThresholds['at_risk']) return 'Monitoring';
            return 'At Risk';
        };

        $fieldStatuses = ['main' => $computeFieldStatus('main')];
        foreach (($user->additional_farms ?? []) as $i => $farm) {
            $key = $farm['id'] ?? ('extra_' . $i);
            $fieldStatuses[$key] = $computeFieldStatus($key);
        }
        // ================================================================================================

                $otherFarms = \App\Models\User::with(['province', 'city', 'barangay'])
            ->where('role', 'farmer')
            ->where('status', 'approved')
            ->where('id', '!=', $user->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'farm_name', 'farm_size', 'latitude', 'longitude', 'province_id', 'city_id', 'barangay_id')
            ->get()
            ->map(function ($farm) {
                $farm->address = collect([
                    optional($farm->barangay)->name,
                    optional($farm->city)->name,
                    optional($farm->province)->name,
                ])->filter()->implode(', ');
                return $farm;
            });

        $viewTemplate = $user->role === 'technician' ? 'technician.field_map' : 'farmer.field_map';

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

            return view($viewTemplate, compact(
                'temp', 'condition', 'humidity', 'wind', 'rain', 'alerts', 'riskLevel', 'riskColor', 
                'userLat', 'userLng', 'farmName', 'farmSize', 'otherFarms',
                'growthStage', 'riceVariety', 'userAddress', 'latestDetection', 'additionalFarmsJson',
                'fieldStatuses', 'fieldStatusColors', 'recentAlerts'
            ));

             } catch (\Exception $e) {
            return view($viewTemplate, compact(
                'userLat', 'userLng', 'farmName', 'farmSize', 'otherFarms',
                'growthStage', 'riceVariety', 'userAddress', 'latestDetection', 'additionalFarmsJson',
                'fieldStatuses', 'fieldStatusColors', 'recentAlerts'
            ))->with('error', 'Weather service is temporarily offline or timed out.');
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
            if (!$userId) return response()->json(['error' => 'User not authenticated'], 401);

            if ($request->isMethod('post')) {
                $layers = $request->input('layers', []);

                DB::transaction(function () use ($userId, $layers) {
                    DB::table('map_layers')->where('user_id', $userId)->delete();

                    $mainFarmPin = null;
                    $additionalFarms = [];
                    $mainPinDeleted = false;

                    foreach ($layers as $layer) {
                        if (($layer['type'] ?? '') === 'DeletedFarmPin') {
                            $mainPinDeleted = true;
                            continue;
                        }

                        $layerId = $layer['id'] ?? uniqid();
                        $props = $layer['properties'] ?? [];
                        $options = $props['options'] ?? [];

                        $isFarmPin = isset($options['isFarmPin']) && $options['isFarmPin'];
                        $isMainFarm = isset($options['isMainFarm']) && $options['isMainFarm'];

                        if ($isFarmPin) {
                            $farmData = [
                                'id'        => $layerId,
                                'coords'    => $layer['geojson']['geometry']['coordinates'] ?? null,
                                'options'   => $options,
                                'placeName' => $props['placeName'] ?? null
                            ];

                            if ($isMainFarm) {
                                $mainFarmPin = $farmData;
                            } else {
                                $additionalFarms[] = $farmData;
                            }
                        } else {
                            // Standard shapes go back to map_layers
                            DB::table('map_layers')->insert([
                                'user_id'    => $userId,
                                'layer_id'   => $layerId,
                                'type'       => $layer['type'] ?? 'Shape',
                                'geojson'    => is_array($layer['geojson']) || is_object($layer['geojson']) ? json_encode($layer['geojson']) : $layer['geojson'],
                                'properties' => is_array($props) || is_object($props) ? json_encode($props) : $props,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $updateData = [];

                    if ($mainPinDeleted) {
                        $updateData['field_id'] = null;
                        $updateData['latitude'] = null;
                        $updateData['longitude'] = null;
                    } elseif ($mainFarmPin && $mainFarmPin['coords']) {
                        $updateData['field_id']     = $mainFarmPin['id'];
                        $updateData['latitude']     = $mainFarmPin['coords'][1];
                        $updateData['longitude']    = $mainFarmPin['coords'][0];
                        $updateData['farm_name']    = $mainFarmPin['options']['farmName'] ?? DB::raw('farm_name');
                        $updateData['farm_size']    = $mainFarmPin['options']['farmSize'] ?? DB::raw('farm_size');
                        $updateData['rice_variety'] = $mainFarmPin['options']['farmVariety'] ?? DB::raw('rice_variety');
                    }

                    // Save additional fields into the users table using the new column
                    $updateData['additional_farms'] = json_encode($additionalFarms);

                    if (!empty($updateData)) {
                        DB::table('users')->where('id', $userId)->update($updateData);
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
            return response()->json(['error' => 'Server Crash', 'message' => $e->getMessage()], 500);
        }
    }

    public function triggerWeatherCron(Request $request, WeatherAlertService $alerts)
    {
        // Fail closed: an empty CRON_SECRET must never let "Bearer " through.
        $secret = (string) env('CRON_SECRET');
        $given  = (string) $request->bearerToken();
        if ($secret === '' || !hash_equals($secret, $given)) {
            return response()->json(['error' => 'Unauthorized request'], 401);
        }

        @set_time_limit(90);

        try {
            // Behaviour depends on the mode saved by the admin: off | dry_run | live.
            $result = $alerts->runCron();
        } catch (\Throwable $e) {
            Log::error('[WeatherCron] ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }

        return response()->json($result + ['message' => 'Automated weather sweep completed.']);
    }
}