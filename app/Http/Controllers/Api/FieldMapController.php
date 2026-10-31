<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FieldMapController as WebFieldMapController;
use App\Models\TreatmentRecord;
use App\Models\User;
use App\Models\WeatherAlert;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mobile (Flutter) side of "Field Map & Weather".
 *
 * Nothing is re-implemented here that the website already does:
 *  - saving pins/shapes calls the SAME WebFieldMapController::syncLayers()
 *  - weather calls the SAME WebFieldMapController::getWeather()
 * The only new thing is index(): the website passes this data to the Blade
 * view, the app needs it as JSON.
 */
class FieldMapController extends Controller
{
    // Same numbers as WebFieldMapController::index()
    private const STATUS_THRESHOLDS = [
        'healthy'    => 1,
        'monitoring' => 25,
        'at_risk'    => 60,
    ];

    private const STATUS_COLORS = [
        'No data'    => '#94a3b8',
        'Healthy'    => '#10b981',
        'Monitoring' => '#f59e0b',
        'At Risk'    => '#ef4444',
    ];

    private function denyIfNotFarmer(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }
        // The web controller reads Auth::id(); make sure it is the token's user.
        Auth::setUser($user);
        return null;
    }

    private function statusFor(int $total): string
    {
        if ($total <= 0) return 'No data';
        if ($total < self::STATUS_THRESHOLDS['monitoring']) return 'Healthy';
        if ($total < self::STATUS_THRESHOLDS['at_risk']) return 'Monitoring';
        return 'At Risk';
    }

    private function addressOf($model): string
    {
        return collect([
            optional($model->barangay)->name,
            optional($model->city)->name,
            optional($model->province)->name,
        ])->filter()->implode(', ');
    }

    /**
     * "Recent Alerts" card (PAGASA + forecast-based). Same rule as
     * WebFieldMapController::index(): this farmer's alerts from the last 3 days,
     * active first (newest first), expired last, max 5.
     * Wrapped so the Field Map still loads if the alerts table does not exist yet.
     */
    private function recentAlertsFor(User $user): array
    {
        try {
            return WeatherAlert::where('user_id', $user->id)
                ->where('issued_at', '>', now()->subDays(3))
                ->orderByDesc('issued_at')
                ->limit(12)
                ->get()
                ->map(function ($a) {
                    $exp = $a->expires_at ? Carbon::parse($a->expires_at) : null;
                    $a->is_expired = $exp ? $exp->isPast() : false;
                    return $a;
                })
                ->sortBy(fn ($a) => [
                    $a->is_expired ? 1 : 0,
                    -($a->issued_at ? Carbon::parse($a->issued_at)->timestamp : 0),
                ])
                ->take(5)
                ->values()
                ->map(function ($a) {
                    $fields = $a->fields ?? [];
                    if (is_string($fields)) {
                        $fields = json_decode($fields, true);
                    }
                    if (!is_array($fields)) {
                        $fields = [];
                    }
                    return [
                        'id'         => $a->id,
                        'source'     => $a->source,
                        'label'      => $a->label,
                        'level'      => $a->level,
                        'headline'   => $a->headline,
                        'fields'     => collect($fields)->pluck('name')->filter()->values()->all(),
                        'issued_at'  => $a->issued_at ? Carbon::parse($a->issued_at)->toIso8601String() : null,
                        'expires_at' => $a->expires_at ? Carbon::parse($a->expires_at)->toIso8601String() : null,
                        'is_expired' => (bool) $a->is_expired,
                    ];
                })
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** GET /field-map  -> everything the web page receives, as JSON. */
    public function index(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }

        $user = $request->user()->load(['province', 'city', 'barangay']);

        // ---- extra farms (users.additional_farms) ----
        $additional = $user->additional_farms ?? [];
        if (is_string($additional)) {
            $additional = json_decode($additional, true);
        }
        if (!is_array($additional)) {
            $additional = [];
        }

        // ---- drawn shapes (map_layers), same format as the web GET /field-map/sync ----
        $safeDecode = function ($data) {
            if (empty($data)) return [];
            if (is_string($data)) {
                $decoded = json_decode($data, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : [];
            }
            return $data;
        };
        $layers = DB::table('map_layers')->where('user_id', $user->id)->get()->map(function ($layer) use ($safeDecode) {
            return [
                'id'         => $layer->layer_id,
                'type'       => $layer->type,
                'geojson'    => $safeDecode($layer->geojson),
                'properties' => $safeDecode($layer->properties),
            ];
        })->values();

        // ---- automatic field status (same rule as the web: count of saved detections) ----
        $counts = DB::table('user_detections')
            ->where('user_id', $user->id)
            ->selectRaw("COALESCE(field_key, 'main') as fk, COUNT(*) as c")
            ->groupByRaw("COALESCE(field_key, 'main')")
            ->pluck('c', 'fk')
            ->map(fn ($c) => (int) $c)
            ->toArray();

        $statuses = ['main' => $this->statusFor($counts['main'] ?? 0)];
        foreach ($additional as $i => $farm) {
            $key = $farm['id'] ?? ('extra_' . $i);
            $statuses[$key] = $this->statusFor($counts[$key] ?? 0);
        }

        // ---- other farmers' pins ----
        $otherFarms = User::with(['province', 'city', 'barangay'])
            ->where('role', 'farmer')
            ->where('status', 'approved')
            ->where('id', '!=', $user->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'farm_name', 'farm_size', 'latitude', 'longitude', 'province_id', 'city_id', 'barangay_id')
            ->get()
            ->map(function ($farm) {
                return [
                    'id'        => $farm->id,
                    'farm_name' => $farm->farm_name,
                    'farm_size' => $farm->farm_size,
                    'latitude'  => $farm->latitude,
                    'longitude' => $farm->longitude,
                    'address'   => $this->addressOf($farm),
                ];
            })->values();

        // ---- "Last Report" widget ----
        $latest = null;
        try {
            $row = TreatmentRecord::where('user_id', $user->id)->latest()->first();
            if ($row) {
                $latest = [
                    'class_name' => $row->class_name ?? null,
                    'created_at' => $row->created_at ? $row->created_at->toIso8601String() : null,
                ];
            }
        } catch (\Throwable $e) {
            $latest = null;
        }

        return response()->json([
            'success' => true,
            'farm' => [
                'field_id'  => $user->field_id,
                'name'      => $user->farm_name,
                'size'      => $user->farm_size,
                'variety'   => $user->rice_variety,
                'latitude'  => $user->latitude !== null ? (float) $user->latitude : null,
                'longitude' => $user->longitude !== null ? (float) $user->longitude : null,
                'address'   => $this->addressOf($user),
            ],
            'additional_farms'  => $additional,
            'layers'            => $layers,
            'other_farms'       => $otherFarms,
            'field_counts'      => (object) $counts,
            'field_statuses'    => (object) $statuses,
            'status_colors'     => self::STATUS_COLORS,
            'status_thresholds' => self::STATUS_THRESHOLDS,
            'latest_detection'  => $latest,
            'recent_alerts'     => $this->recentAlertsFor($user),
        ]);
    }

    /** POST /field-map/sync {layers:[...]} -> same save as the website. */
    public function sync(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        return app(WebFieldMapController::class)->syncLayers($request);
    }

    /** GET /field-map/weather?lat=&lon= -> same weather + risk as the website. */
    public function weather(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $res = app(WebFieldMapController::class)->getWeather($request);

        if ($res->getStatusCode() === 200) {
            $data = $res->getData(true);
            $data['success'] = true;
            return response()->json($data);
        }
        return $res;
    }
}