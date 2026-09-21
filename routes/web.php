<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\FarmerHistoryController;
use App\Http\Controllers\TechnicianController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FieldMapController; // ✅ Added New Field Map Controller
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\FarmerChatController; // Or your current controller
use App\Http\Controllers\Admin\AdminAssignmentController;
use App\Http\Controllers\Admin\AdminSystemReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\FarmerReportController;

Route::get('/locations/provinces', [LocationController::class, 'provinces']);
Route::get('/locations/cities/{province}', [LocationController::class, 'cities']);
Route::get('/locations/barangays/{city}', [LocationController::class, 'barangays']);
// Force Laravel to serve the Service Worker as a static JavaScript file
Route::get('/sw.js', function () {
    return response()->file(public_path('sw.js'), [
        'Content-Type' => 'application/javascript',
        'Cache-Control' => 'no-cache, no-store, must-revalidate'
    ]);
});

Route::post('/farmer/chat-query', [FarmerChatController::class, 'handleChat'])->name('farmer.chat.query');

// ============================================
// DETECTION FEEDBACK LOOP (farmer report -> technician review)
// Two roles, ONE controller. The middleware does the first role check and
// FarmerReportController re-checks auth()->user()->role server-side, so
// neither side can reach the other's screen or rows.
// ============================================
Route::middleware(['auth', 'role:farmer'])->prefix('farmer')->group(function () {
    Route::get('/reports', [FarmerReportController::class, 'index'])->name('farmer.reports');
    // Posted by the "Report a Problem" modal on the detection page.
    Route::post('/reports', [FarmerReportController::class, 'store'])->name('farmer.reports.store');
    // Posted by the three-dot "Delete" action on the farmer's own Report
    // Problem page. Scoped to the farmer's own rows inside the controller.
    Route::delete('/reports/{report}', [FarmerReportController::class, 'destroy'])->name('farmer.reports.destroy');
});


Route::middleware(['auth', 'role:technician'])->prefix('technician')->group(function () {
    Route::get('/reports', [FarmerReportController::class, 'index'])->name('technician.reports');
    // "Save Review & Resolve". {report} is re-scoped to this technician's
    // own queue inside the controller before anything is updated.
    Route::post('/reports/{report}/review', [FarmerReportController::class, 'review'])->name('technician.reports.review');
    Route::post('/reports/{report}/review/clear', [FarmerReportController::class, 'clearReview'])->name('technician.reports.review.clear');
    // "Escalate to Admin". Saves the same review data as the route above,
    // then flags the report for the admin's System Report page. Scoped to
    // this technician's own queue inside the controller, same as review.
    Route::post('/reports/{report}/escalate', [FarmerReportController::class, 'escalate'])->name('technician.reports.escalate');
    // Live admin status of this technician's escalated reports (polled by the technician page).
    Route::get('/reports/escalations', [FarmerReportController::class, 'escalationStatuses'])->name('technician.reports.escalations');
});
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
Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:6,1');

// ✅ Keep this one and ensure the name is 'password.email'
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email');

Route::match(['get', 'post'], '/logout', function (\Illuminate\Http\Request $request) {
    // 1. Log the user out completely
    Auth::logout();
    
    // 2. Invalidate session and token
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    
    // 3. EXPLICITLY delete the session cookie from the browser
    $cookie = cookie()->forget(config('session.cookie'));

    // 4. Redirect with strict anti-cache headers
    return redirect()->route('login')
        ->withCookie($cookie)
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', 'Sat, 01 Jan 1990 00:00:00 GMT');
})->name('logout');
// ==================== ADMIN ROUTES ====================
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/', function () {
        return redirect()->route('admin.login');
    })->name('index');

    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post')->middleware('throttle:6,1');
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

    // System Report: reports technicians escalated to the admin.
    // View / In Progress / Resolved from the three-dot menu post to the status route.
    Route::get('/system-report', [AdminSystemReportController::class, 'index'])->name('system_report');
    Route::post('/system-report/{report}/status', [AdminSystemReportController::class, 'updateStatus'])->name('system_report.status');

    // These three keep their original names/URLs (so existing sidebar links keep working)
    // but now all render the merged User Accounts page (admin.users.user_log),
    // each opening with a different tab pre-selected.
    Route::get('/farmers', [AdminUserController::class, 'userLog'])->name('farmers')->defaults('role', 'farmer');
    Route::get('/technicians', [AdminUserController::class, 'userLog'])->name('technicians')->defaults('role', 'technician');
    Route::get('/admins', [AdminUserController::class, 'userLog'])->name('admins')->defaults('role', 'admin');
    // Neutral entry point (defaults to the Admin tab) for links like "Back to User Accounts".
    Route::get('/users', [AdminUserController::class, 'userLog'])->name('users');

    Route::get('/users/create', [AdminUserController::class, 'createAccount'])->name('account.create')->middleware('permission:user_management,create');
    Route::post('/users/store', [AdminUserController::class, 'storeAccount'])->name('account.store')->middleware('permission:user_management,create');

    Route::get('/users/{id}/info', [AdminUserController::class, 'getUserInfo'])->name('users.info')->middleware('permission:user_management,view');
    Route::post('/users/{id}/update', [AdminUserController::class, 'update'])->name('users.update')->middleware('permission:user_management,edit');
    Route::post('/users/{id}/approve', [AdminUserController::class, 'approve'])->name('users.approve')->middleware('permission:user_management,edit');
    Route::post('/users/{id}/decline', [AdminUserController::class, 'decline'])->name('users.decline')->middleware('permission:user_management,edit');
    Route::post('/users/{id}/delete', [AdminUserController::class, 'delete'])->name('users.delete')->middleware('permission:user_management,delete');

    // ==================== PERMISSION MANAGEMENT (admin only) ====================
    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions');
    Route::post('/permissions/update', [PermissionController::class, 'update'])->name('permissions.update');

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

    Route::get('/assignments', [AdminAssignmentController::class, 'index'])->name('assignment');
    Route::get('/assignments/users', [AdminAssignmentController::class, 'usersByRole'])->name('assignment.users');
    Route::post('/assignments/store', [AdminAssignmentController::class, 'store'])->name('assignment.store');
    Route::post('/assignments/{id}/update', [AdminAssignmentController::class, 'update'])->name('assignment.update');
    Route::get('/assignments/{id}/delete', [AdminAssignmentController::class, 'destroy'])->name('assignment.delete');
});

// ==================== PROTECTED TECHNICIAN ROUTES ====================
// NOTE: this group previously only required ['auth'], which meant ANY
// logged-in user (e.g. a farmer) could open technician pages just by
// knowing the URL. Added 'role:technician' here to close that gap — same
// pattern already used by the admin group below ('role:admin').
Route::prefix('technician')->middleware(['auth', 'role:technician'])->group(function () {

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

    // ==================== USER LOG (technician + farmer only) ====================
    // Reuses AdminUserController — it self-restricts to ['technician','farmer']
    // whenever the authenticated user's role isn't 'admin'. Mirrors the
    // admin.users / admin.technicians / admin.farmers pattern exactly, just
    // without an "admins" tab.
    Route::get('/users', [AdminUserController::class, 'userLog'])->name('technician.users');
    Route::get('/technicians', [AdminUserController::class, 'userLog'])->name('technician.technicians')->defaults('role', 'technician');
    Route::get('/farmers', [AdminUserController::class, 'userLog'])->name('technician.farmers')->defaults('role', 'farmer');

    Route::get('/users/create', [AdminUserController::class, 'createAccount'])->name('technician.account.create')->middleware('permission:user_management,create');
    Route::post('/users/store', [AdminUserController::class, 'storeAccount'])->name('technician.account.store')->middleware('permission:user_management,create');

    Route::get('/users/{id}/info', [AdminUserController::class, 'getUserInfo'])->name('technician.users.info')->middleware('permission:user_management,view');
    Route::post('/users/{id}/update', [AdminUserController::class, 'update'])->name('technician.users.update')->middleware('permission:user_management,edit');
    Route::post('/users/{id}/approve', [AdminUserController::class, 'approve'])->name('technician.users.approve')->middleware('permission:user_management,edit');
    Route::post('/users/{id}/decline', [AdminUserController::class, 'decline'])->name('technician.users.decline')->middleware('permission:user_management,edit');
    Route::post('/users/{id}/delete', [AdminUserController::class, 'delete'])->name('technician.users.delete')->middleware('permission:user_management,delete');

    // ==================== MY ASSIGNMENT (read-only) ====================
    // Reuses AdminAssignmentController@index — it already detects a
    // 'technician' actor, scopes the table/map to that technician's own
    // assignment row(s) only, and renders technician.assignment instead
    // of admin.assignment. store/update/delete stay admin/developer-only
    // (403 for a technician even if the URL is hit directly).
    Route::get('/assignments', [AdminAssignmentController::class, 'index'])->name('technician.assignment');

    // A technician can only reach these once an admin/developer has
    // actively assigned them to a barangay — enforced server-side in
    // AdminAssignmentController@store / @usersByRole (403 otherwise), not
    // just by hiding the form. Lets a technician assign a FELLOW
    // technician into that SAME barangay only; province/city/barangay are
    // never taken from the request on that path.
    Route::get('/assignments/users', [AdminAssignmentController::class, 'usersByRole'])->name('technician.assignment.users');
    Route::post('/assignments/store', [AdminAssignmentController::class, 'store'])->name('technician.assignment.store');
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

    // "Live Camera" on the dashboard used to point at a route that was
    // never defined (farmer.camera), which crashed the whole dashboard
    // with RouteNotFoundException. The detection page already has the
    // camera-capture flow built in, so this just opens that same page —
    // swap this to a dedicated controller/view later if Live Camera is
    // meant to be its own separate experience.
    Route::get('/camera', function () {
        return redirect()->route('farmer.detection');
    })->name('farmer.camera');
    Route::post('/profile/address', [AuthController::class, 'updateAddress'])->name('farmer.profile.address'); // 👈 add this line
    
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



$farmFields = [];
$authUser = auth()->user();
if ($authUser) {
    $farmFields[] = [
        'value' => 'main',
        'label' => $authUser->farm_name ?: 'Main Farm',
    ];

    $additional = is_array($authUser->additional_farms) ? $authUser->additional_farms : [];
    foreach ($additional as $i => $farm) {
        $farmFields[] = [
            'value' => $farm['id'] ?? ('extra_' . $i),
            'label' => $farm['options']['farmName'] ?? ('Additional Field ' . ($i + 1)),
        ];
    }
}



    return view('farmer.detection.index', compact('diseaseNames', 'pestNames', 'knowledgeBase', 'farmFields'));
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