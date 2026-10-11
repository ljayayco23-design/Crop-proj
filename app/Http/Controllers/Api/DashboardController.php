<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FarmerReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DashboardController extends Controller
{
    /**
     * Same numbers the web farmer dashboard (farmer/dashboard.blade.php) shows,
     * scoped to the token's user only.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }

        $total   = DB::table('user_detections')->where('user_id', $user->id)->count();
        $affected = DB::table('user_detections')->where('user_id', $user->id)
            ->where('class_key', 'not like', '%healthy%')->count();
        $healthy = DB::table('user_detections')->where('user_id', $user->id)
            ->where('class_key', 'like', '%healthy%')->count();

        $recent = DB::table('user_detections')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['class_key', 'confidence', 'created_at']);

        // Reports table may not exist on a fresh local DB; never break the dashboard over it.
        try {
            $reports = FarmerReport::where('user_id', $user->id)->count();
        } catch (\Throwable $e) {
            $reports = 0;
        }

        $location = DB::table('barangays')
            ->join('cities', 'barangays.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id')
            ->where('barangays.id', $user->barangay_id)
            ->select(
                'provinces.id as province_id',
                'provinces.name as province_name',
                'cities.id as city_id',
                'cities.name as city_name',
                'barangays.id as barangay_id',
                'barangays.name as barangay_name'
            )
            ->first();

        return response()->json([
            'success' => true,
            'stats' => [
                'total_detections' => $total,
                'affected'         => $affected,
                'healthy'          => $healthy,
                'reports'          => $reports,
            ],
            'recent' => $recent,
            'address' => $location
                ? "{$location->barangay_name}, {$location->city_name}, {$location->province_name}"
                : 'Not yet set',
            // Used by the app's "Edit Farm Address" form to pre-select the
            // current Province / City / Barangay.
            'address_ids' => $location ? [
                'province_id' => $location->province_id,
                'city_id'     => $location->city_id,
                'barangay_id' => $location->barangay_id,
            ] : null,
            'farm' => [
                'name'     => $user->farm_name ?? ($user->full_name . "'s Farm"),
                'size'     => $user->farm_size,
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
            ],
        ]);
    }

    /**
     * POST /profile/address {province_id, city_id, barangay_id}
     *
     * Same save + same response as the website's AuthController@updateAddress
     * (route farmer.profile.address), plus a check that the barangay really
     * belongs to the chosen city and province.
     */
    public function updateAddress(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'province_id' => 'required|exists:provinces,id',
            'city_id'     => 'required|exists:cities,id',
            'barangay_id' => 'required|exists:barangays,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => collect($validator->errors()->all())->first() ?? 'Invalid selection.',
            ], 422);
        }

        // The three ids must belong together (province > city > barangay).
        $matches = DB::table('barangays')
            ->join('cities', 'barangays.city_id', '=', 'cities.id')
            ->where('barangays.id', $request->barangay_id)
            ->where('cities.id', $request->city_id)
            ->where('cities.province_id', $request->province_id)
            ->exists();

        if (!$matches) {
            return response()->json([
                'success' => false,
                'message' => 'The selected barangay does not belong to that city/province.',
            ], 422);
        }

        $user->province_id = $request->province_id;
        $user->city_id     = $request->city_id;
        $user->barangay_id = $request->barangay_id;
        $user->save();

        $location = DB::table('barangays')
            ->join('cities', 'barangays.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id')
            ->where('barangays.id', $user->barangay_id)
            ->select(
                'provinces.name as province_name',
                'cities.name as city_name',
                'barangays.name as barangay_name'
            )
            ->first();

        return response()->json([
            'success' => true,
            'address' => $location
                ? "{$location->barangay_name}, {$location->city_name}, {$location->province_name}"
                : 'Not yet set',
        ]);
    }
}