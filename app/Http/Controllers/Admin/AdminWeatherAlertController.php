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
     * Approved farmers this actor may see/notify.
     *   - developer -> every approved farmer
     *   - admin     -> only farmers in the admin's own Province + City
     *                  (same scoping as AdminUserController@userLog and
     *                  AdminFieldAnalyticsController).
     */
    private function scopedFarmers()
    {
        $actor = Auth::user();

        return User::where('role', 'farmer')
            ->where('status', 'approved')
            ->when(!$this->isDeveloper(), fn ($q) => $q->where('province_id', $actor->province_id)
                                                       ->where('city_id', $actor->city_id));
    }

    /**
     * The saved preview is computed for ALL farmers (the service and the cron
     * are global). A normal admin only gets the rows for farmers in their own
     * area, and the summary numbers are recomputed from those rows.
     */
    private function scopePreview(?array $preview): ?array
    {
        if (!$preview || $this->isDeveloper()) {
            return $preview;
        }

        $farmers = $this->scopedFarmers()->get(['id', 'email']);
        $ids     = $farmers->pluck('id')->map(fn ($v) => (int) $v)->all();
        $emails  = $farmers->pluck('email')->filter()->map(fn ($e) => strtolower($e))->all();

        $items = collect($preview['items'] ?? [])->filter(function ($it) use ($ids, $emails) {
            if (isset($it['user_id'])) {
                return in_array((int) $it['user_id'], $ids, true);
            }
            $email = strtolower((string) ($it['email'] ?? ''));
            return $email !== '' && in_array($email, $emails, true);
        })->values();

        $send = $items->where('action', 'send');

        $preview['items'] = $items->all();
        $preview['summary'] = array_merge($preview['summary'] ?? [], [
            'would_send'       => $send->count(),
            'farmers_to_email' => $send->map(fn ($it) => $it['user_id'] ?? strtolower((string) ($it['email'] ?? $it['user_name'] ?? '')))->unique()->count(),
            'farmers_total'    => count($ids),
        ]);

        // Dispatch stats of a live run are global, so hide them from admins.
        unset($preview['dispatch']);

        return $preview;
    }

    public function index(WeatherAlertService $svc)
    {
        try {
            $settings = $svc->settings();
        } catch (\Throwable $e) {
            return view('admin.weather_alerts', [
                'setupError'  => 'The weather alert tables are missing. Run the migration first (php artisan migrate). ' . $e->getMessage(),
                'isDeveloper' => $this->isDeveloper(),
            ]);
        }

        $actor       = Auth::user();
        $isDeveloper = $this->isDeveloper();

        $preview   = $settings->last_preview ? json_decode($settings->last_preview, true) : null;
        $preview   = $this->scopePreview($preview);
        $gate      = $svc->goLiveGate();
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
            $plan = $svc->runDry();
        } catch (\Throwable $e) {
            return back()->with('error', 'Dry-run failed: ' . $e->getMessage());
        }

        // Report only the numbers this actor is allowed to see.
        $scoped = $this->scopePreview(['items' => $plan['items'] ?? [], 'summary' => $plan['summary'] ?? []]);
        $s = $scoped['summary'] ?? $plan['summary'];

        $msg = "Dry-run finished: {$s['would_send']} alert(s) would be sent to {$s['farmers_to_email']} farmer(s).";
        if (!$plan['complete']) {
            return back()->with('error', $msg . ' Some sources could not be fully read, see the warnings below. "Go Live" stays locked until a complete dry-run.');
        }
        return back()->with('success', $msg);
    }

    /**
     * Switch mode: live (with baseline), dry_run (kill switch) or off.
     * The mode and baseline are GLOBAL (one settings row, one cron for every
     * farmer), so only the developer may change them. A normal admin changing
     * it would switch emailing on/off for farmers outside their area.
     */
    public function setMode(Request $request, WeatherAlertService $svc)
    {
        if (!$this->isDeveloper()) {
            abort(403, 'Only the developer can change the weather alert mode.');
        }

        $mode = $request->input('mode');
        if (!in_array($mode, ['live', 'dry_run', 'off'], true)) {
            return back()->with('error', 'Invalid mode.');
        }

        @set_time_limit(90);
        try {
            if ($mode === 'live') {
                $result = $svc->goLive(Auth::id());
                return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
            }
            $svc->setMode($mode, Auth::id());
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not change mode: ' . $e->getMessage());
        }

        return back()->with('success', $mode === 'dry_run'
            ? 'Back to dry-run. No emails will be sent.'
            : 'Weather alerts are switched off.');
    }

    /** Sends one sample email to the signed-in admin only. */
    public function test(WeatherAlertService $svc)
    {
        $admin = Auth::user();
        if (empty($admin->email)) {
            return back()->with('error', 'Your account has no email address.');
        }

        $settings = $svc->settings();
        $preview  = $settings->last_preview ? json_decode($settings->last_preview, true) : null;
        $preview  = $this->scopePreview($preview);
        $sample   = null;
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
            $svc->sendEmail($sample, $admin->email, true);
        } catch (\Throwable $e) {
            return back()->with('error', 'Test email failed: ' . $e->getMessage());
        }
        return back()->with('success', 'Test email sent to ' . $admin->email . '.');
    }
}