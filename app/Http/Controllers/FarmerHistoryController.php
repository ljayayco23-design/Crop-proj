<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FarmerHistoryController extends Controller
{
    public function index()
    {
        $user_id = Auth::id();

        $diseaseNames = [
            'healthy_rice_plant' => "Healthy Rice Plant", 'bacterial_leaf_blight' => "Bacterial Leaf Blight",
            'leaf_blast' => "Leaf Blast", 'rice_false_smut' => "Rice False Smut",
            'sheath_blight' => "Sheath Blight", 'tungro_virus' => "Tungro Virus"
        ];
        $pestNames = [
            'brown_planthopper' => "Brown Planthopper", 'leaf_folders' => "Leaf Folders",
            'leafhopper' => "Leafhopper", 'rice_bug' => "Rice Bug", 'rice_gall_midge' => "Rice Gall Midge",
            'rice_leaf_roller' => "Rice Leaf Roller", 'rice_stem_borer' => "Rice Stem Borer", 'snail' => "Snail"
        ];

        // Fetch Knowledge Base details from your real treatments table
        $knowledgeBase = [];
        $kbRecords = DB::table('treatment_records')->whereNull('user_id')->get();

        // Safe conversion tool to avoid htmlspecialchars array type crashes
        $flatten = function($val, $def) {
            if (empty($val)) return $def;
            if (is_string($val)) {
                $trimmed = trim($val);
                if (str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{')) {
                    $decoded = json_decode($trimmed, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded;
                    }
                }
            }
            if (is_array($val)) return implode("\n• ", $val);
            if (is_object($val)) return json_encode($val);
            return (string) $val;
        };

        foreach ($kbRecords as $row) {
            $knowledgeBase[strtolower(trim($row->disease))] = [
                'treatments' => $flatten($row->treatments ?? null, 'No data available yet.'),
                'causes' => $flatten($row->causes ?? null, '—'),
                'nutrient_deficiency' => $flatten($row->nutrient_deficiency ?? null, '—'),
                'grain_damage' => $flatten($row->grain_damage ?? null, '—'),
                'prevention' => $flatten($row->prevention ?? null, '—'),
                'natural_enemies' => $flatten($row->natural_enemies ?? null, '—')
            ];
        }

        // Fetch real detections grouped by user_id from the database
        $rawDetections = DB::table('user_detections')
            ->where('user_id', $user_id)
            ->orderBy('class_key')
            ->orderBy('created_at', 'desc')
            ->get();

        $detectionData = [];
        $currentKey = '';
        $images = [];
        $confidences = [];

        foreach ($rawDetections as $row) {
            $key = strtolower(trim($row->class_key));
            
            if ($currentKey !== $key && $currentKey !== '') {
                $isPest = isset($pestNames[$currentKey]);
                $detectionData[] = [
                    'class_key' => $currentKey,
                    'class_name' => $isPest ? ($pestNames[$currentKey] ?? $currentKey) : ($diseaseNames[$currentKey] ?? $currentKey),
                    'is_pest' => $isPest,
                    'images' => $images,
                    'confidence' => !empty($confidences) ? max($confidences) : 65
                ];
                $images = []; $confidences = [];
            }
            
            $currentKey = $key;

            // ✅ THE FIX: Check if it's a Base64 string before applying asset()
            if (!empty($row->image_path)) {
                $path = $row->image_path;
                if (str_starts_with($path, 'data:image/') || str_starts_with($path, 'http') || str_starts_with($path, '/')) {
                    $images[] = $path;
                } elseif (strlen($path) > 255) {
                    // Fallback in case raw Base64 was saved without the data prefix
                    $images[] = 'data:image/jpeg;base64,' . $path;
                } else {
                    $images[] = asset($path);
                }
            }

            if (isset($row->confidence)) $confidences[] = (int)$row->confidence;
        }

        // Catch the last item
        if ($currentKey !== '') {
            $isPest = isset($pestNames[$currentKey]);
            $detectionData[] = [
                'class_key' => $currentKey,
                'class_name' => $isPest ? ($pestNames[$currentKey] ?? $currentKey) : ($diseaseNames[$currentKey] ?? $currentKey),
                'is_pest' => $isPest,
                'images' => $images,
                'confidence' => !empty($confidences) ? max($confidences) : 65
            ];
        }

        return view('farmer.history', compact('diseaseNames', 'pestNames', 'knowledgeBase', 'detectionData'));
    }

    public function saveDetection(Request $request)
    {
        $user_id = Auth::id();
        $class_key = strtolower(trim($request->class_key));

        $diseaseNames = [
            'healthy_rice_plant', 'bacterial_leaf_blight', 'leaf_blast', 
            'rice_false_smut', 'sheath_blight', 'tungro_virus'
        ];
        $pestNames = [
            'brown_planthopper', 'leaf_folders', 'leafhopper', 'rice_bug', 
            'rice_gall_midge', 'rice_leaf_roller', 'rice_stem_borer', 'snail'
        ];

        // Strict Filter Check
        if (!in_array($class_key, $diseaseNames) && !in_array($class_key, $pestNames)) {
            return response()->json([
                'success' => false, 
                'message' => 'Unrecognized or random class detection ignored.'
            ], 422);
        }

        // Simply grab the Base64 string
        $image_data = $request->input('image_base64');

        // Insert directly into your production MySQL/TiDB database table
        DB::table('user_detections')->insert([
            'user_id' => $user_id,
            'class_key' => $request->class_key,
            'confidence' => $request->confidence ?? 0,
            'image_path' => $image_data, 
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    public function action(Request $request)
    {
        $user_id = Auth::id();
        $action = $request->action;

        // Ensure we only delete from the database, not trying to access Vercel's file system
        if ($action === 'delete_image' || $action === 'delete_detection') {
            
            $query = DB::table('user_detections')->where('user_id', $user_id);
            
            if ($action === 'delete_image') {
                $query->where('image_path', $request->image_path);
            } else {
                $query->where('class_key', $request->class_key);
            }

            $query->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }
}