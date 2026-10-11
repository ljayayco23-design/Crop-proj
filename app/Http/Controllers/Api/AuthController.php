<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\IdPhotoChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // Same rules as the web login: 5 wrong passwords in a row -> locked for
    // 15 seconds (per email + IP). Needs a cache driver shared between
    // requests (file / database / redis). If the cache fails, login still
    // works and the problem is written to the log.
    private const MAX_ATTEMPTS   = 5;
    private const LOCK_SECONDS   = 15;
    private const ATTEMPT_WINDOW = 900;

    private const ID_TYPES = ['barangay_clearance', 'voters_id', 'national_id', 'farmer_id', 'other'];

    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'yopmail.com', 'trashmail.com', 'sharklasers.com',
        'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com',
    ];

    // ---------------------------------------------------------------- lockout

    private function lockKey(Request $request): string
    {
        return sha1(Str::lower(trim((string) $request->input('email'))) . '|' . $request->ip());
    }

    private function lockRemaining(string $key): int
    {
        try {
            return max(0, (int) Cache::get("login_lock:$key", 0) - time());
        } catch (\Throwable $e) {
            Log::warning('API login lock check failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** Returns [lockedNow, attemptsLeft]. */
    private function registerFailure(string $key): array
    {
        try {
            $n = (int) Cache::get("login_fail:$key", 0) + 1;
            if ($n >= self::MAX_ATTEMPTS) {
                Cache::put("login_lock:$key", time() + self::LOCK_SECONDS, self::LOCK_SECONDS);
                Cache::forget("login_fail:$key");
                return [true, 0];
            }
            Cache::put("login_fail:$key", $n, self::ATTEMPT_WINDOW);
            return [false, self::MAX_ATTEMPTS - $n];
        } catch (\Throwable $e) {
            Log::warning('API login failure counter failed: ' . $e->getMessage());
            return [false, self::MAX_ATTEMPTS];
        }
    }

    private function clearFailures(string $key): void
    {
        try {
            Cache::forget("login_fail:$key");
            Cache::forget("login_lock:$key");
        } catch (\Throwable $e) {
            Log::warning('API login counter clear failed: ' . $e->getMessage());
        }
    }

    private function lockedResponse(int $seconds)
    {
        return response()->json([
            'success'     => false,
            'locked'      => true,
            'retry_after' => $seconds,
            'message'     => "Masyadong maraming maling subok. Maghintay ng {$seconds} segundo bago subukan ulit.",
        ], 429)->header('Retry-After', $seconds);
    }

    // ------------------------------------------------------------------ login

    public function login(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'email'       => 'required|email|max:100',
            'password'    => 'required|string|max:255',
            'device_name' => 'nullable|string|max:100',
        ]);

        $lockKey = $this->lockKey($request);

        $remaining = $this->lockRemaining($lockKey);
        if ($remaining > 0) {
            return $this->lockedResponse($remaining);
        }

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            [$lockedNow, $attemptsLeft] = $this->registerFailure($lockKey);
            if ($lockedNow) {
                return $this->lockedResponse(self::LOCK_SECONDS);
            }
            return response()->json([
                'success'       => false,
                'attempts_left' => $attemptsLeft,
                'message'       => $attemptsLeft <= 2
                    ? "Mali ang email o password. {$attemptsLeft} subok na lang bago ma-lock."
                    : 'Mali ang email o password.',
            ], 422);
        }

        // Correct password: the failed-attempt counter starts over.
        $this->clearFailures($lockKey);

        // This app is the farmer side only
        if (strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json([
                'success' => false,
                'message' => 'Para lamang sa mga magsasaka ang app na ito.',
            ], 403);
        }

        $status = strtolower(trim((string) $user->status));

        if ($status !== 'approved') {
            if ($status === 'pending') {
                return response()->json([
                    'success' => false,
                    'status'  => 'pending',
                    'message' => 'Ang iyong account ay naka-pending pa para sa approval.',
                ], 403);
            }

            return response()->json([
                'success' => false,
                'status'  => 'declined',
                'message' => 'Ang iyong account ay na-decline.',
            ], 403);
        }

        try {
            $token = $user->createToken($data['device_name'] ?? 'flutter-app')->plainTextToken;
        } catch (\Throwable $e) {
            Log::error('API login token error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error sa token. Subukan ulit mamaya.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'token'   => $token,
            'user'    => $this->userPayload($user),
        ]);
    }

    // --------------------------------------------------------------- register

    /**
     * Farmer sign-up. Same strict rules as the "signup" branch of the web
     * AuthController@login: role = farmer, status = pending.
     */
    public function register(Request $request)
    {
        // Tidy the text fields before they are validated and stored.
        $mobile = preg_replace('/\D+/', '', (string) $request->input('mobile'));
        if (strlen($mobile) === 12 && str_starts_with($mobile, '63')) {
            $mobile = '0' . substr($mobile, 2);          // +63 9xx -> 09xx
        }
        $request->merge([
            'full_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('full_name'))),
            'email'     => Str::lower(trim((string) $request->input('email'))),
            'mobile'    => $mobile,
            'farm_name' => preg_replace('/\s+/', ' ', trim((string) $request->input('farm_name'))),
        ]);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:5', 'max:100', "regex:/^(?=.*\\s)\\p{L}[\\p{L}\\s.'\\-]*$/u"],
            'dob' => [
                'required', 'date_format:Y-m-d',
                'before_or_equal:' . now()->subYears(18)->toDateString(),
                'after_or_equal:' . now()->subYears(100)->toDateString(),
            ],
            'mobile' => ['required', 'regex:/^09\d{9}$/'],
            'email' => [
                'required', 'string', 'max:100', 'email:rfc',
                'regex:/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/',
                function ($attribute, $value, $fail) {
                    if (in_array(Str::after((string) $value, '@'), self::DISPOSABLE_DOMAINS, true)) {
                        $fail('Hindi tinatanggap ang temporary email. Gumamit ng totoong email.');
                    }
                },
            ],
            // The app checks "confirm password" on the phone, so no password_confirmation here.
            'password' => [
                'required', 'string', 'max:64', 'regex:/^\S+$/',
                Password::min(8)->mixedCase()->numbers()->symbols(),
            ],
            'province_id'     => 'required|exists:provinces,id',
            'city_id'         => 'required|exists:cities,id',
            'barangay_id'     => 'required|exists:barangays,id',
            'farmer_category' => ['required', 'in:owner,tenant,laborer'],
            'farm_name'       => ['required', 'string', 'min:2', 'max:100', "regex:/^[\\p{L}\\p{N}\\s.,'#&\\-]+$/u"],
            'farm_size'       => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'water_source'    => ['required', 'in:irrigated,rainfed'],
            // Philippines bounding box
            'latitude'         => ['required', 'numeric', 'between:4.5,21.5'],
            'longitude'        => ['required', 'numeric', 'between:116,127.5'],
            'device_latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'device_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'id_type'          => ['required', 'in:' . implode(',', self::ID_TYPES)],
            'document_photo_base64' => ['required', 'string', 'max:4000000', 'regex:/^data:image\/(jpeg|png|webp);base64,/'],
            'id_check'         => ['nullable', 'string', 'max:5000'],
        ], [
            'full_name.regex'      => 'Ilagay ang buong pangalan (first name at last name), letra lang.',
            'dob.before_or_equal'  => 'Kailangang 18 taong gulang pataas.',
            'dob.after_or_equal'   => 'Hindi valid ang petsa ng kapanganakan.',
            'mobile.regex'         => 'Ang mobile number ay dapat 11 digits at nagsisimula sa 09 (hal. 09123456789).',
            'email.regex'          => 'Hindi valid ang email. Dapat may tamang domain, hal. juan@gmail.com.',
            'password.regex'       => 'Bawal ang espasyo sa password.',
            'farm_size.min'        => 'Ang laki ng sakahan ay dapat hindi bababa sa 0.01 ha.',
            'farm_size.max'        => 'Ang laki ng sakahan ay hindi dapat lumampas sa 1000 ha.',
            'latitude.between'     => 'Ang lokasyon ng sakahan ay dapat nasa loob ng Pilipinas.',
            'longitude.between'    => 'Ang lokasyon ng sakahan ay dapat nasa loob ng Pilipinas.',
            'document_photo_base64.required' => 'Mag-upload ng larawan ng valid ID.',
            'document_photo_base64.regex'    => 'Hindi tinatanggap ang format ng larawan ng ID.',
        ]);

        // The ID photo must really be an image of a usable size.
        $photoBinary = base64_decode(substr($data['document_photo_base64'], strpos($data['document_photo_base64'], ',') + 1), true);
        $photoInfo   = $photoBinary ? @getimagesizefromstring($photoBinary) : false;
        if (!$photoInfo || !in_array($photoInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || min($photoInfo[0], $photoInfo[1]) < 400) {
            return response()->json([
                'success' => false,
                'message' => 'Hindi valid o masyadong maliit ang larawan ng ID. Mag-upload ng mas malinaw na larawan.',
            ], 422);
        }

        if (User::where('email', $data['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Ang email na ito ay nagamit na.',
            ], 422);
        }

        // ---- same ID photo check as the web form -------------------------------
        $checker = new IdPhotoChecker();

        // 1. quality (size / brightness / sharpness) - always, no API needed
        [$qOk, $qMsg, $qMeta] = $checker->quality($photoBinary);
        if (!$qOk) {
            return response()->json(['success' => false, 'message' => $qMsg], 422);
        }

        // 2. hints from the phone's own check (face + text). Only a note for the
        //    admin, never a reason to approve anybody automatically.
        $idCheck = json_decode((string) $request->input('id_check'), true);
        $idCheck = is_array($idCheck) ? $idCheck : [];
        $idCheck = array_intersect_key($idCheck, array_flip([
            'status', 'faces', 'face_ratio', 'type_keyword_match', 'name_match', 'sharpness', 'brightness', 'checked_by',
        ]));

        // 3. the phone could not check face/text (web build, no ML, or it was offline):
        //    ask the vision AI here when a key is set, otherwise mark "unverified".
        if (($idCheck['status'] ?? '') !== 'passed') {
            $vision = $checker->vision($data['document_photo_base64'], $data['id_type'], $data['full_name']);
            if ($vision !== null) {
                [$vOk, $vMsg, $vMeta] = $checker->judge($vision, $data['id_type']);
                if (!$vOk) {
                    return response()->json(['success' => false, 'message' => $vMsg], 422);
                }
                $idCheck = array_merge($idCheck, $vMeta);
            } else {
                $idCheck['status'] = 'unverified';
            }
        }

        $idCheck['reported_by']  = $idCheck['reported_by'] ?? 'mobile-app';
        $idCheck['server_image'] = [
            'width' => $photoInfo[0], 'height' => $photoInfo[1],
            'kb' => (int) round(strlen($photoBinary) / 1024),
            'sharpness' => $qMeta['sharpness'] ?? null, 'brightness' => $qMeta['brightness'] ?? null,
        ];

        try {
            $newUser = User::create([
                'full_name'        => $data['full_name'],
                'email'            => $data['email'],
                'password'         => Hash::make($data['password']),
                'role'             => 'farmer',
                'status'           => 'pending',
                'phone'            => $data['mobile'],
                'dob'              => $data['dob'],
                'farmer_category'  => $data['farmer_category'],
                'farm_size'        => $data['farm_size'],
                'water_source'     => $data['water_source'],
                'id_type'          => $data['id_type'],
                'document_photo'   => $data['document_photo_base64'],
                'province_id'      => $data['province_id'],
                'city_id'          => $data['city_id'],
                'barangay_id'      => $data['barangay_id'],
                'farm_name'        => $data['farm_name'],
                'latitude'         => $data['latitude'],
                'longitude'        => $data['longitude'],
                'device_latitude'  => $data['device_latitude'] ?? null,
                'device_longitude' => $data['device_longitude'] ?? null,
            ]);

            if (Schema::hasColumn('users', 'id_check')) {
                $newUser->forceFill(['id_check' => json_encode($idCheck)])->save();
            }
        } catch (\Throwable $e) {
            Log::error('API signup failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'May problema sa pag-register. Pakisubukan ulit mamaya.',
            ], 500);
        }

        $this->notifyAdmin($data);

        return response()->json([
            'success' => true,
            'status'  => 'pending',
            'email'   => $data['email'],
            'message' => 'Naipadala na ang iyong registration. Hintayin ang approval ng Admin.',
        ], 201);
    }

    /**
     * ID photo check for the phone app (used when the phone cannot check the
     * photo itself, e.g. the Flutter web build). Same rules as the web form.
     * POST { id_type, full_name?, image_base64 (data URI) }
     */
    public function idCheck(Request $request)
    {
        $data = $request->validate([
            'id_type'      => ['required', 'in:' . implode(',', self::ID_TYPES)],
            'full_name'    => ['nullable', 'string', 'max:100'],
            'image_base64' => ['required', 'string', 'max:4000000', 'regex:/^data:image\/(jpeg|png|webp);base64,/'],
        ]);

        $binary = base64_decode(substr($data['image_base64'], strpos($data['image_base64'], ',') + 1), true);
        if (!$binary) {
            return response()->json(['success' => true, 'ok' => false, 'message' => 'Hindi mabuksan ang larawan.', 'meta' => []]);
        }

        $checker = new IdPhotoChecker();
        [$ok, $msg, $meta] = $checker->quality($binary);

        if ($ok) {
            $vision = $checker->vision($data['image_base64'], $data['id_type'], (string) ($data['full_name'] ?? ''));
            if ($vision !== null) {
                [$ok, $msg, $vMeta] = $checker->judge($vision, $data['id_type']);
                $meta = array_merge($meta, $vMeta);
            } else {
                $meta['status'] = 'unverified';
            }
        }

        return response()->json(['success' => true, 'ok' => $ok, 'message' => $msg, 'meta' => $meta]);
    }

    /** Used by the "waiting for approval" screen (same idea as web /check-status). */
    public function checkStatus(Request $request)
    {
        $user = User::where('email', $request->query('email'))->first();

        return response()->json(['status' => $user ? $user->status : 'none'])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user'    => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out.',
        ]);
    }

    /** Same admin email the web registration sends. Never lets a mail failure break sign-up. */
    private function notifyAdmin(array $d): void
    {
        $apiKey = env('BREVO_API_KEY');
        if (empty($apiKey)) {
            return;
        }

        $adminEmail = 'jfconco604@gmail.com';

        $html = '<div style="font-family:system-ui,sans-serif;max-width:600px;margin:0 auto;padding:30px;background:#0f172a;color:#fff;border-radius:20px;">'
            . '<h1 style="color:#10b981;margin-top:0;">RiceGuard AI</h1>'
            . '<h2 style="margin-top:0;">New Farmer Registration</h2>'
            . '<p>A new farmer registered from the mobile app and is awaiting your approval.</p>'
            . '<p><strong style="color:#10b981;">Name:</strong> ' . e($d['full_name']) . '<br>'
            . '<strong style="color:#10b981;">Email:</strong> ' . e($d['email']) . '<br>'
            . '<strong style="color:#10b981;">Farm Name:</strong> ' . e($d['farm_name']) . '<br>'
            . '<strong style="color:#10b981;">Phone:</strong> ' . e($d['mobile']) . '</p>'
            . '<p>Please log in to the Admin Dashboard to review their documents and approve the account.</p>'
            . '</div>';

        try {
            Http::withHeaders([
                'accept'  => 'application/json',
                'api-key' => $apiKey,
            ])->timeout(8)->post('https://api.brevo.com/v3/smtp/email', [
                'sender'      => ['name' => 'RiceGuard AI System', 'email' => $adminEmail],
                'to'          => [['email' => $adminEmail]],
                'subject'     => 'Action Required: New Farmer Registration',
                'htmlContent' => $html,
            ]);
        } catch (\Throwable $e) {
            // ignore: registration already saved
        }
    }

    private function userPayload(User $user): array
    {
        return [
            'id'              => $user->id,
            'full_name'       => $user->full_name,
            'email'           => $user->email,
            'phone'           => $user->phone,
            'role'            => $user->role,
            'status'          => $user->status,
            'farm_name'       => $user->farm_name,
            'farm_size'       => $user->farm_size,
            'farmer_category' => $user->farmer_category,
            'rice_variety'    => $user->rice_variety,
            'latitude'        => $user->latitude,
            'longitude'       => $user->longitude,
            'address'         => $user->address,
            'lang'            => $user->lang,
        ];
    }
}