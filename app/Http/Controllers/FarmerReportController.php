<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\FarmerReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ONE controller for BOTH sides of the detection-feedback loop:
 *   - farmer     -> "Report Problem"  (views/farmer/farmer_report.blade.php)
 *   - technician -> "Farmer Reports"  (views/technician/technician_report.blade.php)
 *
 * Same reasoning as AdminUserController: the routes are already protected by
 * 'role:farmer' / 'role:technician' middleware, but every method here ALSO
 * re-checks auth()->user()->role server-side and scopes the rows before
 * touching data. So a farmer can never read or review another farmer's
 * report, and a technician can never post a report as a farmer, even if a
 * route is misconfigured or an id is guessed in the URL (IDOR).
 */
class FarmerReportController extends Controller
{
    /**
     * Same agronomic fallback maps History uses (resources/views/farmer/
     * history.blade.php: $severityMap / $severityLabelMap) — a MODEL-
     * classified scan never carries a severity_label/percent of its own
     * (only a Groq-classified scan's snapshot does), so both the "Create a
     * Report" snapshot and the presented report fall back to these same
     * per-class values History already shows, instead of showing nothing.
     */
    public const SEVERITY_PERCENT_MAP = [
        'healthy_rice_plant' => 0, 'bacterial_leaf_blight' => 60, 'leaf_blast' => 80,
        'rice_false_smut' => 30, 'sheath_blight' => 40, 'tungro_virus' => 85,
        'brown_planthopper' => 90, 'leaf_folders' => 20, 'leafhopper' => 30,
        'rice_bug' => 80, 'rice_gall_midge' => 40, 'rice_leaf_roller' => 20,
        'rice_stem_borer' => 30, 'snail' => 75,
    ];

    public const SEVERITY_LABEL_MAP = [
        'healthy_rice_plant' => 'HEALTHY', 'bacterial_leaf_blight' => 'SEVERE', 'leaf_blast' => 'SEVERE',
        'rice_false_smut' => 'MODERATE', 'sheath_blight' => 'MODERATE', 'tungro_virus' => 'SEVERE',
        'brown_planthopper' => 'SEVERE', 'leaf_folders' => 'LOW', 'leafhopper' => 'MODERATE',
        'rice_bug' => 'SEVERE', 'rice_gall_midge' => 'MODERATE', 'rice_leaf_roller' => 'LOW',
        'rice_stem_borer' => 'MODERATE', 'snail' => 'SEVERE',
    ];

    /**
     * The single source of truth for the detection information sections,
     * shared by the farmer's report modal, the farmer's report page and the
     * technician's review page — so "what the farmer marked red" means the
     * exact same thing in all three places.
     *
     * Keys match the data-section values in the report modal inside
     * resources/views/farmer/detection/index.blade.php, and the keys stored
     * in farmer_reports.info / flagged_sections.
     */
    public const SECTIONS = [
        'name'            => 'Pest / Disease',
        'severity'        => 'Severity',
        'damagelevel'     => 'Damage Level',
        'description'     => 'Description / About',
        'treatment'       => 'Treatment',
        'causes'          => 'Causes',
        'nutrient'        => 'Nutrient / Deficiency',
        'damage'          => 'Damage Symptoms',
        'grain'           => 'Grain Impact',
        'natural_enemies' => 'Natural Enemies',
        'prevention'      => 'Prevention',
    ];

    /** Status vocabulary — one place, so both views badge alike. */
    public const STATUSES = [
        'pending'        => ['label' => 'Pending',            'class' => 'bg-warning text-dark'],
        'waiting_farmer' => ['label' => 'Waiting for Farmer', 'class' => 'bg-info text-dark'],
        'resolved'       => ['label' => 'Resolved',           'class' => 'bg-success text-white'],
    ];

    /**
     * The ADMIN's progress on an escalated report (farmer_reports.admin_status).
     * Shared by the admin System Report page and the technician's "Escalated
     * to admin" list, so both badge the same status the same way.
     */
    public const ADMIN_STATUSES = [
        'pending'     => ['label' => 'Pending'],
        'open'        => ['label' => 'Open'],
        'in_progress' => ['label' => 'In Progress'],
        'resolved'    => ['label' => 'Resolved'],
    ];

    /** Assessments a technician can record, and the status each one implies. */
    public const ASSESSMENT_STATUS = [
        'correct'          => 'resolved',
        'incorrect'        => 'resolved',
        'info_incorrect'   => 'resolved',
        'need_image'       => 'waiting_farmer',
        'cannot_determine' => 'resolved',
    ];

    /**
     * "What is wrong?" checklist — identical list (and order) to the one in
     * the "Report the Problem" modal on the detection page
     * (resources/views/farmer/detection/index.blade.php), so a report
     * created from History offers the exact same choices.
     */
    public const PROBLEM_TYPES = [
        'Wrong pest/disease detection',
        'Wrong severity level',
        'Wrong damage level',
        'Wrong management/treatment',
        'Wrong causes',
        'Wrong symptoms',
        'Wrong natural enemies',
        'Wrong prevention information',
        'Other system problem',
    ];

    // =====================================================================
    // SHARED ENTRY POINT
    // =====================================================================

    public function index(Request $request)
    {
        $actor = auth()->user();

        return match ($actor->role) {
            'farmer'     => $this->farmerIndex($actor),
            'technician' => $this->technicianIndex($actor),
            default      => abort(403, 'This page is not available for your account type.'),
        };
    }

    /** Farmer side: only ever their OWN reports. */
    private function farmerIndex($actor)
    {
        $reports = FarmerReport::with('farmer')
            ->where('user_id', $actor->id)
            ->latest()
            ->get()
            ->map(fn ($r) => $this->present($r))
            ->all();

        return view('farmer.farmer_report', [
            'reports'       => $reports,
            'sections'      => self::SECTIONS,
            'statuses'      => self::STATUSES,
            // Feeds the "Create a Report" flow: pick a pest/disease name ->
            // pick one of ITS ids -> the full snapshot for that exact scan.
            'myDetections'  => $this->myDetectionsForReport($actor->id),
            'diseaseNames'  => $this->diseaseNames(),
            'pestNames'     => $this->pestNames(),
            'problemTypes'  => self::PROBLEM_TYPES,
        ]);
    }

    /**
     * Every one of this farmer's own past scans, across all of their
     * fields, grouped by pest/disease so the "Create a Report" modal can
     * offer "pick the name, then pick the ID" the same way History groups
     * a farmer's own scans. Same knowledge-base rules as
     * FarmerHistoryController@index: a MODEL-classified scan reads the
     * shared admin knowledge base (treatment_records, user_id IS NULL); a
     * GROQ-classified scan carries its OWN snapshot from `groq_snapshot`
     * and never touches the shared table. Newest scan first within each
     * name, same as History.
     */
    private function myDetectionsForReport(int $userId): array
    {
        $diseaseNames = $this->diseaseNames();
        $pestNames    = $this->pestNames();

        $flatten = function ($val, $def) {
            if (empty($val)) return $def;
            if (is_string($val)) {
                $trimmed = trim($val);
                if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                    $decoded = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE) { $val = $decoded; }
                }
            }
            if (is_array($val)) return implode("\n• ", $val);
            if (is_object($val)) return json_encode($val);
            return (string) $val;
        };

        $knowledgeBase = [];
        $kbRecords = DB::table('treatment_records')->whereNull('user_id')->get();
        foreach ($kbRecords as $row) {
            $knowledgeBase[strtolower(trim($row->disease))] = [
                'description'         => $flatten($row->description ?? null, '—'),
                'treatments'          => $flatten($row->treatments ?? null, 'No data available yet.'),
                'causes'              => $flatten($row->causes ?? null, '—'),
                'nutrient_deficiency' => $flatten($row->nutrient_deficiency ?? null, '—'),
                'grain_damage'        => $flatten($row->grain_damage ?? null, '—'),
                'natural_enemies'     => $flatten($row->natural_enemies ?? null, '—'),
                'prevention'          => $flatten($row->prevention ?? null, '—'),
            ];
        }

        $rawDetections = DB::table('user_detections')
            ->where('user_id', $userId)
            ->orderBy('class_key')
            ->orderBy('created_at', 'desc')
            ->get();

        $hasGroqSnapshotColumn   = Schema::hasColumn('user_detections', 'groq_snapshot');
        $hasSourceColumn         = Schema::hasColumn('user_detections', 'source');
        $hasDetectionBoxesColumn = Schema::hasColumn('user_detections', 'detection_boxes');

        $grouped = $rawDetections->groupBy(fn ($row) => strtolower(trim($row->class_key)));

        $result = [];
        foreach ($grouped as $classKey => $rows) {
            $instances = [];
            $anyInstanceIsPest = null;

            foreach ($rows as $row) {
                $image = null;
                if (!empty($row->image_path)) {
                    $path = $row->image_path;
                    if (str_starts_with($path, 'data:image/') || str_starts_with($path, 'http') || str_starts_with($path, '/')) {
                        $image = $path;
                    } elseif (strlen($path) > 255) {
                        $image = 'data:image/jpeg;base64,' . $path;
                    } else {
                        $image = asset($path);
                    }
                }

                // Same three-way engine check FarmerHistoryController uses —
                // this used to collapse everything that wasn't 'groq' into
                // 'model', which silently relabeled every yolo11n scan as
                // the old MobileNetV2 classifier the moment it was picked
                // for a report. Recognize yolo11n explicitly instead.
                $source = 'model';
                if ($hasSourceColumn && isset($row->source) && in_array($row->source, ['groq', 'yolo11n'], true)) {
                    $source = $row->source;
                }

                $snapshot = null;
                if ($source === 'groq' && $hasGroqSnapshotColumn && !empty($row->groq_snapshot)) {
                    $decoded = json_decode($row->groq_snapshot, true);
                    if (is_array($decoded)) {
                        $snapshot = $decoded;
                    }
                }

                if ($snapshot !== null && array_key_exists('is_pest', $snapshot)) {
                    $anyInstanceIsPest = (bool) $snapshot['is_pest'];
                }

                // Same YOLO11n bounding-box decode as FarmerHistoryController@index
                // — kept independent of the image itself, so the "Create a
                // Report" picker can draw the exact same overlay History does
                // when the farmer previews a past scan before reporting it.
                $boxes = null;
                if ($source === 'yolo11n' && $hasDetectionBoxesColumn && !empty($row->detection_boxes)) {
                    $decodedBoxes = json_decode($row->detection_boxes, true);
                    if (is_array($decodedBoxes) && !empty($decodedBoxes['boxes'])) {
                        $boxes = $decodedBoxes;
                    }
                }

                $instances[] = [
                    'id'               => $row->id,
                    'image'            => $image,
                    'boxes'            => $boxes,
                    'confidence'       => isset($row->confidence) ? (int) $row->confidence : 0,
                    'date'             => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->timezone('Asia/Manila')->format('M d, Y g:i A') : null,
                    'source'           => $source,
                    'kb'               => $snapshot ?? ($knowledgeBase[$classKey] ?? []),
                    // Same History fallback: a MODEL scan's own severity
                    // fields are null, so fall back to the per-class map
                    // instead of leaving the Severity/Damage Level chips
                    // blank on whatever report gets created from this scan.
                    'severity_label'   => $snapshot['severity_label'] ?? (self::SEVERITY_LABEL_MAP[$classKey] ?? null),
                    'severity_percent' => $snapshot['severity_percent'] ?? (self::SEVERITY_PERCENT_MAP[$classKey] ?? null),
                ];
            }

            $isPest = isset($pestNames[$classKey])
                || (!isset($diseaseNames[$classKey]) && $anyInstanceIsPest === true);
            $fallbackName = ucwords(str_replace('_', ' ', $classKey));
            $className = $isPest ? ($pestNames[$classKey] ?? $fallbackName) : ($diseaseNames[$classKey] ?? $fallbackName);

            $result[] = [
                'class_key'  => $classKey,
                'class_name' => $className,
                'is_pest'    => $isPest,
                'instances'  => $instances,
            ];
        }

        usort($result, fn ($a, $b) => strcmp($a['class_name'], $b['class_name']));

        return $result;
    }

    /** Technician side: the review queue, scoped to farmers in their area. */
    private function technicianIndex($actor)
    {
        $reports = $this->technicianQuery($actor)
            ->latest()
            ->get()
            ->map(fn ($r) => $this->present($r))
            ->all();

        // Reports this technician's queue has escalated to the admin, shown
        // in the "Escalated to admin" container above the farmer reports.
        $escalatedReports = $this->technicianQuery($actor)
            ->whereNotNull('escalated_at')
            ->orderByDesc('escalated_at')
            ->get()
            ->map(fn ($r) => $this->presentEscalated($r))
            ->values()
            ->all();

        return view('technician.technician_report', [
            'reports'        => $reports,
            'escalatedReports' => $escalatedReports,
            'adminStatuses'  => self::ADMIN_STATUSES,
            'sections'       => self::SECTIONS,
            'statuses'       => self::STATUSES,
            'diseaseNames'   => $this->diseaseNames(),
            'pestNames'      => $this->pestNames(),
            'severityLevels' => ['HEALTHY', 'LOW', 'MODERATE', 'SEVERE'],
        ]);
    }

    /**
     * Technician scope, in one place so index() and review() can't disagree.
     *
     * STRICT barangay match only: a report is visible to a technician if
     * and only if the farmer's own barangay_id matches a barangay_id on
     * one of that technician's ACTIVE Assignment rows (user_type =
     * 'technician').
     *
     * No fallback to province/city. A technician who hasn't been assigned
     * to a barangay yet (or whose only assignment is inactive) simply sees
     * an empty queue — never "everyone in my city" — so a report can never
     * be visible to a technician outside the farmer's exact barangay.
     */
    private function technicianQuery($actor)
    {
        $barangayIds = Assignment::active()
            ->where('user_id', $actor->id)
            ->where('user_type', 'technician')
            ->pluck('barangay_id');

        return FarmerReport::with('farmer')->whereHas('farmer', function ($q) use ($barangayIds) {
            $q->whereIn('barangay_id', $barangayIds);
        });
    }

    // =====================================================================
    // FARMER: submit a report (posted by the modal on the detection page)
    // =====================================================================

    public function store(Request $request)
    {
        $actor = auth()->user();
        if ($actor->role !== 'farmer') {
            abort(403, 'Only a farmer account can submit a detection report.');
        }

        $data = $request->validate([
            'class_key'          => 'nullable|string|max:255',
            'class_name'         => 'required|string|max:255',
            'confidence'         => 'nullable|integer|min:0|max:100',
            'severity_label'     => 'nullable|string|max:50',
            'severity_percent'   => 'nullable|integer|min:0|max:100',
            'source'             => 'nullable|string|max:20',
            'image_base64'       => 'nullable|string',
            'support_image'      => 'nullable|string',
            // Raw YOLO11n box coordinates for the reported photo, same
            // shape saveDetection() accepts on the History side. The live
            // "Report the Problem" modal already bakes boxes into
            // image_base64 for yolo11n (buildYoloDetectionSnapshot() in
            // the detection page), so these are only actually populated
            // when a report is created from a past scan instead — but
            // accepting them here means the technician review page can
            // draw the same overlay History does either way.
            'detection_boxes'    => 'nullable|array',
            'detection_boxes.*'  => 'array',
            'boxes_src_w'        => 'nullable|numeric',
            'boxes_src_h'        => 'nullable|numeric',
            'info'               => 'nullable|array',
            'problem_types'      => 'required|array|min:1',
            'problem_types.*'    => 'string|max:100',
            'flagged_sections'   => 'nullable|array',
            'flagged_sections.*' => 'string|max:50',
            'message'            => 'required|string|max:2000',
            'suggested_class'    => 'nullable|string|max:255',
        ]);

        // Only ever keep section keys this app actually knows about, so a
        // crafted request can't stuff arbitrary keys into the JSON columns.
        $flagged = array_values(array_intersect($data['flagged_sections'] ?? [], array_keys(self::SECTIONS)));
        $info    = array_intersect_key($data['info'] ?? [], self::SECTIONS);

        $reportData = [
            'report_code'        => FarmerReport::nextReportCode(),
            'user_id'            => $actor->id,
            'class_key'          => $data['class_key'] ?? null,
            'class_name'         => $data['class_name'],
            'confidence'         => $data['confidence'] ?? 0,
            'severity_label'     => $data['severity_label'] ?? null,
            'severity_percent'   => $data['severity_percent'] ?? 0,
            'source'             => $data['source'] ?? null,
            'image_path'         => $data['image_base64'] ?? null,
            'support_image_path' => $data['support_image'] ?? null,
            'info'               => $info,
            'problem_types'      => $data['problem_types'],
            'flagged_sections'   => $flagged,
            'message'            => $data['message'],
            'suggested_class'    => $data['suggested_class'] ?? null,
            'status'             => 'pending',
        ];

        $report = FarmerReport::create($reportData);

        // YOLO11n box coordinates for the reported photo (same shape History
        // stores in user_detections.detection_boxes). Written with a direct
        // query on purpose: FarmerReport::create() silently drops any column
        // that isn't in the model's $fillable, and a model cast on this column
        // would double-encode the JSON — neither can happen this way. Needs the
        // farmer_reports.detection_boxes column (see the add_detection_boxes
        // migration); if it hasn't been run the report still saves, just
        // without boxes, and the failure is logged instead of hidden.
        if (!empty($data['detection_boxes']) && !empty($data['boxes_src_w']) && !empty($data['boxes_src_h'])) {
            if (Schema::hasColumn('farmer_reports', 'detection_boxes')) {
                DB::table('farmer_reports')->where('id', $report->id)->update([
                    'detection_boxes' => json_encode([
                        'boxes' => array_values($data['detection_boxes']),
                        'src_w' => $data['boxes_src_w'],
                        'src_h' => $data['boxes_src_h'],
                    ]),
                ]);
            } else {
                \Log::warning('farmer_reports.detection_boxes column is missing — run the add_detection_boxes_to_farmer_reports migration. Boxes for report ' . $report->report_code . ' were not saved.');
            }
        }

        return response()->json([
            'success'     => true,
            'report_code' => $report->report_code,
        ]);
    }

    // =====================================================================
    // FARMER: delete one of their own reports
    // =====================================================================

    /**
     * Real, permanent delete — scoped the same way every other method here
     * is: re-check the role, then only ever look the row up inside
     * `where('user_id', $actor->id)`, so a farmer can never delete (or even
     * discover, via a 404 vs 403) another farmer's report by guessing an id.
     */
    public function destroy(Request $request, $id)
    {
        $actor = auth()->user();
        if ($actor->role !== 'farmer') {
            abort(403, 'Only a farmer account can delete their own report.');
        }

        $report = FarmerReport::where('user_id', $actor->id)->findOrFail($id);
        $report->delete();

        return response()->json(['success' => true]);
    }

    // =====================================================================
    // TECHNICIAN: save the review
    // =====================================================================

    public function review(Request $request, $id)
    {
        $actor = auth()->user();
        if ($actor->role !== 'technician') {
            abort(403, 'Only a technician account can review farmer reports.');
        }

        // Scoped findOrFail: a technician can only review a report already
        // inside their own queue — guessing an id gets a 404.
        $report = $this->technicianQuery($actor)->findOrFail($id);

        $data = $this->validateReview($request);

        if ($error = $this->saveReview($report, $data, $actor)) {
            return back()->with('error', $error);
        }

        $message = $report->status === 'waiting_farmer'
            ? 'Review saved. Report ' . $report->report_code . ' is now waiting for the farmer.'
            : 'Review saved. Report ' . $report->report_code . ' is now resolved.';

        return redirect()->route('technician.reports')->with('success', $message);
    }

    // =====================================================================
    // TECHNICIAN: escalate to admin
    // =====================================================================

    /**
     * "Escalate to Admin" — saves the technician's assessment/notes/advice
     * exactly like review() does (same validation, same columns), THEN flags
     * the report as escalated so it shows up on the admin's System Report
     * page with the farmer's report on one side and this resolution on the
     * other. The admin's own progress (pending -> open -> in_progress ->
     * resolved) lives in `admin_status` and never touches the farmer-facing
     * `status`.
     */
    public function escalate(Request $request, $id)
    {
        $actor = auth()->user();
        if ($actor->role !== 'technician') {
            abort(403, 'Only a technician account can escalate a farmer report.');
        }

        // Same scoped lookup as review(): only reports in this technician's queue.
        $report = $this->technicianQuery($actor)->findOrFail($id);

        if (!empty($report->escalated_at)) {
            return redirect()->route('technician.reports')
                ->with('error', 'Report ' . $report->report_code . ' was already escalated to the admin.');
        }

        $data = $this->validateReview($request);

        // "Need another image" hands the report back to the farmer — there
        // is no resolution yet, so there is nothing for an admin to act on.
        if ($data['assessment'] === 'need_image') {
            return back()->with('error', 'A report that is waiting for another photo from the farmer cannot be escalated yet.');
        }

        if ($error = $this->saveReview($report, $data, $actor)) {
            return back()->with('error', $error);
        }

        $report->forceFill([
            'escalated_at'            => now(),
            'admin_status'            => 'pending',
            'admin_status_updated_at' => now(),
        ])->save();

        return redirect()->route('technician.reports')
            ->with('success', 'Review saved and report ' . $report->report_code . ' was escalated to the admin.');
    }

    /**
     * "Delete" from the technician resolve-data panel: clears whatever
     * assessment/notes/advice/correction was saved on this report, putting
     * it back to Pending exactly as if it had never been reviewed. Blocked
     * once the report has been escalated — the admin already has that
     * resolution, so it can't just disappear out from under them.
     */
    public function clearReview(Request $request, $id)
    {
        $actor = auth()->user();
        if ($actor->role !== 'technician') {
            abort(403, 'Only a technician account can clear a report review.');
        }

        // Same scoped lookup as review()/escalate(): only this technician's queue.
        $report = $this->technicianQuery($actor)->findOrFail($id);

        if (!empty($report->escalated_at)) {
            return response()->json([
                'success' => false,
                'message' => 'This report was already escalated to the admin, so its resolution can no longer be deleted here.',
            ], 422);
        }

        $report->update([
            'assessment'         => null,
            'corrected_class'    => null,
            'corrected_name'     => null,
            'corrected_severity' => null,
            'corrected_sections' => [],
            'notes'              => null,
            'advice'             => null,
            'reviewed_by'        => null,
            'reviewed_at'        => null,
            'status'             => 'pending',
        ]);

        return response()->json(['success' => true]);
    }

    /** Shared by review() and escalate() so both validate identically. */
    private function validateReview(Request $request): array
    {
        return $request->validate([
            'assessment'           => 'required|in:' . implode(',', array_keys(self::ASSESSMENT_STATUS)),
            'corrected_class'      => 'required_if:assessment,incorrect|nullable|string|max:255',
            'corrected_severity'   => 'nullable|string|max:50',
            'corrected_sections'   => 'nullable|array',
            'corrected_sections.*' => 'string|max:50',
            'notes'                => 'required|string|max:2000',
            'advice'               => 'nullable|string|max:2000',
        ]);
    }

    /**
     * Writes the technician's review onto the report. Returns an error
     * message (string) if it can't be saved, or null on success.
     */
    private function saveReview(FarmerReport $report, array $data, $actor): ?string
    {
        $names = $this->diseaseNames() + $this->pestNames();
        $correctedClass = $data['corrected_class'] ?? null;

        // A corrected class must be one this app knows — no free text.
        if ($correctedClass !== null && !array_key_exists($correctedClass, $names)) {
            return 'Unknown pest/disease selected.';
        }

        $report->update([
            'technician_id'      => $actor->id,
            'assessment'         => $data['assessment'],
            'corrected_class'    => $correctedClass,
            'corrected_name'     => $correctedClass ? $names[$correctedClass] : null,
            'corrected_severity' => $data['corrected_severity'] ?? null,
            'corrected_sections' => array_values(array_intersect($data['corrected_sections'] ?? [], array_keys(self::SECTIONS))),
            'notes'              => $data['notes'],
            'advice'             => $data['advice'] ?? null,
            'reviewed_by'        => ($actor->full_name ?? 'Technician') . ' (Technician)',
            'reviewed_at'        => now(),
            'status'             => self::ASSESSMENT_STATUS[$data['assessment']],
        ]);

        return null;
    }

    /**
     * Live admin status of every report in this technician's queue that has
     * been escalated: { "<report id>": "pending|open|in_progress|resolved" }.
     * The technician page polls this so a status the admin changes shows up
     * without a manual refresh. Same scoping as everything else here.
     */
    public function escalationStatuses()
    {
        $actor = auth()->user();
        if ($actor->role !== 'technician') {
            abort(403, 'Only a technician account can read escalation statuses.');
        }

        $statuses = $this->technicianQuery($actor)
            ->whereNotNull('escalated_at')
            ->pluck('admin_status', 'id')
            ->map(fn ($s) => array_key_exists((string) $s, self::ADMIN_STATUSES) ? $s : 'pending');

        return response()->json((object) $statuses->all())->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma'        => 'no-cache',
        ]);
    }

    // =====================================================================
    // PRESENTATION
    // =====================================================================

    /**
     * Flattens a model into the array shape both views read, so neither view
     * has to know anything about the schema.
     *
     * Public (not private) because AdminSystemReportController reuses it, so
     * the admin's modal is built from the identical shape the technician sees.
     */
    public function present(FarmerReport $r): array
    {
        $names = $this->diseaseNames() + $this->pestNames();

        // Same History fallback as myDetectionsForReport(): if this report
        // was created from a MODEL scan before the fallback existed (or the
        // farmer's browser sent nothing), its severity fields may be null —
        // fall back to the per-class map instead of showing a blank chip.
        $classKey = strtolower(trim((string) $r->class_key));
        $sevLabel = $r->severity_label ?: (self::SEVERITY_LABEL_MAP[$classKey] ?? null);
        $sevPercent = $r->severity_percent;
        if ($sevPercent === null) {
            $sevPercent = self::SEVERITY_PERCENT_MAP[$classKey] ?? null;
        }

        // Same decode as FarmerHistoryController@index / myDetectionsForReport()
        // above — only present at all once the detection_boxes migration has
        // been run on farmer_reports. Every yolo11n report (live from the
        // detection page or created from a past scan) keeps the plain photo in
        // image_path and its boxes here; the views draw them on zoom.
        $boxes = null;
        if (Schema::hasColumn('farmer_reports', 'detection_boxes')) {
            // getRawOriginal(): the JSON string exactly as stored, so a cast
            // or accessor on the model can't change what we decode.
            $rawBoxes = $r->getRawOriginal('detection_boxes');
            $decodedBoxes = is_string($rawBoxes) ? json_decode($rawBoxes, true) : (is_array($rawBoxes) ? $rawBoxes : null);
            if (is_array($decodedBoxes) && !empty($decodedBoxes['boxes'])) {
                $boxes = $decodedBoxes;
            }
        }

        return [
            'id'               => $r->id,
            'report_id'        => $r->report_code,
            'farmer_id'        => $r->user_id,
            'farmer_name'      => $r->farmer->full_name ?? 'Unknown farmer',
            'submitted_at'     => $r->created_at?->timezone('Asia/Manila')->format('M d, Y h:i A'),
            'date'             => $r->created_at?->timezone('Asia/Manila')->format('M d, Y'),
            'problem_summary'  => $r->problem_types[0] ?? 'Other system problem',
            'problem_types'    => $r->problem_types ?? [],
            'message'          => $r->message,
            'flagged_sections' => $r->flagged_sections ?? [],
            'status'           => array_key_exists($r->status, self::STATUSES) ? $r->status : 'pending',
            'suggested_name'   => $r->suggested_class ? ($names[$r->suggested_class] ?? $r->suggested_class) : null,
            'detection'        => [
                'class_key'        => $r->class_key,
                'class_name'       => $r->class_name,
                'confidence'       => $r->confidence,
                'severity_label'   => $sevLabel ?: '—',
                'severity_percent' => $sevPercent,
                'source'           => $r->source,
                'image'            => $this->normalizeImage($r->image_path),
                'boxes'            => $boxes,
            ],
            'support_image'    => $this->normalizeImage($r->support_image_path),
            'info'             => $r->info ?? [],
            'escalated'        => !empty($r->escalated_at),
            'review'           => $r->isReviewed() ? [
                'assessment'         => $r->assessment,
                'corrected_class'    => $r->corrected_class,
                'corrected_name'     => $r->corrected_name,
                'corrected_severity' => $r->corrected_severity,
                'corrected_sections' => $r->corrected_sections ?? [],
                'notes'              => $r->notes,
                'advice'             => $r->advice,
                'reviewed_by'        => $r->reviewed_by,
                'reviewed_at'        => $r->reviewed_at?->timezone('Asia/Manila')->format('M d, Y h:i A'),
            ] : null,
        ];
    }

    /**
     * present() plus the columns of the admin's System Report table (Report
     * ID, Type, Category, Description, Date, Status). Used by BOTH the admin
     * page and the technician's "Escalated to admin" list so the two can never
     * disagree about how an escalated report looks.
     */
    public function presentEscalated(FarmerReport $r): array
    {
        $p = $this->present($r);
        [$typeKey, $typeLabel] = $this->escalationType($p);
        $escalatedAt = \Carbon\Carbon::parse($r->escalated_at)->timezone('Asia/Manila');

        return $p + [
            'sr_code'        => 'SR-' . str_pad((string) $r->id, 3, '0', STR_PAD_LEFT),
            'admin_status'   => array_key_exists((string) $r->admin_status, self::ADMIN_STATUSES) ? $r->admin_status : 'pending',
            'type_key'       => $typeKey,
            'type'           => $typeLabel,
            'category'       => $p['problem_summary'],
            'description'    => $this->escalationDescription($p),
            'escalated_date' => $escalatedAt->format('M d, Y'),
            'escalated_at'   => $escalatedAt->format('M d, Y h:i A'),
        ];
    }

    /**
     * Type column. The technician's verdict wins (AI result wrong -> AI
     * Model, information wrong -> Knowledge Base); otherwise it falls back to
     * what the farmer said was wrong.
     */
    private function escalationType(array $p): array
    {
        $assessment = $p['review']['assessment'] ?? null;
        if ($assessment === 'incorrect') {
            return ['ai_model', 'AI Model'];
        }
        if ($assessment === 'info_incorrect') {
            return ['knowledge_base', 'Knowledge Base'];
        }

        $problem = $p['problem_summary'] ?? '';

        if (in_array($problem, ['Wrong pest/disease detection', 'Wrong severity level', 'Wrong damage level'], true)) {
            return ['ai_model', 'AI Model'];
        }
        if ($problem === 'Other system problem') {
            return ['system', 'System'];
        }

        // Wrong management/treatment, causes, symptoms, natural enemies, prevention.
        return ['knowledge_base', 'Knowledge Base'];
    }

    /** "Brown Planthopper → Rice Bug" when corrected, otherwise the farmer's message. */
    private function escalationDescription(array $p): string
    {
        $corrected = $p['review']['corrected_name'] ?? null;
        if ($corrected) {
            return $p['detection']['class_name'] . ' → ' . $corrected;
        }

        return $p['detection']['class_name'] . ' — ' . \Illuminate\Support\Str::limit((string) ($p['message'] ?? ''), 70);
    }

    /**
     * Same image normalisation FarmerHistoryController@index uses for
     * user_detections.image_path, so report thumbnails behave exactly like
     * the ones on the History page: a stored data URI / absolute URL / root
     * path is used as-is, bare base64 gets the data URI prefix, and anything
     * else is treated as a public asset path.
     */
    private function normalizeImage(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        if (str_starts_with($path, 'data:image/') || str_starts_with($path, 'http') || str_starts_with($path, '/')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $path) && strlen($path) > 100) {
            return 'data:image/jpeg;base64,' . $path;
        }

        return asset($path);
    }

    /** Same maps the detection page and KnowledgeController use. */
    private function diseaseNames(): array
    {
        return [
            'healthy_rice_plant'    => 'Healthy Rice Plant',
            'bacterial_leaf_blight' => 'Bacterial Leaf Blight',
            'leaf_blast'            => 'Leaf Blast',
            'rice_false_smut'       => 'Rice False Smut',
            'sheath_blight'         => 'Sheath Blight',
            'tungro_virus'          => 'Tungro Virus',
        ];
    }

    private function pestNames(): array
    {
        return [
            'brown_planthopper' => 'Brown Planthopper',
            'leaf_folders'      => 'Leaf Folders',
            'leafhopper'        => 'Leafhopper',
            'rice_bug'          => 'Rice Bug',
            'rice_gall_midge'   => 'Rice Gall Midge',
            'rice_leaf_roller'  => 'Rice Leaf Roller',
            'rice_stem_borer'   => 'Rice Stem Borer',
            'snail'             => 'Snail',
        ];
    }
}