<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerReportController;
use App\Models\FarmerReport;
use Illuminate\Http\Request;

/**
 * Admin "System Report" page: the reports technicians escalated
 * (FarmerReportController@escalate), and the admin's own progress on them.
 *
 * The admin's status lives in farmer_reports.admin_status and is completely
 * separate from the farmer-facing `status` (pending / waiting_farmer /
 * resolved), so nothing the admin does here changes what the farmer or the
 * technician see.
 *
 *   pending      escalated, admin hasn't looked yet
 *   open         set automatically the first time the admin clicks View
 *   in_progress  admin picks "In Progress"
 *   resolved     admin picks "Resolved"
 */
class AdminSystemReportController extends Controller
{
    /** Same labels the technician's "Escalated to admin" list uses. */
    public const STATUSES = FarmerReportController::ADMIN_STATUSES;

    public const TYPES = ['AI Model', 'Knowledge Base', 'System'];

    public function index()
    {
        $actor = $this->actor();

        // Same presenter the technician's "Escalated to admin" list uses, so
        // both sides show the identical row and modal data.
        $presenter = app(FarmerReportController::class);

        $reports = $this->scopedQuery($actor)
            ->orderByDesc('escalated_at')
            ->get()
            ->map(fn (FarmerReport $r) => $presenter->presentEscalated($r))
            ->values()
            ->all();

        // "Wrong Detection" summary: how many times each disease/pest name
        // was escalated as a wrong AI result, across every escalated report
        // this admin can see (same scoping as the table below). Keyed by the
        // ORIGINAL detection's class_name (what the AI called it), since
        // that's the name the report is actually complaining about.
        $wrongDetectionStats = collect($reports)
            ->groupBy(fn (array $r) => $r['detection']['class_name'] ?? 'Unknown')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->all();

        return view('admin.admin_report', [
            'reports'             => $reports,
            'statuses'            => self::STATUSES,
            'types'               => self::TYPES,
            'sections'            => FarmerReportController::SECTIONS,
            'wrongDetectionStats' => $wrongDetectionStats,
        ]);
    }

    /**
     * Three-dot menu actions. `view` only ever moves pending -> open (so
     * re-viewing a report that is already in progress / resolved never
     * downgrades it); in_progress and resolved set that status directly.
     */
    public function updateStatus(Request $request, $id)
    {
        $actor = $this->actor();

        $data = $request->validate([
            'action' => 'required|in:view,in_progress,resolved',
        ]);

        // Scoped findOrFail: only escalated reports this admin may see.
        $report = $this->scopedQuery($actor)->findOrFail($id);

        $current = $this->normalizeStatus($report->admin_status);

        $new = match ($data['action']) {
            'view'        => $current === 'pending' ? 'open' : $current,
            'in_progress' => 'in_progress',
            'resolved'    => 'resolved',
        };

        if ($new !== $current) {
            $report->forceFill([
                'admin_status'            => $new,
                'admin_status_updated_at' => now(),
            ])->save();
        }

        return response()->json([
            'success' => true,
            'status'  => $new,
            'label'   => self::STATUSES[$new]['label'],
        ]);
    }

    /** Same role gate the rest of the admin panel uses ('developer' reuses it). */
    private function actor()
    {
        $actor = auth()->user();
        if (!in_array($actor->role, ['admin', 'developer'], true)) {
            abort(403, 'Only an admin account can view system reports.');
        }
        return $actor;
    }

    /**
     * Escalated reports only. A normal admin sees escalations from farmers in
     * their OWN province + city (same scoping as AdminUserController@userLog);
     * developer is unrestricted.
     */
    private function scopedQuery($actor)
    {
        $query = FarmerReport::with('farmer')->whereNotNull('escalated_at');

        if ($actor->role !== 'developer') {
            $query->whereHas('farmer', function ($q) use ($actor) {
                $q->where('province_id', $actor->province_id)
                  ->where('city_id', $actor->city_id);
            });
        }

        return $query;
    }

    private function normalizeStatus($status): string
    {
        return array_key_exists((string) $status, self::STATUSES) ? $status : 'pending';
    }
}