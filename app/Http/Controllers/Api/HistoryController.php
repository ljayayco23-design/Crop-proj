<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerHistoryController;
use App\Http\Controllers\KnowledgeController;
use App\Models\TreatmentRecord;
use App\Support\RiceTaxonomy;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile (Flutter) side of "History".
 *
 * Reads the SAME table the website History page reads (user_detections), so a
 * scan saved from the app shows up on the website and the other way round.
 * Saving is NOT here: the app saves through /detection/save, which calls the
 * website's FarmerHistoryController::saveDetection().
 */
class HistoryController extends Controller
{
    private function denyIfNotFarmer(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }
        Auth::setUser($user); // FarmerHistoryController reads Auth::id()
        return null;
    }

    private function flatten($val, $def)
    {
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
    }

    /** GET /history?limit=100&offset=0 -> the farmer's scans, newest first (no photos). */
    public function index(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }

        $user  = $request->user();
        $limit = min(max((int) $request->query('limit', 100), 1), 300);
        $offset = max((int) $request->query('offset', 0), 0);

        $diseaseNames = KnowledgeController::diseaseNames();
        $pestNames    = KnowledgeController::pestNames();

        // Admin knowledge base: fallback text for scans that have no snapshot.
        $knowledgeBase = [];
        try {
            foreach (TreatmentRecord::whereNull('user_id')->get() as $row) {
                $knowledgeBase[strtolower(trim($row->disease))] = [
                    'description'         => $this->flatten($row->description ?? null, '—'),
                    'treatments'          => $this->flatten($row->treatments ?? null, 'No data available yet.'),
                    'causes'              => $this->flatten($row->causes ?? null, '—'),
                    'nutrient_deficiency' => $this->flatten($row->nutrient_deficiency ?? null, '—'),
                    'grain_damage'        => $this->flatten($row->grain_damage ?? null, '—'),
                    'natural_enemies'     => $this->flatten($row->natural_enemies ?? null, '—'),
                    'prevention'          => $this->flatten($row->prevention ?? null, '—'),
                ];
            }
        } catch (\Throwable $e) {
            $knowledgeBase = [];
        }

        // Field labels (same rule as /detection/meta)
        $fields = ['main' => $user->farm_name ?: 'Main Farm'];
        $additional = is_array($user->additional_farms ?? null) ? $user->additional_farms : [];
        foreach ($additional as $i => $farm) {
            $fields[$farm['id'] ?? ('extra_' . $i)] = $farm['options']['farmName'] ?? ('Additional Field ' . ($i + 1));
        }

        $hasSource    = Schema::hasColumn('user_detections', 'source');
        $hasSnapshot  = Schema::hasColumn('user_detections', 'groq_snapshot');
        $hasBoxes     = Schema::hasColumn('user_detections', 'detection_boxes');

        // image_path is a big base64 string: never select it for the list.
        $cols = ['id', 'field_key', 'class_key', 'confidence', 'created_at'];
        if ($hasSource)   $cols[] = 'source';
        if ($hasSnapshot) $cols[] = 'groq_snapshot';
        if ($hasBoxes)    $cols[] = 'detection_boxes';

        $query = DB::table('user_detections')->where('user_id', $user->id);
        $total = (clone $query)->count();
        $rows  = $query->select($cols)
            ->selectRaw("(image_path IS NOT NULL AND image_path <> '') as has_image")
            ->orderByDesc('created_at')->orderByDesc('id')
            ->offset($offset)->limit($limit)
            ->get();

        $tax = RiceTaxonomy::payload();
        $items = [];
        foreach ($rows as $row) {
            $key = strtolower(trim((string) $row->class_key));

            $source = 'model';
            if ($hasSource && in_array($row->source ?? null, ['groq', 'yolo11n'], true)) {
                $source = $row->source;
            }

            $snapshot = null;
            if ($hasSnapshot && !empty($row->groq_snapshot)) {
                $decoded = json_decode($row->groq_snapshot, true);
                if (is_array($decoded)) {
                    $snapshot = $decoded;
                    $pd = trim((string) ($snapshot['pest_damage'] ?? ''));
                    if (!empty($snapshot['is_pest']) && $pd !== '' && $pd !== '—') {
                        $snapshot['grain_damage'] = $snapshot['pest_damage'];
                    }
                }
            }

            $boxes = null;
            if ($source === 'yolo11n' && $hasBoxes && !empty($row->detection_boxes)) {
                $d = json_decode($row->detection_boxes, true);
                if (is_array($d) && !empty($d['boxes'])) $boxes = $d;
            }

            $isPest = isset($pestNames[$key]) || (!isset($diseaseNames[$key]) && !empty($snapshot['is_pest']));
            $name = $isPest
                ? ($pestNames[$key] ?? ucwords(str_replace('_', ' ', $key)))
                : ($diseaseNames[$key] ?? ucwords(str_replace('_', ' ', $key)));

            $sev = $tax['severity'][$key] ?? null;
            $fieldKey = $row->field_key ?: 'main';

            $items[] = [
                'id'               => (int) $row->id,
                'class_key'        => $key,
                'class_name'       => $name,
                'is_pest'          => $isPest,
                'field_key'        => $fieldKey,
                'field_label'      => $fields[$fieldKey] ?? 'Removed Field',
                'confidence'       => (int) ($row->confidence ?? 0),
                'source'           => $source,
                'created_at'       => $row->created_at ? Carbon::parse($row->created_at)->toIso8601String() : null,
                'has_image'        => (bool) ($row->has_image ?? false),
                'kb'               => $snapshot ?? ($knowledgeBase[$key] ?? new \stdClass()),
                'severity_label'   => $snapshot['severity_label'] ?? ($sev['label'] ?? null),
                'severity_percent' => $snapshot['severity_percent'] ?? ($sev['percent'] ?? null),
                'severity_message' => $snapshot['severity_message'] ?? ($sev['messages']['english'] ?? null),
                'boxes'            => $boxes,
            ];
        }

        return response()->json([
            'success' => true,
            'total'   => $total,
            'items'   => $items,
        ]);
    }

    /** GET /history/{id}/image -> the photo bytes (same code as the website's fallback image route). */
    public function image(Request $request, $id)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        return app(FarmerHistoryController::class)->image($id);
    }

    /** POST /history/delete {id} -> removes ONE scan (same as the website's delete_instance). */
    public function destroy(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing id.'], 422);
        }
        DB::table('user_detections')
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->delete();

        return response()->json(['success' => true]);
    }
}