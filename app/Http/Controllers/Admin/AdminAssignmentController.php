<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;

class AdminAssignmentController extends Controller
{
    /**
     * Assignment Management page: stats, the assignment form, the map,
     * and the paginated Current Assignments table.
     *
     * Shared by BOTH the admin panel and the technician panel:
     *   - developer (superadmin) -> full page, can assign admin OR technician,
     *                                sees the Administrators stat card.
     *   - admin                  -> full page, can assign technician ONLY
     *                                (scoped to admin's own city — see
     *                                usersByRole()), never sees the
     *                                Administrators stat card.
     *   - technician             -> "My Assignment" page. Read-only UNLESS
     *                                an admin/developer has actively
     *                                assigned this technician to a
     *                                barangay — in that case they may also
     *                                assign OTHER technicians into that
     *                                SAME barangay (never a different
     *                                area; see store()/usersByRole()).
     */
    public function index(Request $request)
    {
        $actor = auth()->user();
        $actorRole = $actor->role;
        $isDeveloper = $actorRole === 'developer';
        $isTechnicianActor = $actorRole === 'technician';

        $allowedUserTypes = $this->allowedAssignUserTypes();

        // A technician only gets to create assignments (of OTHER
        // technicians, into their own barangay) once an admin/developer
        // has actively assigned them somewhere. Until then the page stays
        // fully read-only, same as before.
        $technicianOwnAssignment = $isTechnicianActor
            ? Assignment::active()->with(['province', 'city', 'barangay'])
                ->where('user_id', $actor->id)
                ->latest()
                ->first()
            : null;

        $canCreateAssignment = in_array($actorRole, ['admin', 'developer'], true)
            || ($isTechnicianActor && $technicianOwnAssignment !== null);

        $assignments = Assignment::with(['user', 'province', 'city', 'barangay', 'assignedBy'])
            // A technician viewing their own "My Assignment" page only ever
            // sees the assignment row(s) admin created for them — never
            // other technicians' or admins' rows.
            ->when($isTechnicianActor, fn ($q) => $q->where('user_id', $actor->id))
            // THE WALL: a normal admin only ever sees assignments THEY
            // personally created (assigned_by = them) — never a
            // developer's assignments, nor another admin's. Before this,
            // every admin shared one global table, so editing/deleting a
            // row here could silently touch a developer's (or a
            // different admin's) assignment. Developer is the one actual
            // superadmin — it keeps the unrestricted, see-everything view.
            ->when($actorRole === 'admin', fn ($q) => $q->where('assigned_by', $actor->id))
            ->when($request->filled('user_type'), fn ($q) => $q->where('user_type', $request->user_type))
            ->when($request->filled('province_id'), fn ($q) => $q->where('province_id', $request->province_id))
            ->when($request->filled('city_id'), fn ($q) => $q->where('city_id', $request->city_id))
            ->when($request->filled('barangay_id'), fn ($q) => $q->where('barangay_id', $request->barangay_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->search;
                $q->whereHas('user', function ($uq) use ($term) {
                    $uq->where('full_name', 'like', "%{$term}%")
                       ->orWhere('email', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Technicians a technician actor has personally assigned into
        // their own barangay (assigned_by = them, excluding their own
        // assignment row). Small, un-paginated list — shown on the
        // technician's page so they can see who they've brought on.
        $assignedByMe = $isTechnicianActor
            ? Assignment::with(['user', 'barangay'])
                ->where('assigned_by', $actor->id)
                ->where('user_id', '!=', $actor->id)
                ->latest()
                ->get()
            : collect();

        // The "Administrators" stat (and its query) is developer/superadmin
        // only — a normal admin never sees admin data on this page.
        $stats = [
            'technicians'      => Assignment::active()->where('user_type', 'technician')->distinct('user_id')->count('user_id'),
            'technicians_new'  => Assignment::active()->where('user_type', 'technician')->where('created_at', '>=', now()->subMonth())->count(),
            'total_areas'      => Assignment::active()->distinct('barangay_id')->count('barangay_id'),
            'total_areas_new'  => Assignment::active()->where('created_at', '>=', now()->subMonth())->distinct('barangay_id')->count('barangay_id'),
        ];

        if ($isDeveloper) {
            $stats['admins']     = Assignment::active()->where('user_type', 'admin')->distinct('user_id')->count('user_id');
            $stats['admins_new'] = Assignment::active()->where('user_type', 'admin')->where('created_at', '>=', now()->subMonth())->count();
        }

        // Map pins: one per active assignment, placed at the ASSIGNED
        // USER's own registered location (users.latitude/longitude —
        // barangays/cities have no coordinate columns in this schema, but
        // users already do, and every farmer/technician/admin record
        // fills these in on registration). This works identically for
        // both admin-type (province+city scope, no barangay) and
        // technician-type (barangay scope) assignments, since it never
        // touches barangay/city coordinates at all. If a user hasn't set
        // their location yet, they simply get no pin — the map still
        // renders fine either way.
        $mapMarkers = Assignment::active()
            ->with(['user', 'barangay'])
            ->when($isTechnicianActor, fn ($q) => $q->where('user_id', $actor->id))
            // Same wall as the table above — a normal admin's map only
            // pins the assignments they personally made.
            ->when($actorRole === 'admin', fn ($q) => $q->where('assigned_by', $actor->id))
            ->get()
            ->map(function ($a) {
                $user = $a->user;

                if (!$user || $user->latitude === null || $user->longitude === null) {
                    return null;
                }

                $label = $user->full_name ?? 'Unknown';
                $label .= $a->user_type === 'admin'
                    ? ' — Admin'
                    : ($a->barangay ? ' — ' . $a->barangay->name : ' — Technician');

                return [
                    'name' => $label,
                    'lat'  => (float) $user->latitude,
                    'lng'  => (float) $user->longitude,
                    'type' => $a->user_type,
                ];
            })
            ->filter()
            ->values();

        $view = in_array($actorRole, ['admin', 'developer'], true)
            ? 'admin.assignment'
            : 'technician.assignment';

        // Read from config/services.php (which itself reads MAPTILER_API_KEY
        // out of .env) rather than calling env() directly here — env() only
        // works reliably before config is cached, whereas config() always
        // resolves correctly whether or not `php artisan config:cache` has
        // been run. See config/services.php for the 'maptiler' entry this
        // expects; if that entry is missing this simply resolves to null and
        // the map view falls back to the plain OpenStreetMap layer.
        $mapTilerKey = config('services.maptiler.key');

        // Same lock as the Create Account page: a normal admin only ever
        // assigns technicians into their OWN Province + City (that's the
        // only pool usersByRole() gives them anyway), so the form should
        // pin those two fields to the admin's own values instead of
        // letting them pick a different city — picking a different one
        // is exactly what silently makes the technician vanish from
        // userLog() afterwards, since store() copies whatever is chosen
        // here onto the technician's own account. Developer is
        // unrestricted and keeps the original free picker untouched.
        $actorProvinceId   = $actor->province_id;
        $actorCityId       = $actor->city_id;
        $actorProvinceName = $actor->province->name ?? '';
        $actorCityName     = $actor->city->name ?? '';

        return view($view, compact(
            'assignments',
            'stats',
            'mapMarkers',
            'allowedUserTypes',
            'canCreateAssignment',
            'isDeveloper',
            'technicianOwnAssignment',
            'actorProvinceId',
            'actorCityId',
            'actorProvinceName',
            'actorCityName',
            'assignedByMe',
            'mapTilerKey'
        ));
    }

    /**
     * AJAX: list users for a given role, for the "User" dropdown in the
     * assignment form. GET /admin/assignments/users?role=admin|technician
     *
     * Scoping (this is what feeds the assignment form's dropdown — it must
     * always match whoever is ALLOWED to actually get assigned, otherwise
     * you get exactly the bug this was fixing: the dropdown showing users
     * the User Accounts / user_log page wouldn't show at all):
     *   - developer -> unrestricted, any user of the requested role.
     *   - admin     -> technicians only, and only those registered in the
     *                  SAME province + city as the admin. This mirrors
     *                  AdminUserController@userLog's technician scoping
     *                  exactly, so the same technicians who show up on the
     *                  User Accounts page are the only ones assignable here.
     *   - technician-> technicians only, scoped to the SAME city as the
     *                  technician's own active assignment, and only
     *                  reachable at all once that active assignment exists.
     */
public function usersByRole(Request $request)
{
    $request->validate([
        'role' => 'required|in:' . implode(',', $this->allowedAssignUserTypes()),
    ]);

    $actor = auth()->user();
    $actorRole = $actor->role;

    // Parity check with AdminUserController@userLog — if the actor's own
    // "view" permission is off, the dropdown must be empty too, not just
    // the User Accounts page.
    if ($actorRole !== 'developer' && !Permission::can($actorRole, 'user_management', 'view')) {
        abort(403, 'You do not have permission to view users.');
    }

    $query = User::where('role', $request->role);

    if ($actorRole === 'admin') {
        $query->where('province_id', $actor->province_id)
              ->where('city_id', $actor->city_id);
    } elseif ($actorRole === 'technician') {
        $ownAssignment = Assignment::active()->where('user_id', $actor->id)->latest()->first();

        if (!$ownAssignment) {
            abort(403, 'You must be assigned to an area before you can assign other technicians.');
        }

        // Same field (assignment city) userLog() now uses for the
        // Technicians tab — was previously consistent already here, kept
        // as-is; the bug was on the userLog() side.
        $query->where('city_id', $ownAssignment->city_id)
              ->where('id', '!=', $actor->id);
    }
    // developer: intentionally unrestricted.

    $users = $query->orderBy('full_name')->get(['id', 'full_name', 'email', 'role']);

    // This endpoint feeds the "User" dropdown live via fetch(); if it's
    // ever cached (by the browser or, on this app, the service worker
    // registered at /sw.js) a newly created/assigned user can be missing
    // from the list until a hard refresh bypasses that cache. Force this
    // JSON response to never be cached so the dropdown always reflects
    // the current data.
    return response()->json($users)->withHeaders([
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma'        => 'no-cache',
    ]);
}

    public function store(Request $request)
    {
        $actor = auth()->user();
        $actorRole = $actor->role;

        $isAdminActor = in_array($actorRole, ['admin', 'developer'], true);
        $isTechnicianActor = $actorRole === 'technician';

        $technicianOwnAssignment = null;
        if ($isTechnicianActor) {
            $technicianOwnAssignment = Assignment::active()->where('user_id', $actor->id)->latest()->first();
        }

        // A technician only gets to create an assignment if an
        // admin/developer has actively assigned THEM somewhere first.
        // Everyone else who isn't admin/developer is blocked outright,
        // exactly as before.
        if (!$isAdminActor && !($isTechnicianActor && $technicianOwnAssignment)) {
            abort(403, 'You are not allowed to create assignments.');
        }

        $allowedUserTypes = $this->allowedAssignUserTypes();

        if ($isTechnicianActor) {
            // Technician flow: the ONLY thing they choose is which
            // technician and the assignment period. province_id, city_id,
            // and barangay_id are never read from the request — they are
            // always taken straight from the technician's own active
            // assignment, so a tampered request can never move the new
            // assignment to a different area.
            $data = $request->validate([
                'user_type'  => 'required|in:technician',
                'user_id'    => 'required|exists:users,id',
                'start_date' => 'required|date',
                'end_date'   => 'nullable|date|after_or_equal:start_date',
            ]);

            $data['province_id'] = $technicianOwnAssignment->province_id;
            $data['city_id']     = $technicianOwnAssignment->city_id;
            $data['barangay_id'] = $technicianOwnAssignment->barangay_id;
        } else {
            // Province/City are now ALWAYS collected from the acting
            // developer/admin — for BOTH an "Administrator" assignment
            // AND a "Technician" assignment — so the actor can actively
            // set/update the area this user is assigned to, instead of an
            // admin-type assignment silently inheriting whatever is
            // already on the target admin's own account. Barangay is
            // still the one field that's meaningless for an admin-type
            // assignment (an admin's scope is the whole province + city,
            // never a single barangay), so it stays muted/hidden
            // client-side and optional here — never required — for that
            // type only.
            $isAdminType = $request->input('user_type') === 'admin';

            $data = $request->validate([
                'user_type'   => 'required|in:' . implode(',', $allowedUserTypes),
                'user_id'     => 'required|exists:users,id',
                'province_id' => 'required|exists:provinces,id',
                'city_id'     => 'required|exists:cities,id',
                'barangay_id' => $isAdminType
                    ? 'nullable|exists:barangays,id'
                    : 'required|exists:barangays,id',
                'start_date'  => 'required|date',
                'end_date'    => 'nullable|date|after_or_equal:start_date',
                // Actor-selectable at creation time now, instead of always
                // being forced to "active" — e.g. a future-dated
                // assignment can be logged as "Pending" until it starts.
                'status'      => 'required|in:pending,active,ended',
                // Auto-derived client-side by geocoding the selected
                // Province/City/Barangay (same pattern as the Create
                // Account page's location map). Optional: if geocoding
                // failed or hasn't resolved yet, the assigned user's
                // existing pin is simply left as-is.
                'latitude'    => 'nullable|numeric|between:-90,90',
                'longitude'   => 'nullable|numeric|between:-180,180',
            ]);

            // Defense in depth: a normal admin can never sneak an "admin"
            // user_type past validation even if the rule above were
            // tampered with client-side — only a developer (superadmin)
            // may assign admins.
            if (!in_array($data['user_type'], $allowedUserTypes, true)) {
                abort(422, 'You are not allowed to assign that user type.');
            }

            // A normal admin can only ever pick a technician who is
            // already inside their own Province + City (usersByRole()
            // enforces that pool), so the assignment's own province/city
            // must match too — always the admin's own, never a different
            // one from the form. This is what used to let an admin
            // silently reassign a technician's own account into another
            // city (via the locationSync below), which then made that
            // technician disappear from userLog()'s scoped query. The
            // create-assignment form now hides/locks these fields for a
            // normal admin already; this override just makes a tampered
            // request harmless too. Developer keeps its original free
            // choice of province/city for both admin- and
            // technician-type assignments, completely unchanged.
            if ($actorRole === 'admin') {
                $data['province_id'] = $actor->province_id;
                $data['city_id']     = $actor->city_id;
            }
        }

        // Defense in depth: the selected user must actually hold the role
        // that was picked in "User Type" — stops a tampered request from
        // e.g. assigning a farmer's id under user_type=technician.
        $user = User::findOrFail($data['user_id']);
        if ($user->role !== $data['user_type']) {
            abort(422, 'Selected user does not match the chosen user type.');
        }

        // Barangay never applies to an Administrator assignment — an
        // admin's scope is the whole Province + City that was just picked
        // above, never a single barangay.
        if ($data['user_type'] === 'admin') {
            $data['barangay_id'] = null;
        }

        if ($isTechnicianActor && (int) $user->id === (int) $actor->id) {
            abort(422, 'You cannot assign yourself.');
        }

        // Keep the assigned user's OWN account location in sync with
        // where a developer/admin actor just placed them here. This is
        // what makes the Area Map Overview pin — which is always drawn
        // from the user's own latitude/longitude, see index() above —
        // move immediately to the new area instead of staying stuck at
        // wherever the account originally registered. Only the
        // developer/admin flow collects this (the technician
        // self-service flow above never touches another technician's own
        // province/city/location — it only places them in the acting
        // technician's fixed barangay).
        if (!$isTechnicianActor) {
            $locationSync = [
                'province_id' => $data['province_id'],
                'city_id'     => $data['city_id'],
            ];

            if (($data['latitude'] ?? null) !== null && ($data['longitude'] ?? null) !== null) {
                $locationSync['latitude']  = $data['latitude'];
                $locationSync['longitude'] = $data['longitude'];
            }

            $user->update($locationSync);
        }

        // Avoid duplicating an already-active assignment for this user.
        // A technician assignment is scoped by barangay; an admin
        // assignment has no barangay, so it's scoped by city instead.
        $duplicateQuery = Assignment::active()->where('user_id', $user->id);

        if ($data['user_type'] === 'admin') {
            $duplicateQuery->where('city_id', $data['city_id'])->whereNull('barangay_id');
        } else {
            $duplicateQuery->where('barangay_id', $data['barangay_id']);
        }

        $alreadyAssigned = $duplicateQuery->exists();

        if ($alreadyAssigned) {
            return back()->withInput()->with('error', 'This user is already actively assigned to that ' . ($data['user_type'] === 'admin' ? 'city.' : 'barangay.'));
        }

        Assignment::create([
            // latitude/longitude were only along for the ride to update
            // the USER record above — the assignments table itself has no
            // such columns, so they're stripped before mass-assignment.
            ...\Illuminate\Support\Arr::except($data, ['latitude', 'longitude']),
            // Technician self-service assignments (assigning another
            // technician into their own barangay) never collected a
            // status field, so they keep defaulting to "active" as
            // before. The developer/admin flow's own actor-selected
            // status (pending/active/ended) is respected.
            'status'      => $data['status'] ?? 'active',
            'assigned_by' => auth()->id(),
        ]);

        $redirectRoute = $isTechnicianActor ? 'technician.assignment' : 'admin.assignment';

        return redirect()->route($redirectRoute)->with('success', ucfirst($data['user_type']) . ' assigned successfully!');
    }

    public function update(Request $request, $id)
    {
        $actor = auth()->user();

        if (!in_array($actor->role, ['admin', 'developer'], true)) {
            abort(403, 'You are not allowed to update assignments.');
        }

        $assignment = Assignment::findOrFail($id);

        // THE WALL, enforced here too — not just hidden from the table.
        // A normal admin can only update an assignment THEY created;
        // reaching a developer's (or another admin's) row by guessing/
        // editing the {id} in the URL is blocked the same way an IDOR on
        // a user record would be. Developer is unrestricted.
        if ($actor->role === 'admin' && (int) $assignment->assigned_by !== (int) $actor->id) {
            abort(403, 'You can only update assignments you created.');
        }

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
            // "pending" added so an assignment can be moved back to
            // not-yet-started (e.g. its start date was pushed out) instead
            // of only ever toggling between active/ended.
            'status'     => 'required|in:pending,active,ended',
        ]);

        $assignment->update($data);

        return redirect()->route('admin.assignment')->with('success', 'Assignment updated successfully.');
    }

    public function destroy($id)
    {
        $actor = auth()->user();

        if (!in_array($actor->role, ['admin', 'developer'], true)) {
            abort(403, 'You are not allowed to delete assignments.');
        }

        $assignment = Assignment::findOrFail($id);

        // Same wall as update() above.
        if ($actor->role === 'admin' && (int) $assignment->assigned_by !== (int) $actor->id) {
            abort(403, 'You can only delete assignments you created.');
        }

        $assignment->delete();

        return redirect()->route('admin.assignment')->with('success', 'Assignment removed successfully.');
    }

    /**
     * Which user types the currently authenticated actor may assign on
     * this page:
     *   - developer (superadmin) -> admin, technician
     *   - admin                  -> technician only
     *   - technician              -> technician only (their own barangay,
     *                                 enforced server-side in store()/
     *                                 usersByRole(), never user-selectable)
     *
     * Farmer was removed from this list for BOTH developer and admin —
     * farmers aren't assignable through Assignment Management at all
     * anymore (they're scoped to a technician's barangay automatically
     * via Assignment::active() elsewhere, not given their own row here).
     */
   private function allowedAssignUserTypes(): array
{
    $role = auth()->user()->role;

    if ($role === 'developer') {
        return ['admin', 'technician'];
    }

    if ($role === 'admin') {
        return ['technician'];
    }

    return ['technician'];
}
}