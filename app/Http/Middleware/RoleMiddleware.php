<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, $role)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $actorRole = auth()->user()->role;

        // 'developer' is a hardcoded, seeded-only account that reuses the
        // admin panel wholesale (same routes, same views) — so anywhere the
        // route requires 'role:admin', a developer passes too.
        $satisfies = $actorRole === $role
            || ($role === 'admin' && $actorRole === 'developer');

        if (!$satisfies) {
            abort(403, 'Unauthorized access. You do not have the required role.');
        }

        // Process the request
        $response = $next($request);

        // LAYER 2 FALLBACK: Force the browser to NEVER cache protected pages.
        // This instantly disables the "back arrow" exploit.
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
                        ->header('Pragma', 'no-cache')
                        ->header('Expires', 'Sat, 01 Jan 1990 00:00:00 GMT');
    }
}