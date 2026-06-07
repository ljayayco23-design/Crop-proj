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
                'address' => 'nullable|string',
                'farm_size' => 'nullable|numeric',
                'preferred_variety' => 'nullable|string|max:255',
                'bio' => 'nullable|string',
                // We limit it to 1MB here because Base64 strings get very large
                'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024' 
            ]);

            // --- Convert the image to a Base64 string for TiDB ---
            if ($request->hasFile('profile_photo')) {
                $image = $request->file('profile_photo');
                // getRealPath() ensures we safely grab the temporary file path
                $base64Image = base64_encode(file_get_contents($image->getRealPath()));
                $mimeType = $image->getClientMimeType();
                
                // This creates a data URI string that browsers can read directly as an image
                $user->profile_photo = 'data:' . $mimeType . ';base64,' . $base64Image;
            }

            $user->full_name = $request->filled('full_name') ? $request->full_name : $user->name;
            $user->phone = $request->phone;
            $user->address = $request->address;
            
            if ($user->role === 'farmer') {
                $user->farm_size = $request->farm_size;
                $user->preferred_variety = $request->preferred_variety;
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
        $registeredFarmers = User::where('role', 'farmer')->count();
        $activeTechnicians = User::where('role', 'technician')->count();
        $knowledgeEntries  = TreatmentRecord::whereNull('user_id')->count();
        
        // Failsafe in case table doesn't exist yet
        try {
            $totalDetections = DB::table('user_detections')->count();
        } catch (\Exception $e) {
            $totalDetections = 0;
        }
        
        $pendingApprovals  = User::where('role', 'farmer')
                                ->where('status', 'pending')
                                ->count();

        return view('admin.dashboard', compact(
            'registeredFarmers', 
            'activeTechnicians', 
            'knowledgeEntries', 
            'totalDetections', 
            'pendingApprovals'
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