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

        $kbRecords = DB::table('treatment_records')->whereNull('user_id')->get();
        $knowledgeBase = [];
        foreach ($kbRecords as $row) {
            $knowledgeBase[strtolower(trim($row->disease))] = (array) $row;
        }

        $farmers = DB::table('users')->where('role', 'farmer')->orderBy('full_name')->get();
        $allUsersData = [];

        foreach ($farmers as $farmer) {
            $rawDetections = DB::table('user_detections')
                ->where('user_id', $farmer->id)
                ->orderBy('class_key')
                ->orderBy('created_at', 'desc')
                ->get();

            $detectionData = [];
            $currentKey = '';
            $images = [];

            foreach ($rawDetections as $row) {
                $key = strtolower(trim($row->class_key));
                if ($currentKey !== $key && $currentKey !== '') {
                    $isPest = isset($pestNames[$currentKey]);
                    $detectionData[] = [
                        'class_key' => $currentKey,
                        'class_name' => $isPest ? ($pestNames[$currentKey] ?? $currentKey) : ($diseaseNames[$currentKey] ?? $currentKey),
                        'is_pest' => $isPest,
                        'images' => $images
                    ];
                    $images = [];
                }
                $currentKey = $key;
                if ($row->image_path) $images[] = asset($row->image_path);
            }

            if ($currentKey !== '') {
                $isPest = isset($pestNames[$currentKey]);
                $detectionData[] = [
                    'class_key' => $currentKey,
                    'class_name' => $isPest ? ($pestNames[$currentKey] ?? $currentKey) : ($diseaseNames[$currentKey] ?? $currentKey),
                    'is_pest' => $isPest,
                    'images' => $images
                ];
            }

            $allUsersData[] = [
                'user_id' => $farmer->id,
                'user_name' => $farmer->full_name ?: $farmer->email,
                'email' => $farmer->email,
                'detectionData' => $detectionData
            ];
        }

        return view('technician.records', compact('allUsersData', 'knowledgeBase'));
    }

    public function updateKnowledge(Request $request)
    {
        $technician_name = Auth::user()->full_name ?? 'Technician';

        DB::table('treatment_records')
            ->where('disease', strtolower(trim($request->disease_key)))
            ->whereNull('user_id')
            ->update([
                'treatments' => trim($request->treatments ?? ''),
                'causes' => trim($request->causes ?? ''),
                'nutrient_deficiency' => trim($request->nutrient_deficiency ?? ''),
                'grain_damage' => trim($request->grain_damage ?? ''),
                'prevention' => trim($request->prevention ?? ''),
                'updated_by' => $technician_name,
                'updated_at' => now()
            ]);

        return redirect()->route('technician.records')->with('success', 'Knowledge base updated successfully!');
    }
}