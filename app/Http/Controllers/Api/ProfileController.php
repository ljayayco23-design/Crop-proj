<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Account Center of the farmer app (JSON twin of AdminDashboardController@updatePassword).
 *
 *  POST /api/v1/profile/password  -> change password (needs the current one)
 *
 * Behind auth:sanctum + EnsureApiFarmer; works on $request->user() only.
 */
class ProfileController extends Controller
{
    public function password(Request $request)
    {
        $user = $request->user();

        $v = Validator::make($request->all(), [
            'current_password' => ['required', 'string', 'max:255'],
            'new_password'     => [
                'required', 'string', 'max:64', 'regex:/^\S+$/',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'new_password.regex' => 'Bawal ang espasyo sa password.',
        ]);

        if ($v->fails()) {
            return response()->json(['success' => false, 'message' => $v->errors()->first()], 422);
        }

        if (!Hash::check($request->input('current_password'), $user->password)) {
            return response()->json(['success' => false, 'message' => 'Current password is incorrect.'], 422);
        }

        if (Hash::check($request->input('new_password'), $user->password)) {
            return response()->json(['success' => false, 'message' => 'Dapat iba ang bagong password sa kasalukuyan.'], 422);
        }

        try {
            $user->password = Hash::make($request->input('new_password'));
            $user->save();

            // Sign the farmer out of every OTHER device; this phone keeps its token.
            $current = $user->currentAccessToken();
            $q = $user->tokens();
            if ($current instanceof PersonalAccessToken) {
                $q->where('id', '!=', $current->id);
            }
            $q->delete();
        } catch (\Throwable $e) {
            Log::error('API password change failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'May problema sa pagpalit ng password. Pakisubukan ulit mamaya.'], 500);
        }

        return response()->json(['success' => true, 'message' => 'Password updated!']);
    }
}