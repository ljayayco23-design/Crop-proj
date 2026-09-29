<?php

namespace App\Http\Controllers;

use App\Models\GroqTreatmentRecord;
use App\Models\TreatmentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KnowledgeController extends Controller
{
    /**
     * Canonical class list, kept in sync with the labels embedded in
     * best.onnx (23 classes total). Keys MUST match the model's names
     * dict exactly (including "rice_gall_midg", which is how the model
     * actually spells it) so farmer-facing lookups keyed off the raw
     * prediction class always resolve to a knowledge entry.
     *
     * NOTE: this replaces the old 6-disease / 8-pest lists. Two keys
     * changed from before: 'snail' -> 'applesnail_eggs' and
     * 'rice_gall_midge' -> 'rice_gall_midg' (model spelling). Any
     * existing treatment_records/groq_treatment_records rows still
     * saved under the old keys will keep displaying (there's a
     * ucfirst() fallback), but won't be recognized as "already saved"
     * under the new key, so re-check the Knowledge Management list
     * after deploying this.
     */
    public static function diseaseNames(): array
    {
        return [
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
    }

    public static function pestNames(): array
    {
        return [
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
    }

    /** Flat array of every disease key, for in_array() type checks. */
    private static function diseaseKeys(): array
    {
        return array_keys(self::diseaseNames());
    }

    private static function resolveType(string $diseaseKey): string
    {
        return in_array(strtolower($diseaseKey), self::diseaseKeys()) ? 'disease' : 'pest';
    }

    public function editor($id = null)
    {
        $diseaseNames = self::diseaseNames();
        $pestNames = self::pestNames();

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
        // Not yet wired into management.blade.php — added so the data is
        // there and queryable as soon as the view is updated to show it.
        $yoloData = DB::table('yolo11n_treatments_records')->orderBy('updated_at', 'desc')->get();

        $diseaseNames = self::diseaseNames();
        $pestNames = self::pestNames();

        // Groq AI Discovered Data: one entry per class, each holding the
        // CURRENT (newest) row for every dialect. Rows are versioned, so the
        // first row seen per (class, dialect) when walking newest-first is
        // the one that is live on the detection page.
        $groqClasses = [];
        foreach (GroqTreatmentRecord::orderByDesc('id')->get() as $row) {
            // Same rule the detection page uses: blank rows never count as the
            // live entry, so they can't hide real information.
            if (trim((string) $row->description) === '' && trim((string) $row->treatments) === '') {
                continue;
            }
            $key  = strtolower(trim($row->disease));
            $lang = strtolower(trim((string) ($row->language ?? ''))) ?: 'tagalog';

            if (!isset($groqClasses[$key])) {
                $type = $row->type ?: self::resolveType($key);
                $name = $type === 'disease'
                    ? ($diseaseNames[$key] ?? null)
                    : ($pestNames[$key] ?? null);

                $groqClasses[$key] = [
                    'key'        => $key,
                    'type'       => $type,
                    'name'       => $name ?? ucfirst(str_replace('_', ' ', $key)),
                    'updated_by' => $row->updated_by ?? 'Groq AI',
                    'updated_at' => optional($row->updated_at)->format('M d, Y h:i A'),
                    'dialects'   => [],
                ];
            }

            if (!isset($groqClasses[$key]['dialects'][$lang])) {
                $groqClasses[$key]['dialects'][$lang] = [
                    'description'         => $row->description ?? '',
                    'treatments'          => $row->treatments ?? '',
                    'causes'              => $row->causes ?? '',
                    'nutrient_deficiency' => $row->nutrient_deficiency ?? '',
                    'grain_damage'        => $row->grain_damage ?? '',
                    'natural_enemies'     => $row->natural_enemies ?? '',
                    'prevention'          => $row->prevention ?? '',
                    'updated_by'          => $row->updated_by ?? 'Groq AI',
                ];
            }
        }
        $groqClasses = array_values($groqClasses);

        return view('admin.knowledge.management', compact('savedData', 'diseaseNames', 'pestNames', 'groqClasses', 'yoloData'));
    }

    // Saves an admin edit of Groq knowledge for ONE class in ONE dialect. It
    // inserts a new version row (so modifier.blade.php keeps its history) and
    // that newest row immediately becomes what the detection page reuses.
    // Never touches treatment_records (the main knowledge base).
    public function saveGroqEntry(Request $request)
    {
        $key  = strtolower(trim((string) $request->input('disease')));
        $lang = strtolower(trim((string) $request->input('language')));

        if (!in_array($lang, ['tagalog', 'english', 'cebuano', 'hiligaynon'], true)) {
            return response()->json(['success' => false, 'message' => 'Unknown dialect.'], 422);
        }
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'Missing class.'], 422);
        }

        $type = self::resolveType($key);
        $updatedBy = Auth::user()->full_name ?? 'Admin';

        GroqTreatmentRecord::create([
            'type'                => $type,
            'disease'             => $key,
            'language'            => $lang,
            'description'         => (string) $request->input('description', ''),
            'treatments'          => (string) $request->input('treatments', ''),
            'causes'              => (string) $request->input('causes', ''),
            'nutrient_deficiency' => $type === 'disease' ? (string) $request->input('nutrient_deficiency', '') : '',
            'grain_damage'        => (string) $request->input('grain_damage', ''),
            'natural_enemies'     => $type === 'pest' ? (string) $request->input('natural_enemies', '') : '',
            'prevention'          => (string) $request->input('prevention', ''),
            'updated_by'          => $updatedBy,
        ]);

        return response()->json([
            'success'    => true,
            'updated_by' => $updatedBy,
            'updated_at' => now()->format('M d, Y h:i A'),
        ]);
    }

    // Deletes ALL Groq detection data for one class (every dialect, every
    // saved version) from groq_treatment_records ONLY. The main knowledge base
    // is untouched. The next Groq detection of that class regenerates it.
    public function destroyGroqClass(Request $request)
    {
        $key = strtolower(trim((string) $request->input('disease')));

        if ($key !== '') {
            GroqTreatmentRecord::whereRaw('LOWER(TRIM(disease)) = ?', [$key])->delete();
        }

        return redirect()->route('admin.knowledge.management')->with('success', 'Groq detection data removed successfully.');
    }

    // UPDATED: Inserts a new row version to feed the timeline history inside modifier.blade.php
    public function updateGroq(Request $request)
    {
        $oldRecord = DB::table('groq_treatment_records')->where('id', $request->id)->first();
        $diseaseKey = $oldRecord->disease ?? 'unknown';
        $type = $oldRecord->type ?? self::resolveType($diseaseKey);

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
            $type = $row->type ?? self::resolveType($dbKey);

            $data[$type][$dbKey][] = (array) $row;
        }

        // Fetch and group Groq AI data versions for Column 2
        // Ordered per class, then per dialect, newest version first. The first
        // row seen for each (class, dialect) that has real content is the LIVE
        // one (what the detection page currently reuses) and is flagged.
        $groqRecords = DB::table('groq_treatment_records')
            ->orderBy('type')
            ->orderBy('disease')
            ->orderBy('language')
            ->orderBy('id', 'desc')
            ->get();

        $groqGrouped = [];
        $liveSeen = [];
        foreach ($groqRecords as $row) {
            $dbKey = strtolower(trim($row->disease));
            $type = $row->type ?? self::resolveType($dbKey);
            $lang = strtolower(trim((string) ($row->language ?? ''))) ?: 'tagalog';

            $arr = (array) $row;
            $arr['language'] = $lang;
            $hasContent = trim((string) $row->description) !== '' || trim((string) $row->treatments) !== '';
            $arr['is_current'] = false;
            if ($hasContent && !isset($liveSeen[$dbKey . '|' . $lang])) {
                $liveSeen[$dbKey . '|' . $lang] = true;
                $arr['is_current'] = true;
            }

            $groqGrouped[$type][$dbKey][] = $arr;
        }

        $diseaseNames = self::diseaseNames();
        $pestNames = self::pestNames();

        return view('admin.knowledge.modifier', compact('data', 'originalData', 'groqGrouped', 'diseaseNames', 'pestNames'));
    }

    // NEW METHOD: Handles the Technician knowledge base updates globally
    public function technicianUpdate(Request $request)
    {
        $diseaseKey = $request->disease_key;
        $isGroq = $request->is_groq == 1;

        $type = self::resolveType($diseaseKey);

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

    // Mirrors updateGroq() exactly, but against the YOLO11n-specific table —
    // each save is a new version row (not an update), so a timeline history
    // can be shown later the same way modifier.blade.php shows Groq's.
    public function saveYoloKnowledge(Request $request)
    {
        $diseaseKey = $request->disease;
        $type = $request->type ?? self::resolveType($diseaseKey);

        DB::table('yolo11n_treatments_records')->insert([
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

        return redirect()->back()->with('success', 'YOLO11n knowledge entry saved successfully!');
    }

    public function destroy($id)
    {
        TreatmentRecord::whereNull('user_id')->findOrFail($id)->delete();
        return redirect()->route('admin.knowledge.management')->with('success', 'Entry removed successfully.');
    }
}