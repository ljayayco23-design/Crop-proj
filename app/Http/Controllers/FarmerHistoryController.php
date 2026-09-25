<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FarmerHistoryController extends Controller
{
    /**
     * Canonical class list, kept in sync with KnowledgeController and the
     * labels embedded in best.onnx (23 classes total). Two keys changed
     * from before: 'snail' -> 'applesnail_eggs' and
     * 'rice_gall_midge' -> 'rice_gall_midg' (matches the model's own
     * spelling), so raw prediction class keys resolve correctly.
     */
    private function getValidKeys()
    {
        return [
            'disease' => [
                'healthy_rice_plant', 'bacterial_leaf_blight', 'bacterial_leaf_streak',
                'brown_spot', 'downy_mildew', 'leaf_blast', 'rice_false_smut',
                'sheath_blight', 'tungro_virus',
            ],
            'pest' => [
                'applesnail_eggs', 'brown_planthopper', 'dead_heart', 'green_leafhopper',
                'leaf_folders', 'leafhopper', 'rice_bug', 'rice_gall_midg', 'rice_hispa',
                'rice_leaf_roller', 'rice_stem_borer', 'rice_thrips', 'rice_water_weevil',
                'whorl_maggot',
            ],
        ];
    }

    /**
     * Layer-1 image resolution for the history list. Figures out what kind
     * of value `image_path` actually holds and turns it into something an
     * <img src> can use directly — but, unlike the old version of this
     * logic, never just guesses: a base64 payload is decoded and checked
     * with getimagesizefromstring() before being trusted, and a bare
     * relative path is only used if the file is confirmed to exist. A row
     * that fails every check logs exactly why (with the detection id) so
     * the culprit row can be found in the DB instead of just showing up as
     * a plain black thumbnail on screen.
     */
    private function resolveDetectionImageSrc($row): array
    {
        $raw = $row->image_path ?? null;
        if (empty($raw)) {
            return ['src' => null, 'reason' => 'no_image_path'];
        }

        $path = trim($raw);

        // 1) Already a directly-usable URI.
        if (str_starts_with($path, 'data:image/')) {
            return ['src' => $path, 'reason' => null];
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return ['src' => $path, 'reason' => null];
        }
        // A leading "/" usually means an absolute local path, but raw
        // base64 JPEG data also commonly starts with "/9j/" — only trust
        // this as a path if the file actually exists, otherwise fall
        // through to the base64 checks below instead of handing the
        // browser a 404'd asset() URL (which is what used to render as a
        // plain black thumbnail).
        if (str_starts_with($path, '/') && File::exists(public_path(ltrim($path, '/')))) {
            return ['src' => asset($path), 'reason' => null];
        }

        // 2) Raw base64 with no "data:image/..." prefix (how the capture
        //    canvas' toDataURL() output ends up here after the prefix is
        //    stripped, or from older clients). Validate it before trusting
        //    it — a truncated/corrupted value is exactly what used to
        //    render as a blank black square.
        $looksLikeBase64 = (bool) preg_match('/^[A-Za-z0-9+\/]+={0,2}$/', $path);
        if ($looksLikeBase64 && strlen($path) > 100) {
            $decoded = base64_decode($path, true);
            if ($decoded !== false && @getimagesizefromstring($decoded) !== false) {
                return ['src' => 'data:image/jpeg;base64,' . $path, 'reason' => null];
            }

            Log::warning('FarmerHistory: detection row has invalid/corrupted base64 image_path', [
                'detection_id' => $row->id ?? null,
                'user_id' => $row->user_id ?? null,
                'stored_length' => strlen($path),
            ]);
            return ['src' => null, 'reason' => 'invalid_base64'];
        }

        // 3) Last resort: treat it as a relative public path, but only if
        //    the file is actually there — never hand the browser a 404.
        $publicPath = public_path(ltrim($path, '/'));
        if (File::exists($publicPath)) {
            return ['src' => asset($path), 'reason' => null];
        }

        Log::warning('FarmerHistory: detection row image_path did not match any known format', [
            'detection_id' => $row->id ?? null,
            'user_id' => $row->user_id ?? null,
            'value_preview' => substr($path, 0, 40),
            'stored_length' => strlen($path),
        ]);
        return ['src' => null, 'reason' => 'unresolvable_path'];
    }

    public function index()
    {
        $user_id = Auth::id();
        
        $diseaseNames = [
            'healthy_rice_plant'    => "Healthy Rice Plant",
            'bacterial_leaf_blight' => "Bacterial Leaf Blight",
            'bacterial_leaf_streak' => "Bacterial Leaf Streak",
            'brown_spot'            => "Brown Spot",
            'downy_mildew'          => "Downy Mildew",
            'leaf_blast'            => "Leaf Blast",
            'rice_false_smut'       => "Rice False Smut",
            'sheath_blight'         => "Sheath Blight",
            'tungro_virus'          => "Tungro Virus",
        ];
        $pestNames = [
            'applesnail_eggs'   => "Apple Snail Eggs",
            'brown_planthopper' => "Brown Planthopper",
            'dead_heart'        => "Dead Heart",
            'green_leafhopper'  => "Green Leafhopper",
            'leaf_folders'      => "Leaf Folders",
            'leafhopper'        => "Leafhopper",
            'rice_bug'          => "Rice Bug",
            'rice_gall_midg'    => "Rice Gall Midge",
            'rice_hispa'        => "Rice Hispa",
            'rice_leaf_roller'  => "Rice Leaf Roller",
            'rice_stem_borer'   => "Rice Stem Borer",
            'rice_thrips'       => "Rice Thrips",
            'rice_water_weevil' => "Rice Water Weevil",
            'whorl_maggot'      => "Whorl Maggot",
        ];

        // Safe conversion tool
        $flatten = function($val, $def) {
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

        // 1. Fetch Fallback / Admin Knowledge Base — this is the ONLY source
        // of truth for MODEL-classified scans. It is admin-curated (managed
        // elsewhere via KnowledgeController) and is never touched by an
        // individual farmer's scan results.
        $knowledgeBase = [];
        $kbRecords = DB::table('treatment_records')->whereNull('user_id')->get();
        foreach ($kbRecords as $row) {
            $knowledgeBase[strtolower(trim($row->disease))] = [
                'description' => $flatten($row->description ?? null, '—'),
                'treatments' => $flatten($row->treatments ?? null, 'No data available yet.'),
                'causes' => $flatten($row->causes ?? null, '—'),
                'nutrient_deficiency' => $flatten($row->nutrient_deficiency ?? null, '—'),
                'grain_damage' => $flatten($row->grain_damage ?? null, '—'),
                'natural_enemies' => $flatten($row->natural_enemies ?? null, '—'),
                'prevention' => $flatten($row->prevention ?? null, '—')
            ];
        }

        // NOTE: We deliberately do NOT read `groq_treatment_records` here and
        // no longer merge it into $knowledgeBase. That table is the
        // admin-curated Groq knowledge base (managed via KnowledgeController)
        // and mixing it in here used to make EVERY scan of a class — model
        // or Groq, any farmer's — display whatever the most recent Groq
        // write happened to be. Each GROQ-classified scan below instead
        // carries its own independent snapshot (see `groq_snapshot` on
        // `user_detections`, populated in saveDetection()). MODEL-classified
        // scans always read from $knowledgeBase above.
        $activeKnowledgeBase = $knowledgeBase;

        // Resolve this farmer's field list (main pin + any additional pins from the map)
        $authUser = Auth::user();
        $fieldsMeta = [
            'main' => ['key' => 'main', 'label' => ($authUser->farm_name ?? null) ?: 'Main Field'],
        ];
        $additionalFarms = is_array($authUser->additional_farms ?? null) ? $authUser->additional_farms : [];
        foreach ($additionalFarms as $i => $farm) {
            $key = $farm['id'] ?? ('extra_' . $i);
            $fieldsMeta[$key] = [
                'key' => $key,
                'label' => $farm['options']['farmName'] ?? ('Additional Field ' . ($i + 1)),
            ];
        }
        // Fetch real detections, grouped by field + class again — the
        // clickable row in the list is one per CATEGORY (e.g. "Leafhopper"),
        // and every occurrence of that category is kept as its own
        // "instance" underneath it: own id, own single image, own
        // confidence, own date, own source (groq vs on-device model). The
        // detail panel then lists every instance as its own bordered card
        // in a scrollable area, instead of flattening them into one shared
        // gallery/date like the very first version did.
        $rawDetections = DB::table('user_detections')
            ->where('user_id', $user_id)
            ->orderBy('field_key')
            ->orderBy('class_key')
            ->orderBy('created_at', 'desc')
            ->get();

        $grouped = $rawDetections->groupBy(function ($row) {
            $fieldKey = $row->field_key ?: 'main';
            return $fieldKey . '|' . strtolower(trim($row->class_key));
        });

        $hasGroqSnapshotColumn = Schema::hasColumn('user_detections', 'groq_snapshot');
        $hasDetectionBoxesColumn = Schema::hasColumn('user_detections', 'detection_boxes');

        $detectionData = [];
        foreach ($grouped as $groupKey => $rows) {
            [$fieldKey, $classKey] = explode('|', $groupKey, 2);

            // $rows is already ordered newest-first (see the query above),
            // so instance order below is preserved newest-first too.
            $instances = [];
            $anyInstanceIsPest = null; // only used if classKey is unrecognized by either static dictionary
            foreach ($rows as $row) {
                // Layer 1: try to resolve straight to a usable <img src>
                // here on the page itself. This is deliberately strict —
                // it validates base64 payloads instead of trusting them —
                // so a corrupted/truncated image_path becomes a visible
                // fallback icon instead of a blank black thumbnail.
                $resolved = $this->resolveDetectionImageSrc($row);
                $image = $resolved['src'];

                // Per-scan source: which engine actually classified THIS
                // photo — 'groq' (Groq AI vision analysis), 'yolo11n'
                // (on-device YOLO11n detector), or 'model' (the older
                // Teachable Machine/MobileNetV2 classifier). Comes from the
                // `source` column (see migration); rows saved before that
                // column existed, or with an unrecognized value, fall back
                // to 'model'.
                $source = 'model';
                if (isset($row->source) && in_array($row->source, ['groq', 'yolo11n'], true)) {
                    $source = $row->source;
                }

                // Each Groq-classified scan carries its OWN independent
                // snapshot of what Groq returned for that exact photo — never
                // shared with, or overwritten by, any other scan (model or
                // Groq) of the same class. Model-classified scans have no
                // snapshot and read from the shared admin knowledge base
                // instead (assembled into $activeKnowledgeBase above).
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

                // YOLO11n bounding boxes for THIS instance's photo, if any
                // were saved with it. Decoded independently of the image
                // itself — boxes are drawn as an overlay by the blade, not
                // baked into image_path, so a bad/old row here just means
                // no overlay is drawn, never a broken image.
                $boxes = null;
                if ($source === 'yolo11n' && $hasDetectionBoxesColumn && !empty($row->detection_boxes)) {
                    $decodedBoxes = json_decode($row->detection_boxes, true);
                    if (is_array($decodedBoxes) && !empty($decodedBoxes['boxes'])) {
                        $boxes = $decodedBoxes;
                    }
                }

                $instances[] = [
                    'id' => $row->id,
                    'image' => $image,
                    'boxes' => $boxes,
                    // Layer 2: a dedicated endpoint that re-reads this same
                    // row on demand and decodes/validates it server-side,
                    // independent of the Layer-1 guess above. The blade's
                    // onImageError() JS retries through this URL whenever
                    // the Layer-1 <img src> fails to actually render (e.g.
                    // a bad data URI, a 404'd asset path, or a browser/CSP
                    // quirk that Layer 1 alone can't see).
                    'image_fallback_url' => !empty($row->id) ? route('farmer.history.image', $row->id) : null,
                    'confidence' => isset($row->confidence) ? (int) $row->confidence : 0,
                    'date' => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('M d, Y g:i A') : null,
                    'source' => $source,
                    // This instance's own info block — Groq's snapshot if it
                    // has one, otherwise the shared admin/model knowledge base.
                    'kb' => $snapshot ?? ($activeKnowledgeBase[$classKey] ?? []),
                    'severity_label' => $snapshot['severity_label'] ?? null,
                    'severity_percent' => $snapshot['severity_percent'] ?? null,
                    'severity_message' => $snapshot['severity_message'] ?? null,
                ];
            }

            // Disease vs. pest is decided per CATEGORY (class_key), not per
            // scan, since that's what buckets the row into the Diseases or
            // Pests column. Known classes use the static dictionaries;
            // an unrecognized class falls back to whatever any of its own
            // Groq-classified instances reported.
            $isPest = isset($pestNames[$classKey]) || ($anyInstanceIsPest === true && !isset($diseaseNames[$classKey]));
            $fallbackName = ucwords(str_replace('_', ' ', $classKey));
            $className = $isPest ? ($pestNames[$classKey] ?? $fallbackName) : ($diseaseNames[$classKey] ?? $fallbackName);

            $detectionData[] = [
                'field_key' => $fieldKey,
                'class_key' => $classKey,
                'class_name' => $className,
                'is_pest' => $isPest,
                'instances' => $instances,
                // Summary badge in the detail header — most recent
                // instance's confidence.
                'confidence' => $instances[0]['confidence'] ?? 65,
            ];
        }
        // Split detections into per-field sections (main field always shown, others only if they have data)
        $fieldSections = [];
        foreach ($fieldsMeta as $key => $meta) {
            $fieldSections[$key] = ['key' => $key, 'label' => $meta['label'], 'diseases' => [], 'pests' => []];
        }
        foreach ($detectionData as $det) {
            $fk = $det['field_key'];
            if (!isset($fieldSections[$fk])) {
                $fieldSections[$fk] = ['key' => $fk, 'label' => 'Removed Field', 'diseases' => [], 'pests' => []];
            }
            if ($det['is_pest']) {
                $fieldSections[$fk]['pests'][] = $det;
            } else {
                $fieldSections[$fk]['diseases'][] = $det;
            }
        }
        $fieldSections = array_filter($fieldSections, function ($sec, $key) {
            return $key === 'main' || !empty($sec['diseases']) || !empty($sec['pests']);
        }, ARRAY_FILTER_USE_BOTH);
        return view('farmer.history', [
            'diseaseNames' => $diseaseNames, 
            'pestNames' => $pestNames, 
            'knowledgeBase' => $activeKnowledgeBase,
            'detectionData' => $detectionData,
            'fieldSections' => $fieldSections,
        ]);
    }

    public function saveDetection(Request $request)
    {
        $user_id = Auth::id();
        $class_key = strtolower(trim($request->class_key));
        $image_data = $request->input('image_base64');
        $field_key = $request->input('field_key') ?: 'main';
        $requestedSourceRaw = $request->input('source');
        $requestedSource = in_array($requestedSourceRaw, ['groq', 'yolo11n'], true) ? $requestedSourceRaw : 'model';

        // 1. Save standard detection history — one row per scan, own photo.
        $insertData = [
            'user_id' => $user_id,
            'field_key' => $field_key,
            'class_key' => $class_key,
            'confidence' => $request->confidence ?? 0,
            'image_path' => $image_data, 
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Record which engine produced THIS scan's classification, so the
        // history page can badge it later. Guarded with hasColumn() so this
        // doesn't break if the `source` migration hasn't been run yet.
        if (Schema::hasColumn('user_detections', 'source')) {
            $insertData['source'] = $requestedSource;
        }

        // 2. Per-instance Groq snapshot — this scan's OWN classification
        // output, captured at the moment it was made. This is intentionally
        // NOT written to the shared `groq_treatment_records` table anymore:
        // that table is the admin-curated Groq knowledge base (managed via
        // KnowledgeController) and previously got silently overwritten by
        // every farmer's own scan, which is exactly the bug this fixes.
        // Each Groq-classified scan now keeps its own independent
        // description/treatments/etc., separate from every other scan
        // (Groq or model) of the same class. Guarded with hasColumn() so
        // this doesn't break before the migration has been run.
        // 1b. Raw YOLO11n bounding boxes for THIS scan's photo — stored
        // separately from image_path (see the detection_boxes migration)
        // instead of baked into the photo's pixels, so history.blade.php
        // can draw them as an overlay on top of the plain, never-corrupted
        // photo. Only ever written for yolo11n scans that actually sent
        // box data, and only if the migration has been run.
        if ($requestedSource === 'yolo11n' && Schema::hasColumn('user_detections', 'detection_boxes')) {
            $boxes = $request->input('detection_boxes');
            $srcW = $request->input('boxes_src_w');
            $srcH = $request->input('boxes_src_h');
            if (!empty($boxes) && is_array($boxes) && $srcW && $srcH) {
                $insertData['detection_boxes'] = json_encode([
                    'boxes' => $boxes,
                    'src_w' => (float) $srcW,
                    'src_h' => (float) $srcH,
                ]);
            }
        }

        if ($requestedSource === 'groq' && Schema::hasColumn('user_detections', 'groq_snapshot')) {
            $gd = $request->input('groq_data');
            if (!empty($gd) && is_array($gd) && (!empty($gd['treatments']) || !empty($gd['description']))) {
                $insertData['groq_snapshot'] = json_encode([
                    'description'         => $gd['description'] ?? '',
                    'treatments'          => $gd['treatments'] ?? '',
                    'causes'              => $gd['causes'] ?? '',
                    'nutrient_deficiency' => $gd['nutrient_deficiency'] ?? '',
                    'grain_damage'        => $gd['grain_damage'] ?? '',
                    'pest_damage'         => $gd['pest_damage'] ?? '',
                    'natural_enemies'     => $gd['natural_enemies'] ?? '',
                    'prevention'          => $gd['prevention'] ?? '',
                    'severity_label'      => $gd['severity_label'] ?? null,
                    'severity_percent'    => $gd['severity_percent'] ?? null,
                    'severity_message'    => $gd['severity_message'] ?? null,
                    'is_pest'             => (bool) ($gd['is_pest'] ?? false),
                ]);
            }
        }

        DB::table('user_detections')->insert($insertData);

        return response()->json(['success' => true]);
    }

    public function action(Request $request)
    {
        $user_id = Auth::id();
        $action = $request->action;

        if ($action === 'delete_image' || $action === 'delete_detection' || $action === 'delete_instance') {
            $query = DB::table('user_detections')->where('user_id', $user_id);

            if ($action === 'delete_image') {
                // Legacy path, kept for backward compatibility.
                $query->where('image_path', $request->image_path);
            } elseif ($action === 'delete_instance') {
                // Removes exactly one individual detection occurrence (its
                // single photo included) by row id, without touching any
                // other occurrence of the same class.
                $query->where('id', $request->id);
            } else {
                $query->where('class_key', $request->class_key);
                if ($request->filled('field_key')) {
                    $query->where('field_key', $request->field_key);
                }
            }

            $query->delete();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }

    /**
     * Layer 2: fallback image endpoint. history.blade.php's
     * handleThumbImageError() JS retries through this URL whenever the
     * inline <img src> that resolveDetectionImageSrc() picked fails to
     * actually render. This
     * re-reads the row fresh from the DB and decodes/validates it
     * independently — completely different code path from Layer 1 — so a
     * problem specific to one layer (a bad data URI, a stale asset() URL,
     * a browser/CSP quirk) doesn't take the image down entirely.
     *
     * Every failure is logged with the detection id and the reason, so a
     * genuinely corrupted row can be traced back to its exact source
     * instead of just showing up as a broken thumbnail.
     */
    public function image($id)
    {
        $row = DB::table('user_detections')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$row) {
            Log::warning('FarmerHistory image fallback: detection not found or not owned by this user', [
                'detection_id' => $id,
                'user_id' => Auth::id(),
            ]);
            abort(404);
        }

        $raw = trim((string) ($row->image_path ?? ''));
        if ($raw === '') {
            Log::warning('FarmerHistory image fallback: empty image_path', ['detection_id' => $id]);
            abort(404);
        }

        // Remote URL — just send the browser straight there.
        if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
            return redirect($raw);
        }

        if (str_starts_with($raw, 'data:image/')) {
            $comma = strpos($raw, ',');
            $base64 = $comma !== false ? substr($raw, $comma + 1) : '';
        } elseif (str_starts_with($raw, '/')) {
            // Could be an absolute local file path — but raw base64 JPEG
            // data also commonly starts with "/9j/", so only treat this as
            // a file path if it actually exists on disk; otherwise fall
            // through and try decoding it as base64 below instead of
            // dead-ending on a false match.
            $full = public_path(ltrim($raw, '/'));
            if (File::exists($full)) {
                return response(File::get($full))->header('Content-Type', File::mimeType($full));
            }
            $base64 = $raw;
        } else {
            $base64 = $raw;
        }

        $decoded = $base64 !== '' ? base64_decode($base64, true) : false;
        $imgInfo = $decoded !== false ? @getimagesizefromstring($decoded) : false;

        if ($decoded === false || $imgInfo === false) {
            Log::warning('FarmerHistory image fallback: could not decode image_path as a valid image', [
                'detection_id' => $id,
                'user_id' => Auth::id(),
                'stored_length' => strlen($raw),
                'preview' => substr($raw, 0, 30),
            ]);
            abort(422, 'Stored image data is corrupted or unreadable.');
        }

        return response($decoded, 200)->header('Content-Type', $imgInfo['mime'] ?? 'image/jpeg');
    }

    public function analyzeImageWithGroq(Request $request)
    {
        ini_set('max_execution_time', 120);
        ini_set('memory_limit', '256M');
        
        $base64Image = $request->input('image_base64');
        $language = $request->input('language', 'tagalog'); 
        $apiKey = env('GROQ_API_KEY');

        if (!$apiKey) {
            return response()->json(['success' => false, 'message' => 'Groq API Key missing in .env file']);
        }

        if (empty($base64Image)) {
            return response()->json(['success' => false, 'message' => 'No image data received.']);
        }

        if (!preg_match('/^data:image\/(\w+);base64,/', $base64Image)) {
            $base64Image = 'data:image/jpeg;base64,' . $base64Image;
        }

        // Groq's class hints now come from getValidKeys() — the SAME
        // canonical, YOLO11n-synced taxonomy every other engine (and the
        // shared knowledge base) uses. This used to be its own separate,
        // partial list written in the old MobileNetV2/Teachable Machine
        // spelling ('rice_gall_midge', 'snail') and missing 10 of the 23
        // classes outright, which is exactly what made Groq quietly
        // depend on MobileNetV2's taxonomy instead of standing on its own.
        $validKeys = $this->getValidKeys();
        $diseaseKeys = implode(", ", $validKeys['disease']);
        $pestKeys = implode(", ", $validKeys['pest']);

$prompt = "You are an expert senior agronomist and crop pathologist. Perform a rigorous, detail-oriented visual analysis of the provided image.

        CRITICAL REJECTION RULE (NON-PADDY IMAGES):
        First, verify if the image actually contains a rice (paddy) plant, rice disease, or rice pest. If the image is unrelated (e.g., human faces, animals, vehicles, landscapes, different crops, or random objects), you MUST reject it by outputting exactly this:
        - \"class_name\": \"Unrelated Image\"
        - \"class_key\": \"\" (You MUST leave this strictly empty)
        - \"is_pest\": false
        - \"severity_label\": \"UNKNOWN\"
        - \"severity_message\": \"Cannot determine severity on a non-rice image.\"
        - \"description\": \"The uploaded image does not appear to be a rice plant, disease, or pest. Please upload a clear photo of a rice leaf, stem, or paddy field.\"
        - Set \"treatments\", \"causes\", \"nutrient_deficiency\", \"grain_damage\", \"pest_damage\", \"natural_enemies\", and \"prevention\" to \"—\".
        - Set \"confidence\" and \"severity_percent\" to 0.

        DIAGNOSIS RULE (RICE IMAGES):
        If it IS a rice plant, carefully analyze precise visual markers: lesion shapes, color gradients (e.g., chlorosis, necrosis), pest bite marks, frass, or structural tissue damage. Provide a highly accurate, reality-based diagnosis focused strictly on what is physically visible in the photo.

        CRITICAL INSTRUCTION: DO NOT use <think> tags. DO NOT output any reasoning, thinking, or step-by-step logic. Start your response immediately with the '{' character.
        
        CRITICAL CONTENT & LENGTH RULE: Provide high-grade, practical agronomic information. Make the text for 'description', 'treatments', 'causes', 'prevention', etc., concise yet comprehensive, limited strictly to 2 to 3 information-dense sentences each to prevent token overflow. Ensure the description specifically references what is visibly happening in the plant photo.

        CRITICAL LANGUAGE RULE:
        You MUST generate the content values for 'description', 'treatments', 'causes', 'nutrient_deficiency', 'grain_damage', 'pest_damage', 'prevention', and 'natural_enemies' strictly in the {$language} language. The JSON keys themselves MUST remain in English.

        CRITICAL NAMING RULE:
        If you detect one of these diseases: [{$diseaseKeys}] or pests: [{$pestKeys}], you MUST use the exact key string provided for 'class_key'. Do not invent a variation.

        CRITICAL FIELD-USAGE RULE (DISEASE VS. PEST DAMAGE):
        - 'grain_damage' describes how a DISEASE affects the grain/panicle (e.g., chalky or empty grains from a fungal or bacterial disease). Use \"—\" for this field when 'is_pest' is true.
        - 'pest_damage' describes the physical symptoms a PEST leaves on the plant (e.g., bite marks, leaf rolling, frass, hopperburn, sap-sucking lesions) — even for pests like leafhopper that mainly act as disease vectors rather than damaging grain directly, describe the visible feeding/vector damage here. Use \"—\" for this field when 'is_pest' is false.

        JSON format required:
        {
            \"class_name\": \"string\",
            \"class_key\": \"string\",
            \"is_pest\": boolean,
            \"confidence\": integer (0-100),
            \"severity_label\": \"HEALTHY, LOW, MODERATE, SEVERE, or UNKNOWN\",
            \"severity_message\": \"string\",
            \"severity_percent\": integer (0-100),
            \"description\": \"string\",
            \"treatments\": \"string\",
            \"causes\": \"string\",
            \"nutrient_deficiency\": \"string\",
            \"grain_damage\": \"string\",
            \"pest_damage\": \"string\",
            \"natural_enemies\": \"string\",
            \"prevention\": \"string\"
        }";
        
        // qwen/qwen3.8-27b is the vision model actually enabled for this
        // API key (qwen/qwen3.6-27b returned "does not exist or you do
        // not have access to it" — confirmed via test.php).
        try {
$response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(60)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'qwen/qwen3.8-27b', 
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text', 
                                    'text' => $prompt . "\n\nCRITICAL: Return ONLY raw, valid JSON. Do not wrap the response in markdown code blocks like ```json."
                                ],
                                [
                                    'type' => 'image_url', 
                                    'image_url' => ['url' => $base64Image]
                                ]
                            ]
                        ]
                    ],
                    'temperature' => 0.0, 
                    // 900, not 1024 — this org's on_demand tier caps
                    // output tokens per minute (OTPM) at 1000, and 1024
                    // tripped "Request too large" every single call.
                    'max_tokens' => 900, 
                    'response_format' => ['type' => 'json_object'],
                    'reasoning_effort' => 'none', // 🚨 Disables Qwen's thinking mode to pass JSON validation
                ]);
                
                if ($response->successful()) {
                $jsonData = $response->json();
                
                if (isset($jsonData['choices'][0]['message']['content'])) {
                    $content = $jsonData['choices'][0]['message']['content'];
                    
                    // BULLETPROOFING: Forcefully remove any <think> blocks if the model ignores the prompt
                    $content = preg_replace('/<think>.*?<\/think>/s', '', $content);
                    
                    // Clean markdown formatting if the AI wraps it in ```json ... ```
                    $content = str_replace(['```json', '```'], '', $content);
                    
                    // Match ONLY from the first '{' to the last '}'
                    if (preg_match('/\{.*\}/s', trim($content), $matches)) {
                        $jsonString = $matches[0];
                        $parsedData = json_decode($jsonString, true);

                        // If it parses cleanly, return the success payload
                        if (json_last_error() === JSON_ERROR_NONE && isset($parsedData['class_key'])) {
                            return response()->json(['success' => true, 'data' => $parsedData]);
                        }
                    }
                }
            }
            
// ... (Inside the try block, right after the if($response->successful()) block) ...

            // 🚨 ENHANCED ERROR HANDLING: Extract the exact Groq error message
            $errorData = $response->json();
            $exactGroqError = 'Unknown Groq Error';
            
            if (isset($errorData['error']['message'])) {
                $exactGroqError = $errorData['error']['message'];
            } elseif (isset($errorData['error'])) {
                $exactGroqError = is_string($errorData['error']) ? $errorData['error'] : json_encode($errorData['error']);
            }

            return response()->json([
                'success' => false, 
                'message' => 'Groq API Error: ' . $exactGroqError,
                'status_code' => $response->status(),
                'debug_info' => $errorData
            ]);

        // Catch Throwable to prevent fatal TypeErrors from breaking the JSON response
        } catch (\Throwable $e) { 
            return response()->json([
                'success' => false, 
                'message' => 'Server Error: ' . $e->getMessage(),
                'line' => $e->getLine()
            ]);
        }
    }
}