<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    /**
     * Usage in routes: ->middleware('permission:user_management,delete')
     */
    public function handle(Request $request, Closure $next, string $module, string $action)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!Permission::can(auth()->user()->role, $module, $action)) {
            abort(403, 'You do not have permission to perform this action.');
        }

        return $next($request);
    }
}