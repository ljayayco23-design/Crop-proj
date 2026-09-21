<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUserController extends Controller
{
    /**
     * Unified User Accounts log — shared by BOTH the admin panel and the
     * technician panel. Which rows/tabs are visible is decided ENTIRELY by
     * the currently authenticated user's role via allowedRolesForActor():
     *   - admin      -> can see admin + technician + farmer
     *   - technician -> can see technician + farmer ONLY (never admin)
     *
     * This is intentional: the route itself is protected by 'role:admin' or
     * 'role:technician' middleware, and this method double-checks with a
     * server-side whitelist so a technician can never pull admin rows even
     * if the route/query were tampered with.
     */
    public function userLog(Request $request)
    {
        $allowedRoles = $this->allowedRolesForActor();
        $actorRole = auth()->user()->role;
        $isDeveloper = $actorRole === 'developer';
        $actor = auth()->user();

        // "View" is now a MASTER SWITCH for that actor's whole page, not a
        // per-row grant — checked against the actor's OWN role's row
        // ('admin' row governs the normal-admin page, 'technician' row
        // governs the technician's own page). Developer is never gated.
        $masterVisible = $isDeveloper || Permission::can($actorRole, 'user_management', 'view');

        // Admin accounts are ONLY ever visible to developer — hardcoded,
        // not tied to any permission flag. A normal admin never has 'admin'
        // in $allowedRoles at all anymore (see allowedRolesForActor()).
        $showAdmins = $isDeveloper && in_array('admin', $allowedRoles, true);

        $showTechnicians = in_array('technician', $allowedRoles, true) && $masterVisible;
        $showFarmers     = in_array('farmer', $allowedRoles, true) && $masterVisible;

        // A technician's OWN "farmers I can see" area is whatever an
        // admin/developer has actively assigned them to via the
        // Assignment Management page (barangay-level) — not the
        // technician's own registration province/city fields. Empty until
        // an admin assigns them somewhere, by design.
        $isTechnicianActor = $actorRole === 'technician';
        $assignedBarangayIds = $isTechnicianActor
            ? Assignment::active()->where('user_id', $actor->id)->pluck('barangay_id')
            : collect();

        $admins = $showAdmins
            ? User::with(['province', 'city', 'barangay'])
                  ->where('role', 'admin')
                  ->orderBy('full_name')
                  ->get()
            : collect();

        // Normal admin/technician actors only see technician & farmer
        // accounts within their OWN province + city (not barangay — that
        // would be too narrow and hide farmers who share the city but not
        // the exact barangay). Developer is unrestricted, sees everyone.
        $technicians = $showTechnicians
            ? User::with(['province', 'city', 'barangay'])
                  ->where('role', 'technician')
                  ->when(!$isDeveloper, fn ($q) => $q->where('province_id', $actor->province_id)
                                                      ->where('city_id', $actor->city_id))
                  ->orderBy('full_name')
                  ->get()
            : collect();

        $farmers = $showFarmers
            ? User::with(['province', 'city', 'barangay'])
                  ->where('role', 'farmer')
                  ->when($isTechnicianActor, fn ($q) => $q->whereIn('barangay_id', $assignedBarangayIds))
                  ->when(!$isDeveloper && !$isTechnicianActor, fn ($q) => $q->where('province_id', $actor->province_id)
                                                      ->where('city_id', $actor->city_id))
                  ->orderBy('full_name')
                  ->get()
            : collect();

        $role = $request->route('role');
        $activeRole = in_array($role, $allowedRoles, true) ? $role : $allowedRoles[0];

        $view = $this->isAdminActor()
            ? 'admin.users.user_log'
            : 'technician.users.technician_log';

        return view($view, compact('admins', 'technicians', 'farmers', 'activeRole', 'allowedRoles'));
    }

    public function createAccount()
    {
        $allowedRoles = $this->allowedRolesForActor();

        $view = $this->isAdminActor()
            ? 'admin.users.create_admin_technician_farmer'
            : 'technician.users.create_technician_farmer';

        // Same key the Assignment Management map reads (see
        // AdminAssignmentController@index) — reused here so the Location
        // Preview map on this page can offer the same style switcher
        // (Streets/Satellite/Topographic/Dark) instead of being stuck on
        // plain OpenStreetMap tiles only.
        $mapTilerKey = config('services.maptiler.key');

        // A normal admin's Technician/Farmer accounts are ALWAYS created
        // inside the admin's own Province + City — never freely chosen —
        // because userLog() (and the Assignment "User" dropdown) both
        // scope technicians/farmers to `where province_id = actor's,
        // city_id = actor's`. Letting the form collect a different
        // province/city here just silently produces an account that can
        // never show up on this admin's own User Accounts page.
        //
        // Developer keeps the original, fully free province/city picker
        // (they're unrestricted and can genuinely place someone anywhere),
        // so this only locks the fields for the 'admin' role.
        $actor = auth()->user();
        $isDeveloperActor = $actor->role === 'developer';

        $actorProvinceId   = $actor->province_id;
        $actorCityId       = $actor->city_id;
        $actorProvinceName = $actor->province->name ?? '';
        $actorCityName     = $actor->city->name ?? '';

        return view($view, compact(
            'allowedRoles',
            'mapTilerKey',
            'isDeveloperActor',
            'actorProvinceId',
            'actorCityId',
            'actorProvinceName',
            'actorCityName'
        ));
    }

    public function storeAccount(Request $request)
    {
        $this->assertPermission('create');

        $allowedRoles = $this->allowedRolesForActor();

        $actor = auth()->user();
        $isDeveloperActor = $actor->role === 'developer';

        $request->validate([
            'role'            => 'required|in:' . implode(',', $allowedRoles),
            'full_name'       => 'required|string|max:255',
            'email'           => 'required|email|unique:users',
            'password'        => 'required|min:8',
            // Only actually enforced/used for a developer actor below — a
            // normal admin's province_id/city_id are always overridden to
            // their own, regardless of what's submitted, so this rule
            // mainly guards the developer flow. Left required here too so
            // a request missing them entirely still fails validation
            // instead of silently falling through.
            'province_id'     => 'required|exists:provinces,id',
            'city_id'         => 'required|exists:cities,id',
            // Barangay is only required for Technician/Farmer — an admin's
            // coverage is the whole city, not a single barangay (mirrors
            // the Assignment Management form's rule).
            'barangay_id'     => 'required_if:role,technician,farmer|nullable|exists:barangays,id',
            'phone'           => 'nullable|string|max:20',
            'dob'             => 'nullable|date',
            // Auto-derived on the create-account page from the selected
            // City (admin) or Barangay (technician/farmer) via geocoding,
            // with a manual map fallback — so this is always expected to
            // be present by the time the form is submitted. Needed so the
            // account gets a map pin on the Assignment page.
            'latitude'        => 'required|numeric|between:-90,90',
            'longitude'       => 'required|numeric|between:-180,180',
            // Farmer-only fields — required only when role is farmer.
            'farm_name'       => 'required_if:role,farmer|nullable|string|max:255',
            'farm_size'       => 'required_if:role,farmer|nullable|numeric|min:0',
            'water_source'    => 'required_if:role,farmer|nullable|string|max:255',
            'farmer_category' => 'nullable|string|max:255',
        ]);

        // Defense in depth: even if someone crafts a request with a role
        // outside what this actor is allowed to create, reject it here too
        // (the 'in:' rule above already covers this, but this keeps the
        // check explicit and independent of validation rule wording).
        if (!in_array($request->role, $allowedRoles, true)) {
            abort(403, 'You are not allowed to create that type of account.');
        }

        // Defense in depth, mirroring createAccount(): a normal admin's
        // new Technician/Farmer account is ALWAYS pinned to the admin's
        // own Province + City — never whatever the request says — so it
        // can never end up invisible on this admin's own User Accounts
        // page (userLog() scopes by exactly these two columns). The
        // create form hides/locks these fields for a normal admin
        // already; this override just makes sure a tampered request
        // can't bypass that. Developer keeps full free choice, unchanged.
        $provinceId = $isDeveloperActor ? $request->province_id : $actor->province_id;
        $cityId     = $isDeveloperActor ? $request->city_id : $actor->city_id;

        $user = User::create([
            'full_name'       => $request->full_name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'role'            => $request->role,
            'status'          => 'approved',
            'province_id'     => $provinceId,
            'city_id'         => $cityId,
            'barangay_id'     => $request->barangay_id,
            'phone'           => $request->phone,
            'dob'             => $request->dob,
            'latitude'        => $request->latitude,
            'longitude'       => $request->longitude,
            'farm_name'       => $request->farm_name,
            'farm_size'       => $request->farm_size,
            'water_source'    => $request->water_source,
            'farmer_category' => $request->farmer_category,
        ]);

        $label = ucfirst($user->role);

        return redirect()->route($this->roleRoute($user->role))
                         ->with('success', $label . ' account created successfully!');
    }

    public function update(Request $request, $id)
    {
        $this->assertPermission('edit');

        $user = User::whereIn('role', $this->allowedRolesForActor())->findOrFail($id);

        $request->validate([
            'full_name' => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,'.$id,
            'status'    => 'required|in:pending,approved,declined',
        ]);

        $user->update($request->only(['full_name', 'email', 'status']));

        return redirect()->route($this->roleRoute($user->role))->with('success', 'User updated successfully.');
    }

    public function approve($id)
    {
        // Approve/Decline change a user's status field, which is the same
        // data the Edit modal controls — so both are gated by 'edit'.
        $this->assertPermission('edit');

        $user = User::whereIn('role', $this->allowedRolesForActor())->findOrFail($id);
        $user->update(['status' => 'approved']);

        return redirect()->route($this->roleRoute($user->role))->with('success', ucfirst($user->role) . ' approved successfully!');
    }

    public function decline($id)
    {
        $this->assertPermission('edit');

        $user = User::whereIn('role', $this->allowedRolesForActor())->findOrFail($id);
        $user->update(['status' => 'declined']);

        return redirect()->route($this->roleRoute($user->role))->with('success', ucfirst($user->role) . ' declined.');
    }

    public function delete($id)
    {
        $this->assertPermission('delete');

        $user = User::whereIn('role', $this->allowedRolesForActor())->findOrFail($id);
        $role = $user->role;

        // 1. Delete standard treatment records
        \App\Models\TreatmentRecord::where('user_id', $user->id)->delete();

        // 2. Delete Groq treatment records (if they are tied to the user)
        // \Illuminate\Support\Facades\DB::table('groq_treatment_records')->where('user_id', $user->id)->delete();

        // 3. Delete the detection history (the root of the issue)
        \Illuminate\Support\Facades\DB::table('user_detections')->where('user_id', $user->id)->delete();

        // 4. Finally, delete the user account
        $user->delete();

        return redirect()->route($this->roleRoute($role))->with('success', ucfirst($role) . ' and all associated history were deleted successfully.');
    }

    public function getUserInfo($id)
    {
        $this->assertPermission('view'); // still the master-switch gate

        // Restricted to whatever roles this actor is allowed to see, so a
        // technician can't fetch an admin's info by guessing/incrementing
        // the {id} in the URL (IDOR).
        $user = User::with(['province', 'city', 'barangay'])
                    ->whereIn('role', $this->allowedRolesForActor())
                    ->findOrFail($id);

        $actor = auth()->user();
        $isDeveloper = $actor->role === 'developer';

        if (!$isDeveloper) {
            // Defense in depth: if the target's own role has had its "Info"
            // flag unchecked, block fetching their info by ID directly —
            // the Info button never renders for them, but the endpoint
            // shouldn't be reachable by URL either.
            if (in_array($user->role, ['admin', 'technician'], true)
                && !Permission::can($user->role, 'user_management', 'info')) {
                abort(404);
            }

            // Same reasoning as the userLog() province/city scoping — a
            // normal admin/technician shouldn't be able to fetch info for
            // a technician/farmer outside their own province+city just by
            // guessing an ID, even though the row never renders for them.
            if (in_array($user->role, ['technician', 'farmer'], true)
                && ($user->province_id !== $actor->province_id || $user->city_id !== $actor->city_id)) {
                abort(404);
            }
        }

        return response()->json($user);
    }

    /**
     * The single source of truth for "what can the currently logged-in
     * user see/manage/create on this shared controller".
     *   - admin      -> admin, technician, farmer
     *   - technician -> technician, farmer (admin rows are never exposed)
     *
     * Every method above filters through this list before touching data,
     * so even if a technician-side route is hit directly with a crafted
     * id/role, they still can't read, edit, approve, decline, delete, or
     * create an admin account.
     */
    /**
     * Which roles this actor can see/manage across the whole controller
     * (create form, updates, etc). A normal admin no longer gets 'admin'
     * here at all — admin accounts are only ever visible to developer,
     * hardcoded, not gated by the permission matrix. isAdminActor() still
     * decides which VIEW TEMPLATE renders (admin vs technician panel) —
     * that's a separate concern from which roles are in scope.
     */
    private function allowedRolesForActor(): array
    {
        return auth()->user()->role === 'developer'
            ? ['admin', 'technician', 'farmer']
            : ['technician', 'farmer'];
    }

    private function isAdminActor(): bool
    {
        // 'developer' reuses the admin panel wholesale (same views, same
        // tabs — including the admin tab technicians never see).
        return in_array(auth()->user()->role, ['admin', 'developer'], true);
    }

    /**
     * Belt-and-braces check mirroring the 'permission:user_management,X'
     * route middleware (see web.php). Kept here too, same reasoning as
     * allowedRolesForActor(): route middleware can be bypassed by a
     * misconfigured route, this can't.
     */
    private function assertPermission(string $action): void
    {
        if (!Permission::can(auth()->user()->role, 'user_management', $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    /**
     * Maps a user role to the route name that opens the merged User
     * Accounts page with the matching tab active — resolved against
     * whichever panel (admin or technician) the current actor belongs to.
     */
    private function roleRoute(string $role): string
    {
        if ($this->isAdminActor()) {
            return match ($role) {
                'admin'      => 'admin.admins',
                'technician' => 'admin.technicians',
                default      => 'admin.farmers',
            };
        }

        // Technician actor: only two tabs exist (no admin tab).
        return match ($role) {
            'technician' => 'technician.technicians',
            default      => 'technician.farmers',
        };
    }
}