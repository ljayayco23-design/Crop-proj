<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WeatherAlert;
use App\Services\WeatherAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminWeatherAlertController extends Controller
{
    private function isDeveloper(): bool
    {
        return Auth::user()->role === 'developer';
    }

    /**
     * The settings "scope" of the signed-in actor.
     *   - developer -> null  (GLOBAL row: every farmer not managed by an area admin)
     *   - admin     -> own Province + City (their OWN row: mode, baseline, dry-run
     *                  gate and preview are independent from every other area).
     * Same area rule as AdminUserController@userLog and AdminFieldAnalyticsController.
     */
    private function scope(WeatherAlertService $svc): ?array
    {
        return $svc->scopeFor(Auth::user());
    }

    public function index(WeatherAlertService $svc)
    {
        $isDeveloper = $this->isDeveloper();

        try {
            $scope    = $this->scope($svc);
            $settings = $svc->settings($scope);
        } catch (\Throwable $e) {
            return view('admin.weather_alerts', [
                'setupError'  => $e instanceof \RuntimeException
                    ? $e->getMessage()
                    : 'The weather alert tables are missing or outdated. Run the migration first (php artisan migrate). ' . $e->getMessage(),
                'isDeveloper' => $isDeveloper,
            ]);
        }

        $actor = Auth::user();

        // The preview is already built for this scope only (see WeatherAlertService::buildPlan),
        // so no extra filtering is needed here.
        $preview   = $settings->last_preview ? json_decode($settings->last_preview, true) : null;
        $gate      = $svc->goLiveGate($scope);
        $changer   = $settings->changed_by ? User::find($settings->changed_by) : null;
        $changedBy = $changer ? ($changer->full_name ?? $changer->name) : null;

        $log = WeatherAlert::with('user')
            ->when(!$isDeveloper, fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('province_id', $actor->province_id)
                ->where('city_id', $actor->city_id)))
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $scopeLabel = $isDeveloper ? 'All cities' : (optional($actor->city)->name ?? 'Your city');

        return view('admin.weather_alerts', compact('settings', 'preview', 'gate', 'changedBy', 'log', 'isDeveloper', 'scopeLabel') + ['setupError' => null]);
    }

    /** "Run now (dry-run)": computes the plan and saves it as the preview. Sends nothing. */
    public function run(WeatherAlertService $svc)
    {
        @set_time_limit(90);
        try {
            $plan = $svc->runDry($this->scope($svc));
        } catch (\Throwable $e) {
            return back()->with('error', 'Dry-run failed: ' . $e->getMessage());
        }

        $s = $plan['summary'];

        $msg = "Dry-run finished: {$s['would_send']} alert(s) would be sent to {$s['farmers_to_email']} farmer(s).";
        if (!$plan['complete']) {
            return back()->with('error', $msg . ' Some sources could not be fully read, see the warnings below. "Go Live" stays locked until a complete dry-run.');
        }
        return back()->with('success', $msg);
    }

    /**
     * Switch mode: live (with baseline), dry_run (kill switch) or off.
     * Each actor switches ONLY their own scope: a normal admin controls the
     * farmers of their own Province + City; the developer controls the global
     * row (all farmers outside areas an admin manages).
     */
    public function setMode(Request $request, WeatherAlertService $svc)
    {
        $mode = $request->input('mode');
        if (!in_array($mode, ['live', 'dry_run', 'off'], true)) {
            return back()->with('error', 'Invalid mode.');
        }

        @set_time_limit(90);
        try {
            $scope = $this->scope($svc);

            if ($mode === 'live') {
                $result = $svc->goLive(Auth::id(), $scope);
                return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
            }
            $svc->setMode($mode, Auth::id(), $scope);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not change mode: ' . $e->getMessage());
        }

        return back()->with('success', $mode === 'dry_run'
            ? 'Back to dry-run. No emails will be sent.'
            : 'Weather alerts are switched off.');
    }

    /** "Send alerts now": emails this scope's new alerts immediately (live mode only). */
    public function sendNow(WeatherAlertService $svc)
    {
        @set_time_limit(90);
        try {
            $result = $svc->sendNow($this->scope($svc));
        } catch (\Throwable $e) {
            return back()->with('error', 'Send failed: ' . $e->getMessage());
        }
        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /** Sends one sample email to the signed-in admin only. */
    public function test(WeatherAlertService $svc)
    {
        $admin = Auth::user();
        if (empty($admin->email)) {
            return back()->with('error', 'Your account has no email address.');
        }

        try {
            $settings = $svc->settings($this->scope($svc));
        } catch (\Throwable $e) {
            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }
        $preview = $settings->last_preview ? json_decode($settings->last_preview, true) : null;
        $sample  = null;
        foreach (($preview['items'] ?? []) as $i) {
            if (($i['action'] ?? '') === 'send') { $sample = $i; break; }
        }
        $sample = $sample ?: [
            'source' => 'pagasa', 'label' => 'Official PAGASA Advisory', 'level' => 'Moderate',
            'headline' => 'General Flood Advisory (Moderate) - Region 18 (Negros Island Region)',
            'fields' => [['name' => 'Sample Farm']],
        ];
        // Preview items don't keep the long text; add a readable sample.
        $sample['body'] = $sample['body'] ?? "Under present weather conditions, the Intertropical Convergence Zone (ITCZ) is affecting Palawan, Visayas and Mindanao.\nThe 12-hour rainfall forecast is light to moderate rains and thunderstorms.\n\n- Negros Occidental - All rivers and its tributaries";
        $sample['instruction'] = $sample['instruction'] ?? 'People living near the mountain slopes and in the low lying areas of the above mentioned river systems are advised to take necessary precautionary measures.';

        try {
            $messageId = $svc->sendEmail($sample, $admin->email, true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }
        return back()->with('success', 'Brevo accepted the test email for ' . $admin->email
            . ' (sender: ' . env('BREVO_SENDER_EMAIL', 'jfconco604@gmail.com') . ($messageId ? ', id ' . $messageId : '') . '). '
            . 'If it does not arrive, check your Spam folder and Brevo > Transactional > Logs.');
    }
}