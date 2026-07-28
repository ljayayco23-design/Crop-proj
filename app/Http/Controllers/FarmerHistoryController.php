<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class FarmerHistoryController extends Controller
{
    private function getValidKeys()
    {
        return [
            'disease' => ['healthy_rice_plant', 'bacterial_leaf_blight', 'leaf_blast', 'rice_false_smut', 'sheath_blight', 'tungro_virus'],
            'pest' => ['brown_planthopper', 'leaf_folders', 'leafhopper', 'rice_bug', 'rice_gall_midge', 'rice_leaf_roller', 'rice_stem_borer', 'snail']
        ];
    }

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

        // 1. Fetch Fallback Knowledge Base
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

        // 2. Fetch Groq AI Knowledge Base
        $groqKnowledgeBase = [];
        $groqRecords = DB::table('groq_treatment_records')->get();
        $groqTypes = []; 
        
        foreach ($groqRecords as $row) {
            $diseaseKey = strtolower(trim($row->disease));
            $groqTypes[$diseaseKey] = $row->type;
            
            $groqKnowledgeBase[$diseaseKey] = [
                'description' => $flatten($row->description ?? null, '—'),
                'treatments' => $flatten($row->treatments ?? null, '—'),
                'causes' => $flatten($row->causes ?? null, '—'),
                'nutrient_deficiency' => $flatten($row->nutrient_deficiency ?? null, '—'),
                'grain_damage' => $flatten($row->grain_damage ?? null, '—'),
                'natural_enemies' => $flatten($row->natural_enemies ?? null, '—'),
                'prevention' => $flatten($row->prevention ?? null, '—')
            ];
        }

        // 3. STRICT MERGE: Force Groq Data to overwrite Fallback Data
        $activeKnowledgeBase = $knowledgeBase;
        foreach ($groqKnowledgeBase as $key => $data) {
            $activeKnowledgeBase[$key] = $data;
        }

        // Fetch real detections ordered by newest first
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
                $isPest = isset($pestNames[$currentKey]) || (isset($groqTypes[$currentKey]) && $groqTypes[$currentKey] === 'pest');
                $fallbackName = ucwords(str_replace('_', ' ', $currentKey));
                $className = $isPest ? ($pestNames[$currentKey] ?? $fallbackName) : ($diseaseNames[$currentKey] ?? $fallbackName);

                $detectionData[] = [
                    'class_key' => $currentKey,
                    'class_name' => $className,
                    'is_pest' => $isPest,
                    'images' => $images,
                    'confidence' => $confidences[0] ?? 65 // Grab newest confidence
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

        // Add the final group
        if ($currentKey !== '') {
            $isPest = isset($pestNames[$currentKey]) || (isset($groqTypes[$currentKey]) && $groqTypes[$currentKey] === 'pest');
            $fallbackName = ucwords(str_replace('_', ' ', $currentKey));
            $className = $isPest ? ($pestNames[$currentKey] ?? $fallbackName) : ($diseaseNames[$currentKey] ?? $fallbackName);

            $detectionData[] = [
                'class_key' => $currentKey,
                'class_name' => $className,
                'is_pest' => $isPest,
                'images' => $images,
                'confidence' => $confidences[0] ?? 65
            ];
        }

        return view('farmer.history', [
            'diseaseNames' => $diseaseNames, 
            'pestNames' => $pestNames, 
            'knowledgeBase' => $activeKnowledgeBase, // Passes the updated overwritten data!
            'groqKnowledgeBase' => $groqKnowledgeBase, 
            'detectionData' => $detectionData
        ]);
    }

    public function saveDetection(Request $request)
    {
        $user_id = Auth::id();
        $class_key = strtolower(trim($request->class_key));
        $image_data = $request->input('image_base64');

        // 1. Save standard detection history (accumulates photos for the user)
        DB::table('user_detections')->insert([
            'user_id' => $user_id,
            'class_key' => $class_key,
            'confidence' => $request->confidence ?? 0,
            'image_path' => $image_data, 
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 2. Save/Update Groq Independent Data
        $gd = $request->input('groq_data');
        if (!empty($gd) && is_array($gd)) {
            // Only update/insert if Groq actually provided text
            if (!empty($gd['treatments']) || !empty($gd['description'])) {
                DB::table('groq_treatment_records')->updateOrInsert(
                    ['disease' => $class_key],
                    [
                        'type'                => (isset($gd['is_pest']) && $gd['is_pest']) ? 'pest' : 'disease',
                        'description'         => $gd['description'] ?? '',
                        'treatments'          => $gd['treatments'] ?? '',
                        'causes'              => $gd['causes'] ?? '',
                        'nutrient_deficiency' => $gd['nutrient_deficiency'] ?? '',
                        'grain_damage'        => $gd['grain_damage'] ?? '',
                        'natural_enemies'     => $gd['natural_enemies'] ?? '',
                        'prevention'          => $gd['prevention'] ?? '',
                        'updated_by'          => 'Groq AI (Auto)',
                        'updated_at'          => now()
                    ]
                );
            }
        }

        return response()->json(['success' => true]);
    }

    public function action(Request $request)
    {
        $user_id = Auth::id();
        $action = $request->action;

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

        $diseaseKeys = implode(", ", ['healthy_rice_plant', 'bacterial_leaf_blight', 'leaf_blast', 'rice_false_smut', 'sheath_blight', 'tungro_virus']);
        $pestKeys = implode(", ", ['brown_planthopper', 'leaf_folders', 'leafhopper', 'rice_bug', 'rice_gall_midge', 'rice_leaf_roller', 'rice_stem_borer', 'snail']);

$prompt = "You are an expert senior agronomist and crop pathologist. Perform a rigorous, detail-oriented visual analysis of the provided image.

        CRITICAL REJECTION RULE (NON-PADDY IMAGES):
        First, verify if the image actually contains a rice (paddy) plant, rice disease, or rice pest. If the image is unrelated (e.g., human faces, animals, vehicles, landscapes, different crops, or random objects), you MUST reject it by outputting exactly this:
        - \"class_name\": \"Unrelated Image\"
        - \"class_key\": \"\" (You MUST leave this strictly empty)
        - \"is_pest\": false
        - \"severity_label\": \"UNKNOWN\"
        - \"severity_message\": \"Cannot determine severity on a non-rice image.\"
        - \"description\": \"The uploaded image does not appear to be a rice plant, disease, or pest. Please upload a clear photo of a rice leaf, stem, or paddy field.\"
        - Set \"treatments\", \"causes\", \"nutrient_deficiency\", \"grain_damage\", \"natural_enemies\", and \"prevention\" to \"—\".
        - Set \"confidence\" and \"severity_percent\" to 0.

        DIAGNOSIS RULE (RICE IMAGES):
        If it IS a rice plant, carefully analyze precise visual markers: lesion shapes, color gradients (e.g., chlorosis, necrosis), pest bite marks, frass, or structural tissue damage. Provide a highly accurate, reality-based diagnosis focused strictly on what is physically visible in the photo.

        CRITICAL INSTRUCTION: DO NOT use <think> tags. DO NOT output any reasoning, thinking, or step-by-step logic. Start your response immediately with the '{' character.
        
        CRITICAL CONTENT & LENGTH RULE: Provide high-grade, practical agronomic information. Make the text for 'description', 'treatments', 'causes', 'prevention', etc., concise yet comprehensive, limited strictly to 2 to 3 information-dense sentences each to prevent token overflow. Ensure the description specifically references what is visibly happening in the plant photo.

        CRITICAL LANGUAGE RULE:
        You MUST generate the content values for 'description', 'treatments', 'causes', 'nutrient_deficiency', 'grain_damage', 'prevention', and 'natural_enemies' strictly in the {$language} language. The JSON keys themselves MUST remain in English.

        CRITICAL NAMING RULE:
        If you detect one of these diseases: [{$diseaseKeys}] or pests: [{$pestKeys}], you MUST use the exact key string provided for 'class_key'. Do not invent a variation.

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
            \"natural_enemies\": \"string\",
            \"prevention\": \"string\"
        }";
        
        try {
$response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(60)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => 'qwen/qwen3.6-27b', 
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
                    'max_tokens' => 1024, 
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