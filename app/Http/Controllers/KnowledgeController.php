<?php

namespace App\Http\Controllers;

use App\Models\TreatmentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KnowledgeController extends Controller
{
    public function editor($id = null)
    {
        $diseaseNames = ['healthy_rice_plant' => "Healthy Rice Plant", 'bacterial_leaf_blight' => "Bacterial Leaf Blight", 'leaf_blast' => "Leaf Blast", 'rice_false_smut' => "Rice False Smut", 'sheath_blight' => "Sheath Blight", 'tungro_virus' => "Tungro Virus"];
        $pestNames = ['brown_planthopper' => "Brown Planthopper", 'leaf_folders' => "Leaf Folders", 'leafhopper' => "Leafhopper", 'rice_bug' => "Rice Bug", 'rice_gall_midge' => "Rice Gall Midge", 'rice_leaf_roller' => "Rice Leaf Roller", 'rice_stem_borer' => "Rice Stem Borer", 'snail' => "Snail"];

        $record = $id ? TreatmentRecord::whereNull('user_id')->findOrFail($id) : null;

        $savedKeys = TreatmentRecord::whereNull('user_id')
            ->when($record, function($query) use ($record) { return $query->where('id', '!=', $record->id); })
            ->pluck('disease')->map(fn($d) => strtolower(trim($d)))->toArray();

        return view('admin.knowledge.editor', compact('diseaseNames', 'pestNames', 'record', 'savedKeys'));
    }

    public function store(Request $request)
    {
        $data = [
            'type'       => $request->type,
            'disease'    => $request->disease,
            'description' => $request->description ?? '',
            'treatments' => $request->treatments ?? '',
            'causes'     => $request->causes ?? '',
            'updated_by' => Auth::user()->full_name ?? 'Admin',
        ];

        if ($request->type === 'disease') {
            $data['nutrient_deficiency'] = $request->nutrient_deficiency ?? '';
            $data['grain_damage']        = $request->grain_damage ?? '';
            $data['prevention']          = $request->prevention_tips ?? '';
            $data['natural_enemies']     = ''; 
        } else {
            $data['nutrient_deficiency'] = ''; 
            $data['grain_damage']        = $request->damage_symptoms ?? '';
            $data['natural_enemies']     = $request->natural_enemies ?? ''; 
            $data['prevention']          = $request->prevention ?? '';
        }

        if ($request->filled('record_id')) {
            TreatmentRecord::whereNull('user_id')->findOrFail($request->record_id)->update($data);
        } else {
            TreatmentRecord::create($data);
        }
        return back()->with('success', 'Knowledge entry saved successfully!');
    }

    public function destroyGroq($id)
    {
        DB::table('groq_treatment_records')->where('id', $id)->delete();
        return redirect()->route('admin.knowledge.management')->with('success', 'Groq Entry removed successfully.');
    }

    public function management()
    {
        $savedData = TreatmentRecord::whereNull('user_id')->latest()->get();
        $groqData = DB::table('groq_treatment_records')->orderBy('updated_at', 'desc')->get();
        
        $diseaseNames = ['healthy_rice_plant' => "Healthy Rice Plant", 'bacterial_leaf_blight' => "Bacterial Leaf Blight", 'leaf_blast' => "Leaf Blast", 'rice_false_smut' => "Rice False Smut", 'sheath_blight' => "Sheath Blight", 'tungro_virus' => "Tungro Virus"];
        $pestNames = ['brown_planthopper' => "Brown Planthopper", 'leaf_folders' => "Leaf Folders", 'leafhopper' => "Leafhopper", 'rice_bug' => "Rice Bug", 'rice_gall_midge' => "Rice Gall Midge", 'rice_leaf_roller' => "Rice Leaf Roller", 'rice_stem_borer' => "Rice Stem Borer", 'snail' => "Snail"];

        return view('admin.knowledge.management', compact('savedData', 'diseaseNames', 'pestNames', 'groqData'));
    }

    // UPDATED: Inserts a new row version to feed the timeline history inside modifier.blade.php
    public function updateGroq(Request $request)
    {
        $oldRecord = DB::table('groq_treatment_records')->where('id', $request->id)->first();
        $diseaseKey = $oldRecord->disease ?? 'unknown';
        $type = $oldRecord->type ?? (in_array(strtolower($diseaseKey), ['healthy_rice_plant','bacterial_leaf_blight','leaf_blast','rice_false_smut','sheath_blight','tungro_virus']) ? 'disease' : 'pest');

        DB::table('groq_treatment_records')->insert([
            'type'                => $type,
            'disease'             => $diseaseKey,
            'description'         => $request->description ?? '',
            'treatments'          => $request->treatments ?? '',
            'causes'              => $request->causes ?? '',
            'nutrient_deficiency' => $request->nutrient_deficiency ?? '',
            'grain_damage'        => $request->grain_damage ?? '',
            'natural_enemies'     => $request->natural_enemies ?? '', 
            'prevention'          => $request->prevention ?? '',
            'updated_by'          => Auth::user()->full_name ?? 'Admin',
            'created_at'          => now(),
            'updated_at'          => now()
        ]);

        return redirect()->back()->with('success', 'Groq AI generated knowledge updated successfully!');
    }

    // FIXED: Now queries groq_treatment_records & compacts $groqGrouped into the view 
    public function modifier()
    {
        $originalFile = storage_path('app/original_knowledge.json');
        $originalData = file_exists($originalFile) ? json_decode(file_get_contents($originalFile), true) : [];

        $records = DB::table('treatment_records')
            ->whereNull('user_id')
            ->orderBy('type')
            ->orderBy('disease')
            ->orderBy('updated_at', 'desc')
            ->get();

        $data = [];
        foreach ($records as $row) {
            $dbKey = strtolower($row->disease);
            $type = $row->type ?? (in_array($dbKey, ['healthy_rice_plant','bacterial_leaf_blight','leaf_blast','rice_false_smut','sheath_blight','tungro_virus']) ? 'disease' : 'pest');
            
            $data[$type][$dbKey][] = (array) $row;
        }

        // Fetch and group Groq AI data versions for Column 2
        $groqRecords = DB::table('groq_treatment_records')
            ->orderBy('type')
            ->orderBy('disease')
            ->orderBy('updated_at', 'desc')
            ->get();

        $groqGrouped = [];
        foreach ($groqRecords as $row) {
            $dbKey = strtolower($row->disease);
            $type = $row->type ?? (in_array($dbKey, ['healthy_rice_plant','bacterial_leaf_blight','leaf_blast','rice_false_smut','sheath_blight','tungro_virus']) ? 'disease' : 'pest');
            
            $groqGrouped[$type][$dbKey][] = (array) $row;
        }

        return view('admin.knowledge.modifier', compact('data', 'originalData', 'groqGrouped'));
    }

    // NEW METHOD: Handles the Technician knowledge base updates globally
    public function technicianUpdate(Request $request)
    {
        $diseaseKey = $request->disease_key;
        $isGroq = $request->is_groq == 1;

        $type = in_array(strtolower($diseaseKey), ['healthy_rice_plant','bacterial_leaf_blight','leaf_blast','rice_false_smut','sheath_blight','tungro_virus']) ? 'disease' : 'pest';

        $insertData = [
            'type'                => $type,
            'disease'             => $diseaseKey,
            'description'         => $request->description ?? '',
            'treatments'          => $request->treatments ?? '',
            'causes'              => $request->causes ?? '',
            'nutrient_deficiency' => $request->nutrient_deficiency ?? '',
            'grain_damage'        => $request->grain_damage ?? '',
            'natural_enemies'     => $request->natural_enemies ?? '',
            'prevention'          => $request->prevention ?? '',
            'updated_by'          => Auth::user()->full_name ?? 'Technician',
            'created_at'          => now(),
            'updated_at'          => now()
        ];

        if ($isGroq) {
            DB::table('groq_treatment_records')->insert($insertData);
            $message = 'Groq AI discovered knowledge updated successfully!';
        } else {
            DB::table('treatment_records')->insert($insertData);
            $message = 'Shared global knowledge base updated successfully!';
        }

        return redirect()->back()->with('success', $message);
    }

    public function destroy($id)
    {
        TreatmentRecord::whereNull('user_id')->findOrFail($id)->delete();
        return redirect()->route('admin.knowledge.management')->with('success', 'Entry removed successfully.');
    }
}