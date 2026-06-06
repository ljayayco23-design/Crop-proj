<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnnouncementController extends Controller
{
    // ====================== ADMIN ======================
    public function adminIndex()
    {
        $announcements = Announcement::with('creator')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.announcement', compact('announcements'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'target_role' => 'required|in:global,farmer,technician'
        ]);

        Announcement::create([
            'title'       => $request->title,
            'message'     => $request->message,
            'role'        => $request->target_role,
            'urgent'      => str_contains(strtolower($request->title), 'urgent') ||
                            str_contains(strtolower($request->title), 'typhoon') ||
                            str_contains(strtolower($request->title), 'warning'),
            'created_by'  => Auth::id(),
        ]);

        return redirect()->route('admin.announcement')
                         ->with('success', 'Announcement created successfully!');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'target_role' => 'required|in:global,farmer,technician'
        ]);

        $announcement->update([
            'title'   => $request->title,
            'message' => $request->message,
            'role'    => $request->target_role,
            'urgent'  => str_contains(strtolower($request->title), 'urgent') ||
                        str_contains(strtolower($request->title), 'typhoon') ||
                        str_contains(strtolower($request->title), 'warning'),
        ]);

        return redirect()->route('admin.announcement')
                         ->with('success', 'Announcement updated successfully!');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return redirect()->route('admin.announcement')
                         ->with('success', 'Announcement deleted successfully!');
    }

    // ====================== FARMER ======================
    public function farmerIndex()
    {
        $announcements = Announcement::whereIn('role', ['global', 'farmer'])
            ->orderBy('created_at', 'desc')
            ->orderBy('urgent', 'desc')
            ->get();

        return view('farmer.announcement', compact('announcements'));
    }

    // ====================== TECHNICIAN ======================
    public function technicianIndex()
    {
        $announcements = Announcement::whereIn('role', ['global', 'technician'])
            ->orderBy('created_at', 'desc')
            ->orderBy('urgent', 'desc')
            ->get();

        return view('technician.announcement', compact('announcements'));
    }
}