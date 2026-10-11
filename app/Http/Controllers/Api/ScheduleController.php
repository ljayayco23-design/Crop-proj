<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ScheduleController as WebScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Mobile (Flutter) side of the farmer "Schedule".
 * Nothing is re-implemented: it calls the SAME WebScheduleController::sync()
 * the website uses (same table, same "latest edit wins" rule).
 */
class ScheduleController extends Controller
{
    /** POST /schedule/sync {changes:[...]} -> {success, events:[...]} */
    public function sync(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }
        // The web controller reads auth()->id() / auth()->user(): make it the token's user.
        Auth::setUser($user);

        $res = app(WebScheduleController::class)->sync($request);

        if ($res->getStatusCode() === 200) {
            $data = $res->getData(true);
            $data['success'] = true;
            return response()->json($data);
        }
        return $res;
    }
}