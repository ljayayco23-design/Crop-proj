<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * Mobile (Flutter) side of "Announcements".
 * Same rule as the website's AnnouncementController::farmerIndex():
 * role global/farmer, from an admin of the farmer's own city, plus
 * developer broadcasts (city_id = null). Only the output is JSON.
 */
class AnnouncementController extends Controller
{
    /** GET /announcements */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }

        $items = Announcement::with('creator')
            ->whereIn('role', ['global', 'farmer'])
            ->where(function ($q) use ($user) {
                $q->where('city_id', $user->city_id)
                  ->orWhereNull('city_id');
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('urgent', 'desc')
            ->limit(200)
            ->get()
            ->map(fn ($a) => [
                'id'         => $a->id,
                'title'      => $a->title,
                'message'    => $a->message,
                'urgent'     => (bool) $a->urgent,
                'created_at' => $a->created_at ? $a->created_at->toIso8601String() : null,
                'posted_by'  => optional($a->creator)->full_name,
            ])
            ->values();

        return response()->json(['success' => true, 'announcements' => $items]);
    }
}