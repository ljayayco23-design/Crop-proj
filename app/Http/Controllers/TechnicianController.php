<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TechnicianController extends Controller
{
    public function dashboard()
    {
        // Safely fetch only independent metrics
        $totalFarmers = DB::table('users')->where('role', 'farmer')->count();
        $totalDetections = DB::table('user_detections')->count();

        return view('technician.dashboard', compact('totalFarmers', 'totalDetections'));
    }

    public function records()
    {
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

        // 4. Fetch All Users who are registered as farmers with their separate history
        $users = DB::table('users')->where('role', 'farmer')->get();
        $allUsersData = [];

        foreach ($users as $user) {
            $rawDetections = DB::table('user_detections')
                ->where('user_id', $user->id)
                ->orderBy('class_key')
                ->orderBy('created_at', 'desc')
                ->get();

            // Skip users with no logged detection records
            if ($rawDetections->isEmpty()) {
                continue;
            }

            $detectionData = [];
            $currentKey = '';
            $images = [];
            $confidences = [];

            foreach ($rawDetections as $row) {
                $key = strtolower(trim($row->class_key));
                
                if ($currentKey !== $key && $currentKey !== '') {
                    $isPest = isset($pestNames[$currentKey]) || (isset($groqTypes[$currentKey]) && $groqTypes[$currentKey] === 'pest');
                    $fallbackName = ucwords(str_replace('_', ' ', $currentKey));
                    $className = $isPest ? ($pestNames[$currentKey] ?? $fallbackName) : ($diseaseNames[$currentKey] ?? $fallbackName);

                    $detectionData[] = [
                        'class_key'  => $currentKey,
                        'class_name' => $className,
                        'is_pest'    => $isPest,
                        'images'     => $images,
                        'confidence' => $confidences[0] ?? 65
                    ];
                    $images = []; $confidences = [];
                }
                
                $currentKey = $key;

                if (!empty($row->image_path)) {
                    $path = $row->image_path;
                    if (str_starts_with($path, 'data:image/') || str_starts_with($path, 'http') || str_starts_with($path, '/')) {
                        $images[] = $path;
                    } elseif (strlen($path) > 255) {
                        $images[] = 'data:image/jpeg;base64,' . $path;
                    } else {
                        $images[] = asset($path);
                    }
                }
                if (isset($row->confidence)) $confidences[] = (int)$row->confidence;
            }

            if ($currentKey !== '') {
                $isPest = isset($pestNames[$currentKey]) || (isset($groqTypes[$currentKey]) && $groqTypes[$currentKey] === 'pest');
                $fallbackName = ucwords(str_replace('_', ' ', $currentKey));
                $className = $isPest ? ($pestNames[$currentKey] ?? $fallbackName) : ($diseaseNames[$currentKey] ?? $fallbackName);

                $detectionData[] = [
                    'class_key'  => $currentKey,
                    'class_name' => $className,
                    'is_pest'    => $isPest,
                    'images'     => $images,
                    'confidence' => $confidences[0] ?? 65
                ];
            }

            $allUsersData[] = [
                'user_name'     => $user->full_name ?? $user->name ?? 'Unknown Farmer',
                'email'         => $user->email,
                'detectionData' => $detectionData
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