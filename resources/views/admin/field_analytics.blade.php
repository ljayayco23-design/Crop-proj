{{-- resources/views/admin/field_analytics.blade.php --}}
@extends('layouts.admin')

@section('content')
<style>
    .fa-wrap {
        --fa-surface:  #1e2937;
        --fa-surface2: #172033;
        --fa-border:   rgba(148,163,184,.14);
        --fa-text:     #e2e8f0;
        --fa-muted:    #94a3b8;
        --fa-green:    #10b981;
        --fa-blue:     #3b82f6;
        --fa-amber:    #f59e0b;
        --fa-slate:    #64748b;
        color: var(--fa-text);
    }
    .fa-wrap h3 { color: #f8fafc; letter-spacing: -.01em; }
    .fa-sub { color: var(--fa-muted); }
    .fa-scope {
        display: inline-flex; align-items: center; gap: .4rem;
        background: rgba(16,185,129,.12); color: var(--fa-green);
        border: 1px solid rgba(16,185,129,.3);
        border-radius: 999px; padding: .15rem .7rem; font-size: .8rem; font-weight: 600;
    }

    /* KPI cards */
    .fa-kpi {
        background: var(--fa-surface); border: 1px solid var(--fa-border);
        border-radius: 14px; padding: 1.1rem 1.2rem; height: 100%;
        display: flex; align-items: center; gap: 1rem;
    }
    .fa-kpi-icon {
        width: 46px; height: 46px; border-radius: 12px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
    }
    .fa-kpi-label { color: var(--fa-muted); font-size: .82rem; }
    .fa-kpi-value { color: #f8fafc; font-size: 1.65rem; font-weight: 700; line-height: 1.15; }

    /* Toolbar */
    .fa-search {
        background: var(--fa-surface); border: 1px solid var(--fa-border);
        color: var(--fa-text); border-radius: 10px; padding: .55rem .9rem .55rem 2.3rem; width: 100%;
    }
    .fa-search::placeholder { color: var(--fa-muted); }
    .fa-search:focus { outline: none; border-color: var(--fa-green); box-shadow: 0 0 0 3px rgba(16,185,129,.18); }
    .fa-search-wrap { position: relative; min-width: 260px; }
    .fa-search-wrap i { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: var(--fa-muted); font-size: .85rem; }
    .fa-btn {
        background: var(--fa-surface); border: 1px solid var(--fa-border); color: var(--fa-text);
        border-radius: 10px; padding: .5rem .9rem; font-size: .88rem;
    }
    .fa-btn:hover { border-color: var(--fa-green); color: #fff; }

    /* Table-like list */
    .fa-panel { background: var(--fa-surface); border: 1px solid var(--fa-border); border-radius: 14px; overflow: hidden; }
    .fa-head, .fa-row {
        display: grid; align-items: center; column-gap: 1rem; padding: .85rem 1.25rem;
        grid-template-columns: 34px minmax(150px,1.3fr) 100px 110px minmax(150px,1.5fr) 100px 20px;
    }
    .fa-head { color: var(--fa-muted); font-size: .8rem; font-weight: 600; border-bottom: 1px solid var(--fa-border); background: rgba(15,23,42,.35); }
    .fa-item { border-bottom: 1px solid var(--fa-border); }
    .fa-item:last-child { border-bottom: 0; }
    .fa-row { color: var(--fa-text); text-decoration: none; cursor: pointer; transition: background .15s; }
    .fa-row:hover { background: rgba(148,163,184,.06); color: var(--fa-text); }
    .fa-rank { color: var(--fa-muted); font-size: .85rem; font-variant-numeric: tabular-nums; }
    .fa-name { font-weight: 600; color: #f8fafc; }
    .fa-city { color: var(--fa-muted); font-size: .8rem; }
    .fa-num  { font-variant-numeric: tabular-nums; font-weight: 600; }
    .fa-dim  { color: var(--fa-muted); font-weight: 400; }
    .fa-bar { height: 8px; background: rgba(148,163,184,.16); border-radius: 99px; overflow: hidden; }
    .fa-bar > span { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg,#059669,#10b981); }
    .fa-pct { font-size: .75rem; color: var(--fa-muted); margin-top: .25rem; }
    .fa-chev { color: var(--fa-muted); font-size: .8rem; transition: transform .2s; }
    .fa-row[aria-expanded="true"] .fa-chev { transform: rotate(180deg); }
    .fa-row[aria-expanded="true"] { background: rgba(16,185,129,.06); }

    /* Expanded farmers */
    .fa-detail { background: var(--fa-surface2); padding: .5rem 1.25rem 1rem 3.4rem; }
    .fa-mhead, .fa-mrow {
        display: grid; align-items: center; column-gap: 1rem; padding: .55rem .25rem;
        grid-template-columns: minmax(160px,1.4fr) minmax(120px,1fr) minmax(120px,1fr) 90px;
    }
    .fa-mhead { color: var(--fa-muted); font-size: .78rem; font-weight: 600; border-bottom: 1px solid var(--fa-border); }
    .fa-mrow { border-bottom: 1px solid rgba(148,163,184,.07); font-size: .9rem; }
    .fa-mrow:last-child { border-bottom: 0; }
    .fa-avatar {
        width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
        background: rgba(59,130,246,.18); color: #93c5fd; font-size: .75rem; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .fa-mini { height: 5px; background: rgba(148,163,184,.16); border-radius: 99px; overflow: hidden; }
    .fa-mini > span { display: block; height: 100%; background: var(--fa-blue); border-radius: 99px; }
    .fa-empty { padding: 3.5rem 1rem; text-align: center; color: var(--fa-muted); }
    .fa-empty i { font-size: 2rem; margin-bottom: .75rem; opacity: .6; }

    @media (max-width: 991px) {
        .fa-head, .fa-row { grid-template-columns: 28px minmax(120px,1fr) 80px 90px 20px; }
        .fa-col-share, .fa-col-avg { display: none; }
        .fa-detail { padding-left: 1.25rem; }
        .fa-mhead, .fa-mrow { grid-template-columns: minmax(140px,1.4fr) 80px; }
        .fa-mcol-farm, .fa-mcol-bar { display: none; }
    }
</style>

@php
    $avgAll = $totals['farmers'] > 0 ? round($totals['detections'] / $totals['farmers'], 1) : 0;
@endphp

<div class="container-fluid py-4 fa-wrap">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <h3 class="fw-bold mb-1">Field Analytics</h3>
            <div class="fa-sub">
                Registered farmers and detections by barangay
                <span class="fa-scope ms-2"><i class="fas fa-location-dot"></i> {{ $scopeLabel }}</span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <div class="fa-search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="faSearch" class="fa-search" placeholder="Search barangay or farmer" autocomplete="off">
            </div>
            <button type="button" class="fa-btn" id="faToggleAll"><i class="fas fa-up-down me-1"></i> Expand all</button>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="fa-kpi">
                <div class="fa-kpi-icon" style="background:rgba(59,130,246,.15);color:#60a5fa;"><i class="fas fa-map-location-dot"></i></div>
                <div><div class="fa-kpi-label">Barangays</div><div class="fa-kpi-value">{{ number_format($totals['barangays']) }}</div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="fa-kpi">
                <div class="fa-kpi-icon" style="background:rgba(16,185,129,.15);color:#34d399;"><i class="fas fa-users"></i></div>
                <div><div class="fa-kpi-label">Farmers</div><div class="fa-kpi-value">{{ number_format($totals['farmers']) }}</div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="fa-kpi">
                <div class="fa-kpi-icon" style="background:rgba(245,158,11,.15);color:#fbbf24;"><i class="fas fa-magnifying-glass-chart"></i></div>
                <div><div class="fa-kpi-label">Total detections</div><div class="fa-kpi-value">{{ number_format($totals['detections']) }}</div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="fa-kpi">
                <div class="fa-kpi-icon" style="background:rgba(100,116,139,.2);color:#cbd5e1;"><i class="fas fa-gauge-high"></i></div>
                <div><div class="fa-kpi-label">Avg. per farmer</div><div class="fa-kpi-value">{{ $avgAll }}</div></div>
            </div>
        </div>
    </div>

    {{-- Barangay list --}}
    <div class="fa-panel">
        @if($barangays->isEmpty())
            <div class="fa-empty">
                <i class="fas fa-seedling d-block"></i>
                No approved farmers found for this area yet.
            </div>
        @else
            <div class="fa-head">
                <div>#</div>
                <div>Barangay</div>
                <div>Farmers</div>
                <div>Detections</div>
                <div class="fa-col-share">Share of detections</div>
                <div class="fa-col-avg">Avg / farmer</div>
                <div></div>
            </div>

            @foreach($barangays as $i => $b)
                @php
                    $share     = $totals['detections'] > 0 ? round($b['detections'] / $totals['detections'] * 100) : 0;
                    $barWidth  = round($b['detections'] / $maxDetections * 100);
                    $memberMax = max(1, (int) $b['members']->max('detections'));
                    $search    = strtolower($b['barangay'] . ' ' . $b['members']->pluck('name')->implode(' '));
                @endphp
                <div class="fa-item" data-search="{{ $search }}">
                    <a class="fa-row" data-bs-toggle="collapse" href="#brgy{{ $i }}" role="button"
                       aria-expanded="false" aria-controls="brgy{{ $i }}">
                        <div class="fa-rank">{{ $i + 1 }}</div>
                        <div>
                            <div class="fa-name">{{ $b['barangay'] }}</div>
                            @if($isDeveloper && $b['city'])
                                <div class="fa-city">{{ $b['city'] }}</div>
                            @endif
                        </div>
                        <div class="fa-num">{{ number_format($b['farmers']) }}</div>
                        <div class="fa-num">{{ number_format($b['detections']) }}</div>
                        <div class="fa-col-share">
                            <div class="fa-bar"><span style="width: {{ $barWidth }}%"></span></div>
                            <div class="fa-pct">{{ $share }}% of total</div>
                        </div>
                        <div class="fa-num fa-col-avg">{{ $b['average'] }}</div>
                        <div><i class="fas fa-chevron-down fa-chev"></i></div>
                    </a>

                    <div class="collapse fa-collapse" id="brgy{{ $i }}">
                        <div class="fa-detail">
                            <div class="fa-mhead">
                                <div>Farmer</div>
                                <div class="fa-mcol-farm">Farm</div>
                                <div class="fa-mcol-bar">Activity</div>
                                <div class="text-end">Detections</div>
                            </div>
                            @foreach($b['members'] as $m)
                                <div class="fa-mrow">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fa-avatar">{{ strtoupper(mb_substr($m['name'] ?? '?', 0, 1)) }}</span>
                                        <span>{{ $m['name'] }}</span>
                                    </div>
                                    <div class="fa-mcol-farm fa-dim">{{ $m['farm_name'] ?: '—' }}</div>
                                    <div class="fa-mcol-bar">
                                        <div class="fa-mini"><span style="width: {{ round($m['detections'] / $memberMax * 100) }}%"></span></div>
                                    </div>
                                    <div class="text-end fa-num">{{ number_format($m['detections']) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>

<script>
(function () {
    var search  = document.getElementById('faSearch');
    var toggle  = document.getElementById('faToggleAll');
    var items   = document.querySelectorAll('.fa-item');
    var allOpen = false;

    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            items.forEach(function (el) {
                el.style.display = (!q || el.dataset.search.indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            allOpen = !allOpen;
            document.querySelectorAll('.fa-collapse').forEach(function (el) {
                if (window.bootstrap && bootstrap.Collapse) {
                    var inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                    allOpen ? inst.show() : inst.hide();
                }
            });
            toggle.innerHTML = '<i class="fas fa-up-down me-1"></i> ' + (allOpen ? 'Collapse all' : 'Expand all');
        });
    }
})();
</script>
@endsection