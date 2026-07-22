<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\FarmerHistoryController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FieldMapController; // ✅ Added New Field Map Controller
use Illuminate\Support\Facades\Artisan;


// ============================================
// SHARED PROFILE ROUTES (For ALL roles)
// ============================================
Route::middleware(['auth'])->group(function () {
    Route::post('/profile/update', [AdminDashboardController::class, 'updateProfile'])->name('profile.update');
    Route::post('/password/update', [AdminDashboardController::class, 'updatePassword'])->name('password.update');
});

Route::get('/', function () {
    return redirect()->route('login');
});


// ==================== AUTHENTICATION ROUTES ====================

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

// ✅ Keep this one and ensure the name is 'password.email'
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');

Route::post('/logout', function (\Illuminate\Http\Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    
    // Explicitly prevent the browser from caching the redirect state
    return redirect()->route('login')
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
})->name('logout');

// ==================== ADMIN ROUTES ====================
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.login');
    })->name('index');

    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

// ==================== PROTECTED ADMIN ROUTES ====================
Route::prefix('admin')->middleware(['auth', 'role:admin'])->name('admin.')->group(function () {


// Admin Documents Route
    Route::get('/documents', function () {
        // Fetch all farmers to display their verification documents
        $users = \App\Models\User::where('role', 'farmer')->get();
        return view('admin.documents', compact('users'));
    })->name('documents');
    // The route for the new Groq Edit Modal
    Route::post('/knowledge/update-groq', [\App\Http\Controllers\KnowledgeController::class, 'updateGroq'])->name('knowledge.updateGroq');

    Route::get('/notifications', [AdminDashboardController::class, 'getNotifications'])->name('notifications');
    Route::post('/notifications/{id}/read', [AdminDashboardController::class, 'markAsRead'])->name('notifications.read');
    Route::delete('/notifications/{id}', [AdminDashboardController::class, 'deleteNotification'])->name('notifications.delete');

    Route::post('/profile/update', [AdminDashboardController::class, 'updateProfile'])->name('profile.update');
    
    // Admin: Diagnoses / All User History
    Route::get('/history', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'allUserHistory'])->name('history');
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::get('/farmers', [AdminUserController::class, 'farmers'])->name('farmers');
    Route::get('/technicians', [AdminUserController::class, 'technicians'])->name('technicians');
    Route::get('/technician/create', [AdminUserController::class, 'createTechnician'])->name('technician.create');
    Route::post('/technician/store', [AdminUserController::class, 'storeTechnician'])->name('technician.store');

    Route::get('/users/{id}/info', [AdminUserController::class, 'getUserInfo'])->name('users.info');
    Route::post('/users/{id}/update', [AdminUserController::class, 'update'])->name('users.update');    
    Route::get('/users/{id}/approve', [AdminUserController::class, 'approve'])->name('users.approve');
    Route::get('/users/{id}/decline', [AdminUserController::class, 'decline'])->name('users.decline');
    Route::get('/users/{id}/delete', [AdminUserController::class, 'delete'])->name('users.delete');

    Route::prefix('knowledge')->name('knowledge.')->group(function () {
        Route::post('/delete-groq/{id}', [KnowledgeController::class, 'destroyGroq'])->name('deleteGroq');
        Route::get('/editor/{id?}', [KnowledgeController::class, 'editor'])->name('editor');
        Route::post('/editor/store', [KnowledgeController::class, 'store'])->name('store');
        Route::get('/management', [KnowledgeController::class, 'management'])->name('management');
        Route::get('/modifier', [KnowledgeController::class, 'modifier'])->name('modifier');
        Route::post('/delete/{id}', [KnowledgeController::class, 'destroy'])->name('delete');
    });

    Route::get('/announcement', [AnnouncementController::class, 'adminIndex'])->name('announcement');
    Route::post('/announcement', [AnnouncementController::class, 'store'])->name('announcement.store');
    Route::put('/announcement/{announcement}', [AnnouncementController::class, 'update'])->name('announcement.update');
    Route::delete('/announcement/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcement.destroy');
});

// ==================== PROTECTED TECHNICIAN ROUTES ====================
Route::prefix('technician')->middleware(['auth'])->group(function () {

// Technician Documents Route
    Route::get('/documents', function () {
        // Fetch only approved farmers for technicians to view
        $users = \App\Models\User::where('role', 'farmer')->where('status', 'approved')->get();
        return view('technician.documents', compact('users'));
    })->name('technician.documents');
    Route::get('/dashboard', [TechnicianController::class, 'dashboard'])->name('technician.dashboard');
    Route::get('/records', [TechnicianController::class, 'records'])->name('technician.records');
    Route::post('/knowledge/update', [TechnicianController::class, 'updateKnowledge'])->name('technician.knowledge.update');

    Route::get('/announcement', [AnnouncementController::class, 'technicianIndex'])->name('technician.announcement');
    
    // ✅ Messenger
    Route::get('/live-com', [ChatController::class, 'technicianIndex'])->name('technician.live_com');
    Route::get('/field-map/weather', [\App\Http\Controllers\FieldMapController::class, 'getWeather'])->name('technician.field_map.weather');
    Route::get('/field-map', [\App\Http\Controllers\FieldMapController::class, 'index'])->name('technician.field_map');
    Route::match(['get', 'post'], '/field-map/sync', [\App\Http\Controllers\FieldMapController::class, 'syncLayers'])->name('technician.field_map.sync');
});

// ==================== PROTECTED FARMER ROUTES ====================
Route::prefix('farmer')->middleware(['auth'])->group(function () {
    
    // 🗺️ UNIFIED FIELD MAP & WEATHER ROUTES
    Route::get('/field-map/weather', [FieldMapController::class, 'getWeather'])->name('farmer.field_map.weather');
    Route::get('/field-map', [FieldMapController::class, 'index'])->name('farmer.field_map');
    Route::match(['get', 'post'], '/field-map/sync', [FieldMapController::class, 'syncLayers'])->name('farmer.field_map.sync');

    // AI Analysis route for farmers
    Route::post('/history/groq', [App\Http\Controllers\FarmerHistoryController::class, 'analyzeImageWithGroq'])->name('farmer.history.groq');

    Route::get('/dashboard', function () { return view('farmer.dashboard'); })->name('farmer.dashboard');
    Route::get('/camera', function () { return view('farmer.camera'); })->name('farmer.camera');
    
    Route::get('/history', [FarmerHistoryController::class, 'index'])->name('farmer.history');
    Route::post('/history/save', [FarmerHistoryController::class, 'saveDetection'])->name('farmer.history.save');
    Route::post('/history/action', [FarmerHistoryController::class, 'action'])->name('farmer.history.action');

    Route::get('/announcement', [AnnouncementController::class, 'farmerIndex'])->name('farmer.announcement');
    
    // ✅ Messenger
    Route::get('/live-com', [ChatController::class, 'farmerIndex'])->name('farmer.live_com');
});

// ==================== CHAT API ROUTES ====================
Route::middleware('auth')->prefix('chat')->group(function () {
    Route::get('/users', [\App\Http\Controllers\ChatController::class, 'getUsers']);
    Route::get('/messages', [\App\Http\Controllers\ChatController::class, 'getMessages']);
    Route::post('/send', [\App\Http\Controllers\ChatController::class, 'sendMessage']);
    Route::delete('/delete/{id}', [\App\Http\Controllers\ChatController::class, 'deleteMessage']);
});

// ==================== FARMER DETECTION ROUTE ====================
Route::match(['get', 'post'], '/farmer/detection', function (\Illuminate\Http\Request $request) { 
    if ($request->isMethod('post') && $request->has('action')) {
        if ($request->action === 'chat_query') {
            $result = tryGetChatResponse($request->input('query') ?? '', $request->language ?? 'en');
            return response()->json($result);
        }
        if ($request->action === 'save_detection') return response()->json(['success' => true]);
    }

    $diseaseNames = ['healthy_rice_plant' => "Healthy Rice Plant", 'bacterial_leaf_blight' => "Bacterial Leaf Blight", 'leaf_blast' => "Leaf Blast", 'rice_false_smut' => "Rice False Smut", 'sheath_blight' => "Sheath Blight", 'tungro_virus' => "Tungro Virus"];
    $pestNames = ['brown_planthopper' => "Brown Planthopper", 'leaf_folders' => "Leaf Folders", 'leafhopper' => "Leafhopper", 'rice_bug' => "Rice Bug", 'rice_gall_midge' => "Rice Gall Midge", 'rice_leaf_roller' => "Rice Leaf Roller", 'rice_stem_borer' => "Rice Stem Borer", 'snail' => "Snail"];

    $knowledgeBase = [];
    try {
        $records = \App\Models\TreatmentRecord::whereNull('user_id')->get();
        
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

        foreach ($records as $row) {
            $key = strtolower(trim($row->disease));
            $knowledgeBase[$key] = [
                'description' => $flatten($row->description ?? null, '—'), // <-- Added description mapping here
                'treatment' => $flatten($row->treatments ?? null, 'No data available yet.'),
                'treatments' => $flatten($row->treatments ?? null, 'No data available yet.'),
                'causes' => $flatten($row->causes ?? null, '—'),
                'nutrient_deficiency' => $flatten($row->nutrient_deficiency ?? null, '—'),
                'grain_damage' => $flatten($row->grain_damage ?? null, '—'),
                'prevention' => $flatten($row->prevention ?? null, '—'),
                'natural_enemies' => $flatten($row->natural_enemies ?? null, '—')
            ];
        }
    } catch (\Exception $e) { 
        $knowledgeBase = []; 
    }

    return view('farmer.detection.index', compact('diseaseNames', 'pestNames', 'knowledgeBase'));
})->name('farmer.detection');

function tryGetChatResponse($query, $language = 'en') {
    $apiKey = env('GROQ_API_KEY');
    if (empty($apiKey)) return ['response' => '❌ API key not configured.'];

    $langName = ($language === 'en') ? 'English' : 'Cebuano';
    $prompt = "You are a friendly rice farming expert from the Philippines. User asked: \"$query\". Reply in $langName. Keep answer short, practical and helpful for Filipino farmers.";

    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, 
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'llama-3.3-70b-versatile', 
            'messages' => [['role' => 'user', 'content' => $prompt]], 
            'temperature' => 0.7, 
            'max_tokens' => 600
        ]),
        CURLOPT_TIMEOUT => 30, 
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $raw = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $json = json_decode($raw, true);
    if ($httpCode === 200 && isset($json['choices'][0]['message']['content'])) {
        return ['response' => trim($json['choices'][0]['message']['content'])];
    }
    return ['response' => 'Sorry, I couldn\'t get a response right now.'];
}


Route::get('/setup-db', function() {
    try {
        Artisan::call('migrate', ['--force' => true]);
        return "Database migrated successfully!";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
});


Route::get('/check-status', function(\Illuminate\Http\Request $request) {
    $user = \App\Models\User::where('email', $request->query('email'))->first();
    return response()->json(['status' => $user ? $user->status : 'none'])
                     ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
});


Route::get('/api/cron/weather-alerts', [FieldMapController::class, 'triggerWeatherCron']);