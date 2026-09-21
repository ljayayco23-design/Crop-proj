<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    // Only one module for now, but the table/UI already supports more.
    private const MODULES = [
        'user_management' => 'User Management',
    ];

    /**
     * Which rows the CURRENTLY LOGGED IN actor is allowed to see/edit.
     *   - developer -> admin, technician  (the one row developer can NOT
     *                  touch is its own — there's no "developer" row at
     *                  all, it's a hardcoded account, not a managed role)
     *   - admin     -> technician only    (admin can never edit its own
     *                  row, or any other admin's — only developer can)
     * Farmer is always shown, but as a locked/blocked row, for both.
     */
    private function editableRolesForActor(): array
    {
        return auth()->user()->role === 'developer'
            ? ['admin', 'technician']
            : ['technician'];
    }

    public function index()
    {
        $editableRoles = $this->editableRolesForActor();

        $matrices = [];
        foreach (self::MODULES as $key => $label) {
            $matrices[$key] = Permission::matrixFor($key);
        }

        return view('admin.permission_management', [
            'editableRoles' => $editableRoles,
            'lockedRoles'   => Permission::LOCKED_ROLES, // ['farmer']
            'modules'       => self::MODULES,
            'matrices'      => $matrices,
        ]);
    }

    public function update(Request $request)
    {
        $editableRoles = $this->editableRolesForActor();

        $data = $request->validate([
            'permissions'                  => 'required|array',
            'permissions.*.*'              => 'array',
            'permissions.*.*.can_view'     => 'nullable|boolean',
            'permissions.*.*.can_info'     => 'nullable|boolean',
            'permissions.*.*.can_create'   => 'nullable|boolean',
            'permissions.*.*.can_edit'     => 'nullable|boolean',
            'permissions.*.*.can_delete'   => 'nullable|boolean',
            'permissions.*.*.hide_module'  => 'nullable|boolean',
        ]);

        foreach ($data['permissions'] as $module => $roles) {
            if (!array_key_exists($module, self::MODULES)) {
                continue; // unknown module in a tampered form
            }

            foreach ($roles as $role => $flags) {
                // Server-side wall, independent of what checkboxes the
                // browser rendered: an admin POSTing an "admin" or
                // "farmer" block (devtools-edited form) is simply ignored.
                if (!in_array($role, $editableRoles, true)) {
                    continue;
                }

                Permission::updateOrCreate(
                    ['role' => $role, 'module' => $module],
                    [
                        'can_view'    => (bool) ($flags['can_view'] ?? false),
                        'can_info'    => (bool) ($flags['can_info'] ?? false),
                        'can_create'  => (bool) ($flags['can_create'] ?? false),
                        'can_edit'    => (bool) ($flags['can_edit'] ?? false),
                        'can_delete'  => (bool) ($flags['can_delete'] ?? false),
                        'hide_module' => (bool) ($flags['hide_module'] ?? false),
                    ]
                );
            }

            Permission::flushCache($module);
        }

        return redirect()->route('admin.permissions')->with('success', 'Permissions updated successfully.');
    }
}