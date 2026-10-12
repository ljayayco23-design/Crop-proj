<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerReportController;
use App\Models\FarmerFeedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Admin side of the farmer "Suggest Improvement" / "General Feedback" entries.
 *
 *   list()     rows for the System Report page (suggestions or general feedback)
 *   assign()   admin picks a technician (+ priority + private notes)
 *   resolve()  admin resolves the suggestion HIMSELF (status + notes)
 *
 * Scope = same as the escalated reports: a normal admin only sees farmers in
 * their OWN province + city, 'developer' sees everything.
 */
class AdminSuggestionController extends Controller
{
    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /** Rows for the page. $type = suggestion | general. */
    public function list($actor, string $type = FarmerFeedback::TYPE_SUGGESTION): array
    {
        if (!Schema::hasTable('farmer_feedback')) {
            return [];
        }

        $entries   = $this->scopedQuery($actor)->where('type', $type)->latest()->get();
        $presenter = app(FarmerReportController::class);
        $names     = $presenter->techNamesFor($entries);

        return $entries
            ->map(fn (FarmerFeedback $f) => $presenter->presentFeedback($f, 'admin', $actor->id, $names))
            ->values()
            ->all();
    }

    /** Admin -> technician. Only a suggestion can be assigned. */
    public function assign(Request $request, $feedback)
    {
        $actor = $this->actor();

        if ($missing = $this->missingColumns()) {
            return $missing;
        }

        $entry = $this->scopedQuery($actor)
            ->where('type', FarmerFeedback::TYPE_SUGGESTION)
            ->findOrFail($feedback);

        $data = $request->validate([
            'technician_id' => 'required|integer',
            'priority'      => 'required|in:' . implode(',', self::PRIORITIES),
            'notes'         => 'nullable|string|max:2000',
        ]);

        // The technician must be a real technician inside this admin's area.
        $techQuery = User::where('role', 'technician')->where('id', $data['technician_id']);
        if ($actor->role !== 'developer') {
            $techQuery->where('province_id', $actor->province_id)->where('city_id', $actor->city_id);
        }
        $tech = $techQuery->first();
        if (!$tech) {
            return response()->json([
                'success' => false,
                'message' => 'That technician is not available in your province and city.',
            ], 422);
        }

        $entry->forceFill([
            'assigned_technician_id' => $tech->id,
            'assigned_at'            => now(),
            'assigned_priority'      => $data['priority'],
            'assigned_notes'         => $data['notes'] ?? null,
        ])->save();

        return response()->json([
            'success'    => true,
            'tech_name'  => $tech->full_name,
            'priority'   => $data['priority'],
            'notes'      => $data['notes'] ?? null,
            'suggestion' => $this->present($entry, $actor),
        ]);
    }

    /**
     * Admin resolves it himself: status + notes. Written to the same
     * support_* columns the technician's "Create Support" uses (so the farmer
     * sees the outcome the same way), with support_by = 'Admin'.
     */
    public function resolve(Request $request, $feedback)
    {
        $actor = $this->actor();

        if ($missing = $this->missingColumns()) {
            return $missing;
        }

        $entry = $this->scopedQuery($actor)
            ->where('type', FarmerFeedback::TYPE_SUGGESTION)
            ->findOrFail($feedback);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(FarmerReportController::SUPPORT_STATUSES)),
            'notes'  => 'required|string|max:2000',
        ]);

        $entry->forceFill([
            'support_status'     => $data['status'],
            'support_notes'      => $data['notes'],
            'support_updated_at' => now(),
            'support_by'         => 'Admin',
            'status'             => 'reviewed',
        ])->save();

        return response()->json([
            'success'    => true,
            'suggestion' => $this->present($entry, $actor),
        ]);
    }

    // ---------------------------------------------------------------------

    private function present(FarmerFeedback $entry, $actor): array
    {
        $presenter = app(FarmerReportController::class);
        return $presenter->presentFeedback($entry->fresh(), 'admin', $actor->id, $presenter->techNamesFor([$entry->fresh()]));
    }

    private function actor()
    {
        $actor = auth()->user();
        if (!in_array($actor->role, ['admin', 'developer'], true)) {
            abort(403, 'Only an admin account can manage farmer suggestions.');
        }
        return $actor;
    }

    private function scopedQuery($actor)
    {
        $query = FarmerFeedback::query();

        // Same rule as the User Accounts page: a normal admin only sees farmers
        // whose province AND city are the admin's own. Developer sees all.
        if ($actor->role !== 'developer') {
            $farmerIds = User::where('role', 'farmer')
                ->where('province_id', $actor->province_id)
                ->where('city_id', $actor->city_id)
                ->select('id');
            $query->whereIn('user_id', $farmerIds);
        }

        return $query;
    }

    /** Numbers shown in the empty state so a scoping problem is visible on the page. */
    public function diagnostics($actor): array
    {
        if (!Schema::hasTable('farmer_feedback')) {
            return ['table' => false, 'total' => 0, 'in_area' => 0, 'province_id' => $actor->province_id, 'city_id' => $actor->city_id];
        }

        return [
            'table'       => true,
            'total'       => FarmerFeedback::count(),
            'in_area'     => $this->scopedQuery($actor)->count(),
            'province_id' => $actor->province_id,
            'city_id'     => $actor->city_id,
        ];
    }

    /** If the add_support_columns migration hasn't run, say so instead of a 500. */
    private function missingColumns()
    {
        if (!Schema::hasTable('farmer_feedback')
            || !Schema::hasColumn('farmer_feedback', 'assigned_technician_id')
            || !Schema::hasColumn('farmer_feedback', 'support_status')) {
            return response()->json([
                'success' => false,
                'message' => 'Run "php artisan migrate" first (add_support_columns_to_farmer_feedback).',
            ], 503);
        }
        return null;
    }
}