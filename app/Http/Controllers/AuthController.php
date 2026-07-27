<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException; 

class AuthController extends Controller
{
    public function showLoginForm()
    {
    // If the user is already logged in, redirect them to their role dashboard
    if (auth()->check()) {
        $role = auth()->user()->role;
        return match ($role) {
            'admin' => redirect()->route('admin.dashboard'),
            'technician' => redirect()->route('technician.dashboard'),
            'farmer' => redirect()->route('farmer.dashboard'),
            default => redirect()->route('login'),
        };
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
        // Gracefully clear old active session if user submits form while logged in
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Catch validation exception and strip base64 to prevent payload errors
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput($request->except(['document_photo_base64']));
        }

        // Handle Signup
        if ($request->has('action') && $request->action === 'signup') {
            
            try {
                $request->validate([
                    'full_name' => 'required|string|max:255',
                    'email' => 'required|email',
                    'password' => 'required|min:6',
                    'address' => 'required|string',
                    'farm_name' => 'required|string',
                    'latitude' => 'required|numeric',
                    'longitude' => 'required|numeric',
                ]);
            } catch (ValidationException $e) {
                return back()
                    ->withErrors($e->errors())
                    ->withInput($request->except(['document_photo_base64']));
            }

            $existing = User::where('email', $request->email)->first();
            if ($existing) {
                return back()
                    ->with('error', 'Ang email na ito ay nagamit na.')
                    ->withInput($request->except(['document_photo_base64']));
            }

            // Handle DB errors gracefully without crashing Vercel
            try {
                User::create([
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
                    'address' => $request->address,
                    'farm_name' => $request->farm_name,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'device_latitude' => $request->device_latitude, 
                    'device_longitude' => $request->device_longitude,
                ]);

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
                return back()
                    ->with('error', 'Database Error: ' . $e->getMessage())
                    ->withInput($request->except(['document_photo_base64']));
            }

            // Clear registration-specific session footprint to prevent auto-redirect loop
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('pending', $request->email);
        }

        // Normal Login
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->with('error', 'Mali ang email o password.');
        }

        if ($user->role === 'farmer' && $user->status !== 'approved') {
            if ($user->status === 'pending') {
                return back()->with('pending', $user->email);
            }
            return back()->with('declined', $user->email);
        }

        (Auth::login($user));
        $request->session()->regenerate();

        if ($user->role === 'admin') return redirect('/admin/dashboard');
        if ($user->role === 'technician') return redirect('/technician/dashboard');
        return redirect('/farmer/dashboard');
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