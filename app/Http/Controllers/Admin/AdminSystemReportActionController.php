<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerReportController;
use App\Models\FarmerReport;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Admin actions on an escalated report, from the System Report page's
 * View modal:
 *
 *   technicians()  assignable technicians (same province + city as the admin)
 *   resolve()      admin status + notes
 *   assign()       pick a technician + priority + private notes
 *   destroy()      remove the report from the System Reports list
 *
 * Needs these farmer_reports columns (see the migration):
 *   admin_notes, assigned_technician_id, assigned_priority, assigned_notes
 */
class AdminSystemReportActionController extends Controller
{
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** GET /admin/system-report/technicians -> [{id, name, barangay}] */
    public function technicians()
    {
        $actor = $this->actor();

        $query = User::with('barangay')
            ->where('role', 'technician')
            ->where('status', 'approved');

        if ($actor->role !== 'developer') {
            $query->where('province_id', $actor->province_id)
                  ->where('city_id', $actor->city_id);
        }

        $techs = $query->orderBy('full_name')->get()->map(fn ($t) => [
            'id'       => $t->id,
            'name'     => $t->full_name,
            'barangay' => $t->barangay->name ?? null,
        ])->values();

        return response()->json($techs)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    /** POST /admin/system-report/{report}/resolve  { status, notes } */
    public function resolve(Request $request, $report)
    {
        $actor = $this->actor();

        if ($missing = $this->missingColumns()) {
            return $missing;
        }

        $row = $this->scopedQuery($actor)->findOrFail($report);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(FarmerReportController::ADMIN_STATUSES)),
            'notes'  => 'required|string|max:2000',
        ]);

        $row->forceFill([
            'admin_status'            => $data['status'],
            'admin_notes'             => $data['notes'],
            'admin_status_updated_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'status'  => $data['status'],
            'label'   => FarmerReportController::ADMIN_STATUSES[$data['status']]['label'],
            'notes'   => $data['notes'],
        ]);
    }

    /** POST /admin/system-report/{report}/assign  { technician_id, priority, notes } */
    public function assign(Request $request, $report)
    {
        $actor = $this->actor();

        if ($missing = $this->missingColumns()) {
            return $missing;
        }

        $row = $this->scopedQuery($actor)->findOrFail($report);

        $data = $request->validate([
            'technician_id' => 'required|integer',
            'priority'      => 'required|in:' . implode(',', self::PRIORITIES),
            'notes'         => 'nullable|string|max:2000',
        ]);

        // Must be a real, approved technician inside this admin's area.
        $techQuery = User::where('role', 'technician')
            ->where('status', 'approved')
            ->where('id', $data['technician_id']);

        if ($actor->role !== 'developer') {
            $techQuery->where('province_id', $actor->province_id)
                      ->where('city_id', $actor->city_id);
        }

        $tech = $techQuery->first();
        if (!$tech) {
            return response()->json([
                'success' => false,
                'message' => 'That technician is not available in your province and city.',
            ], 422);
        }

        // Assigning counts as the admin having looked at it: pending -> open.
        $current = array_key_exists((string) $row->admin_status, FarmerReportController::ADMIN_STATUSES)
            ? $row->admin_status : 'pending';
        $newStatus = $current === 'pending' ? 'open' : $current;

        $row->forceFill([
            'assigned_technician_id'  => $tech->id,
            'assigned_priority'       => $data['priority'],
            'assigned_notes'          => $data['notes'] ?? null,
            'admin_status'            => $newStatus,
            'admin_status_updated_at' => $newStatus !== $current ? now() : $row->admin_status_updated_at,
        ])->save();

        return response()->json([
            'success'   => true,
            'tech_name' => $tech->full_name,
            'priority'  => $data['priority'],
            'notes'     => $data['notes'] ?? null,
            'status'    => $newStatus,
        ]);
    }

    /**
     * DELETE /admin/system-report/{report}
     *
     * Removes the report from the System Reports list ONLY. The farmer's
     * report and the technician's review stay untouched; the escalation
     * flags and the admin's own work are cleared.
     */
    public function destroy($report)
    {
        $actor = $this->actor();

        $row = $this->scopedQuery($actor)->findOrFail($report);

        $clear = [
            'escalated_at'            => null,
            'admin_status'            => null,
            'admin_status_updated_at' => null,
        ];
        foreach (['admin_notes', 'assigned_technician_id', 'assigned_priority', 'assigned_notes'] as $col) {
            if (Schema::hasColumn('farmer_reports', $col)) {
                $clear[$col] = null;
            }
        }

        $row->forceFill($clear)->save();

        return response()->json(['success' => true]);
    }

    // ---------------------------------------------------------------------

    private function actor()
    {
        $actor = auth()->user();
        if (!in_array($actor->role, ['admin', 'developer'], true)) {
            abort(403, 'Only an admin account can manage system reports.');
        }
        return $actor;
    }

    /** Escalated reports only; a normal admin sees their own province + city. */
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

    private function missingColumns()
    {
        foreach (['admin_notes', 'assigned_technician_id', 'assigned_priority', 'assigned_notes'] as $col) {
            if (!Schema::hasColumn('farmer_reports', $col)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Run "php artisan migrate" first (add_admin_columns_to_farmer_reports).',
                ], 503);
            }
        }
        return null;
    }
}