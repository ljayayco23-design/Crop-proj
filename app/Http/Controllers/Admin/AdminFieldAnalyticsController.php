<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminFieldAnalyticsController extends Controller
{
    /**
     * Field Analytics: approved farmers grouped by the barangay they
     * registered in, with farmer count + total detections per barangay.
     *
     *   - admin     -> only farmers in the admin's own Province + City
     *                  (same scoping as AdminUserController@userLog).
     *   - developer -> every farmer, grouped by City > Barangay.
     *
     * "Detections" = rows in user_detections (same table FieldMapController
     * uses for the field status pins).
     */
    public function index()
    {
        $actor       = auth()->user();
        $isDeveloper = $actor->role === 'developer';

        $farmers = User::with(['city', 'barangay'])
            ->where('role', 'farmer')
            ->where('status', 'approved')
            ->when(!$isDeveloper, fn ($q) => $q->where('province_id', $actor->province_id)
                                               ->where('city_id', $actor->city_id))
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'farm_name', 'province_id', 'city_id', 'barangay_id']);

        // One query for all detection totals: [user_id => total]
        $detectionTotals = DB::table('user_detections')
            ->whereIn('user_id', $farmers->pluck('id'))
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $barangays = $farmers
            ->groupBy(fn ($f) => ($f->city_id ?? 0) . '-' . ($f->barangay_id ?? 0))
            ->map(function ($group) use ($detectionTotals) {
                $first = $group->first();

                $members = $group->map(fn ($f) => [
                    'name'       => $f->full_name,
                    'farm_name'  => $f->farm_name,
                    'detections' => (int) ($detectionTotals[$f->id] ?? 0),
                ])->sortByDesc('detections')->values();

                $detections = $members->sum('detections');

                return [
                    'barangay'   => optional($first->barangay)->name ?? 'No barangay set',
                    'city'       => optional($first->city)->name,
                    'farmers'    => $group->count(),
                    'detections' => $detections,
                    'average'    => $group->count() ? round($detections / $group->count(), 1) : 0,
                    'members'    => $members,
                ];
            })
            ->sortByDesc('detections')
            ->values();

        $totals = [
            'barangays'  => $barangays->count(),
            'farmers'    => $barangays->sum('farmers'),
            'detections' => $barangays->sum('detections'),
        ];

        $maxDetections = max(1, (int) $barangays->max('detections'));
        $scopeLabel    = $isDeveloper ? 'All cities' : (optional($actor->city)->name ?? 'Your city');

        return view('admin.field_analytics', compact(
            'barangays', 'totals', 'maxDetections', 'scopeLabel', 'isDeveloper'
        ));
    }
}