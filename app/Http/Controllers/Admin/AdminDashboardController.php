<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\TreatmentRecord;

class AdminDashboardController extends Controller
{
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'full_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                // province_id / city_id / barangay_id are INTENTIONALLY not
                // validated or accepted here. That's the user's assigned
                // AREA — it's set once by whoever (admin/developer, or a
                // technician creating a farmer) creates the account via
                // AdminUserController@storeAccount, and it drives who can
                // see this user on the User Accounts / Dashboard / Users
                // Log pages (province+city scoping for admin/technician
                // rows, barangay Assignment scoping for farmer rows). If a
                // user could change their own area here, they could quietly
                // move themselves out of (or into) another admin's/
                // technician's visibility. This endpoint is "my personal
                // info", not "my area assignment" — even if a client sends
                // these fields, they are ignored below.
                'farm_size' => 'nullable|numeric',
                'bio' => 'nullable|string',
                // The image is already compressed client-side (canvas, resized to
                // 512px wide, JPEG @ 60% quality — same as the document/selfie
                // upload on registration) before it ever reaches the server, so
                // this is just a Base64 data URI string, not a raw file upload.
                'profile_photo_base64' => 'nullable|string|max:2097152',
            ]);

            // --- Store the already-compressed Base64 string for TiDB ---
            if ($request->filled('profile_photo_base64')) {
                $photo = $request->profile_photo_base64;

                // Sanity check: must actually be an image data URI, never trust
                // arbitrary text blindly just because the field name matches.
                if (str_starts_with($photo, 'data:image/')) {
                    $user->profile_photo = $photo;
                }
            }

            $user->full_name = $request->filled('full_name') ? $request->full_name : $user->name;
            $user->phone = $request->phone;
            // NOTE: province_id / city_id / barangay_id are NEVER written
            // here — see the validate() comment above. Area assignment can
            // only be changed by an admin/developer (AdminUserController)
            // or, for a technician's own coverage area / a farmer's
            // barangay assignment, via the Assignment Management page.

            if ($user->role === 'farmer') {
                $user->farm_size = $request->farm_size;
                $user->bio = $request->bio;
            }

            $user->save();

            // Prepare the image URL to send back to JS instantly
            $userFullName = $user->full_name ?? $user->name ?? 'Admin';
            $newImageUrl = !empty($user->profile_photo) 
                ? $user->profile_photo 
                : 'https://ui-avatars.com/api/?name=' . urlencode($userFullName) . '&background=3b82f6&color=fff&size=140&bold=true';

            return response()->json([
                'success' => true, 
                'message' => 'Profile updated successfully!',
                'user' => [
                    'full_name' => $userFullName,
                    'profile_photo_url' => $newImageUrl
                ]
            ]);

        } catch (\Exception $e) {
            // If anything fails (like a database error), catch it and tell the frontend
            return response()->json([
                'success' => false, 
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['success' => true, 'message' => 'Password updated!']);
    }

public function index()
    {
        $actor = Auth::user();
        $isDeveloper = $actor->role === 'developer';

        // Same province+city scoping AdminUserController@userLog uses for a
        // normal admin/technician, so these dashboard totals always match
        // what that actor sees on the Users Log page. Developer stays
        // unscoped (sees everyone), same as userLog().
        $scopeToActor = function ($query) use ($isDeveloper, $actor) {
            return $isDeveloper
                ? $query
                : $query->where('province_id', $actor->province_id)
                        ->where('city_id', $actor->city_id);
        };

        $registeredFarmers = $scopeToActor(User::where('role', 'farmer'))->count();
        $activeTechnicians = $scopeToActor(User::where('role', 'technician'))->count();
        $knowledgeEntries  = TreatmentRecord::whereNull('user_id')->count();
        
        // Failsafe in case table doesn't exist yet
        try {
            $totalDetections = $isDeveloper
                ? DB::table('user_detections')->count()
                : DB::table('user_detections')
                    ->join('users', 'user_detections.user_id', '=', 'users.id')
                    ->where('users.province_id', $actor->province_id)
                    ->where('users.city_id', $actor->city_id)
                    ->count();
        } catch (\Exception $e) {
            $totalDetections = 0;
        }
        
        $pendingApprovals = $scopeToActor(User::where('role', 'farmer'))
                                ->where('status', 'pending')
                                ->count();

        // NEW: Calculate total hectares and fetch all users for the tables/map
        $totalPaddyArea = $scopeToActor(User::where('role', 'farmer'))->sum('farm_size');
        $allFarmers = $scopeToActor(User::where('role', 'farmer'))->get();
        $allTechnicians = $scopeToActor(User::where('role', 'technician'))->get();

        // ====================== ANALYTICS: DETECTION TREND (LINE GRAPH) ======================
        // Last 6 months of detections, zero-filled so the line never has gaps
        $trendLabels = [];
        $trendData = [];
        try {
            $rawTrend = DB::table('user_detections')
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
                ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
                ->groupBy('ym')
                ->get()
                ->keyBy('ym');

            for ($i = 5; $i >= 0; $i--) {
                $month = now()->subMonths($i);
                $key = $month->format('Y-m');
                $trendLabels[] = $month->format('M Y');
                $trendData[] = isset($rawTrend[$key]) ? (int) $rawTrend[$key]->total : 0;
            }
        } catch (\Exception $e) {
            for ($i = 5; $i >= 0; $i--) {
                $trendLabels[] = now()->subMonths($i)->format('M Y');
                $trendData[] = 0;
            }
        }

        // ====================== ANALYTICS: DISEASE/PEST DISTRIBUTION (PIE GRAPH) ======================
        $pieLabels = [];
        $pieData = [];
        try {
            $readableNames = [
                'healthy_rice_plant' => "Healthy Rice Plant", 'bacterial_leaf_blight' => "Bacterial Leaf Blight",
                'leaf_blast' => "Leaf Blast", 'rice_false_smut' => "Rice False Smut",
                'sheath_blight' => "Sheath Blight", 'tungro_virus' => "Tungro Virus",
                'brown_planthopper' => "Brown Planthopper", 'leaf_folders' => "Leaf Folders",
                'leafhopper' => "Leafhopper", 'rice_bug' => "Rice Bug", 'rice_gall_midge' => "Rice Gall Midge",
                'rice_leaf_roller' => "Rice Leaf Roller", 'rice_stem_borer' => "Rice Stem Borer", 'snail' => "Snail"
            ];

            $topClasses = DB::table('user_detections')
                ->select('class_key', DB::raw('COUNT(*) as total'))
                ->whereNotNull('class_key')
                ->groupBy('class_key')
                ->orderByDesc('total')
                ->limit(6)
                ->get();

            foreach ($topClasses as $row) {
                $pieLabels[] = $readableNames[$row->class_key] ?? ucfirst(str_replace('_', ' ', $row->class_key));
                $pieData[] = (int) $row->total;
            }
        } catch (\Exception $e) {
            $pieLabels = [];
            $pieData = [];
        }

        return view('admin.dashboard', compact(
            'registeredFarmers', 
            'activeTechnicians', 
            'knowledgeEntries', 
            'totalDetections', 
            'pendingApprovals',
            'totalPaddyArea',
            'allFarmers',
            'allTechnicians',
            'trendLabels',
            'trendData',
            'pieLabels',
            'pieData'
        ));
    }

    // ====================== NOTIFICATIONS ======================
    public function getNotifications()
    {
        try {
            $notifications = DB::table('notifications')
                ->where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
            return response()->json($notifications);
        } catch (\Exception $e) {
            return response()->json([]); // Return empty if no DB yet
        }
    }

    public function markAsRead($id)
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function deleteNotification($id)
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json(['success' => true]);
    }

    // ====================== HISTORY / DETECTIONS ======================
    public function allUserHistory()
    {
        try {
            // Join the users table to get the real farmer's name and email
            $histories = DB::table('user_detections')
                ->leftJoin('users', 'user_detections.user_id', '=', 'users.id')
                ->select(
                    'user_detections.*', 
                    'users.full_name as user_name',
                    'users.email as user_email'
                )
                ->orderBy('user_detections.created_at', 'desc')
                ->get();

            // Dictionary to convert raw database keys into clean, readable text
            $readableNames = [
                'healthy_rice_plant' => "Healthy Rice Plant", 'bacterial_leaf_blight' => "Bacterial Leaf Blight",
                'leaf_blast' => "Leaf Blast", 'rice_false_smut' => "Rice False Smut",
                'sheath_blight' => "Sheath Blight", 'tungro_virus' => "Tungro Virus",
                'brown_planthopper' => "Brown Planthopper", 'leaf_folders' => "Leaf Folders",
                'leafhopper' => "Leafhopper", 'rice_bug' => "Rice Bug", 'rice_gall_midge' => "Rice Gall Midge",
                'rice_leaf_roller' => "Rice Leaf Roller", 'rice_stem_borer' => "Rice Stem Borer", 'snail' => "Snail"
            ];

            // Format the data for the view
            foreach($histories as $h) {
                $h->readable_name = $readableNames[$h->class_key] ?? ucfirst(str_replace('_', ' ', $h->class_key));
                $h->image_url = $h->image_path ? asset($h->image_path) : null;
            }

        } catch (\Exception $e) {
            $histories = collect([]); 
        }

        return view('admin.history', compact('histories'));
    }
}