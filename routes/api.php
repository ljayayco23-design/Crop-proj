<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\LocationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DetectionController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\FieldMapController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\ScheduleController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Middleware\EnsureApiFarmer;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1');

    // Farmer self-registration (account starts as "pending" until an admin approves it)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    // ID photo check (quality + face + text), same rules as the web register form.
    Route::post('/id-check', [AuthController::class, 'idCheck'])->middleware('throttle:20,1');

    // Polled by the "waiting for approval" screen
    Route::get('/check-status', [AuthController::class, 'checkStatus'])->middleware('throttle:60,1');

    // Forgot password: e-mails a temporary password (same idea as the web
    // /forgot-password, but JSON + no CSRF/session so the phone can call it).
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');

    // Province / City / Barangay dropdowns for the register form.
    // Same controller the web login page uses, exposed under /api so CORS
    // (Flutter web on :5000 -> Laravel on :8000) applies to it.
    Route::get('/locations/provinces', [LocationController::class, 'provinces']);
    Route::get('/locations/cities/{province}', [LocationController::class, 'cities']);
    Route::get('/locations/barangays/{city}', [LocationController::class, 'barangays']);

    // auth:sanctum = valid token. EnsureApiFarmer = the account is STILL an approved
    // farmer on every request (same idea as EnsureUserRole on the website).
    Route::middleware(['auth:sanctum', EnsureApiFarmer::class])->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/dashboard', [DashboardController::class, 'index']);
        // Farm Address editor on the dashboard (same save as the website's farmer.profile.address)
        Route::post('/profile/address', [DashboardController::class, 'updateAddress']);

        // Account Center: change password (same as the website's password.update)
        Route::post('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:5,1');

        Route::get('/detection/meta', [DetectionController::class, 'meta']);
        Route::post('/detection/groq', [DetectionController::class, 'groq']);
        Route::post('/detection/groq-knowledge', [DetectionController::class, 'groqKnowledge']);
        Route::post('/detection/save', [DetectionController::class, 'save']);

        // History (same data as the website History page)
        Route::get('/history', [HistoryController::class, 'index']);
        Route::get('/history/{id}/image', [HistoryController::class, 'image'])->whereNumber('id');
        Route::post('/history/delete', [HistoryController::class, 'destroy']);

        // Field Map & Weather (same data + same save as the website's field-map page)
        Route::get('/field-map', [FieldMapController::class, 'index']);
        Route::post('/field-map/sync', [FieldMapController::class, 'sync']);
        Route::get('/field-map/weather', [FieldMapController::class, 'weather']);

        // Announcements (same list as the website's farmer announcement page)
        Route::get('/announcements', [AnnouncementController::class, 'index']);

        // Schedule (offline-first, same sync as the website's /schedule/sync)
        Route::post('/schedule/sync', [ScheduleController::class, 'sync']);

        // Report Problem (same data + same rules as the website's farmer_report page)
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/detections', [ReportController::class, 'detections']);
        Route::get('/reports/{id}', [ReportController::class, 'show'])->whereNumber('id');
        Route::post('/reports', [ReportController::class, 'store']);
        Route::post('/reports/delete', [ReportController::class, 'destroy']);

        // Suggest Improvement + General Feedback (same data as the website's farmer_report tabs;
        // each entry carries the admin / technician response)
        Route::get('/feedback', [FeedbackController::class, 'index']);
        Route::get('/feedback/{id}', [FeedbackController::class, 'show'])->whereNumber('id');
        Route::post('/feedback', [FeedbackController::class, 'store']);
        Route::post('/feedback/delete', [FeedbackController::class, 'destroy']);

        // Messenger (same people + same messages as the website's live_com page)
        Route::get('/chat/users', [ChatController::class, 'users']);
        Route::get('/chat/messages', [ChatController::class, 'messages']);
        Route::post('/chat/send', [ChatController::class, 'send']);
        Route::post('/chat/delete', [ChatController::class, 'destroy']);

        // AI Assistant (same Groq answer as the website's floating chat)
        Route::post('/ai-chat', [ChatController::class, 'ai'])->middleware('throttle:30,1');
    });
});