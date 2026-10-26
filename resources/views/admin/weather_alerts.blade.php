@extends('layouts.admin')
@section('title', 'Weather Alerts • RICEGUARD AI')

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h3 class="fw-bold text-white mb-0"><i class="fas fa-cloud-bolt text-info me-2"></i>Weather Alerts</h3>
            <small class="text-secondary">Official PAGASA advisories + forecast-based risk, matched to each farmer's pins.</small>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success py-2">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger py-2">{{ session('error') }}</div>
    @endif

    @if(!empty($setupError))
        <div class="alert alert-warning">{{ $setupError }}</div>
    @else
    @php
        $mode = $settings->mode;
        $modeBadge = ['live' => 'bg-danger', 'dry_run' => 'bg-warning text-dark', 'off' => 'bg-secondary'][$mode] ?? 'bg-secondary';
        $modeLabel = ['live' => 'LIVE', 'dry_run' => 'DRY-RUN', 'off' => 'OFF'][$mode] ?? strtoupper($mode);
        $sum = $preview['summary'] ?? null;
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->timezone(config('app.timezone'))->format('M j, g:i A') . ' (' . \Carbon\Carbon::parse($d)->diffForHumans() . ')' : 'Never';
        $gateOk = $gate['ok'] ?? false;
        $goLiveMsg = "Go Live?\n\n" . ($sum['would_send'] ?? 0) . ' alert(s) active right now (' . ($sum['farmers_to_email'] ?? 0)
            . " farmer(s)) will be saved as BASELINE and will NOT be emailed.\n\nFrom then on, only new or updated alerts will be emailed.";
    @endphp

    {{-- STATUS CARDS --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3 col-6">
            <div class="prodigy-card p-3 h-100">
                <div class="prodigy-label">Current mode</div>
                <span class="badge {{ $modeBadge }} fs-6">{{ $modeLabel }}</span>
                @if($mode === 'live' && $settings->live_since)
                    <div class="small text-secondary mt-2">Live since {{ \Carbon\Carbon::parse($settings->live_since)->timezone(config('app.timezone'))->format('M j, g:i A') }}</div>
                @endif
                @if($settings->changed_at)
                    <div class="small text-secondary mt-1">Changed by {{ $changedBy ?? 'unknown' }}, {{ \Carbon\Carbon::parse($settings->changed_at)->diffForHumans() }}</div>
                @endif
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="prodigy-card p-3 h-100">
                <div class="prodigy-label">Last run</div>
                <div class="small text-white">{{ $fmt($settings->last_run_at) }}</div>
                <div class="small text-secondary mt-1">Mode: {{ $settings->last_run_mode ?? '-' }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="prodigy-card p-3 h-100">
                <div class="prodigy-label">Last complete dry-run</div>
                <div class="small text-white">{{ $fmt($settings->last_dry_run_ok_at) }}</div>
                <div class="small mt-1 {{ $gateOk ? 'text-success' : 'text-warning' }}">{{ $gateOk ? 'Go Live unlocked' : ($gate['reason'] ?? '') }}</div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="prodigy-card p-3 h-100">
                <div class="prodigy-label">Farmers affected</div>
                <div class="fs-3 fw-bold text-white">{{ $sum['farmers_to_email'] ?? 0 }}</div>
                <div class="small text-secondary">of {{ $sum['farmers_total'] ?? 0 }} approved farmers ({{ $sum['would_send'] ?? 0 }} alert(s))</div>
            </div>
        </div>
    </div>

    {{-- CONTROLS --}}
    <div class="prodigy-card p-3 mb-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <form method="POST" action="{{ route('admin.weather_alerts.run') }}" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='<i class=&quot;fas fa-spinner fa-spin me-2&quot;></i>Running... (can take up to a minute)';">
                @csrf
                <button class="btn btn-primary"><i class="fas fa-play me-2"></i>Run now (dry-run)</button>
            </form>

            <form method="POST" action="{{ route('admin.weather_alerts.test') }}">
                @csrf
                <button class="btn btn-outline-info"><i class="fas fa-envelope me-2"></i>Send test email to me</button>
            </form>

            <div class="vr mx-2 d-none d-md-block"></div>

            @if($mode !== 'live')
                <form method="POST" action="{{ route('admin.weather_alerts.mode') }}"
                      onsubmit="return confirm(@json($goLiveMsg, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_TAG | JSON_HEX_AMP));">
                    @csrf
                    <input type="hidden" name="mode" value="live">
                    <button class="btn btn-danger" {{ $gateOk ? '' : 'disabled' }} title="{{ $gateOk ? '' : ($gate['reason'] ?? '') }}"><i class="fas fa-rocket me-2"></i>Go Live</button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.weather_alerts.mode') }}"
                  onsubmit="return confirm('Switch back to dry-run? No emails will be sent until you go live again.');">
                @csrf
                <input type="hidden" name="mode" value="dry_run">
                <button class="btn btn-warning" {{ $mode === 'dry_run' ? 'disabled' : '' }}><i class="fas fa-rotate-left me-2"></i>Back to Dry-run</button>
            </form>

            @if($mode !== 'off')
                <form method="POST" action="{{ route('admin.weather_alerts.mode') }}"
                      onsubmit="return confirm('Turn weather alerts completely off? The cron will do nothing.');">
                    @csrf
                    <input type="hidden" name="mode" value="off">
                    <button class="btn btn-outline-secondary"><i class="fas fa-power-off me-2"></i>Turn off</button>
                </form>
            @endif
        </div>
        <div class="small text-secondary mt-2">
            Dry-run builds the full list below but sends nothing and shows nothing to farmers. The cron keeps refreshing this preview while in dry-run.
        </div>
    </div>

    {{-- ERRORS / NOTES --}}
    @if($preview && (!empty($preview['errors']) || !empty($preview['notes'])))
        <div class="alert alert-warning py-2">
            <strong>Warnings from the last run</strong>
            <ul class="mb-0 small">
                @foreach(($preview['errors'] ?? []) as $e)<li>{{ $e }}</li>@endforeach
                @foreach(($preview['notes'] ?? []) as $n)<li>{{ ltrim($n, '!') }}</li>@endforeach
            </ul>
        </div>
    @endif

    @if(!empty($preview['dispatch']) && ($preview['mode'] ?? '') === 'live')
        <div class="alert alert-info py-2 small">
            Last live run: sent {{ $preview['dispatch']['sent'] ?? 0 }}, failed {{ $preview['dispatch']['failed'] ?? 0 }},
            no email on file {{ $preview['dispatch']['no_email'] ?? 0 }}, deferred {{ $preview['dispatch']['deferred_to_next_run'] ?? 0 }}.
        </div>
    @endif

    {{-- PREVIEW TABLE --}}
    <div class="prodigy-card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
            <h5 class="text-white fw-bold mb-0">Preview: who would be notified</h5>
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-light active" data-filter="all">All</button>
                <button type="button" class="btn btn-outline-light" data-filter="send">Will send</button>
                <button type="button" class="btn btn-outline-light" data-filter="skip">Skipped</button>
            </div>
        </div>

        @if(!$preview)
            <div class="text-secondary small py-3 text-center">No preview yet. Press "Run now (dry-run)".</div>
        @else
            <div class="small text-secondary mb-2">
                Generated {{ $fmt($preview['generated_at'] ?? null) }} &middot;
                {{ $sum['pagasa_active'] ?? 0 }} active PAGASA alert(s), {{ $sum['pagasa_matched'] ?? 0 }} matched a farmer &middot;
                {{ $sum['forecast_pins'] ?? 0 }} pin location(s) checked for forecast &middot;
                {{ $sum['farmers_without_pin'] ?? 0 }} farmer(s) have no map pin (province match only, no forecast)
            </div>
            <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                <table class="table table-dark table-sm align-middle mb-0" id="previewTable">
                    <thead>
                        <tr><th>Farmer</th><th>Field(s)</th><th>Alert</th><th>Matched by</th><th>Result</th></tr>
                    </thead>
                    <tbody>
                    @forelse(($preview['items'] ?? []) as $it)
                        @php
                            $isSend = ($it['action'] ?? '') === 'send';
                            $methods = collect($it['fields'] ?? [])->map(fn ($f) => ($f['method'] ?? '') === 'polygon' ? 'Inside PAGASA area' : (($f['method'] ?? '') === 'province' ? 'Province match' : 'Forecast'))->unique()->implode(', ');
                        @endphp
                        <tr data-action="{{ $isSend ? 'send' : 'skip' }}">
                            <td>
                                <div class="fw-semibold">{{ $it['user_name'] ?? '-' }}</div>
                                <div class="small text-secondary">{{ $it['email'] ?: 'no email' }}</div>
                            </td>
                            <td class="small">{{ collect($it['fields'] ?? [])->pluck('name')->implode(', ') }}</td>
                            <td>
                                <span class="badge {{ ($it['source'] ?? '') === 'pagasa' ? 'bg-primary' : 'bg-warning text-dark' }}">{{ $it['label'] ?? '' }}</span>
                                <span class="badge bg-danger">{{ $it['level'] ?? '' }}</span>
                                <div class="small mt-1">{{ $it['headline'] ?? '' }}</div>
                            </td>
                            <td class="small">{{ $methods }}</td>
                            <td class="small">
                                @if($isSend)
                                    <span class="text-success fw-bold">Will send</span>
                                    @if(!empty($it['reason']))<div class="text-warning">{{ $it['reason'] }}</div>@endif
                                @else
                                    <span class="text-secondary fw-bold">Skipped</span>
                                    <div class="text-secondary">{{ $it['reason'] ?? '' }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-secondary py-4">No active alert matches any farmer right now.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- SENT / RECORDED LOG --}}
    <div class="prodigy-card p-3 mb-4">
        <h5 class="text-white fw-bold mb-2">Recorded alerts (latest 25)</h5>
        <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0">
                <thead><tr><th>Issued</th><th>Farmer</th><th>Alert</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($log as $row)
                    @php
                        $st = ['sent' => 'bg-success', 'baselined' => 'bg-secondary', 'failed' => 'bg-danger', 'no_email' => 'bg-warning text-dark', 'pending' => 'bg-info text-dark'][$row->status] ?? 'bg-secondary';
                    @endphp
                    <tr>
                        <td class="small">{{ optional($row->issued_at)->timezone(config('app.timezone'))->format('M j, g:i A') }}</td>
                        <td class="small">{{ optional($row->user)->full_name ?? optional($row->user)->name ?? ('#' . $row->user_id) }}</td>
                        <td class="small"><span class="fw-semibold">{{ $row->label }}</span> &middot; {{ $row->level }}<div class="text-secondary">{{ $row->headline }}</div></td>
                        <td class="small">
                            <span class="badge {{ $st }}">{{ str_replace('_', ' ', $row->status) }}</span>
                            @if($row->error)<div class="text-danger">{{ \Illuminate\Support\Str::limit($row->error, 120) }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-secondary py-3">Nothing recorded yet. Alerts appear here after Go Live.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('[data-filter]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('[data-filter]').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var f = btn.getAttribute('data-filter');
        document.querySelectorAll('#previewTable tbody tr[data-action]').forEach(function (tr) {
            tr.style.display = (f === 'all' || tr.getAttribute('data-action') === f) ? '' : 'none';
        });
    });
});
</script>
@endsection