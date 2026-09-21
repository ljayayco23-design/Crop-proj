<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Permission extends Model
{
    protected $fillable = [
        'role', 'module', 'can_view', 'can_info', 'can_create', 'can_edit', 'can_delete', 'hide_module',
    ];

    protected $casts = [
        'can_view'    => 'boolean',
        'can_info'    => 'boolean',
        'can_create'  => 'boolean',
        'can_edit'    => 'boolean',
        'can_delete'  => 'boolean',
        'hide_module' => 'boolean',
    ];

    /**
     * Every valid action column, mapped to its DB column name.
     * 'hide' is intentionally NOT here — it isn't a "can do X" grant, it's
     * "hide this role's whole User section from its sidebar", so it goes
     * through isHidden() instead of can().
     *
     * 'view' is a MASTER SWITCH for that actor's whole User Log page (does
     * this admin/technician see any data at all), not a per-row grant.
     * 'info' is what used to be gated by 'view' — whether the Info
     * button/panel shows for THAT ROLE's accounts specifically.
     */
    public const ACTIONS = [
        'view'   => 'can_view',
        'info'   => 'can_info',
        'create' => 'can_create',
        'edit'   => 'can_edit',
        'delete' => 'can_delete',
    ];

    /**
     * Roles that are never editable/enabled in the matrix at all — farmer
     * has no admin/technician-style panel today, so it's shown "Blocked"
     * and can't be turned on from the UI.
     */
    public const LOCKED_ROLES = ['farmer'];

    /**
     * Cached role => {can_view, can_create, can_edit, can_delete, hide_module}
     * map for a module. Rebuilt whenever the matrix is saved.
     */
    public static function matrixFor(string $module): array
    {
        return Cache::rememberForever("permissions:{$module}", function () use ($module) {
            return static::where('module', $module)
                ->get()
                ->keyBy('role')
                ->map(fn ($row) => [
                    'can_view'    => $row->can_view,
                    'can_info'    => $row->can_info,
                    'can_create'  => $row->can_create,
                    'can_edit'    => $row->can_edit,
                    'can_delete'  => $row->can_delete,
                    'hide_module' => $row->hide_module,
                ])
                ->toArray();
        });
    }

    /**
     * The single check controllers/middleware/blade files call for
     * view/create/edit/delete. 'developer' is a hardcoded, seeded-only
     * super-user that reuses the admin UI wholesale and configures
     * everyone else's row — it is never itself restricted by the matrix.
     * Fails CLOSED for everyone else: no row / unknown action => false.
     */
    public static function can(?string $role, string $module, string $action): bool
    {
        if ($role === 'developer') {
            return true;
        }

        if (!$role || !isset(self::ACTIONS[$action]) || in_array($role, self::LOCKED_ROLES, true)) {
            return false;
        }

        $matrix = static::matrixFor($module);
        $column = self::ACTIONS[$action];

        return (bool) ($matrix[$role][$column] ?? false);
    }

    /**
     * Whether a role's whole module section (e.g. the "User" sidebar group)
     * should be hidden. Callers pass the FIXED role the sidebar belongs to
     * ('admin' or 'technician') — not auth()->user()->role — because the
     * admin sidebar is shared by both admin and developer actors, and the
     * "admin" row's hide flag is meant to govern that shared view for
     * whoever is looking at it, developer included.
     */
    public static function isHidden(?string $role, string $module): bool
    {
        if (!$role || in_array($role, self::LOCKED_ROLES, true)) {
            return false;
        }

        $matrix = static::matrixFor($module);

        return (bool) ($matrix[$role]['hide_module'] ?? false);
    }

    public static function flushCache(string $module): void
    {
        Cache::forget("permissions:{$module}");
    }
}