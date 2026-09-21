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
        $actor = Auth::user();
        $isDeveloper = $actor->role === 'developer';

        $announcements = Announcement::with('creator')
            // A normal admin only ever manages announcements posted for
            // THEIR OWN city — never a developer's global broadcast, nor
            // another city's admin's announcement. Same wall pattern as
            // AdminUserController / AdminAssignmentController. Developer
            // keeps the unrestricted, see-everything view.
            ->when(!$isDeveloper, fn ($q) => $q->where('city_id', $actor->city_id))
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

        $actor = Auth::user();

        Announcement::create([
            'title'       => $request->title,
            'message'     => $request->message,
            'role'        => $request->target_role,
            'urgent'      => str_contains(strtolower($request->title), 'urgent') ||
                            str_contains(strtolower($request->title), 'typhoon') ||
                            str_contains(strtolower($request->title), 'warning'),
            'created_by'  => $actor->id,
            // ALWAYS the posting admin's own city — never client-chosen,
            // same lock AdminUserController::createAccount() uses for
            // province/city. This is what makes farmerIndex()/
            // technicianIndex() only ever surface this to farmers/
            // technicians in that same city.
            //
            // A developer has no single city to scope to, so a
            // developer-posted announcement gets city_id = null, which
            // farmerIndex()/technicianIndex() treat as "visible in every
            // city" — the one deliberate global-broadcast channel,
            // reserved for the superadmin role only.
            'city_id'     => $actor->role === 'developer' ? null : $actor->city_id,
        ]);

        return redirect()->route('admin.announcement')
                         ->with('success', 'Announcement created successfully!');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $actor = Auth::user();

        // Same wall as adminIndex(): a normal admin can only edit an
        // announcement that belongs to their own city — guessing another
        // city's announcement id in the URL gets a 403, not a silent edit.
        if ($actor->role !== 'developer' && (int) $announcement->city_id !== (int) $actor->city_id) {
            abort(403, 'You can only edit announcements from your own city.');
        }

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
            // city_id intentionally left untouched here — an
            // announcement's city is fixed at creation and never moved.
        ]);

        return redirect()->route('admin.announcement')
                         ->with('success', 'Announcement updated successfully!');
    }

    public function destroy(Announcement $announcement)
    {
        $actor = Auth::user();

        // Same wall as update() above.
        if ($actor->role !== 'developer' && (int) $announcement->city_id !== (int) $actor->city_id) {
            abort(403, 'You can only delete announcements from your own city.');
        }

        $announcement->delete();
        return redirect()->route('admin.announcement')
                         ->with('success', 'Announcement deleted successfully!');
    }

    // ====================== FARMER ======================
    public function farmerIndex()
    {
        $actor = Auth::user();

        $announcements = Announcement::whereIn('role', ['global', 'farmer'])
            // Only ever an announcement from an admin in the SAME city as
            // this farmer, plus any developer-posted announcement
            // (city_id null — the one deliberate global channel).
            ->where(function ($q) use ($actor) {
                $q->where('city_id', $actor->city_id)
                  ->orWhereNull('city_id');
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('urgent', 'desc')
            ->get();

        return view('farmer.announcement', compact('announcements'));
    }

    // ====================== TECHNICIAN ======================
    public function technicianIndex()
    {
        $actor = Auth::user();

        $announcements = Announcement::whereIn('role', ['global', 'technician'])
            ->where(function ($q) use ($actor) {
                $q->where('city_id', $actor->city_id)
                  ->orWhereNull('city_id');
            })
            ->orderBy('created_at', 'desc')
            ->orderBy('urgent', 'desc')
            ->get();

        return view('technician.announcement', compact('announcements'));
    }
}