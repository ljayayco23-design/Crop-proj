<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TechnicianController extends Controller
{
    /**
     * STRICT barangay match, shared by dashboard() and records() so they
     * can't disagree. A farmer counts for this technician if and only if
     * their own barangay_id matches a barangay_id on one of this
     * technician's ACTIVE Assignment rows (user_type = 'technician').
     *
     * No fallback to province/city. A technician with no active
     * assignment yet gets an empty list of barangay ids, so every query
     * built from this returns nothing for them — never "everyone".
     */
    private function assignedBarangayIds(): \Illuminate\Support\Collection
    {
        return Assignment::active()
            ->where('user_id', Auth::id())
            ->where('user_type', 'technician')
            ->pluck('barangay_id');
    }

    public function dashboard()
    {
        $barangayIds = $this->assignedBarangayIds();

        // Both metrics scoped to this technician's own assigned
        // barangay(s) only — previously these were global counts across
        // every farmer/detection in the system, regardless of area.
        $totalFarmers = DB::table('users')
            ->where('role', 'farmer')
            ->whereIn('barangay_id', $barangayIds)
            ->count();

        $totalDetections = DB::table('user_detections')
            ->whereIn('user_id', function ($q) use ($barangayIds) {
                $q->select('id')
                  ->from('users')
                  ->where('role', 'farmer')
                  ->whereIn('barangay_id', $barangayIds);
            })
            ->count();

        return view('technician.dashboard', compact('totalFarmers', 'totalDetections'));
    }

    public function records()
    {
        $barangayIds = $this->assignedBarangayIds();

        // Safe conversion tool to handle string/JSON array formatting
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

        // 1. Fetch Fallback Knowledge Base
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
                'is_groq'             => false // Tag as primary fallback data
            ];
        }

        // 2. Fetch Groq AI Knowledge Base
        $groqKnowledgeBase = [];
        $groqRecords = DB::table('groq_treatment_records')->get();
        $groqTypes = [];

        foreach ($groqRecords as $row) {
            $diseaseKey = strtolower(trim($row->disease));
            $groqTypes[$diseaseKey] = $row->type;

            $groqKnowledgeBase[$diseaseKey] = [
                'description'         => $flatten($row->description ?? null, '—'),
                'treatments'          => $flatten($row->treatments ?? null, '—'),
                'causes'              => $flatten($row->causes ?? null, '—'),
                'nutrient_deficiency' => $flatten($row->nutrient_deficiency ?? null, '—'),
                'grain_damage'        => $flatten($row->grain_damage ?? null, '—'),
                'natural_enemies'     => $flatten($row->natural_enemies ?? null, '—'),
                'prevention'          => $flatten($row->prevention ?? null, '—'),
                'is_groq'             => true // Tag as Groq AI data
            ];
        }

        // 3. STRICT MERGE: Force Groq Data to overwrite Fallback Data globally
        foreach ($groqKnowledgeBase as $key => $data) {
            $knowledgeBase[$key] = $data;
        }

        // Static name mapping arrays
        $diseaseNames = [
            'healthy_rice_plant'    => "Healthy Rice Plant",
            'bacterial_leaf_blight' => "Bacterial Leaf Blight",
            'leaf_blast'            => "Leaf Blast",
            'rice_false_smut'       => "Rice False Smut",
            'sheath_blight'         => "Sheath Blight",
            'tungro_virus'          => "Tungro Virus"
        ];
        $pestNames = [
            'brown_planthopper' => "Brown Planthopper",
            'leaf_folders'      => "Leaf Folders",
            'leafhopper'        => "Leafhopper",
            'rice_bug'          => "Rice Bug",
            'rice_gall_midge'   => "Rice Gall Midge",
            'rice_leaf_roller'  => "Rice Leaf Roller",
            'rice_stem_borer'   => "Rice Stem Borer",
            'snail'             => "Snail"
        ];

        // 4. Fetch ONLY farmers in this technician's assigned barangay(s).
        // Previously this was `where('role', 'farmer')` with no area
        // scope at all, so every technician saw every farmer in the
        // system here.
        $users = DB::table('users')
            ->where('role', 'farmer')
            ->whereIn('barangay_id', $barangayIds)
            ->get();

        $hasGroqSnapshotColumn = \Illuminate\Support\Facades\Schema::hasColumn('user_detections', 'groq_snapshot');
        $hasSourceColumn = \Illuminate\Support\Facades\Schema::hasColumn('user_detections', 'source');
        $hasDetectionBoxesColumn = \Illuminate\Support\Facades\Schema::hasColumn('user_detections', 'detection_boxes');

        $allUsersData = [];

        foreach ($users as $user) {
            // records_blade.php's @forelse renders a card per farmer
            // regardless of whether they have detections yet (it shows
            // "This farmer has no detection records yet." itself), so —
            // unlike the old flat version — we no longer `continue` past
            // farmers with zero rows.

            // Resolve this farmer's field list (main pin + any additional
            // pins from the map), same as FarmerHistoryController@index.
            // $user is a stdClass from DB::table(), so additional_farms
            // is still a raw JSON string here and needs decoding.
            $fieldsMeta = [
                'main' => ['key' => 'main', 'label' => ($user->farm_name ?? null) ?: 'Main Field'],
            ];
            $additionalFarms = [];
            if (!empty($user->additional_farms)) {
                $decoded = json_decode($user->additional_farms, true);
                if (is_array($decoded)) {
                    $additionalFarms = $decoded;
                }
            }
            foreach ($additionalFarms as $i => $farm) {
                $key = $farm['id'] ?? ('extra_' . $i);
                $fieldsMeta[$key] = [
                    'key'   => $key,
                    'label' => $farm['options']['farmName'] ?? ('Additional Field ' . ($i + 1)),
                ];
            }

            $rawDetections = DB::table('user_detections')
                ->where('user_id', $user->id)
                ->orderBy('field_key')
                ->orderBy('class_key')
                ->orderBy('created_at', 'desc')
                ->get();

            $grouped = $rawDetections->groupBy(function ($row) {
                $fieldKey = $row->field_key ?: 'main';
                return $fieldKey . '|' . strtolower(trim($row->class_key));
            });

            $detectionData = [];
            foreach ($grouped as $groupKey => $rows) {
                [$fieldKey, $classKey] = explode('|', $groupKey, 2);

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

                    // Same three-way engine check as FarmerHistoryController@index
                    // ('groq' / 'yolo11n' / fallback 'model') — this used to only
                    // ever check for 'groq' and call everything else "model",
                    // which is why every yolo11n scan showed up here badged
                    // "Model" with no box data at all.
                    $source = 'model';
                    if ($hasSourceColumn && isset($row->source) && in_array($row->source, ['groq', 'yolo11n'], true)) {
                        $source = $row->source;
                    }

                    $snapshot = null;
                    if ($source === 'groq' && $hasGroqSnapshotColumn && !empty($row->groq_snapshot)) {
                        $decodedSnap = json_decode($row->groq_snapshot, true);
                        if (is_array($decodedSnap)) {
                            $snapshot = $decodedSnap;
                        }
                    }

                    if ($snapshot !== null && array_key_exists('is_pest', $snapshot)) {
                        $anyInstanceIsPest = (bool) $snapshot['is_pest'];
                    }

                    // Same YOLO11n bounding-box decode as FarmerHistoryController@index
                    // — this is the piece that was missing entirely here, which is
                    // why Records never had anything to draw on click even after
                    // the blade templates were fixed to draw it.
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
                        'date'             => $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('M d, Y g:i A') : null,
                        'source'           => $source,
                        'kb'               => $snapshot ?? ($knowledgeBase[$classKey] ?? []),
                        'severity_label'   => $snapshot['severity_label'] ?? null,
                        'severity_percent' => $snapshot['severity_percent'] ?? null,
                        'severity_message' => $snapshot['severity_message'] ?? null,
                    ];
                }

                // Class-level pest/disease decision: known dictionaries
                // first, then this class's own Groq instance data, then
                // the admin-curated groq_treatment_records 'type' column.
                $isPest = isset($pestNames[$classKey])
                    || (!isset($diseaseNames[$classKey]) && $anyInstanceIsPest === true)
                    || (!isset($diseaseNames[$classKey]) && $anyInstanceIsPest === null && ($groqTypes[$classKey] ?? null) === 'pest');

                $fallbackName = ucwords(str_replace('_', ' ', $classKey));
                $className = $isPest ? ($pestNames[$classKey] ?? $fallbackName) : ($diseaseNames[$classKey] ?? $fallbackName);

                $detectionData[] = [
                    'field_key'  => $fieldKey,
                    'class_key'  => $classKey,
                    'class_name' => $className,
                    'is_pest'    => $isPest,
                    'instances'  => $instances,
                    'confidence' => $instances[0]['confidence'] ?? 65,
                ];
            }

            // Split into per-field sections (main field always shown,
            // others only if they have data) — same as
            // FarmerHistoryController@index.
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

            $allUsersData[] = [
                'user_id'       => $user->id,
                'user_name'     => $user->full_name ?? $user->name ?? 'Unknown Farmer',
                'email'         => $user->email,
                'fieldSections' => $fieldSections,
            ];
        }

        return view('technician.records', [
            'allUsersData'  => $allUsersData,
            'knowledgeBase' => $knowledgeBase
        ]);
    }

    public function updateKnowledge(Request $request)
    {
        $diseaseKey = strtolower(trim($request->disease_key));
        $isGroq = $request->is_groq == 1; // Pulled from the modal's hidden input
        $technician_name = Auth::user()->full_name ?? Auth::user()->name ?? 'Technician';

        // Determine if this is a disease or a pest for schema saving
        $type = in_array($diseaseKey, ['healthy_rice_plant','bacterial_leaf_blight','leaf_blast','rice_false_smut','sheath_blight','tungro_virus']) ? 'disease' : 'pest';

        // Prepare the unified data payload
        $insertData = [
            'type'                => $type,
            'disease'             => $diseaseKey,
            'description'         => trim($request->description ?? ''),
            'treatments'          => trim($request->treatments ?? ''),
            'causes'              => trim($request->causes ?? ''),
            'nutrient_deficiency' => trim($request->nutrient_deficiency ?? ''),
            'grain_damage'        => trim($request->grain_damage ?? ''),
            'natural_enemies'     => trim($request->natural_enemies ?? ''),
            'prevention'          => trim($request->prevention ?? ''),
            'updated_by'          => $technician_name,
            'created_at'          => now(),
            'updated_at'          => now()
        ];

        // Route the data to the correct table based on where the detection came from
        if ($isGroq) {
            DB::table('groq_treatment_records')->insert($insertData);
            $message = 'Groq AI discovered knowledge updated successfully! It will now appear in the Modifier History.';
        } else {
            DB::table('treatment_records')->insert($insertData);
            $message = 'Shared Primary Knowledge Base updated successfully! It will now appear in the Modifier History.';
        }

        return redirect()->route('technician.records')->with('success', $message);
    }
}