<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerReportController;
use App\Models\FarmerFeedback;
use App\Models\FarmerReport;
use Illuminate\Http\Request;

/**
 * Admin "System Report" page: the reports technicians escalated
 * (FarmerReportController@escalate), plus the farmers' suggestions and
 * general feedback, and the admin's own progress on them.
 *
 * admin_status lives in farmer_reports.admin_status and is separate from the
 * farmer-facing `status`.
 *
 *   pending      escalated, admin hasn't looked yet
 *   open         set automatically the first time the admin clicks View
 *   in_progress  admin picks "In Progress"
 *   resolved     admin picks "Resolved"
 */
class AdminSystemReportController extends Controller
{
    public const STATUSES = FarmerReportController::ADMIN_STATUSES;

    public const TYPES = ['AI Model', 'Knowledge Base', 'System'];

    public function index()
    {
        $actor = $this->actor();

        $presenter = app(FarmerReportController::class);

        $reports = $this->scopedQuery($actor)
            ->orderByDesc('escalated_at')
            ->get()
            ->map(fn (FarmerReport $r) => $presenter->presentEscalated($r))
            ->values()
            ->all();

        $wrongDetectionStats = collect($reports)
            ->groupBy(fn (array $r) => $r['detection']['class_name'] ?? 'Unknown')
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->all();

        // Farmer "Suggest Improvement" + "General Feedback" entries.
        $suggestionsCtrl = app(AdminSuggestionController::class);
        $suggestions = $suggestionsCtrl->list($actor, FarmerFeedback::TYPE_SUGGESTION);
        $feedbacks   = $suggestionsCtrl->list($actor, FarmerFeedback::TYPE_GENERAL);

        return view('admin.admin_report', [
            'reports'             => $reports,
            'statuses'            => self::STATUSES,
            'types'               => self::TYPES,
            'sections'            => FarmerReportController::SECTIONS,
            'wrongDetectionStats' => $wrongDetectionStats,

            'suggestions'          => $suggestions,
            'feedbacks'            => $feedbacks,
            'sgDiag'               => $suggestionsCtrl->diagnostics($actor),
            'supportStatuses'      => FarmerReportController::SUPPORT_STATUSES,
            'suggestionCategories' => FarmerReportController::SUGGESTION_CATEGORIES,
            'feedbackFeatures'     => FarmerReportController::FEEDBACK_FEATURES,
            'ratingLabels'         => FarmerReportController::RATING_LABELS,
        ]);
    }

    /**
     * `view` only ever moves pending -> open; in_progress / resolved set
     * that status directly.
     */
    public function updateStatus(Request $request, $id)
    {
        $actor = $this->actor();

        $data = $request->validate([
            'action' => 'required|in:view,in_progress,resolved',
        ]);

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

    private function actor()
    {
        $actor = auth()->user();
        if (!in_array($actor->role, ['admin', 'developer'], true)) {
            abort(403, 'Only an admin account can view system reports.');
        }
        return $actor;
    }

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