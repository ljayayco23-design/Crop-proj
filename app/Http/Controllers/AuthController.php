<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureUserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ------------------------------------------------------------------
    // Login lockout: 5 wrong passwords in a row -> locked for 15 seconds.
    // Counted per (email + IP). Needs a cache driver that is shared between
    // requests (database / redis). If the cache is down, login still works
    // (fails open) and the problem is written to the log.
    // ------------------------------------------------------------------
    private const MAX_ATTEMPTS   = 5;
    private const LOCK_SECONDS   = 15;
    private const ATTEMPT_WINDOW = 900; // forget old failures after 15 min

    private const ID_TYPES = ['barangay_clearance', 'voters_id', 'national_id', 'farmer_id', 'other'];

    // Throw-away mailbox providers are not accepted for a farmer account.
    private const DISPOSABLE_DOMAINS = [
        'mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com',
        'temp-mail.org', 'yopmail.com', 'trashmail.com', 'sharklasers.com',
        'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com',
    ];

    private function lockKey(Request $request): string
    {
        return sha1(Str::lower(trim((string) $request->input('email'))) . '|' . $request->ip());
    }

    /** Seconds left on the lock, 0 when not locked. */
    private function lockRemaining(string $key): int
    {
        try {
            $until = (int) Cache::get("login_lock:$key", 0);
            return max(0, $until - time());
        } catch (\Throwable $e) {
            Log::warning('Login lock check failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** Records one failed login. Returns [lockedNow, attemptsLeft]. */
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
            Log::warning('Login failure counter failed: ' . $e->getMessage());
            return [false, self::MAX_ATTEMPTS];
        }
    }

    private function clearFailures(string $key): void
    {
        try {
            Cache::forget("login_fail:$key");
            Cache::forget("login_lock:$key");
        } catch (\Throwable $e) {
            Log::warning('Login counter clear failed: ' . $e->getMessage());
        }
    }

    private function lockedResponse(Request $request, bool $isAjax, int $seconds)
    {
        $message = "Masyadong maraming maling subok. Maghintay ng {$seconds} segundo bago subukan ulit.";
        if ($isAjax) {
            return response()->json([
                'success'     => false,
                'locked'      => true,
                'retry_after' => $seconds,
                'message'     => $message,
            ], 429)->header('Retry-After', $seconds);
        }
        return back()
            ->with('error', $message)
            ->with('lockout_seconds', $seconds)
            ->withInput($request->only('email'));
    }

    public function showLoginForm()
    {
    // If the user is already logged in, redirect them to THEIR OWN role dashboard.
    if (auth()->check()) {
        $user = auth()->user();
        $home = EnsureUserRole::homeRouteFor($user->role);

        if ($home && !EnsureUserRole::accountProblem($user)) {
            return redirect()->route($home);
        }

        // Unknown role or a blocked account: log it out and show the login form.
        // (Redirecting to 'login' again here used to loop forever.)
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }

    // Return view with explicit anti-caching headers
    return response()
        ->view('auth.login') 
        ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
        ->header('Pragma', 'no-cache')
        ->header('Expires', 'Sat, 01 Jan 1900 00:00:00 GMT');
    }

    public function login(Request $request)
    {
        // The offline-login script on the login page submits this same
        // route via fetch() with these two headers set, so it can read a
        // clean JSON result instead of a redirect. A normal <form> POST
        // (no JS, or JS disabled) never sends them, so nothing below
        // changes for that path — it keeps returning redirects exactly as
        // before.
        $isAjax = $request->ajax() || $request->wantsJson();

        // Gracefully clear old active session if user submits form while logged in
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Catch validation exception and strip base64 to prevent payload errors
        try {
            $request->validate([
                'email' => 'required|email|max:100',
                'password' => 'required|string|max:255',
            ]);
        } catch (ValidationException $e) {
            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Invalid input.',
                ], 422);
            }
            return back()
                ->withErrors($e->errors())
                ->withInput($request->except(['password', 'password_confirmation', 'document_photo_base64', 'selfie_photo_base64']));
        }

        // Handle Signup
        if ($request->has('action') && $request->action === 'signup') {
            
            $flashExcept = ['password', 'password_confirmation', 'document_photo_base64', 'selfie_photo_base64', 'id_check'];

            // Tidy the text fields before they are validated and stored.
            $request->merge([
                'full_name' => preg_replace('/\s+/', ' ', trim((string) $request->full_name)),
                'email'     => Str::lower(trim((string) $request->email)),
                'mobile'    => preg_replace('/\D+/', '', (string) $request->mobile),
                'farm_name' => preg_replace('/\s+/', ' ', trim((string) $request->farm_name)),
            ]);

            try {
                $request->validate([
                    'full_name' => ['required', 'string', 'min:5', 'max:100', "regex:/^(?=.*\\s)\\p{L}[\\p{L}\\s.'\\-]*$/u"],
                    'dob' => [
                        'required', 'date_format:Y-m-d',
                        'before_or_equal:' . now()->subYears(18)->toDateString(),
                        'after_or_equal:' . now()->subYears(100)->toDateString(),
                    ],
                    'mobile' => ['required', 'regex:/^09\d{9}$/'],
                    'email' => [
                        'required', 'string', 'max:100', 'email:rfc',
                        // must look like name@domain.tld (TLD = letters only, 2+ chars)
                        'regex:/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/',
                        function ($attribute, $value, $fail) {
                            $domain = Str::after((string) $value, '@');
                            if (in_array($domain, self::DISPOSABLE_DOMAINS, true)) {
                                $fail('Hindi tinatanggap ang temporary email. Gumamit ng totoong email.');
                            }
                        },
                    ],
                    'password' => [
                        'required', 'string', 'max:64', 'confirmed', 'regex:/^\S+$/',
                        Password::min(8)->mixedCase()->numbers()->symbols(),
                    ],
                    'province_id' => 'required|exists:provinces,id',
                    'city_id' => 'required|exists:cities,id',
                    'barangay_id' => 'required|exists:barangays,id',
                    'farmer_category' => ['required', 'in:owner,tenant,laborer'],
                    'farm_name' => ['required', 'string', 'min:2', 'max:100', "regex:/^[\\p{L}\\p{N}\\s.,'#&\\-]+$/u"],
                    'farm_size' => ['required', 'numeric', 'min:0.01', 'max:1000'],
                    'water_source' => ['required', 'in:irrigated,rainfed'],
                    'growth_stage' => ['nullable', 'in:Seedling,Vegetative,Reproductive,Ripening'],
                    'rice_variety' => ['nullable', 'string', 'max:50', "regex:/^[\\p{L}\\p{N}\\s.\\-]+$/u"],
                    // Philippines bounding box
                    'latitude' => ['required', 'numeric', 'between:4.5,21.5'],
                    'longitude' => ['required', 'numeric', 'between:116,127.5'],
                    'device_latitude' => ['nullable', 'numeric', 'between:-90,90'],
                    'device_longitude' => ['nullable', 'numeric', 'between:-180,180'],
                    'id_type' => ['required', 'in:' . implode(',', self::ID_TYPES)],
                    'document_photo_base64' => ['required', 'string', 'starts_with:data:image/jpeg;base64,', 'max:3000000'],
                    'id_check' => ['nullable', 'string', 'max:5000'],
                ], [
                    'full_name.regex' => 'Ilagay ang buong pangalan (first name at last name), letra lang.',
                    'dob.before_or_equal' => 'Kailangang 18 taong gulang pataas.',
                    'dob.after_or_equal' => 'Hindi valid ang petsa ng kapanganakan.',
                    'mobile.regex' => 'Ang mobile number ay dapat 11 digits at nagsisimula sa 09 (hal. 09123456789).',
                    'email.regex' => 'Hindi valid ang email. Dapat may tamang domain, hal. juan@gmail.com.',
                    'password.regex' => 'Bawal ang espasyo sa password.',
                    'password.confirmed' => 'Hindi magkapareho ang password at confirm password.',
                    'farm_size.min' => 'Ang laki ng sakahan ay dapat hindi bababa sa 0.01 ha.',
                    'farm_size.max' => 'Ang laki ng sakahan ay hindi dapat lumampas sa 1000 ha.',
                    'latitude.between' => 'Ang lokasyon ng sakahan ay dapat nasa loob ng Pilipinas.',
                    'longitude.between' => 'Ang lokasyon ng sakahan ay dapat nasa loob ng Pilipinas.',
                    'document_photo_base64.required' => 'Mag-upload ng larawan ng valid ID.',
                    'document_photo_base64.starts_with' => 'Hindi tinatanggap ang format ng larawan ng ID.',
                ]);
            } catch (ValidationException $e) {
                return back()
                    ->withErrors($e->errors())
                    ->withInput($request->except($flashExcept))
                    ->with('open_signup', true);
            }

            // The ID photo must really be a JPEG image of a usable size.
            $photoBinary = base64_decode(substr($request->document_photo_base64, strlen('data:image/jpeg;base64,')), true);
            $photoInfo = $photoBinary ? @getimagesizefromstring($photoBinary) : false;
            if (!$photoInfo || $photoInfo[2] !== IMAGETYPE_JPEG || min($photoInfo[0], $photoInfo[1]) < 400) {
                return back()
                    ->withErrors(['document_photo_base64' => 'Hindi valid o masyadong maliit ang larawan ng ID. Mag-upload ng mas malinaw na larawan.'])
                    ->withInput($request->except($flashExcept))
                    ->with('open_signup', true);
            }

            // Hints from the browser-side ID check (face / text / quality).
            // They come from the user's browser, so they are only a note for
            // the admin, never a reason to approve anybody automatically.
            $idCheck = json_decode((string) $request->id_check, true);
            $idCheck = is_array($idCheck) ? $idCheck : [];
            $idCheck = array_intersect_key($idCheck, array_flip([
                'status', 'faces', 'face_ratio', 'type_keyword_match', 'name_match', 'sharpness', 'brightness',
            ]));
            $idCheck['reported_by'] = 'browser';
            $idCheck['server_image'] = ['width' => $photoInfo[0], 'height' => $photoInfo[1], 'kb' => (int) round(strlen($photoBinary) / 1024)];

            // Case-insensitive on purpose: TiDB's default collation is case-sensitive.
            $existing = User::whereRaw('LOWER(email) = ?', [Str::lower($request->email)])->first();
            if ($existing) {
                return back()
                    ->with('error', 'Ang email na ito ay nagamit na.')
                    ->with('open_signup', true)
                    ->withInput($request->except($flashExcept));
            }

            // Handle DB errors gracefully without crashing Vercel
            try {
                $newUser = User::create([
                    'full_name' => $request->full_name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'farmer',
                    'status' => 'pending',
                    'phone' => $request->mobile,
                    'dob' => $request->dob,
                    'farmer_category' => $request->farmer_category,
                    'farm_size' => $request->farm_size,
                    'water_source' => $request->water_source,
                    'id_type' => $request->id_type,
                    'document_photo' => $request->document_photo_base64, 
                    'province_id' => $request->province_id,
                    'city_id' => $request->city_id,
                    'barangay_id' => $request->barangay_id,
                    'farm_name' => $request->farm_name,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'device_latitude' => $request->device_latitude, 
                    'device_longitude' => $request->device_longitude,
                ]);

                // Optional column (see the add_id_check_to_users_table migration).
                if (Schema::hasColumn('users', 'id_check')) {
                    $newUser->forceFill(['id_check' => json_encode($idCheck)])->save();
                }

                // ==========================================
                // EMAIL NOTIFICATION FOR NEW REGISTRATION
                // ==========================================
                $api_key = env('BREVO_API_KEY');
                $admin_email = 'jfconco604@gmail.com';

                $html_content = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                </head>
                <body style="background-color: #f1f5f9; padding: 20px; margin: 0; font-family: system-ui, -apple-system, sans-serif;">
                    <div style="max-width: 600px; margin: 0 auto; padding: 30px; background-color: #0f172a; color: #ffffff; border-radius: 20px;">
                        <h1 style="color: #10b981; margin-bottom: 20px; margin-top: 0;">RiceGuard AI</h1>
                        <h2 style="color: #ffffff; margin-top: 0;">New Farmer Registration</h2>
                        <p style="color: #e2e8f0;">Hello Admin,</p>
                        <p style="color: #e2e8f0;">A new farmer has just registered and is awaiting your approval.</p>
                        
                        <div style="margin: 30px 0; background-color: #1e293b; padding: 20px; border-radius: 12px;">
                            <p style="margin: 5px 0;"><strong style="color: #10b981;">Name:</strong> ' . htmlspecialchars($request->full_name) . '</p>
                            <p style="margin: 5px 0;"><strong style="color: #10b981;">Email:</strong> ' . htmlspecialchars($request->email) . '</p>
                            <p style="margin: 5px 0;"><strong style="color: #10b981;">Farm Name:</strong> ' . htmlspecialchars($request->farm_name) . '</p>
                            <p style="margin: 5px 0;"><strong style="color: #10b981;">Phone:</strong> ' . htmlspecialchars($request->mobile) . '</p>
                        </div>
                        
                        <p style="color: #e2e8f0;">Please log in to your Admin Dashboard to review their documents and approve the account.</p>
                        <hr style="border: none; border-top: 1px solid #334155; margin: 30px 0;">
                        <p style="color: #94a3b8; font-size: 14px; margin-bottom: 0;">System Notification<br>RiceGuard AI Team</p>
                    </div>
                </body>
                </html>';

                $data = [
                    "sender" => ["name" => "RiceGuard AI System", "email" => $admin_email],
                    "to" => [["email" => $admin_email]],
                    "subject" => "Action Required: New Farmer Registration",
                    "htmlContent" => $html_content
                ];

                $ch = curl_init('https://api.brevo.com/v3/smtp/email');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'accept: application/json',
                    'Content-Type: application/json',
                    'api-key: ' . $api_key
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_exec($ch);
                curl_close($ch);
                // ==========================================
                
            } catch (\Exception $e) {
                Log::error('Signup failed: ' . $e->getMessage());
                return back()
                    ->with('error', 'May problema sa pag-register. Pakisubukan ulit mamaya.')
                    ->with('open_signup', true)
                    ->withInput($request->except($flashExcept));
            }

            // Clear registration-specific session footprint to prevent auto-redirect loop
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('pending', $request->email);
        }

        // Normal Login
        $lockKey = $this->lockKey($request);

        // Still locked from 5 wrong attempts? Don't even look at the password.
        $remaining = $this->lockRemaining($lockKey);
        if ($remaining > 0) {
            return $this->lockedResponse($request, $isAjax, $remaining);
        }

        // Look the account up ignoring upper/lower case and stray spaces. Phones
        // often capitalise the first letter ("Juan@gmail.com"), and signup always
        // stores the email in lower case, so an exact match would reject a real user.
        $loginEmail = Str::lower(trim((string) $request->input('email')));
        $user = User::whereRaw('LOWER(email) = ?', [$loginEmail])->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            [$lockedNow, $attemptsLeft] = $this->registerFailure($lockKey);
            if ($lockedNow) {
                return $this->lockedResponse($request, $isAjax, self::LOCK_SECONDS);
            }
            $message = $attemptsLeft <= 2
                ? "Mali ang email o password. {$attemptsLeft} subok na lang bago ma-lock."
                : 'Mali ang email o password.';
            if ($isAjax) {
                return response()->json(['success' => false, 'message' => $message, 'attempts_left' => $attemptsLeft], 422);
            }
            return back()->with('error', $message)->withInput($request->only('email'));
        }

        // Correct password: the failed-attempt counter starts over.
        $this->clearFailures($lockKey);

        // The role decides which side of the app this person belongs to. An
        // unknown/empty role is refused — it must never fall through to the
        // farmer dashboard (that is how someone ends up on the wrong side).
        $role = EnsureUserRole::normalize($user->role);
        $home = EnsureUserRole::homeRouteFor($role);
        if (!$home) {
            Log::warning('Login refused: account has no valid role', ['user_id' => $user->id, 'role' => $user->role]);
            $message = 'Walang valid na role ang account na ito. Makipag-ugnayan sa admin.';
            if ($isAjax) {
                return response()->json(['success' => false, 'message' => $message], 403);
            }
            return back()->with('error', $message)->withInput($request->only('email'));
        }

        if ($role === 'farmer' && $user->status !== 'approved') {
            if ($user->status === 'pending') {
                if ($isAjax) {
                    return response()->json(['success' => false, 'status' => 'pending', 'message' => 'Ang iyong account ay naka-pending pa para sa approval.'], 403);
                }
                return back()->with('pending', $user->email);
            }
            if ($isAjax) {
                return response()->json(['success' => false, 'status' => 'declined', 'message' => 'Ang iyong account ay na-decline.'], 403);
            }
            return back()->with('declined', $user->email);
        }

        // Staff accounts that were disabled / suspended cannot log in either.
        if ($problem = EnsureUserRole::accountProblem($user)) {
            if ($isAjax) {
                return response()->json(['success' => false, 'message' => $problem], 403);
            }
            return back()->with('error', $problem)->withInput($request->only('email'));
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        // Developer is a hardcoded, seeded-only account (see the
        // 2026_08_23_000002_seed_developer_user migration) that reuses the
        // admin panel wholesale — same dashboard, same views.
        //
        // Built with route(), NOT a hard-coded "/admin/dashboard": a root path
        // ignores the folder the app lives in (http://localhost/Crop-proj/public)
        // and lands on http://localhost/admin/dashboard, which does not exist.
        // route() always includes the correct base URL.
        $redirectUrl = route($home);

        if ($isAjax) {
            // The offline-login script caches full_name/role/redirect (never
            // the raw password) alongside a locally-hashed password so this
            // same device can "log in" again with no network. See the
            // <script> block at the bottom of login.blade.php.
            return response()->json([
                'success'   => true,
                'redirect'  => $redirectUrl,
                'role'      => $user->role,
                'full_name' => $user->full_name ?? $user->name,
                'email'     => $user->email,
            ]);
        }

        // Always the person's OWN dashboard — never a previously requested URL
        // that may belong to another role.
        return redirect()->to($redirectUrl);
    }

    // ==========================================
    // FORGOT PASSWORD FEATURE (Kept Intact)
    // ==========================================
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'forgot_email' => 'required|email',
        ]);

        $user = User::where('email', $request->forgot_email)->first();

        if (!$user) {
            return back()->with('error', 'Walang account na nakarehistro sa email na ito.');
        }

        // Generate temporary password (12 characters)
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnopqrstuvwxyz23456789!@#$%^&*()_+-';
        $new_password = '';
        for ($i = 0; $i < 12; $i++) {
            $new_password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Update user password in DB
        $user->password = Hash::make($new_password);
        $user->save();

        // Secured Brevo API configuration via Env
        $api_key = env('BREVO_API_KEY');
        $sender_name = 'RiceGuard AI Support';
        $sender_email = 'jfconco604@gmail.com';

        // HTML Email Template
        $html_content = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="background-color: #f1f5f9; padding: 20px; margin: 0; font-family: system-ui, -apple-system, sans-serif;">
            <div style="max-width: 600px; margin: 0 auto; padding: 30px; background-color: #0f172a; color: #ffffff; border-radius: 20px;">
                <h1 style="color: #10b981; margin-bottom: 20px; margin-top: 0;">RiceGuard AI</h1>
                <h2 style="color: #ffffff; margin-top: 0;">Your New Temporary Password</h2>
                <p style="color: #e2e8f0;">Hi ' . htmlspecialchars($user->full_name) . ',</p>
                <p style="color: #e2e8f0;">A password reset was requested for your RiceGuard AI account.</p>
                
                <div style="margin: 30px 0; font-size: 24px; background-color: #1e293b; padding: 20px; border-radius: 12px; text-align: center; letter-spacing: 2px;">
                    <strong style="color: #10b981;">' . htmlspecialchars($new_password) . '</strong>
                </div>
                
                <p style="color: #e2e8f0;"><strong>Please login with this password and change it immediately</strong> for your security.</p>
                <p style="color: #e2e8f0;">If you did not request this reset, please ignore this email.</p>
                
                <hr style="border: none; border-top: 1px solid #334155; margin: 30px 0;">
                <p style="color: #94a3b8; font-size: 14px; margin-bottom: 0;">Thank you,<br>RiceGuard AI Team</p>
            </div>
        </body>
        </html>';

        $data = [
            "sender" => ["name" => $sender_name, "email" => $sender_email],
            "to" => [["email" => $user->email]],
            "subject" => "RiceGuard AI - Your New Temporary Password",
            "htmlContent" => $html_content
        ];

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'Content-Type: application/json',
            'api-key: ' . $api_key
        ]);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 201 || $http_code === 200) {
            return back()->with('success', 'Naipadala na ang bagong temporaryong password sa iyong email. Paki-check ang iyong inbox (at spam folder).');
        } else {
            $errorMessage = 'Nabigong ipadala ang email. Mangyaring subukan muli mamaya.';
            
            if ($curl_error) {
                $errorMessage .= ' (cURL Error: ' . $curl_error . ')';
            } elseif ($response) {
                $responseData = json_decode($response, true);
                $errorMessage .= ' (Brevo API Error: ' . ($responseData['message'] ?? $response) . ')';
            }

            return back()->with('error', $errorMessage);
        }
    }

    // ==========================================
    // UPDATE FARM ADDRESS (Province / City / Barangay)
    // Called via AJAX from the farmer dashboard "Farm Address" card
    // ==========================================
    public function updateAddress(Request $request)
    {
        try {
            $request->validate([
                'province_id'  => 'required|exists:provinces,id',
                'city_id'      => 'required|exists:cities,id',
                'barangay_id'  => 'required|exists:barangays,id',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Invalid selection.',
            ], 422);
        }

        $user = Auth::user();
        $user->province_id  = $request->province_id;
        $user->city_id      = $request->city_id;
        $user->barangay_id  = $request->barangay_id;
        $user->save();

        $location = DB::table('barangays')
            ->join('cities', 'barangays.city_id', '=', 'cities.id')
            ->join('provinces', 'cities.province_id', '=', 'provinces.id')
            ->where('barangays.id', $user->barangay_id)
            ->select(
                'provinces.name as province_name',
                'cities.name as city_name',
                'barangays.name as barangay_name'
            )
            ->first();

        $address = $location
            ? "{$location->barangay_name}, {$location->city_name}, {$location->province_name}"
            : 'Not yet set';

        return response()->json([
            'success' => true,
            'address' => $address,
        ]);
    }

    public function logout(Request $request)
    {
        // 1. Log out the authenticated user
        Auth::logout();

        // 2. Invalidate user's session and clear session data
        $request->session()->invalidate();

        // 3. Regenerate CSRF token for the next request
        $request->session()->regenerateToken();

        // 4. Redirect to login with cache-prevention headers
        return redirect()->route('login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
    }
}