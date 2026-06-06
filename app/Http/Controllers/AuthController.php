<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'admin') return redirect('/admin/dashboard');
            if ($user->role === 'technician') return redirect('/technician/dashboard');
            if ($user->role === 'farmer') return redirect('/farmer/dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Handle Signup
        if ($request->has('action') && $request->action === 'signup') {
            $request->validate([
                'full_name' => 'required|string|max:255',
            ]);

            $existing = User::where('email', $request->email)->first();
            if ($existing) {
                return back()->with('error', 'Ang email na ito ay nagamit na.');
            }

            User::create([
                'full_name' => $request->full_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'farmer',
                'status' => 'pending',
            ]);

            return back()->with('pending', $request->email);
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

        Auth::login($user);
        $request->session()->regenerate();

        if ($user->role === 'admin') return redirect('/admin/dashboard');
        if ($user->role === 'technician') return redirect('/technician/dashboard');
        return redirect('/farmer/dashboard');
    }

    // ==========================================
    // UPDATED FORGOT PASSWORD FEATURE
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

        // ==========================================
        // SECURED BREVO API KEY VIA ENV
        // ==========================================
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

        // Disable SSL verification for localhost testing
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
}

