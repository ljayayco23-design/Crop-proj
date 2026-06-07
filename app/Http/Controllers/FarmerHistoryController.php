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
        foreach ($kbRecords as $row) {
            $knowledgeBase[strtolower(trim($row->disease))] = [
                'treatments' => $row->treatments,
                'causes' => $row->causes ?? '—',
                'nutrient_deficiency' => $row->nutrient_deficiency ?? '—',
                'grain_damage' => $row->grain_damage ?? '—',
                'prevention' => $row->prevention ?? '—'
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
            if ($row->image_path) {
                            // If it's a base64 string, don't use asset(). If it's an old file path, use asset().
                            if (str_starts_with($row->image_path, 'data:image')) {
                                $images[] = $row->image_path;
                            } else {
                                $images[] = asset($row->image_path); 
                            }
                        }

            if (isset($row->confidence)) $confidences[] = (int)$row->confidence;
        }

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

        // Strict Filter Check: If the detection is random/unrecognized, reject it
        if (!in_array($class_key, $diseaseNames) && !in_array($class_key, $pestNames)) {
            return response()->json([
                'success' => false, 
                'message' => 'Unrecognized or random class detection ignored.'
            ], 422);
        }

        $image_path = null;

        // VERCEL FIX: Avoid using public_path() or File::put() because Vercel is read-only.
        // Instead, we directly store the compressed Base64 string in the database.
        if ($request->filled('image_base64')) {
            $image_path = $request->image_base64; 
        }

        // Insert directly into your production TiDB database table
        DB::table('user_detections')->insert([
            'user_id' => $user_id,
            'class_key' => $request->class_key,
            'confidence' => $request->confidence ?? 0,
            'image_path' => $image_path,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json(['success' => true]);
    }

    
    public function action(Request $request)
    {
        $user_id = Auth::id();
        $action = $request->action;

        if ($action === 'delete_image') {
            $image_path = str_replace(asset(''), '', $request->image_path);
            $image_path = ltrim($image_path, '/');

            if (File::exists(public_path($image_path))) {
                File::delete(public_path($image_path));
            }

            DB::table('user_detections')
                ->where('user_id', $user_id)
                ->where('image_path', $image_path)
                ->delete();

            return response()->json(['success' => true]);
        }

        if ($action === 'delete_detection') {
            $records = DB::table('user_detections')
                ->where('user_id', $user_id)
                ->where('class_key', $request->class_key)
                ->get();

            foreach ($records as $record) {
                if ($record->image_path && File::exists(public_path($record->image_path))) {
                    File::delete(public_path($record->image_path));
                }
            }

            DB::table('user_detections')
                ->where('user_id', $user_id)
                ->where('class_key', $request->class_key)
                ->delete();

            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false]);
    }
}