<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role gate for the mobile API (routes/api.php, inside auth:sanctum).
 *
 * This is the API twin of EnsureUserRole (web). The web version runs on EVERY
 * request, but the API used to check role + status only once, at login. So a
 * farmer who was declined / changed role AFTER logging in kept a working token.
 *
 * On every request this makes sure:
 *   1. the account is still allowed in (same rule as the web: EnsureUserRole::accountProblem,
 *      i.e. a farmer must still be "approved");
 *   2. the account is still a FARMER (this app is the farmer side only);
 *   3. when either fails, the token used for the request is deleted and the
 *      phone gets JSON with logged_out = true, which the Flutter app turns
 *      into "back to the login screen".
 */
class EnsureApiFarmer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Mag-login muna para magpatuloy.'], 401);
        }

        // 1. same account rule as the website (unknown role, farmer not approved, ...)
        if ($problem = EnsureUserRole::accountProblem($user)) {
            return $this->reject($request, $user, $problem, 401);
        }

        // 2. farmer side only
        if (EnsureUserRole::normalize($user->role) !== 'farmer') {
            return $this->reject($request, $user, 'Para lamang sa mga magsasaka ang app na ito.', 403);
        }

        return $next($request);
    }

    private function reject(Request $request, $user, string $message, int $status): Response
    {
        Log::notice('API account blocked by role middleware', [
            'user_id' => $user->id,
            'role'    => $user->role,
            'status'  => $user->status ?? null,
            'path'    => $request->path(),
        ]);

        // Cancel the token that was just used, so it can never be used again.
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'success'    => false,
            'logged_out' => true,
            'message'    => $message,
        ], $status);
    }
}