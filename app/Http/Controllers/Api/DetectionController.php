<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerHistoryController;
use App\Http\Controllers\KnowledgeController;
use App\Models\TreatmentRecord;
use App\Support\RiceTaxonomy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mobile (Flutter) side of "Upload Detection".
 *
 * Nothing new is re-implemented here: Groq analysis, Groq knowledge text and
 * saving to History all call the SAME methods the web page uses
 * (FarmerHistoryController), so the app and the website always behave and
 * store data identically. The GROQ_API_KEY stays on the server (.env) and is
 * never put inside the app.
 */
class DetectionController extends Controller
{
    private function denyIfNotFarmer(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }
        // FarmerHistoryController reads Auth::id(); make sure it is the token's user.
        Auth::setUser($user);
        return null;
    }

    /**
     * Same data the web route farmer.detection passes to the page:
     * class names, the dialect/severity taxonomy, the admin fallback
     * knowledge base and the farmer's fields. The app caches this so it
     * still shows names/severity when there is no internet.
     */
    public function meta(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }

        $user = $request->user();

        $knowledgeBase = [];
        try {
            $flatten = function ($val, $def) {
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

            foreach (TreatmentRecord::whereNull('user_id')->get() as $row) {
                $key = strtolower(trim($row->disease));
                $knowledgeBase[$key] = [
                    'description'         => $flatten($row->description ?? null, '—'),
                    'treatments'          => $flatten($row->treatments ?? null, 'No data available yet.'),
                    'causes'              => $flatten($row->causes ?? null, '—'),
                    'nutrient_deficiency' => $flatten($row->nutrient_deficiency ?? null, '—'),
                    'grain_damage'        => $flatten($row->grain_damage ?? null, '—'),
                    'prevention'          => $flatten($row->prevention ?? null, '—'),
                    'natural_enemies'     => $flatten($row->natural_enemies ?? null, '—'),
                ];
            }
        } catch (\Throwable $e) {
            $knowledgeBase = [];
        }

        $fields = [['value' => 'main', 'label' => $user->farm_name ?: 'Main Farm']];
        $additional = is_array($user->additional_farms ?? null) ? $user->additional_farms : [];
        foreach ($additional as $i => $farm) {
            $fields[] = [
                'value' => $farm['id'] ?? ('extra_' . $i),
                'label' => $farm['options']['farmName'] ?? ('Additional Field ' . ($i + 1)),
            ];
        }

        return response()->json([
            'success'       => true,
            'diseaseNames'  => KnowledgeController::diseaseNames(),
            'pestNames'     => KnowledgeController::pestNames(),
            'taxonomy'      => RiceTaxonomy::payload(),
            'knowledgeBase' => $knowledgeBase,
            'fields'        => $fields,
        ]);
    }

    /** Groq vision analysis of the photo (same as web route farmer.history.groq). */
    public function groq(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        return app(FarmerHistoryController::class)->analyzeImageWithGroq($request);
    }

    /** Groq-written knowledge for a class + dialect (same as farmer.history.groqKnowledge). */
    public function groqKnowledge(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        return app(FarmerHistoryController::class)->groqKnowledge($request);
    }

    /** Save a detection to History (same as farmer.history.save). */
    public function save(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        return app(FarmerHistoryController::class)->saveDetection($request);
    }
}