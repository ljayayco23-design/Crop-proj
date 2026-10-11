<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerReportController;
use App\Http\Controllers\KnowledgeController;
use App\Models\FarmerReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile (Flutter) side of "Report Problem".
 *
 * Nothing new is re-implemented here: creating a report, presenting a report
 * and the "Create a Report" scan picker all call the SAME methods the website
 * uses (FarmerReportController), so a report made in the app lands in the
 * exact same technician queue with the exact same shape as one made on the
 * website. This controller only adds what a phone needs on top:
 *   - Sanctum token auth (same Auth::setUser() trick as DetectionController)
 *   - light list responses (no photos inside the list; photos are fetched
 *     per report when it is opened)
 *   - offline retry protection (captured_at -> created_at + duplicate guard,
 *     same idea as FarmerHistoryController@saveDetection)
 *   - `detection_id`: a report for an already-synced scan does not have to
 *     re-upload the photo, the server reads it from user_detections itself
 */
class ReportController extends Controller
{
    /** Same canonical lists as FarmerHistoryController::getValidKeys(). */
    private const DISEASE_KEYS = [
        'healthy_rice_plant', 'bacterial_leaf_blight', 'bacterial_leaf_streak',
        'brown_spot', 'downy_mildew', 'leaf_blast', 'rice_false_smut',
        'sheath_blight', 'tungro_virus',
    ];

    private const PEST_KEYS = [
        'applesnail_eggs', 'brown_planthopper', 'dead_heart', 'green_leafhopper',
        'leaf_folders', 'leafhopper', 'rice_bug', 'rice_gall_midg', 'rice_hispa',
        'rice_leaf_roller', 'rice_stem_borer', 'rice_thrips', 'rice_water_weevil',
        'whorl_maggot',
    ];

    private function denyIfNotFarmer(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }
        // FarmerReportController reads auth()->user(); make sure it is the token's user.
        Auth::setUser($user);
        return null;
    }

    private function web(): FarmerReportController
    {
        return app(FarmerReportController::class);
    }

    /**
     * My submitted reports (same rows + same present() shape as the web page).
     * Photos are left out of the list on purpose (they can be 100s of KB each);
     * `has_image` / `has_support_image` tell the app whether one exists and
     * GET /reports/{id} returns them.
     */
    public function index(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        $reports = FarmerReport::with('farmer')
            ->where('user_id', $user->id)
            ->latest()
            ->limit(100)
            ->get()
            ->map(function ($r) {
                $hasImage   = !empty($r->image_path);
                $hasSupport = !empty($r->support_image_path);
                // Not saved - only stops present() from building the data URIs.
                $r->image_path = null;
                $r->support_image_path = null;

                $p = $this->web()->present($r);
                $p['detection']['has_image'] = $hasImage;
                $p['has_support_image']      = $hasSupport;
                return $p;
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'reports' => $reports,
        ]);
    }

    /** One of MY reports, with its photos (404 for anyone else's id). */
    public function show(Request $request, $id)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        $report = FarmerReport::with('farmer')
            ->where('user_id', $user->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'report'  => $this->web()->present($report),
        ]);
    }

    /**
     * Feeds "Create a Report": my own past scans grouped by pest/disease name
     * (same data as the web page's $myDetections). Photos are not included -
     * the app loads a scan's photo from /history/{id}/image when one is picked.
     */
    public function detections(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        // The web builder loads every scan's photo to build its page; give it room.
        @ini_set('memory_limit', '512M');

        $groups = $this->web()->myDetectionsForReport((int) $user->id);

        $names = [];
        try {
            $names = KnowledgeController::diseaseNames() + KnowledgeController::pestNames();
        } catch (\Throwable $e) {
            $names = [];
        }

        foreach ($groups as &$g) {
            $key = $g['class_key'];

            if (isset($names[$key])) {
                $g['class_name'] = $names[$key];
            }
            // The web builder only knows 8 pest names, so several of the model's
            // pests (dead_heart, rice_hispa, ...) would be labelled "Disease".
            if (in_array($key, self::DISEASE_KEYS, true)) {
                $g['is_pest'] = false;
            } elseif (in_array($key, self::PEST_KEYS, true)) {
                $g['is_pest'] = true;
            }

            foreach ($g['instances'] as &$inst) {
                $inst['has_image'] = !empty($inst['image']);
                $inst['image']     = null;
            }
            unset($inst);
        }
        unset($g);

        return response()->json([
            'success'    => true,
            'detections' => $groups,
        ]);
    }

    /**
     * Submit a report. Same endpoint logic as the website
     * (FarmerReportController@store), plus the offline helpers described above.
     */
    public function store(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        // Reports saved OFFLINE on the phone carry the moment they were really
        // written. Use it as created_at, and ignore a retry of a report that
        // already reached the server (e.g. the reply was lost, so the phone
        // queued it again).
        $createdAt = null;
        $capturedRaw = $request->input('captured_at');
        if ($capturedRaw) {
            try {
                $cap = Carbon::parse($capturedRaw)->setTimezone(config('app.timezone'));
                if ($cap->lte(now()->addMinutes(5)) && $cap->gte(now()->subYear())) {
                    $createdAt = $cap;
                    $dup = DB::table('farmer_reports')
                        ->where('user_id', $user->id)
                        ->where('class_key', $request->input('class_key'))
                        ->where('message', $request->input('message'))
                        ->where('created_at', $cap->format('Y-m-d H:i:s'))
                        ->first();
                    if ($dup) {
                        return response()->json([
                            'success'     => true,
                            'duplicate'   => true,
                            'id'          => $dup->id,
                            'report_code' => $dup->report_code,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // bad timestamp -> just use "now"
            }
        }

        // A report for a scan that is already in History: the phone only sends
        // detection_id, the photo and YOLO boxes are read from that scan here.
        $detectionId = (int) $request->input('detection_id');
        if ($detectionId > 0) {
            $row = DB::table('user_detections')
                ->where('user_id', $user->id)
                ->where('id', $detectionId)
                ->first();

            if ($row) {
                $merge = [];
                if (empty($request->input('image_base64')) && !empty($row->image_path)) {
                    $merge['image_base64'] = $row->image_path;
                }
                if (
                    empty($request->input('detection_boxes'))
                    && Schema::hasColumn('user_detections', 'detection_boxes')
                    && !empty($row->detection_boxes)
                ) {
                    $decoded = json_decode($row->detection_boxes, true);
                    if (is_array($decoded) && !empty($decoded['boxes'])) {
                        $merge['detection_boxes'] = $decoded['boxes'];
                        $merge['boxes_src_w']     = $decoded['src_w'] ?? null;
                        $merge['boxes_src_h']     = $decoded['src_h'] ?? null;
                    }
                }
                if ($merge) {
                    $request->merge($merge);
                }
            }
        }

        // The real work: identical validation + insert to the website.
        $response = $this->web()->store($request);
        $payload  = method_exists($response, 'getData') ? $response->getData(true) : [];

        if (!empty($payload['success']) && !empty($payload['report_code'])) {
            $report = FarmerReport::where('report_code', $payload['report_code'])->first();
            if ($report) {
                $payload['id'] = $report->id;
                if ($createdAt) {
                    DB::table('farmer_reports')->where('id', $report->id)->update([
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
            return response()->json($payload);
        }

        return $response;
    }

    /** Delete one of MY reports (same rule as the website: own reports only). */
    public function destroy(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        $report = FarmerReport::where('user_id', $user->id)->find((int) $request->input('id'));
        if (!$report) {
            return response()->json(['success' => false, 'message' => 'Report not found.'], 404);
        }
        $report->delete();

        return response()->json(['success' => true]);
    }
}