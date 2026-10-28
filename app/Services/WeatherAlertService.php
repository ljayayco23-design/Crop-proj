<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeatherAlert;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Two alert layers, one pipeline:
 *   1) PAGASA public CAP feed  -> "Official PAGASA Advisory"
 *   2) WeatherAPI 3-day forecast per farm pin -> "Forecast-based"
 *        a) disease risk (rain chance / humidity)
 *        b) storm risk   (strong wind / heavy rain amount)
 *
 * buildPlan() never sends or writes alert rows. It only computes what WOULD
 * happen. runCron() / goLive() decide what to do with that plan, based on the
 * mode stored in weather_alert_settings (off | dry_run | live).
 */
class WeatherAlertService
{
    // ---------- Tunables ----------
    public const FEED_URL              = 'https://publicalert.pagasa.dost.gov.ph/feeds/';
    public const FEED_MAX_AGE_HOURS    = 36;   // ignore feed entries older than this
    public const CAP_FETCH_LIMIT       = 40;   // max new CAP files downloaded per run
    public const MIN_SEVERITY_RANK     = 2;    // Minor=1 is skipped; Moderate=2 and above are sent (GFA / other)
    public const RAIN_HIGH             = 70;   // same "High risk" rule the Field Map page already uses
    public const HUMIDITY_HIGH         = 88;
    public const WIND_HIGH_KPH         = 40;   // storm risk: max wind of the day at or above this (km/h)
    public const HEAVY_RAIN_MM         = 50;   // storm risk: total rain of the day at or above this (mm)
    public const MAX_EMAILS_PER_RUN    = 40;   // the rest go out on the next cron tick
    public const GO_LIVE_DRYRUN_MAX_AGE_HOURS = 6;
    public const TIME_BUDGET_SECONDS   = 40;

    // Minimum hours between two emails of the same type to the same farmer.
    public const THROTTLE_HOURS = ['GFA' => 12, 'TC' => 12, 'OTHER' => 12, 'FORECAST' => 48, 'STORM' => 24];

    private float $startedAt = 0;

    // Cron batch state: the global row + every area row run in ONE request,
    // sharing one time budget and one PAGASA download.
    private bool $inBatch = false;
    private ?array $pagasaMemo = null;
    private array $pagasaMemoNotes = [];

    // =====================================================================
    //  SETTINGS  (one row per scope)
    //    scope = null                                  -> GLOBAL row (developer, all farmers
    //                                                     not managed by an area admin)
    //    scope = ['province_id' => X, 'city_id' => Y]  -> AREA row (a normal admin: only the
    //                                                     farmers of that province + city)
    // =====================================================================

    /** Scope for an actor: developer = null (global), admin = own province + city. */
    public function scopeFor(User $actor): ?array
    {
        if ($actor->role === 'developer') return null;
        if (!$actor->province_id || !$actor->city_id) {
            throw new \RuntimeException('Your account has no province/city set, so there is no area to send alerts for.');
        }
        return ['province_id' => (int) $actor->province_id, 'city_id' => (int) $actor->city_id];
    }

    private function scopeQuery(?array $scope)
    {
        $q = DB::table('weather_alert_settings');
        if ($scope) {
            return $q->where('province_id', $scope['province_id'])->where('city_id', $scope['city_id']);
        }
        return $q->whereNull('province_id')->whereNull('city_id');
    }

    public function settings(?array $scope = null): object
    {
        $row = $this->scopeQuery($scope)->orderBy('id')->first();
        if (!$row) {
            DB::table('weather_alert_settings')->insert([
                'mode'        => 'dry_run',
                'province_id' => $scope['province_id'] ?? null,
                'city_id'     => $scope['city_id'] ?? null,
                'created_at'  => now(), 'updated_at' => now(),
            ]);
            $row = $this->scopeQuery($scope)->orderBy('id')->first();
        }
        return $row;
    }

    private function saveSettings(array $data, ?array $scope = null): void
    {
        DB::table('weather_alert_settings')
            ->where('id', $this->settings($scope)->id)
            ->update($data + ['updated_at' => now()]);
    }

    public function setMode(string $mode, ?int $userId, ?array $scope = null): void
    {
        $this->saveSettings([
            'mode' => $mode, 'changed_by' => $userId, 'changed_at' => now(),
        ], $scope);
    }

    /** "Go Live" is only allowed after a complete, recent dry-run. */
    public function goLiveGate(?array $scope = null): array
    {
        $s = $this->settings($scope);
        if (!$s->last_dry_run_ok_at) {
            return ['ok' => false, 'reason' => 'Run a dry-run first.'];
        }
        $age = Carbon::parse($s->last_dry_run_ok_at)->diffInHours(now());
        if ($age >= self::GO_LIVE_DRYRUN_MAX_AGE_HOURS) {
            return ['ok' => false, 'reason' => 'Last successful dry-run is older than ' . self::GO_LIVE_DRYRUN_MAX_AGE_HOURS . ' hours. Run it again.'];
        }
        return ['ok' => true, 'reason' => ''];
    }

    // =====================================================================
    //  ENTRY POINTS
    // =====================================================================

    /**
     * Called by the cron route. Runs the GLOBAL row first, then every AREA row
     * that a normal admin has switched to live (dry-run area rows are only
     * refreshed when that admin presses "Run now", to keep the cron fast).
     */
    public function runCron(): array
    {
        $this->inBatch = true;
        $this->pagasaMemo = null;
        $this->pagasaMemoNotes = [];
        $this->startedAt = microtime(true);

        try {
            $result = $this->runCronFor(null);

            $areas = DB::table('weather_alert_settings')
                ->whereNotNull('province_id')->whereNotNull('city_id')
                ->whereNotNull('changed_by')
                ->where('mode', 'live')
                ->get(['province_id', 'city_id']);

            $areaResults = [];
            foreach ($areas as $a) {
                $scope = ['province_id' => (int) $a->province_id, 'city_id' => (int) $a->city_id];
                try {
                    $areaResults[] = $scope + $this->runCronFor($scope);
                } catch (\Throwable $e) {
                    Log::error('[WeatherCron] area ' . $a->province_id . '/' . $a->city_id . ': ' . $e->getMessage());
                    $areaResults[] = $scope + ['status' => 'error', 'message' => $e->getMessage()];
                }
            }
            return $result + ['areas' => $areaResults];
        } finally {
            $this->inBatch = false;
            $this->pagasaMemo = null;
        }
    }

    private function runCronFor(?array $scope): array
    {
        $s = $this->settings($scope);

        if ($s->mode === 'off') {
            return ['status' => 'off', 'mode' => 'off', 'message' => 'Weather alerts are switched off.'];
        }

        $plan = $this->buildPlan($scope);

        if ($s->mode === 'live') {
            $result = $this->dispatchPlan($plan);
            $this->savePreview($plan, 'live', $result, $scope);
            $this->saveSettings([
                'last_run_at' => now(), 'last_run_mode' => 'live',
                'last_error'  => $plan['errors'] ? implode(' | ', $plan['errors']) : null,
            ], $scope);
            return ['status' => 'success', 'mode' => 'live', 'summary' => $plan['summary'], 'dispatch' => $result, 'errors' => $plan['errors']];
        }

        // dry_run
        $this->savePreview($plan, 'dry_run', [], $scope);
        $this->saveSettings([
            'last_run_at' => now(), 'last_run_mode' => 'dry_run',
            'last_dry_run_ok_at' => $plan['complete'] ? now() : $s->last_dry_run_ok_at,
            'last_error' => $plan['errors'] ? implode(' | ', $plan['errors']) : null,
        ], $scope);
        return ['status' => 'success', 'mode' => 'dry_run', 'summary' => $plan['summary'], 'errors' => $plan['errors']];
    }

    /** Admin "Run now (dry-run)". Never sends, whatever the current mode is. */
    public function runDry(?array $scope = null): array
    {
        $plan = $this->buildPlan($scope);
        $this->savePreview($plan, 'dry_run', [], $scope);
        $s = $this->settings($scope);
        $this->saveSettings([
            'last_run_at' => now(), 'last_run_mode' => 'dry_run',
            'last_dry_run_ok_at' => $plan['complete'] ? now() : $s->last_dry_run_ok_at,
            'last_error' => $plan['errors'] ? implode(' | ', $plan['errors']) : null,
        ], $scope);
        return $plan;
    }

    /**
     * Admin "Go Live": switches this scope to LIVE and immediately emails every
     * alert that is active right now to the farmers of the scope. Every alert is
     * also saved, so it shows on the farmer's "Recent Alerts" card.
     * Only allowed after a complete, recent dry-run (see goLiveGate()).
     */
    public function goLive(?int $userId, ?array $scope = null): array
    {
        $gate = $this->goLiveGate($scope);
        if (!$gate['ok']) {
            return ['ok' => false, 'message' => $gate['reason']];
        }

        // Check every source can be read BEFORE changing anything.
        $plan = $this->buildPlan($scope);
        if (!$plan['complete']) {
            $this->savePreview($plan, 'dry_run', [], $scope);
            return ['ok' => false, 'message' => 'Could not read every source just now (' . implode(' | ', array_merge($plan['errors'], $plan['notes'])) . '). Nothing was changed. Try again.'];
        }

        // Alerts saved as "baselined" by an earlier Go Live were never emailed.
        // Remove them so they count as new again and are emailed now.
        $ids = $this->farmerQuery($scope)->pluck('id');
        WeatherAlert::where('status', 'baselined')->whereIn('user_id', $ids)->delete();
        $plan = $this->buildPlan($scope);

        $this->saveSettings([
            'mode' => 'live', 'live_since' => now(),
            'changed_by' => $userId, 'changed_at' => now(),
            'last_run_at' => now(), 'last_run_mode' => 'live',
            'last_error' => $plan['errors'] ? implode(' | ', $plan['errors']) : null,
        ], $scope);

        $result = $this->dispatchPlan($plan);
        $this->savePreview($plan, 'live', $result, $scope);

        $msg = "Live. Sent {$result['sent']} email(s), failed {$result['failed']}, no email on file (card only) {$result['no_email']}";
        if ($result['deferred_to_next_run'] > 0) {
            $msg .= ", {$result['deferred_to_next_run']} waiting for the next cron run (max " . self::MAX_EMAILS_PER_RUN . ' emails per run)';
        }
        $msg .= '.';
        if ($result['sent'] === 0 && $result['failed'] === 0 && $result['no_email'] === 0) {
            $msg .= ' No alert is active for the farmers in your area right now; new alerts will be emailed automatically.';
        }
        return ['ok' => true, 'message' => $msg];
    }

    /**
     * "Send alerts now" (live mode only): does the cron's job by hand for this
     * scope. Emails every NEW alert (not already recorded, not throttled) to the
     * farmers of the scope and saves it for their "Recent Alerts" card.
     */
    public function sendNow(?array $scope = null): array
    {
        $s = $this->settings($scope);
        if ($s->mode !== 'live') {
            return ['ok' => false, 'message' => 'Switch to Live first.'];
        }

        $plan = $this->buildPlan($scope);
        if (!$plan['complete']) {
            $this->savePreview($plan, 'live', [], $scope);
            return ['ok' => false, 'message' => 'Could not read every source just now (' . implode(' | ', array_merge($plan['errors'], $plan['notes'])) . '). Nothing was sent. Try again.'];
        }

        $result = $this->dispatchPlan($plan);
        $this->savePreview($plan, 'live', $result, $scope);
        $this->saveSettings([
            'last_run_at' => now(), 'last_run_mode' => 'live',
            'last_error'  => $plan['errors'] ? implode(' | ', $plan['errors']) : null,
        ], $scope);

        $msg = "Sent {$result['sent']} email(s), failed {$result['failed']}, no email on file (card only) {$result['no_email']}";
        if ($result['deferred_to_next_run'] > 0) {
            $msg .= ", {$result['deferred_to_next_run']} waiting for the next run (max " . self::MAX_EMAILS_PER_RUN . ' emails per run)';
        }
        $msg .= '.';
        if ($result['sent'] === 0 && $result['failed'] === 0 && $result['no_email'] === 0) {
            $msg .= ' Nothing new to send: every active alert was already sent, or none is active for your farmers.';
        }
        return ['ok' => true, 'message' => $msg];
    }

    /** Approved farmers for a scope (area for a normal admin, the rest for the developer). */
    private function farmerQuery(?array $scope = null)
    {
        $q = User::where('role', 'farmer')->where('status', 'approved');

        if ($scope) {
            return $q->where('province_id', $scope['province_id'])
                     ->where('city_id', $scope['city_id']);
        }

        // Global row (developer): skip areas a normal admin manages on their own.
        $managed = DB::table('weather_alert_settings')
            ->whereNotNull('province_id')->whereNotNull('city_id')
            ->whereNotNull('changed_by')
            ->get(['province_id', 'city_id']);
        if ($managed->isNotEmpty()) {
            $q->where(function ($w) use ($managed) {
                foreach ($managed as $m) {
                    $w->whereRaw('NOT (province_id <=> ? AND city_id <=> ?)', [$m->province_id, $m->city_id]);
                }
            });
        }
        return $q;
    }

    // =====================================================================
    //  PLAN BUILDER  (read-only: no emails, no alert rows)
    // =====================================================================

    public function buildPlan(?array $scope = null): array
    {
        if (!$this->inBatch) {
            $this->startedAt = microtime(true);
        }
        $errors = [];
        $notes  = [];

        $farmers = $this->farmerQuery($scope)->with('province')->get();

        $targetsByUser = [];
        $withoutPin = 0;
        foreach ($farmers as $u) {
            $targetsByUser[$u->id] = $this->targetsFor($u);
            if (!collect($targetsByUser[$u->id])->contains(fn ($t) => $t['lat'] !== null)) {
                $withoutPin++;
            }
        }

        // ---------------- Layer 1: PAGASA ----------------
        $alerts = [];
        try {
            if ($this->inBatch && $this->pagasaMemo !== null) {
                $alerts = $this->pagasaMemo;
                $notes  = array_merge($notes, $this->pagasaMemoNotes);
            } else {
                $alertNotes = [];
                $alerts = $this->loadPagasaAlerts($alertNotes);
                $notes  = array_merge($notes, $alertNotes);
                if ($this->inBatch) {
                    $this->pagasaMemo = $alerts;
                    $this->pagasaMemoNotes = $alertNotes;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'PAGASA feed: ' . $e->getMessage();
            Log::warning('[WeatherAlert] PAGASA load failed: ' . $e->getMessage());
        }

        $candidates = [];
        foreach ($farmers as $u) {
            $provName = optional($u->province)->name;
            foreach ($alerts as $a) {
                $hits = [];
                foreach ($targetsByUser[$u->id] as $t) {
                    $m = $this->matchTarget($t, $a, $provName);
                    if ($m) {
                        $hits[] = ['key' => $t['key'], 'name' => $t['name'], 'method' => $m['method'], 'area' => $m['area']];
                    }
                }
                if ($hits) {
                    $candidates[] = $this->pagasaItem($u, $a, $hits);
                }
            }
        }

        // Same farmer + same event (e.g. Signal #1 and #2 of one typhoon): keep only the strongest/newest.
        usort($candidates, fn ($x, $y) => [$y['rank'], $y['issued_at']] <=> [$x['rank'], $x['issued_at']]);
        $seen = [];
        foreach ($candidates as &$c) {
            $k = $c['user_id'] . '|' . $c['compete_key'];
            if (isset($seen[$k])) {
                $c['action'] = 'skip';
                $c['reason'] = 'Superseded by a stronger or newer alert of the same event for this farmer';
            } else {
                $seen[$k] = true;
                $c['action'] = 'send';
                $c['reason'] = '';
            }
        }
        unset($c);

        // ---------------- Layer 2: Forecast ----------------
        $forecastItems = [];
        $pinsChecked = 0;
        $forecastOk = true;
        try {
            [$forecastItems, $pinsChecked, $forecastOk] = $this->buildForecastItems($farmers, $targetsByUser, $notes);
        } catch (\Throwable $e) {
            $errors[] = 'Forecast: ' . $e->getMessage();
            $forecastOk = false;
            Log::warning('[WeatherAlert] Forecast layer failed: ' . $e->getMessage());
        }

        $items = array_merge($candidates, $forecastItems);

        // ---------------- De-dupe + throttle ----------------
        $existing = [];
        foreach (WeatherAlert::where('created_at', '>', now()->subDays(14))->get(['user_id', 'source', 'dedupe_key']) as $r) {
            $existing[$r->user_id . '|' . $r->source . '|' . $r->dedupe_key] = true;
        }
        $lastSent = [];
        foreach (WeatherAlert::where('status', 'sent')->where('sent_at', '>', now()->subHours(72))->get(['user_id', 'throttle_group', 'sent_at']) as $r) {
            $k = $r->user_id . '|' . $r->throttle_group;
            if (!isset($lastSent[$k]) || $r->sent_at->gt($lastSent[$k])) $lastSent[$k] = $r->sent_at;
        }

        foreach ($items as &$it) {
            if ($it['action'] !== 'send') continue;

            if (isset($existing[$it['user_id'] . '|' . $it['source'] . '|' . $it['dedupe_key']])) {
                $it['action'] = 'skip';
                $it['reason'] = 'Already recorded earlier (emailed or baselined)';
                continue;
            }
            $tk = $it['user_id'] . '|' . $it['throttle_group'];
            if (isset($lastSent[$tk]) && $lastSent[$tk]->gt(now()->subHours($it['throttle_hours']))) {
                $it['action'] = 'skip';
                $it['reason'] = "Throttled: same alert type already emailed within {$it['throttle_hours']}h";
                continue;
            }
            if (empty($it['email'])) {
                $it['reason'] = 'No email on file: in-app card only';
            }
        }
        unset($it);

        // ---------------- Summary ----------------
        $send = array_filter($items, fn ($i) => $i['action'] === 'send');
        $emailUsers = [];
        foreach ($send as $i) {
            if (!empty($i['email'])) $emailUsers[$i['user_id']] = true;
        }
        $summary = [
            'farmers_total'       => $farmers->count(),
            'farmers_without_pin' => $withoutPin,
            'pagasa_active'       => count($alerts),
            'pagasa_matched'      => count(array_unique(array_column($candidates, 'dedupe_key'))),
            'forecast_pins'       => $pinsChecked,
            'items'               => count($items),
            'would_send'          => count($send),
            'farmers_to_email'    => count($emailUsers),
            'skipped'             => count($items) - count($send),
        ];

        // A plan is "complete" only if every source was read fully (notes starting with "!" flag gaps).
        $gaps = array_filter($notes, fn ($n) => str_starts_with($n, '!'));
        $complete = empty($errors) && $forecastOk && empty($gaps);

        return [
            'generated_at' => now()->toIso8601String(),
            'items'        => array_values($items),
            'summary'      => $summary,
            'errors'       => $errors,
            'notes'        => $notes,
            'complete'     => $complete,
        ];
    }

    // =====================================================================
    //  DISPATCH  (live mode only)
    // =====================================================================

    public function dispatchPlan(array $plan): array
    {
        $sent = $failed = $noEmail = $capped = 0;

        foreach ($plan['items'] as $it) {
            if ($it['action'] !== 'send') continue;

            if (empty($it['email'])) {
                if ($this->createRow($it, 'no_email', 'No email on file')) $noEmail++;
                continue;
            }
            if (($sent + $failed) >= self::MAX_EMAILS_PER_RUN) { $capped++; continue; }

            $row = $this->createRow($it, 'pending');
            if (!$row) continue; // another run already took it

            try {
                $this->sendEmail($it, $it['email']);
                $row->update(['status' => 'sent', 'sent_at' => now()]);
                $sent++;
            } catch (\Throwable $e) {
                $row->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 480)]);
                Log::error('[WeatherAlert] mail failed for user ' . $it['user_id'] . ': ' . $e->getMessage());
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'no_email' => $noEmail, 'deferred_to_next_run' => $capped];
    }

    /**
     * Sends one alert email through Brevo's HTTP API (same method and
     * BREVO_API_KEY the registration email already uses; no SMTP needed).
     * Throws on failure so the caller can mark the row as failed.
     */
    public function sendEmail(array $alert, string $to, bool $isTest = false): ?string
    {
        $apiKey = env('BREVO_API_KEY');
        if (!$apiKey) {
            throw new \RuntimeException('BREVO_API_KEY is not set.');
        }

        $html = view('emails.weather_alert', ['alert' => $alert, 'isTest' => $isTest])->render();
        $sender = env('BREVO_SENDER_EMAIL', 'jfconco604@gmail.com'); // must be a verified Brevo sender

        $res = Http::timeout(15)->withHeaders([
            'accept' => 'application/json',
            'api-key' => $apiKey,
        ])->post('https://api.brevo.com/v3/smtp/email', [
            'sender'      => ['name' => 'RiceGuard AI Alerts', 'email' => $sender],
            'to'          => [['email' => $to]],
            'subject'     => ($isTest ? '[TEST] ' : '') . 'RiceGuard AI - ' . ($alert['label'] ?? 'Weather Alert') . ': ' . ($alert['headline'] ?? ''),
            'htmlContent' => $html,
        ]);

        if ($res->failed()) {
            throw new \RuntimeException('Brevo HTTP ' . $res->status() . ': ' . Str::limit($res->body(), 300));
        }

        // Brevo's id for this message: lets you find it in Brevo > Transactional > Logs.
        return $res->json('messageId');
    }

    /** Insert one alert row. Returns null if it already exists (unique key). */
    private function createRow(array $it, string $status, ?string $reason = null): ?WeatherAlert
    {
        try {
            return WeatherAlert::create([
                'user_id'        => $it['user_id'],
                'source'         => $it['source'],
                'dedupe_key'     => $it['dedupe_key'],
                'throttle_group' => $it['throttle_group'],
                'label'          => $it['label'],
                'level'          => $it['level'],
                'event'          => $it['event'],
                'headline'       => Str::limit((string) $it['headline'], 250, ''),
                'body'           => $it['body'],
                'instruction'    => $it['instruction'],
                'fields'         => $it['fields'],
                'status'         => $status,
                'status_reason'  => $reason,
                'issued_at'      => $this->parseTime($it['issued_at']),
                'expires_at'     => $this->parseTime($it['expires_at']),
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') return null; // duplicate
            throw $e;
        }
    }

    // =====================================================================
    //  PREVIEW STORAGE
    // =====================================================================

    private function savePreview(array $plan, string $mode, array $dispatch = [], ?array $scope = null): void
    {
        $items = array_map(function ($i) {
            unset($i['body'], $i['instruction'], $i['compete_key'], $i['rank']);
            return $i;
        }, array_slice($plan['items'], 0, 400));

        $json = json_encode([
            'generated_at' => $plan['generated_at'],
            'mode'         => $mode,
            'summary'      => $plan['summary'],
            'errors'       => $plan['errors'],
            'notes'        => $plan['notes'],
            'complete'     => $plan['complete'],
            'dispatch'     => $dispatch,
            'items'        => $items,
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        $this->saveSettings(['last_preview' => $json], $scope);
    }

    // =====================================================================
    //  FARM TARGETS + MATCHING
    // =====================================================================

    /** Main pin + every additional pin. Region numbers are never used (Negros is Region 18 now). */
    private function targetsFor(User $u): array
    {
        $targets = [];
        $hasMain = is_numeric($u->latitude) && is_numeric($u->longitude);
        $targets[] = [
            'key' => 'main', 'name' => $u->farm_name ?: 'Main Farm',
            'lat' => $hasMain ? (float) $u->latitude : null,
            'lng' => $hasMain ? (float) $u->longitude : null,
            'allow_province' => true, // registered address is a valid fallback for the main farm only
        ];

        $extra = $u->additional_farms;
        if (is_string($extra)) $extra = json_decode($extra, true);
        if (!is_array($extra)) $extra = [];

        foreach ($extra as $i => $farm) {
            $coords = $farm['coords'] ?? null; // GeoJSON order: [lng, lat]
            if (!is_array($coords) || count($coords) < 2 || !is_numeric($coords[0]) || !is_numeric($coords[1])) continue;
            $targets[] = [
                'key' => $farm['id'] ?? ('extra_' . $i),
                'name' => $farm['options']['farmName'] ?? ('Additional Field ' . ($i + 1)),
                'lat' => (float) $coords[1], 'lng' => (float) $coords[0],
                'allow_province' => false,
            ];
        }
        return $targets;
    }

    private function matchTarget(array $t, array $alert, ?string $province): ?array
    {
        if ($t['lat'] !== null) {
            foreach ($alert['areas'] as $area) {
                foreach ($area['polygons'] as $poly) {
                    if ($this->inPolygon($t['lat'], $t['lng'], $poly)) {
                        return ['method' => 'polygon', 'area' => $area['name']];
                    }
                }
            }
        }
        if ($t['allow_province'] && $province) {
            foreach ($alert['areas'] as $area) {
                if ($this->nameMatches($province, $area['name'])) {
                    return ['method' => 'province', 'area' => $area['name']];
                }
            }
        }
        return null;
    }

    /** Ray casting. Polygon points are [lat, lng]. */
    private function inPolygon(float $lat, float $lng, array $poly): bool
    {
        $inside = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$yi, $xi] = $poly[$i];
            [$yj, $xj] = $poly[$j];
            if ((($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi)) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    private function norm(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/\b(province|city|municipality) of\b/u', ' ', $s);
        $s = preg_replace('/[^a-z0-9ñ]+/u', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /**
     * Whole-phrase province match. "Samar" must not match "Northern Samar",
     * "Leyte" must not match "Southern Leyte", but "Northern portion of
     * Negros Occidental" must match "Negros Occidental".
     */
    private function nameMatches(string $needle, string $hay): bool
    {
        $n = $this->norm($needle);
        $h = $this->norm($hay);
        if ($n === '' || $h === '') return false;
        if ($n === $h) return true;

        $dirs = ['northern', 'southern', 'eastern', 'western', 'north', 'south', 'east', 'west'];
        $needleWords = explode(' ', $n);
        $padded = ' ' . $h . ' ';
        $offset = 0;

        while (($pos = strpos($padded, ' ' . $n . ' ', $offset)) !== false) {
            $before = trim(substr($padded, 0, $pos));
            $prev = '';
            if ($before !== '') {
                $sp = strrpos($before, ' ');
                $prev = $sp === false ? $before : substr($before, $sp + 1);
            }
            if (!in_array($prev, $dirs, true) || in_array($needleWords[0], $dirs, true)) {
                return true;
            }
            $offset = $pos + 1;
        }
        return false;
    }

    // =====================================================================
    //  PAGASA FEED + CAP
    // =====================================================================

    /** Returns active, non-superseded alerts, normalised and ready to match. */
    private function loadPagasaAlerts(array &$notes): array
    {
        $res = Http::timeout(15)->get(self::FEED_URL);
        if ($res->failed()) {
            throw new \RuntimeException('HTTP ' . $res->status());
        }
        $xml = $this->loadXml($res->body());
        if (!$xml) {
            throw new \RuntimeException('feed is not valid XML');
        }

        $cutoff = now()->subHours(self::FEED_MAX_AGE_HOURS);
        $entries = [];
        foreach ($xml->entry as $e) {
            $href = '';
            foreach ($e->link as $l) {
                $h = (string) $l['href'];
                if ($h === '') continue;
                $href = $h;
                if (stripos((string) $l['type'], 'cap') !== false) break;
            }
            if ($href === '') continue;

            $updated = $this->parseTime((string) $e->updated);
            if (!$updated || $updated->lt($cutoff)) continue;

            $identifier = basename((string) parse_url($href, PHP_URL_PATH), '.cap');
            if ($identifier === '') continue;

            $entries[$identifier] = ['identifier' => $identifier, 'href' => $href, 'title' => trim((string) $e->title), 'updated' => $updated->toIso8601String()];
        }

        // Cache lookup, then download only what's new.
        $cached = DB::table('weather_alert_cap_cache')->whereIn('identifier', array_keys($entries))->pluck('payload', 'identifier');
        $caps = [];
        foreach ($cached as $id => $payload) {
            $arr = json_decode($payload, true);
            if (is_array($arr)) $caps[$id] = $arr;
        }

        $missing = array_values(array_diff(array_keys($entries), array_keys($caps)));
        if (count($missing) > self::CAP_FETCH_LIMIT) {
            $notes[] = '!' . (count($missing) - self::CAP_FETCH_LIMIT) . ' CAP file(s) not downloaded yet (limit per run); they will be read on the next run.';
            $missing = array_slice($missing, 0, self::CAP_FETCH_LIMIT);
        }

        foreach (array_chunk($missing, 10) as $chunk) {
            if ($this->overBudget()) {
                $notes[] = '!Time budget reached while downloading CAP files; the rest will be read on the next run.';
                break;
            }
            $responses = Http::pool(function (Pool $pool) use ($chunk, $entries) {
                foreach ($chunk as $id) {
                    $pool->as($id)->timeout(10)->get($entries[$id]['href']);
                }
            });
            foreach ($chunk as $id) {
                $r = $responses[$id] ?? null;
                if (!($r instanceof \Illuminate\Http\Client\Response) || $r->failed()) {
                    $notes[] = '!Could not download CAP ' . $id;
                    continue;
                }
                $cap = $this->parseCap($r->body(), $entries[$id]);
                if (!$cap) {
                    $notes[] = '!Could not parse CAP ' . $id;
                    continue;
                }
                $caps[$id] = $cap;
                DB::table('weather_alert_cap_cache')->updateOrInsert(
                    ['identifier' => $id],
                    ['payload' => json_encode($cap, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'fetched_at' => now()]
                );
            }
        }

        // Housekeeping
        DB::table('weather_alert_cap_cache')->where('fetched_at', '<', now()->subDays(7))->delete();

        // Anything referenced by a newer message is superseded (GFA #2 replaced by #3, cancellations, ...).
        $superseded = [];
        foreach ($caps as $c) {
            foreach (($c['references'] ?? []) as $ref) $superseded[$ref] = true;
        }

        $active = [];
        foreach ($caps as $id => $c) {
            if (isset($superseded[$id])) continue;
            if (($c['status'] ?? '') !== 'Actual') continue;
            if (strcasecmp($c['msgType'] ?? '', 'Cancel') === 0) continue;
            if (stripos($c['title'] ?? '', 'cancellation') === 0) continue;

            $sent = $this->parseTime($c['sent'] ?? '') ?: $this->parseTime($c['updated'] ?? '') ?: now();
            $expires = $this->parseTime($c['expires'] ?? '') ?: $sent->copy()->addHours(18);
            if ($expires->lte(now())) continue;

            $d = $this->deriveAlert($c, $sent, $expires);
            if ($d) $active[] = $d;
        }
        return $active;
    }

    private function parseCap(string $body, array $entry): ?array
    {
        $xml = $this->loadXml($body);
        if (!$xml || !isset($xml->info[0])) return null;
        $info = $xml->info[0];

        $areas = [];
        foreach ($info->area as $a) {
            $polys = [];
            foreach ($a->polygon as $p) {
                $pts = [];
                foreach (preg_split('/\s+/', trim((string) $p)) as $pair) {
                    $xy = explode(',', $pair);
                    if (count($xy) === 2 && is_numeric($xy[0]) && is_numeric($xy[1])) {
                        $pts[] = [(float) $xy[0], (float) $xy[1]]; // CAP order: lat,lng
                    }
                }
                if (count($pts) >= 3) $polys[] = $pts;
            }
            $areas[] = ['name' => trim((string) $a->areaDesc), 'polygons' => $polys];
        }

        $region = '';
        foreach ($info->parameter as $p) {
            if (stripos((string) $p->valueName, 'Region') !== false) { $region = trim((string) $p->value); break; }
        }

        $refs = [];
        foreach (preg_split('/\s+/', trim((string) $xml->references)) as $ref) {
            $parts = explode(',', $ref);          // "sender,identifier,sent"
            if (count($parts) >= 2 && $parts[1] !== '') $refs[] = $parts[1];
        }

        return [
            'identifier'  => $entry['identifier'],
            'title'       => $entry['title'],
            'updated'     => $entry['updated'],
            'status'      => trim((string) $xml->status),
            'msgType'     => trim((string) $xml->msgType),
            'sent'        => trim((string) $xml->sent),
            'references'  => $refs,
            'event'       => trim((string) $info->event),
            'headline'    => trim((string) $info->headline),
            'severity'    => trim((string) $info->severity),
            'expires'     => trim((string) $info->expires),
            'description' => $this->cleanText((string) $info->description),
            'instruction' => $this->cleanText((string) $info->instruction),
            'region'      => $region,
            'areas'       => $areas,
        ];
    }

    /** Classifies a CAP message and applies the "should this ever be emailed?" severity rules. */
    private function deriveAlert(array $c, Carbon $sent, Carbon $expires): ?array
    {
        $title = $c['title'] ?? '';
        $hay = $title . ' ' . ($c['event'] ?? '');
        $sevRank = ['minor' => 1, 'moderate' => 2, 'severe' => 3, 'extreme' => 4][strtolower($c['severity'] ?? '')] ?? 0;

        if (preg_match('/tropical cyclone|typhoon|signal\s*#/i', $hay)) {
            $type = 'TC';
            $signal = preg_match('/signal\s*#?\s*(\d)/i', $hay, $m) ? (int) $m[1] : 0;

            $name = preg_match('/:\s*(.+?)(?:\s+signal\b.*)?$/i', $title, $m2) ? trim($m2[1]) : $title;
            $core = preg_replace('/^(super\s+)?(typhoon|severe tropical storm|tropical storm|tropical depression)\s+/i', '', $name);
            $coreKey = mb_strtolower(trim($core));

            $level = $signal > 0 ? "Signal #{$signal}" : 'Tropical Cyclone Alert';
            $headline = $title;
            $rank = $signal;
            $group = 'TC:' . $coreKey . ':S' . $signal;
            $compete = 'TC:' . $coreKey;
        } elseif (preg_match('/flood advisory|\bGFA\b/i', $hay)) {
            if ($sevRank < self::MIN_SEVERITY_RANK) return null; // skip Minor GFA
            $type = 'GFA';
            $level = ucfirst(strtolower($c['severity'] ?: 'Moderate'));
            $headline = ($c['event'] ?: 'General Flood Advisory') . ($c['region'] ? ' - ' . $c['region'] : '');
            $rank = $sevRank;
            $group = 'GFA:' . mb_strtolower($c['region'] ?: 'all');
            $compete = $group;
        } else {
            if ($sevRank < self::MIN_SEVERITY_RANK) return null;
            $type = 'OTHER';
            $level = ucfirst(strtolower($c['severity']));
            $headline = $c['event'] ?: ($c['headline'] ?: $title);
            $rank = $sevRank;
            $group = 'OTHER:' . mb_strtolower($c['event'] ?: $title);
            $compete = $group;
        }

        return [
            'identifier'  => $c['identifier'],
            'type'        => $type,
            'level'       => $level,
            'rank'        => $rank,
            'group'       => $group,
            'compete'     => $compete,
            'event'       => $c['event'],
            'headline'    => $headline,
            'description' => $c['description'],
            'instruction' => $c['instruction'],
            'sent'        => $sent->toIso8601String(),
            'expires'     => $expires->toIso8601String(),
            'areas'       => $c['areas'],
        ];
    }

    private function pagasaItem(User $u, array $a, array $hits): array
    {
        return [
            'user_id'        => $u->id,
            'user_name'      => $u->full_name ?? $u->name ?? ('Farmer #' . $u->id),
            'email'          => $u->email,
            'source'         => 'pagasa',
            'label'          => 'Official PAGASA Advisory',
            'type'           => $a['type'],
            'level'          => $a['level'],
            'rank'           => $a['rank'],
            'event'          => $a['event'],
            'headline'       => $a['headline'],
            'body'           => $a['description'],
            'instruction'    => $a['instruction'],
            'issued_at'      => $a['sent'],
            'expires_at'     => $a['expires'],
            'dedupe_key'     => $a['identifier'],
            'throttle_group' => $a['group'],
            'throttle_hours' => self::THROTTLE_HOURS[$a['type']] ?? 12,
            'compete_key'    => $a['compete'],
            'fields'         => $hits,
            'action'         => 'send',
            'reason'         => '',
        ];
    }

    // =====================================================================
    //  FORECAST LAYER (WeatherAPI, 3 days, per farm pin)
    // =====================================================================

    private function buildForecastItems($farmers, array $targetsByUser, array &$notes): array
    {
        $coordKeys = [];
        foreach ($targetsByUser as $targets) {
            foreach ($targets as $t) {
                if ($t['lat'] !== null) $coordKeys[$this->coordKey($t['lat'], $t['lng'])] = true;
            }
        }
        $coordKeys = array_keys($coordKeys);
        if (!$coordKeys) return [[], 0, true];

        [$forecasts, $ok] = $this->fetchForecasts($coordKeys, $notes);

        $items = [];
        foreach ($farmers as $u) {
            // Two independent alerts per farmer: disease risk and storm risk.
            foreach (['disease', 'storm'] as $kind) {
                $item = $this->forecastItemFor($u, $targetsByUser[$u->id], $forecasts, $kind);
                if ($item) $items[] = $item;
            }
        }

        return [$items, count($coordKeys), $ok];
    }

    /**
     * Builds ONE forecast alert for one farmer.
     *   $kind = 'disease' -> rain chance / humidity (rice disease risk)
     *   $kind = 'storm'   -> strong wind / heavy rain amount
     * Returns null when none of the farmer's pins has a risky day in the next 3 days.
     */
    private function forecastItemFor(User $u, array $targets, array $forecasts, string $kind): ?array
    {
        $flag = $kind === 'storm' ? 'storm' : 'high';

        $fields = [];
        $lines  = [];
        $dates  = [];
        foreach ($targets as $t) {
            if ($t['lat'] === null) continue;
            $days = $forecasts[$this->coordKey($t['lat'], $t['lng'])] ?? null;
            if (!$days) continue;
            $risky = array_values(array_filter($days, fn ($d) => $d[$flag]));
            if (!$risky) continue;

            $fields[] = ['key' => $t['key'], 'name' => $t['name'], 'method' => 'forecast', 'area' => implode(', ', array_column($risky, 'label'))];
            foreach ($risky as $d) {
                $dates[$d['date']] = $d['label'];
                $lines[] = $kind === 'storm'
                    ? $t['name'] . ' - ' . $d['label'] . ': up to ' . $d['wind'] . ' km/h wind, ' . $d['precip'] . ' mm of rain (' . $d['condition'] . ')'
                    : $t['name'] . ' - ' . $d['label'] . ': ' . $d['rain'] . '% chance of rain, ' . $d['humidity'] . '% humidity (' . $d['condition'] . ')';
            }
        }
        if (!$fields) return null;

        ksort($dates);
        $firstDate = array_key_first($dates);
        $lastDate  = array_key_last($dates);
        $labels = array_values($dates);
        $span = count($labels) > 1 ? $labels[0] . ' to ' . end($labels) : $labels[0];

        if ($kind === 'storm') {
            $label = 'Forecast-based (Storm risk)'; $type = 'STORM'; $event = 'Strong wind / heavy rain forecast';
            $headline = 'Strong wind or heavy rain expected (' . $span . ')';
            $instruction = 'Strong wind can cause lodging in tall rice, and heavy rain can flood fields. Clear drainage canals, secure farm structures and equipment, and avoid spraying or fertilizing right before the rain. Follow official PAGASA announcements.';
            $dedupe = 'storm:' . $firstDate;
        } else {
            $label = 'Forecast-based'; $type = 'FORECAST'; $event = 'High-risk weather forecast';
            $headline = 'High disease-risk weather ahead (' . $span . ')';
            $instruction = 'Heavy rain and high humidity favor Sheath Blight, Rice Blast and Brown Spot. Check field drainage and inspect your crop for early symptoms.';
            $dedupe = 'fc:' . $firstDate;
        }

        return [
            'user_id'        => $u->id,
            'user_name'      => $u->full_name ?? $u->name ?? ('Farmer #' . $u->id),
            'email'          => $u->email,
            'source'         => 'forecast',
            'label'          => $label,
            'type'           => $type,
            'level'          => 'High',
            'rank'           => 1,
            'event'          => $event,
            'headline'       => $headline,
            'body'           => implode("\n", $lines),
            'instruction'    => $instruction,
            'issued_at'      => now()->toIso8601String(),
            'expires_at'     => Carbon::parse($lastDate, config('app.timezone'))->endOfDay()->toIso8601String(),
            'dedupe_key'     => $dedupe,
            'throttle_group' => $type,
            'throttle_hours' => self::THROTTLE_HOURS[$type],
            'compete_key'    => $type,
            'fields'         => $fields,
            'action'         => 'send',
            'reason'         => '',
        ];
    }

    private function coordKey(float $lat, float $lng): string
    {
        return round($lat, 2) . ',' . round($lng, 2); // ~1 km: neighbouring pins share one API call
    }

    private function fetchForecasts(array $coordKeys, array &$notes): array
    {
        $out = [];
        $ok = true;
        $apiKey = env('WEATHER_API_KEY');
        if (!$apiKey) {
            $notes[] = '!WEATHER_API_KEY is not set; forecast layer skipped.';
            return [[], false];
        }

        foreach (array_chunk($coordKeys, 10) as $chunk) {
            if ($this->overBudget()) {
                $notes[] = '!Time budget reached; some pins were not checked for forecast.';
                $ok = false;
                break;
            }
            $responses = Http::pool(function (Pool $pool) use ($chunk, $apiKey) {
                foreach ($chunk as $k) {
                    $pool->as($k)->timeout(8)->get('http://api.weatherapi.com/v1/forecast.json', [
                        'key' => $apiKey, 'q' => $k, 'days' => 3, 'aqi' => 'no', 'alerts' => 'no',
                    ]);
                }
            });

            foreach ($chunk as $k) {
                $r = $responses[$k] ?? null;
                if (!($r instanceof \Illuminate\Http\Client\Response) || $r->failed() || isset($r->json()['error'])) {
                    $ok = false;
                    $notes[] = 'Forecast failed for pin ' . $k;
                    continue;
                }
                $days = [];
                foreach (($r->json()['forecast']['forecastday'] ?? []) as $fd) {
                    $d = $fd['day'] ?? [];
                    $rain = (int) ($d['daily_chance_of_rain'] ?? 0);
                    $hum  = (int) round($d['avghumidity'] ?? 0);
                    $wind = (int) round($d['maxwind_kph'] ?? 0);
                    $mm   = round((float) ($d['totalprecip_mm'] ?? 0), 1);
                    $date = $fd['date'];
                    $days[] = [
                        'date' => $date,
                        'label' => Carbon::parse($date)->format('D M j'),
                        'rain' => $rain, 'humidity' => $hum,
                        'condition' => $d['condition']['text'] ?? '',
                        'high' => ($rain > self::RAIN_HIGH || $hum > self::HUMIDITY_HIGH),
                        'wind' => $wind, 'precip' => $mm,
                        'storm' => ($wind >= self::WIND_HIGH_KPH || $mm >= self::HEAVY_RAIN_MM),
                    ];
                }
                $out[$k] = $days;
            }
        }
        return [$out, $ok];
    }

    // =====================================================================
    //  SMALL HELPERS
    // =====================================================================

    private function overBudget(): bool
    {
        // A cron batch (global + several areas) shares one budget, still under the 90s limit.
        $budget = $this->inBatch ? 75 : self::TIME_BUDGET_SECONDS;
        return (microtime(true) - $this->startedAt) > $budget;
    }

    private function loadXml(string $body): ?\SimpleXMLElement
    {
        // Drop default namespaces so ->entry / ->info work plainly.
        $body = preg_replace('/\sxmlns="[^"]*"/', '', $body);
        $prev = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        return $xml ?: null;
    }

    /** Always returns a Carbon in the app timezone (so DB values aren't shifted). */
    private function parseTime(?string $s): ?Carbon
    {
        if (!$s) return null;
        try {
            return Carbon::parse($s)->setTimezone(config('app.timezone'));
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** CAP descriptions contain markdown (**bold**, " + " bullets). Make them readable plain text. */
    private function cleanText(string $s): string
    {
        $s = str_replace('**', '', $s);
        $s = preg_replace('/^[ \t]*\+\s+/m', '- ', $s);
        $s = preg_replace('/[ \t]+$/m', '', $s);
        $s = preg_replace("/\n{3,}/", "\n\n", $s);
        return trim($s);
    }
}