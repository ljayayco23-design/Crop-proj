{{--
    resources/views/admin/admin_report.blade.php

    Admin side of the detection-feedback loop: every report a technician
    pressed "Escalate to Admin" on (FarmerReportController@escalate), with the
    farmer's report on the LEFT and the technician's resolution on the RIGHT —
    the same two-column layout the farmer sees on their own Report Problem page.

    Rows come from AdminSystemReportController@index, built with the SAME
    FarmerReportController@present() the technician and farmer pages use, so
    the detection image, flagged (red) sections and technician review are
    identical everywhere.

    Status flow (admin_status):
        pending      -> escalated, admin has not looked at it yet
        open         -> set AUTOMATICALLY the first time the admin clicks View
        in_progress  -> admin picks "In Progress" in the three-dot menu
        resolved     -> admin picks "Resolved" in the three-dot menu

    NOTE: change the @extends below if your admin layout file is named
    differently (this assumes resources/views/layouts/admin.blade.php).
--}}
@extends('layouts.admin')

@section('title', 'RICEGUARD AI • System Reports')

@section('content')
<style>
    .fr-hidden { display: none !important; }

    .fr-card { background: #121826; border: 1px solid #263349; border-radius: 12px; }
    .fr-panel { border: 1px solid #263349; border-radius: 10px; padding: 14px; }

    .fr-table thead th {
        font-size: .74rem; text-transform: uppercase; letter-spacing: .04em;
        color: #94a3b8; border-bottom: 1px solid #263349; white-space: nowrap;
    }
    .fr-table tbody td { border-bottom: 1px solid #1d2636; color: #e2e8f0; font-size: .86rem; vertical-align: middle; }
    .fr-table tbody tr:hover { background: rgba(255,255,255,.03); }
    .sr-desc { max-width: 320px; }

    .fr-input {
        background: #0f1522; border: 1px solid #334155; color: #e2e8f0;
        border-radius: 8px; font-size: .85rem;
    }
    .fr-input:focus { background: #0f1522; color: #fff; border-color: #3b82f6; box-shadow: none; }
    .fr-input option { background: #1a1f2b; color: #e2e8f0; }

    /* ---------- Badges (Type / Status) ---------- */
    .sr-badge {
        display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;
        padding: 4px 12px; border-radius: 999px; font-size: .74rem; font-weight: 700; letter-spacing: .01em;
    }
    .sr-status-pending     { background: rgba(148,163,184,.18); color: #cbd5e1; }
    .sr-status-open        { background: rgba(244,114,182,.18); color: #f472b6; }
    .sr-status-in_progress { background: #facc15; color: #3b2f00; }
    .sr-status-resolved    { background: #22c55e; color: #052e16; }

    .sr-type-ai_model       { background: #ef4444; color: #fff; }
    .sr-type-knowledge_base { background: rgba(59,130,246,.18); color: #60a5fa; }
    .sr-type-system         { background: rgba(148,163,184,.18); color: #cbd5e1; }

    /* ---------- Row actions: three-dot dropdown ----------
       One floating menu shared by every row, positioned with fixed
       coordinates, so it is never clipped by the table's scroll container. */
    .sr-action-toggle {
        width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
        background: transparent; border: none; outline: none; color: #cbd5e1; border-radius: 8px;
    }
    .sr-action-toggle:hover, .sr-action-toggle:focus { background: rgba(255,255,255,.08); color: #fff; box-shadow: none; }
    .sr-menu {
        position: fixed; z-index: 1090; min-width: 160px;
        background: #1a1f2b; border: 1px solid #334155; border-radius: 10px;
        box-shadow: 0 10px 26px rgba(0,0,0,.5); padding: 6px;
    }
    .sr-menu-item {
        display: flex; align-items: center; gap: 9px; width: 100%; padding: 8px 10px;
        border-radius: 7px; cursor: pointer; font-size: .85rem; color: #cbd5e1;
        background: none; border: none; text-align: left;
    }
    .sr-menu-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .sr-menu-item i { width: 16px; text-align: center; }

    /* ---------- Floating modal (same shell as the farmer's Create a Report modal) ---------- */
    .rg-report-backdrop {
        position: fixed; inset: 0; z-index: 1100;
        background: rgba(2, 6, 12, 0.35);
        backdrop-filter: blur(2px);
        display: flex; align-items: flex-start; justify-content: center;
        padding: 2.5rem 1rem; overflow-y: auto;
    }
    .rg-report-backdrop.fr-hidden { display: none !important; }
    .rg-report-dialog {
        width: 100%; max-width: 1120px;
        background: #121826; border: 1px solid #334155; border-radius: 14px;
        box-shadow: 0 18px 60px rgba(0,0,0,.55);
        max-height: 92vh; display: flex; flex-direction: column;
    }
    .rg-report-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #263349; }
    .rg-report-body { padding: 16px 18px; overflow-y: auto; }
    .rg-report-foot { display: flex; align-items: center; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #263349; }

    .fr-col-head {
        font-size: .74rem; text-transform: uppercase; letter-spacing: .05em;
        color: #94a3b8; margin-bottom: .5rem; font-weight: 700;
    }

    /* ---------- Mirrored detection panel (identical to the farmer / technician pages) ---------- */
    .rg-type-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(16,185,129,0.14); color: #10b981;
        font-weight: 700; font-size: 0.8rem; padding: 7px 14px;
        border-radius: 999px; flex: 0 0 auto; align-self: flex-start;
    }
    .rg-type-badge.is-disease { background: rgba(239,68,68,0.14); color: #f87171; }

    .rg-report-thumb {
        width: 92px; height: 92px; object-fit: cover; cursor: pointer;
        border-radius: 12px; background: #000; flex: 0 0 auto;
        box-shadow: 0 4px 14px rgba(0,0,0,.4);
    }
    .rg-report-top { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .rg-report-top-info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
    .rg-report-name { font-size: 1.15rem; font-weight: 800; color: #fff; line-height: 1.2; }

    .rg-confidence-gauge { position: relative; width: 140px; height: 80px; flex: 0 0 auto; }
    .rg-confidence-gauge svg { width: 100%; height: 100%; display: block; overflow: visible; }
    .rg-confidence-gauge .gauge-track { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 16; stroke-linecap: round; }
    .rg-confidence-gauge .gauge-fill { fill: none; stroke-width: 16; stroke-linecap: round; }
    .rg-report-gauge-center {
        position: absolute; left: 0; right: 0; bottom: 2px;
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
    }
    .rg-report-gauge-center span { font-size: 1.1rem; font-weight: 800; line-height: 1.1; color: inherit; }
    .rg-report-gauge-center small { font-size: .65rem; font-weight: 600; color: #94a3b8; letter-spacing: .02em; }

    .rg-report-stat { background: rgba(255,255,255,0.04); border-radius: 10px; padding: 10px 12px; height: 100%; }
    .rg-report-stat-label { font-size: .72rem; font-weight: 600; color: #94a3b8; margin-bottom: 3px; }
    .rg-report-stat-value { font-size: 1rem; font-weight: 800; color: #fff; }
    .rg-report-stat-value.text-success { color: #22c55e !important; }
    .rg-report-stat-value.text-info { color: #38bdf8 !important; }
    .rg-report-stat-value.text-warning { color: #f59e0b !important; }
    .rg-report-stat-value.text-danger { color: #ef4444 !important; }

    .rg-flag-block { transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }
    .rg-flag-row { border-bottom: 1px solid #263349; padding: 10px 2px 12px; }
    .rg-flag-row:last-child { border-bottom: none; }
    .rg-flag-row.rg-flagged { border-bottom-color: #ef4444; background: rgba(239, 68, 68, .07); border-radius: 6px; }
    .rg-flag-row.rg-flagged .rg-flag-title { color: #f87171 !important; }
    .rg-flag-row.rg-flagged .rg-flag-title::after {
        content: " • marked wrong"; font-size: .7rem; font-weight: 600; letter-spacing: .02em; color: #f87171;
    }
    .rg-flag-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; }
    .rg-flag-chip.rg-flagged {
        box-shadow: inset 0 0 0 1.5px #ef4444; background: rgba(239, 68, 68, .08); border-radius: 10px;
    }
    .rg-flag-chip.rg-flagged .rg-flag-title,
    .rg-flag-chip.rg-flagged .rg-report-name,
    .rg-flag-chip.rg-flagged .rg-report-stat-value,
    .rg-flag-chip.rg-flagged .rg-report-gauge-center { color: #f87171 !important; }

    .rg-flag-title.text-white { color: #fff; }
    .rg-flag-title.text-success { color: #22c55e; }
    .rg-flag-title.text-warning { color: #f59e0b; }
    .rg-flag-title.text-info { color: #38bdf8; }
    .rg-flag-title.text-danger { color: #ef4444; }

    .fr-mirror-note { font-size: .72rem; color: #f87171; font-weight: 700; }

    /* Farmer's message / problem tags */
    .fr-report-tags { display: flex; flex-wrap: wrap; gap: 6px; }
    .fr-report-tag {
        display: inline-flex; align-items: center; background: rgba(245,158,11,.14); color: #fbbf24;
        font-size: .76rem; font-weight: 700; padding: 3px 10px; border-radius: 999px;
    }
    .fr-report-suggest-tag {
        display: inline-flex; align-items: center; gap: 5px; background: rgba(56,189,248,.14); color: #38bdf8;
        font-size: .8rem; font-weight: 700; padding: 4px 11px; border-radius: 999px;
    }
    .fr-report-message {
        background: rgba(255,255,255,.045); border-left: 2px solid #334155; border-radius: 0 8px 8px 0;
        padding: 10px 12px; font-style: italic; color: #e2e8f0; font-size: .85rem;
    }
    .fr-thumb {
        width: 88px; height: 88px; object-fit: cover; border-radius: 10px;
        background: #0b0f17; cursor: pointer; border: 2px solid transparent;
        transition: border-color .15s ease, transform .15s ease;
    }
    .fr-thumb:hover { border-color: #3b82f6; transform: scale(1.04); }

    /* Technician's resolution */
    .fr-resolve-box { border: 1px solid #1f4d3a; background: rgba(16,185,129,.07); border-radius: 10px; padding: 12px; }
    .fr-wait-box { border: 1px solid #334155; background: rgba(148,163,184,.06); border-radius: 10px; padding: 14px; }
    .sr-assess { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: .78rem; font-weight: 700; }
    .sr-assess.correct          { background: rgba(34,197,94,.16);  color: #4ade80; }
    .sr-assess.incorrect        { background: rgba(239,68,68,.16);  color: #f87171; }
    .sr-assess.info_incorrect   { background: rgba(245,158,11,.16); color: #fbbf24; }
    .sr-assess.cannot_determine { background: rgba(148,163,184,.18); color: #cbd5e1; }
    .sr-assess.need_image       { background: rgba(56,189,248,.16); color: #38bdf8; }

    /* Image lightbox */
    .fr-lightbox {
        position: fixed; inset: 0; z-index: 1250; background: rgba(2,6,12,.85);
        display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    }
    .fr-lightbox img { max-width: 92vw; max-height: 88vh; border-radius: 12px; }

    /* ---------- "Wrong Detection" summary (donut + legend) ----------
       Pure CSS conic-gradient ring, painted by JS from $wrongDetectionStats
       (name => escalated-report count). No chart library needed. */
    .wd-donut-wrap { width: 190px; height: 190px; flex: 0 0 auto; }
    .wd-donut {
        width: 190px; height: 190px; border-radius: 50%;
        background: conic-gradient(#263349 0deg 360deg);
        position: relative; display: flex; align-items: center; justify-content: center;
        transition: background 0.3s ease;
    }
    .wd-donut::before {
        content: ""; position: absolute; inset: 20px; border-radius: 50%;
        background: #121826; box-shadow: inset 0 0 0 1px #263349;
    }
    .wd-donut-center { position: relative; z-index: 1; display: flex; flex-direction: column; align-items: center; }
    .wd-donut-total { font-size: 1.7rem; font-weight: 800; color: #fff; line-height: 1; }
    .wd-donut-center small { font-size: .66rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-top: 3px; }

    .wd-legend { display: flex; flex-direction: column; gap: 4px; max-height: 230px; overflow-y: auto; padding-right: 4px; }
    .wd-legend-row { display: flex; align-items: center; gap: 10px; font-size: .85rem; padding: 6px 4px; border-radius: 8px; }
    .wd-legend-row:hover { background: rgba(255,255,255,.035); }
    .wd-legend-dot { width: 10px; height: 10px; border-radius: 3px; flex: 0 0 auto; }
    .wd-legend-name { color: #e2e8f0; flex: 1 1 auto; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .wd-legend-count { color: #fff; font-weight: 700; background: rgba(255,255,255,.07); padding: 2px 10px; border-radius: 999px; font-size: .76rem; min-width: 28px; text-align: center; }
    .wd-legend-pct { color: #64748b; font-size: .74rem; width: 38px; text-align: right; flex: 0 0 auto; }
</style>

<div class="container-fluid px-0">
    <div class="mb-4">
        <h4 class="fw-bold mb-1">System / Issue Reports</h4>
        <div class="text-secondary small">Reports that technicians escalated to the admin.</div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- "Wrong Detection" summary: every disease/pest the AI got wrong,
         tallied by how many technicians escalated a report about it. --}}
    <div class="fr-card p-3 p-md-4 mb-3">
        <div class="mb-3">
            <h6 class="mb-1 fw-bold text-white">Wrong Detection</h6>
            <div class="text-secondary small">How many times each disease/pest was escalated as a wrong AI detection.</div>
        </div>
        @if(empty($wrongDetectionStats))
            <p class="text-secondary mb-0">No wrong detections have been escalated yet.</p>
        @else
            <div class="d-flex flex-wrap align-items-center gap-4">
                <div class="wd-donut-wrap">
                    <div id="wd-donut" class="wd-donut">
                        <div class="wd-donut-center">
                            <span id="wd-donut-total" class="wd-donut-total">0</span>
                            <small>Reports</small>
                        </div>
                    </div>
                </div>
                <div id="wd-legend" class="wd-legend flex-grow-1" style="min-width: 220px;"></div>
            </div>
        @endif
    </div>

    <div class="fr-card p-3 p-md-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <h6 class="mb-0 fw-bold text-white">Escalated reports</h6>
            <div class="d-flex flex-wrap gap-2">
                <select id="sr-filter-status" class="form-select form-select-sm fr-input" style="width: 165px;" onchange="srApplyFilters()">
                    <option value="">All Status</option>
                    @foreach($statuses as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <select id="sr-filter-type" class="form-select form-select-sm fr-input" style="width: 165px;" onchange="srApplyFilters()">
                    <option value="">All Types</option>
                    @foreach($types as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                <input type="text" id="sr-filter-search" class="form-control form-control-sm fr-input"
                       style="width: 210px;" placeholder="Search report ID, farmer..." oninput="srApplyFilters()">
            </div>
        </div>

        @if(count($reports) === 0)
            <p class="text-secondary mb-0">No reports have been escalated to the admin yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-borderless mb-0 fr-table">
                    <thead>
                        <tr>
                            <th>Report ID</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody id="sr-table-body">
                        @foreach($reports as $report)
                            <tr data-id="{{ $report['id'] }}"
                                data-status="{{ $report['admin_status'] }}"
                                data-type="{{ $report['type'] }}"
                                data-search="{{ strtolower($report['sr_code'].' '.$report['report_id'].' '.$report['farmer_name'].' '.$report['detection']['class_name'].' '.$report['category'].' '.$report['description']) }}">
                                <td class="fw-bold">#{{ $report['sr_code'] }}</td>
                                <td><span class="sr-badge sr-type-{{ $report['type_key'] }}">{{ $report['type'] }}</span></td>
                                <td>{{ $report['category'] }}</td>
                                <td class="sr-desc">{{ $report['description'] }}</td>
                                <td class="text-secondary">{{ $report['escalated_date'] }}</td>
                                <td>
                                    <span class="sr-badge sr-status-badge sr-status-{{ $report['admin_status'] }}">{{ $statuses[$report['admin_status']]['label'] }}</span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="sr-action-toggle" title="Actions"
                                            onclick="srToggleMenu(event, {{ $report['id'] }})">
                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div id="sr-empty" class="text-center text-secondary py-4 fr-hidden">No reports match your filters.</div>
            </div>
        @endif
    </div>
</div>

{{-- Shared three-dot menu (positioned under whichever row's button was clicked). --}}
<div id="sr-action-menu" class="sr-menu fr-hidden">
    <button type="button" class="sr-menu-item" onclick="srAction('view')">
        <i class="fa-solid fa-eye text-info"></i> View
    </button>
    <button type="button" class="sr-menu-item" onclick="srAction('in_progress')">
        <i class="fa-solid fa-hourglass-half text-warning"></i> In Progress
    </button>
    <button type="button" class="sr-menu-item" onclick="srAction('resolved')">
        <i class="fa-solid fa-circle-check text-success"></i> Resolved
    </button>
</div>

{{-- ============ View modal: farmer's report (left) + technician's resolution (right) ============ --}}
<div id="sr-modal" class="rg-report-backdrop fr-hidden" onclick="if (event.target === this) srCloseModal()">
    <div class="rg-report-dialog">
        <div class="rg-report-head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 class="mb-0 fw-bold text-white">System Report <span id="sr-m-id">#SR-000</span></h6>
                <span id="sr-m-status" class="sr-badge sr-status-pending">Pending</span>
                <span id="sr-m-type" class="sr-badge sr-type-system">System</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="srCloseModal()" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="rg-report-body">
            <div class="row g-4">
                {{-- LEFT: the farmer's report --}}
                <div class="col-lg-6">
                    <div class="fr-col-head">Farmer's report</div>
                    <div class="small text-secondary mb-3">
                        Farmer report <span id="sr-m-code" class="text-light">—</span>
                        · by <span id="sr-m-farmer" class="text-light">—</span>
                        · <span id="sr-m-submitted">—</span>
                    </div>

                    <div class="rg-report-top mb-3">
                        <img id="sr-d-image" class="rg-report-thumb fr-hidden" alt="Reported detection" onclick="srZoom(this.src)">
                        <div id="sr-d-image-missing" class="rg-report-thumb d-flex align-items-center justify-content-center text-secondary fr-hidden"><i class="fas fa-image"></i></div>
                        <div id="sr-block-name" class="rg-flag-block rg-flag-chip rg-report-top-info" data-section="name">
                            <div id="sr-type-badge" class="rg-type-badge">
                                <i class="fa-solid fa-bug" id="sr-type-badge-icon"></i>
                                <span id="sr-type-badge-text">Pest</span>
                            </div>
                            <div id="sr-d-class" class="rg-report-name">—</div>
                        </div>
                        <div id="sr-block-gauge" class="rg-confidence-gauge rg-flag-block rg-flag-chip" data-section="name">
                            <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
                                <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100"></path>
                                <path class="gauge-fill" id="sr-confidence-fill" d="M14,100 A86,86 0 0 1 186,100"></path>
                            </svg>
                            <div class="rg-report-gauge-center">
                                <span id="sr-d-confidence">0%</span>
                                <small>Confidence</small>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div id="sr-block-severity" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="severity">
                                <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-gauge-high me-1"></i>Severity</div>
                                <div id="sr-d-severity" class="rg-report-stat-value">—</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div id="sr-block-damagelevel" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="damagelevel">
                                <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-wheat-awn me-1"></i>Damage Level</div>
                                <div id="sr-d-damage" class="rg-report-stat-value">—</div>
                            </div>
                        </div>
                    </div>




                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="small text-light">Detection information</div>
                        <span class="fr-mirror-note">Red = marked wrong</span>
                    </div>
                    
                    <div id="sr-d-info"></div>

                                        <div class="small text-secondary mb-3">Engine: <span id="sr-d-source" class="text-light">—</span></div>

                </div>

                {{-- RIGHT: the technician's resolution --}}
                <div class="col-lg-6">

                                    <div class="small text-light mb-1">What the farmer reported</div>
                    <div id="sr-d-problems" class="fr-report-tags mb-2">—</div>
                    <div id="sr-d-message" class="fr-report-message mb-3">—</div>

                    <div id="sr-d-suggested-wrap" class="mb-3 fr-hidden">
                        <div class="small text-light mb-1">Farmer suggests</div>
                        <span id="sr-d-suggested" class="fr-report-suggest-tag">—</span>
                    </div>

                    <div id="sr-d-support-wrap" class="mb-3 fr-hidden">
                        <div class="small text-light mb-1">Supporting photo from farmer</div>
                        <img id="sr-d-support" class="fr-thumb" alt="Supporting photo" onclick="srZoom(this.src)">
                    </div>
                    <div class="fr-col-head">Technician's resolution</div>

                    <div id="sr-pending-box" class="fr-wait-box fr-hidden">
                        <div class="fw-bold text-white mb-1"><i class="fa-solid fa-clock me-2 text-warning"></i>No technician review saved</div>
                        <p class="small text-light mb-0">This report has no technician resolution attached.</p>
                    </div>

                    <div id="sr-review-box" class="fr-hidden">
                        <div class="mb-2 small text-light">
                            Reviewed by <span id="sr-r-by" class="fw-bold text-white">—</span>
                            · <span id="sr-r-at" class="text-secondary">—</span>
                        </div>
                        <div class="mb-3"><span id="sr-r-assessment" class="sr-assess">—</span></div>

                        <div id="sr-r-need-image" class="fr-wait-box mb-3 fr-hidden">
                            <div class="fw-bold text-white mb-1"><i class="fa-solid fa-camera me-2 text-info"></i>The technician asked the farmer for another photo</div>
                        </div>

                        <div id="sr-r-correction" class="row g-2 align-items-stretch mb-3">
                            <div class="col-6">
                                <div class="fr-panel h-100">
                                    <div class="mb-1" style="font-size:.72rem; color:#94a3b8;">Original AI detection</div>
                                    <div id="sr-r-orig-class" class="fw-bold text-light">—</div>
                                    <div class="small text-secondary">Confidence: <span id="sr-r-orig-conf">—</span></div>
                                    <div class="small text-secondary">Severity: <span id="sr-r-orig-sev" class="text-danger">—</span></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="fr-panel h-100" style="border-color:#1f4d3a; background: rgba(16,185,129,.07);">
                                    <div class="mb-1" style="font-size:.72rem; color:#94a3b8;">Technician-corrected detection</div>
                                    <div id="sr-r-new-class" class="fw-bold text-success">—</div>
                                    <div class="small text-secondary">Severity: <span id="sr-r-new-sev" class="text-warning">—</span></div>
                                </div>
                            </div>
                        </div>

                        <div id="sr-r-titles-wrap" class="mb-3 fr-hidden">
                            <div class="small text-light mb-1">Information the technician marked for correction</div>
                            <div id="sr-r-titles" class="small text-warning">—</div>
                        </div>

                        <div class="fr-resolve-box mb-2">
                            <div class="fw-bold text-white small mb-1"><i class="fa-solid fa-clipboard-check me-2 text-success"></i>Technician notes</div>
                            <p id="sr-r-notes" class="small text-light mb-0">—</p>
                        </div>
                        <div id="sr-r-advice-wrap" class="fr-resolve-box">
                            <div class="fw-bold text-white small mb-1"><i class="fa-solid fa-lightbulb me-2 text-success"></i>Advice given to the farmer</div>
                            <p id="sr-r-advice" class="small text-light mb-0">—</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rg-report-foot">
            <span class="small text-secondary me-auto">
                <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>
                Escalated <span id="sr-m-escalated">—</span>
            </span>
            <button type="button" class="btn btn-sm btn-secondary" onclick="srCloseModal()">Close</button>
        </div>
    </div>
</div>

<div id="sr-lightbox" class="fr-lightbox fr-hidden" onclick="srCloseZoom()">
    <img id="sr-lightbox-img" src="" alt="Enlarged photo">
</div>
@endsection

@section('scripts')
<script>
const srReports   = @json($reports);
const srSections  = @json($sections);
const srStatuses  = @json($statuses);
const srStatusUrl = "{{ route('admin.system_report.status', ['report' => '__ID__']) }}";
const SR_CSRF     = "{{ csrf_token() }}";

// Same engine names the technician page shows.
const srEngineLabels = { model: 'MobileNetV2 (On-Device)', yollo11n: 'yollo11n', groq: 'Groq AI' };

const srAssessmentLabels = {
    correct:          'AI result is correct',
    incorrect:        'AI result is incorrect',
    info_incorrect:   'Information is incorrect',
    need_image:       'Need another image',
    cannot_determine: 'Cannot determine'
};

// Same icon + color pairing as the farmer detection page / technician review.
const srSectionMeta = {
    description:     { icon: 'fa-circle-info',        color: 'text-white'   },
    treatment:       { icon: 'fa-spray-can-sparkles', color: 'text-success' },
    causes:          { icon: 'fa-question-circle',    color: 'text-warning' },
    prevention:      { icon: 'fa-shield-heart',       color: 'text-info'    },
    damage:          { icon: 'fa-wheat-awn',          color: 'text-danger'  },
    natural_enemies: { icon: 'fa-bug-slash',          color: 'text-success' },
    nutrient:        { icon: 'fa-leaf',               color: 'text-warning' },
    grain:           { icon: 'fa-seedling',           color: 'text-danger'  }
};

const SR_ARC_LEN = Math.PI * 86;   // same half-circle gauge as the other pages
let srMenuId  = null;              // report the three-dot menu is open for
let srModalId = null;              // report currently shown in the modal

function srFind(id) { return srReports.find(x => Number(x.id) === Number(id)); }

function srEscape(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

// ==================== "Wrong Detection" summary ====================
// name => count of escalated reports, already sorted desc by the controller.
const wdStats = @json($wrongDetectionStats ?? []);
const wdColors = ['#ef4444','#f59e0b','#10b981','#38bdf8','#a78bfa','#f472b6','#facc15','#34d399','#60a5fa','#fb923c','#c084fc','#4ade80'];

function srRenderWrongDetection() {
    const donut = document.getElementById('wd-donut');
    const legend = document.getElementById('wd-legend');
    const totalEl = document.getElementById('wd-donut-total');
    const entries = Object.entries(wdStats);
    if (!donut || !legend || !entries.length) return;

    const total = entries.reduce((sum, [, count]) => sum + count, 0);

    let angle = 0;
    const segments = entries.map(([name, count], i) => {
        const color = wdColors[i % wdColors.length];
        const share = total ? (count / total) * 360 : 0;
        const slice = `${color} ${angle}deg ${angle + share}deg`;
        angle += share;
        return { name, count, color, slice, pct: total ? Math.round((count / total) * 100) : 0 };
    });

    donut.style.background = `conic-gradient(${segments.map(s => s.slice).join(', ')})`;
    if (totalEl) totalEl.textContent = total;

    legend.innerHTML = segments.map(s => `
        <div class="wd-legend-row">
            <span class="wd-legend-dot" style="background:${s.color}"></span>
            <span class="wd-legend-name" title="${srEscape(s.name)}">${srEscape(s.name)}</span>
            <span class="wd-legend-count">${s.count}</span>
            <span class="wd-legend-pct">${s.pct}%</span>
        </div>`).join('');
}
srRenderWrongDetection();

// ==================== LIST: filters ====================
window.srApplyFilters = function () {
    const status = document.getElementById('sr-filter-status').value;
    const type   = document.getElementById('sr-filter-type').value;
    const term   = document.getElementById('sr-filter-search').value.trim().toLowerCase();

    let shown = 0;
    document.querySelectorAll('#sr-table-body tr').forEach(row => {
        const ok = (!status || row.dataset.status === status)
                && (!type   || row.dataset.type === type)
                && (!term   || row.dataset.search.includes(term));
        row.classList.toggle('fr-hidden', !ok);
        if (ok) shown++;
    });
    const empty = document.getElementById('sr-empty');
    if (empty) empty.classList.toggle('fr-hidden', shown > 0);
};

// ==================== STATUS ====================
// Repaints the row badge (and the modal badge, if this report is open).
function srPaintStatus(r) {
    const meta = srStatuses[r.admin_status] || srStatuses.pending;

    const row = document.querySelector(`#sr-table-body tr[data-id="${r.id}"]`);
    if (row) {
        row.dataset.status = r.admin_status;
        const badge = row.querySelector('.sr-status-badge');
        if (badge) {
            badge.className = 'sr-badge sr-status-badge sr-status-' + r.admin_status;
            badge.textContent = meta.label;
        }
    }

    if (srModalId !== null && Number(srModalId) === Number(r.id)) {
        const mb = document.getElementById('sr-m-status');
        mb.className = 'sr-badge sr-status-' + r.admin_status;
        mb.textContent = meta.label;
    }

    srApplyFilters();
}

async function srPostStatus(r, action) {
    try {
        const res = await fetch(srStatusUrl.replace('__ID__', r.id), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': SR_CSRF,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ action })
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) throw new Error(data.message || 'Could not update the status.');

        r.admin_status = data.status;
        srPaintStatus(r);
    } catch (e) {
        alert(e.message || 'A network error occurred. Please try again.');
    }
}

// ==================== THREE-DOT MENU ====================
window.srToggleMenu = function (ev, id) {
    ev.stopPropagation();
    const menu = document.getElementById('sr-action-menu');

    if (!menu.classList.contains('fr-hidden') && srMenuId === id) { srCloseMenu(); return; }

    srMenuId = id;
    menu.classList.remove('fr-hidden');

    const btn = ev.currentTarget.getBoundingClientRect();
    const mw = menu.offsetWidth, mh = menu.offsetHeight;
    let top = btn.bottom + 4;
    if (top + mh > window.innerHeight - 8) top = Math.max(8, btn.top - mh - 4);
    menu.style.top  = top + 'px';
    menu.style.left = Math.max(8, btn.right - mw) + 'px';
};

function srCloseMenu() {
    document.getElementById('sr-action-menu').classList.add('fr-hidden');
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('#sr-action-menu')) srCloseMenu();
});
window.addEventListener('scroll', srCloseMenu, true);
window.addEventListener('resize', srCloseMenu);

window.srAction = async function (action) {
    const r = srFind(srMenuId);
    srCloseMenu();
    if (!r) return;

    if (action === 'view') {
        srOpenModal(r);
        // Viewing is what moves a report from Pending to Open — and only
        // that. A report already open / in progress / resolved is left alone.
        if (r.admin_status === 'pending') await srPostStatus(r, 'view');
        return;
    }

    await srPostStatus(r, action);
};

// ==================== MODAL ====================
function srSetImage(imgId, missingId, src) {
    const img = document.getElementById(imgId);
    const missing = missingId ? document.getElementById(missingId) : null;
    if (src) {
        img.src = src;
        img.classList.remove('fr-hidden');
        missing?.classList.add('fr-hidden');
    } else {
        img.removeAttribute('src');
        img.classList.add('fr-hidden');
        missing?.classList.remove('fr-hidden');
    }
}

window.srZoom = function (src) {
    if (!src) return;
    document.getElementById('sr-lightbox-img').src = src;
    document.getElementById('sr-lightbox').classList.remove('fr-hidden');
};
window.srCloseZoom = function () {
    document.getElementById('sr-lightbox').classList.add('fr-hidden');
};

// Same thresholds/colors as the farmer and technician pages.
function srSeverityColor(pct) {
    const n = Number(pct);
    if (!Number.isFinite(n)) return 'text-light';
    if (n === 0) return 'text-success';
    if (n <= 30) return 'text-info';
    if (n <= 50) return 'text-warning';
    return 'text-danger';
}

function srUpdateTypeBadge(isPest) {
    document.getElementById('sr-type-badge').classList.toggle('is-disease', !isPest);
    document.getElementById('sr-type-badge-icon').className = isPest ? 'fa-solid fa-bug' : 'fa-solid fa-disease';
    document.getElementById('sr-type-badge-text').textContent = isPest ? 'Pest' : 'Disease';
}

function srUpdateGauge(confidence) {
    const fill = document.getElementById('sr-confidence-fill');
    const pct = Math.max(0, Math.min(100, Number(confidence) || 0));
    fill.style.strokeDasharray = `${SR_ARC_LEN}`;
    fill.style.strokeDashoffset = `${SR_ARC_LEN * (1 - pct / 100)}`;
    document.getElementById('sr-d-confidence').textContent = `${pct}%`;

    let color = '#10b981';
    if (pct < 50) color = '#ef4444';
    else if (pct < 80) color = '#f59e0b';
    fill.style.stroke = color;
    document.querySelector('#sr-block-gauge .rg-report-gauge-center').style.color = color;
}

// Snapshot the farmer submitted, flagged red where they marked it wrong —
// identical to the technician's review screen.
function srRenderInfo(r) {
    const flagged = r.flagged_sections || [];
    const info = r.info || {};

    document.querySelectorAll('#sr-block-name, #sr-block-gauge, #sr-block-severity, #sr-block-damagelevel')
        .forEach(block => block.classList.toggle('rg-flagged', flagged.includes(block.dataset.section)));

    let html = '';
    ['description','treatment','causes','nutrient','damage','grain','natural_enemies','prevention'].forEach(key => {
        if (!info[key]) return;
        const meta = srSectionMeta[key] || { icon: 'fa-circle-info', color: 'text-white' };
        html += `<div class="rg-flag-block rg-flag-row small ${flagged.includes(key) ? 'rg-flagged' : ''}" data-section="${key}">
                    <strong class="rg-flag-title d-block mb-2 ${meta.color}"><i class="fa-solid ${meta.icon} me-2"></i>${srEscape(srSections[key] || key)}</strong>
                    <div class="small text-light">${srEscape(info[key])}</div>
                 </div>`;
    });
    document.getElementById('sr-d-info').innerHTML = html;
}

function srRenderReview(r) {
    const pendingBox = document.getElementById('sr-pending-box');
    const reviewBox  = document.getElementById('sr-review-box');

    if (!r.review) {
        pendingBox.classList.remove('fr-hidden');
        reviewBox.classList.add('fr-hidden');
        return;
    }
    pendingBox.classList.add('fr-hidden');
    reviewBox.classList.remove('fr-hidden');

    const rv = r.review;
    document.getElementById('sr-r-by').textContent = rv.reviewed_by || '—';
    document.getElementById('sr-r-at').textContent = rv.reviewed_at || '—';

    const assess = document.getElementById('sr-r-assessment');
    assess.className = 'sr-assess ' + (rv.assessment || '');
    assess.textContent = srAssessmentLabels[rv.assessment] || rv.assessment || '—';

    const needImage = rv.assessment === 'need_image';
    document.getElementById('sr-r-need-image').classList.toggle('fr-hidden', !needImage);
    document.getElementById('sr-r-correction').classList.toggle('fr-hidden', needImage);

    document.getElementById('sr-r-orig-class').textContent = r.detection.class_name;
    document.getElementById('sr-r-orig-conf').textContent = (r.detection.confidence ?? 0) + '%';
    document.getElementById('sr-r-orig-sev').textContent = r.detection.severity_label || '—';

    document.getElementById('sr-r-new-class').textContent =
        rv.corrected_name || (rv.assessment === 'correct' ? 'Confirmed correct' : 'No change');
    document.getElementById('sr-r-new-sev').textContent =
        rv.corrected_severity || r.detection.severity_label || '—';

    const titles = rv.corrected_sections || [];
    document.getElementById('sr-r-titles-wrap').classList.toggle('fr-hidden', titles.length === 0);
    document.getElementById('sr-r-titles').textContent = titles.map(k => srSections[k] || k).join(', ');

    document.getElementById('sr-r-notes').textContent = rv.notes || '—';
    document.getElementById('sr-r-advice-wrap').classList.toggle('fr-hidden', !rv.advice);
    document.getElementById('sr-r-advice').textContent = rv.advice || '';
}

function srOpenModal(r) {
    srModalId = r.id;

    document.getElementById('sr-m-id').textContent = '#' + r.sr_code;
    const statusMeta = srStatuses[r.admin_status] || srStatuses.pending;
    const sb = document.getElementById('sr-m-status');
    sb.className = 'sr-badge sr-status-' + r.admin_status;
    sb.textContent = statusMeta.label;
    const tb = document.getElementById('sr-m-type');
    tb.className = 'sr-badge sr-type-' + r.type_key;
    tb.textContent = r.type;

    document.getElementById('sr-m-code').textContent = '#' + r.report_id;
    document.getElementById('sr-m-farmer').textContent = r.farmer_name;
    document.getElementById('sr-m-submitted').textContent = r.submitted_at || '—';
    document.getElementById('sr-m-escalated').textContent = r.escalated_at || '—';

    srSetImage('sr-d-image', 'sr-d-image-missing', r.detection.image);
    document.getElementById('sr-d-class').textContent = r.detection.class_name;

    const sevColor = srSeverityColor(r.detection.severity_percent);
    const sevEl = document.getElementById('sr-d-severity');
    sevEl.textContent = r.detection.severity_label || '—';
    sevEl.className = 'rg-report-stat-value ' + sevColor;
    const dmgEl = document.getElementById('sr-d-damage');
    dmgEl.textContent = Number.isFinite(Number(r.detection.severity_percent)) ? r.detection.severity_percent + '%' : '—';
    dmgEl.className = 'rg-report-stat-value ' + sevColor;

    document.getElementById('sr-d-source').textContent = srEngineLabels[r.detection.source] || r.detection.source || '—';

    // 'nutrient' only exists in a disease snapshot (pests never carry it).
    srUpdateTypeBadge(!(r.info && ('nutrient' in r.info)));
    srUpdateGauge(r.detection.confidence);

    const problems = r.problem_types || [];
    document.getElementById('sr-d-problems').innerHTML = problems.length
        ? problems.map(p => `<span class="fr-report-tag">${srEscape(p)}</span>`).join('')
        : '—';
    document.getElementById('sr-d-message').textContent = '"' + (r.message || '') + '"';

    document.getElementById('sr-d-suggested-wrap').classList.toggle('fr-hidden', !r.suggested_name);
    if (r.suggested_name) document.getElementById('sr-d-suggested').textContent = r.suggested_name;

    document.getElementById('sr-d-support-wrap').classList.toggle('fr-hidden', !r.support_image);
    if (r.support_image) srSetImage('sr-d-support', null, r.support_image);

    srRenderInfo(r);
    srRenderReview(r);

    const modal = document.getElementById('sr-modal');
    modal.classList.remove('fr-hidden');
    modal.scrollTop = 0;
    document.body.style.overflow = 'hidden';
}

window.srCloseModal = function () {
    document.getElementById('sr-modal').classList.add('fr-hidden');
    document.body.style.overflow = '';
    srModalId = null;
};

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!document.getElementById('sr-lightbox').classList.contains('fr-hidden')) { srCloseZoom(); return; }
    if (!document.getElementById('sr-modal').classList.contains('fr-hidden')) { srCloseModal(); return; }
    srCloseMenu();
});
</script>
@endsection