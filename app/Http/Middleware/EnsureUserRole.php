<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict role gate for every protected area (admin / technician / farmer).
 *
 * Used straight from routes/web.php with its class name, so it does NOT depend
 * on any alias being registered in bootstrap/app.php or Kernel.php:
 *
 *     Route::middleware(['auth', EnsureUserRole::allow('admin', 'developer')])
 *
 * What it guarantees on every request:
 *   1. The role is read from the logged-in user (never from the URL / request).
 *   2. A role that is not one of the four known ones is logged out.
 *   3. An account that is no longer allowed in (farmer not approved any more,
 *      or a disabled / suspended / declined staff account) is logged out.
 *   4. A known user in the WRONG area is sent back to THEIR OWN dashboard
 *      (JSON 403 for fetch/AJAX calls) — never shown the other role's page.
 *
 * `developer` is a seeded account that reuses the admin panel, so it shares the
 * admin dashboard and may enter the admin area.
 */
class EnsureUserRole
{
    /** role => the named route of that role's own dashboard. */
    public const HOME_ROUTES = [
        'developer'  => 'admin.dashboard',
        'admin'      => 'admin.dashboard',
        'technician' => 'technician.dashboard',
        'farmer'     => 'farmer.dashboard',
    ];

    /** Staff (non-farmer) accounts with one of these statuses cannot use the app. */
    private const BLOCKED_STATUSES = ['declined', 'rejected', 'suspended', 'inactive', 'disabled', 'banned'];

    /** Builds the middleware string for a route group: EnsureUserRole::allow('admin', 'developer'). */
    public static function allow(string ...$roles): string
    {
        return static::class . ':' . implode(',', $roles);
    }

    public static function normalize($role): string
    {
        return strtolower(trim((string) $role));
    }

    /** Named route of the role's own dashboard, or null for an unknown role. */
    public static function homeRouteFor($role): ?string
    {
        return self::HOME_ROUTES[self::normalize($role)] ?? null;
    }

    /**
     * Why this account may NOT use the app right now, or null when it may.
     * Shared with AuthController so login and every later request agree.
     */
    public static function accountProblem($user): ?string
    {
        $role = self::normalize($user->role ?? null);

        if (!isset(self::HOME_ROUTES[$role])) {
            return 'Walang valid na role ang account na ito. Makipag-ugnayan sa admin.';
        }

        $status = strtolower(trim((string) ($user->status ?? '')));

        if ($role === 'farmer') {
            return $status === 'approved'
                ? null
                : 'Ang iyong account ay hindi pa naaprubahan o na-decline. Makipag-ugnayan sa admin.';
        }

        if (in_array($status, self::BLOCKED_STATUSES, true)) {
            return 'Naka-disable ang account na ito. Makipag-ugnayan sa admin.';
        }

        return null;
    }

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendToLogin($request, 'Mag-login muna para magpatuloy.');
        }

        if ($problem = self::accountProblem($user)) {
            Log::notice('Account blocked by role middleware', [
                'user_id' => $user->id,
                'role'    => $user->role,
                'status'  => $user->status ?? null,
            ]);
            return $this->logoutAndSendToLogin($request, $problem);
        }

        $role    = self::normalize($user->role);
        $allowed = array_map([self::class, 'normalize'], $roles);

        if (in_array($role, $allowed, true)) {
            return $next($request);
        }

        // A real, valid user — just in the wrong area. Send them home.
        Log::notice('Role access denied', [
            'user_id' => $user->id,
            'role'    => $role,
            'path'    => $request->path(),
        ]);

        $message = 'Hindi para sa uri ng account mo ang pahinang iyon. Ibinalik ka sa sarili mong dashboard.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        $home = self::homeRouteFor($role);

        // Never redirect to the page we are already refusing (no redirect loop).
        if ($request->routeIs($home)) {
            abort(403, $message);
        }

        return redirect()->route($home)->with('error', $message);
    }

    private function sendToLogin(Request $request, string $message): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'message' => $message], 401);
        }

        return redirect()->route('login')->with('error', $message);
    }

    private function logoutAndSendToLogin(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => false, 'logged_out' => true, 'message' => $message], 401);
        }

        return redirect()->route('login')->with('error', $message);
    }
}