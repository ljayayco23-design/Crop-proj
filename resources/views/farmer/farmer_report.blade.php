{{--
    resources/views/farmer/farmer_report.blade.php

    Farmer side of the detection-feedback loop: the reports this farmer
    submitted, and the technician's resolution once one exists — PLUS the
    "Create a Report" flow, which lets the farmer start a report from ANY
    past scan in their History (not only the one just shown on the
    detection page): pick the pest/disease name, then pick which scan ID
    of that name, and the full snapshot for that exact scan is loaded.

    Everything comes from farmer_reports via FarmerReportController@present
    (and, for the create flow, @myDetectionsForReport) — the same snapshot
    shape the technician reviews, so all sides always show the identical
    detection, image and knowledge-base text.
--}}
@extends('layouts.farmer')

@section('title', 'RICEGUARD AI • Report Problem')

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

    /* Thumbnails behave like the History page ones: click to enlarge. */
    .fr-thumb {
        width: 88px; height: 88px; object-fit: cover; border-radius: 10px;
        background: #0b0f17; cursor: pointer; border: 2px solid transparent;
        transition: border-color .15s ease, transform .15s ease;
    }
    .fr-thumb:hover { border-color: #3b82f6; transform: scale(1.04); }
    .fr-thumb-missing {
        width: 88px; height: 88px; border-radius: 10px;
        background: rgba(148,163,184,.15); color: #94a3b8;
        display: flex; align-items: center; justify-content: center;
    }

    .fr-col-head {
        font-size: .74rem; text-transform: uppercase; letter-spacing: .05em;
        color: #94a3b8; margin-bottom: .5rem;
    }
    .fr-resolve-box { border: 1px solid #1f4d3a; background: rgba(16,185,129,.07); border-radius: 10px; padding: 12px; }
    .fr-wait-box { border: 1px solid #334155; background: rgba(148,163,184,.06); border-radius: 10px; padding: 14px; }

    .fr-lightbox {
        position: fixed; inset: 0; z-index: 1090; background: rgba(2,6,12,.85);
        display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    }
    .fr-lightbox img { max-width: 92vw; max-height: 88vh; border-radius: 12px; }

    /* Report photos are ALWAYS shown plain (thumbnails never carry boxes).
       YOLO11n's boxes are stored as data (detection_boxes) and painted as a
       canvas overlay only when the photo is opened full-size in the
       lightbox — same approach as History and the detection page. The
       model name is a normal chip in the header (next to the report ID),
       never drawn on top of the picture. */
    .rg-thumb-wrap { position: relative; display: inline-block; flex: 0 0 auto; }
    .rg-thumb-wrap img { display: block; border-radius: 12px; }
    .rg-thumb-canvas { position: absolute; top: 0; left: 0; pointer-events: none; border-radius: 12px; }
    .fr-model-chip {
        display: inline-flex; align-items: center; align-self: center;
        background: rgba(16,185,129,.16); color: #34d399;
        border: 1px solid rgba(16,185,129,.4);
        font-size: .68rem; font-weight: 800; letter-spacing: .03em;
        padding: 3px 9px; border-radius: 6px; text-transform: uppercase;
        white-space: nowrap;
    }
    .fr-lightbox-inner { display: flex; flex-direction: column; align-items: center; gap: 10px; }

    /* ---------- Mirrored detection header (same look everywhere) ----------
       Identical markup/classes to the "Report the Problem" modal on the
       detection page and to the technician's Farmer Reports review screen:
       thumbnail + type badge + name + confidence gauge up top, then
       Severity / Damage Level chips, then the knowledge-base text. Used
       both by "What I reported" (below) and by the new "Create a Report"
       modal's left-hand detail panel. */
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

    .rg-confidence-gauge { position: relative; width: 128px; height: 74px; flex: 0 0 auto; }
    .rg-confidence-gauge svg { width: 100%; height: 100%; display: block; overflow: visible; }
    .rg-confidence-gauge .gauge-track { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 16; stroke-linecap: round; }
    .rg-confidence-gauge .gauge-fill { fill: none; stroke-width: 16; stroke-linecap: round; }
    .rg-report-gauge-center {
        position: absolute; left: 0; right: 0; bottom: 2px;
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        color: #10b981;
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

    /* "Flagged" = ticked the matching problem in "What is wrong?", used in
       the Create a Report modal exactly like the detection page's modal:
       a boxed inset-red-outline for the header chips, a red underline for
       the knowledge-base text rows. */
    .rg-flag-block { transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }
    .rg-flag-row { border-bottom: 1px solid rgba(255,255,255,0.08); padding: 10px 2px 12px; }
    .rg-flag-row:last-child { border-bottom: none; }
    .rg-flag-row.rg-flagged { border-bottom-color: #ef4444; background: rgba(239, 68, 68, .06); }
    .rg-flag-row.rg-flagged .rg-flag-title,
    .rg-flag-row.rg-flagged strong { color: #f87171 !important; }
    .rg-flag-row.rg-flagged .rg-flag-title::after {
        content: " • marked as wrong"; font-size: .7rem; font-weight: 600; letter-spacing: .02em; color: #f87171;
    }
    .rg-flag-chip.rg-flagged {
        box-shadow: inset 0 0 0 1.5px #ef4444; background: rgba(239, 68, 68, .08); border-radius: 10px;
    }
    .rg-flag-chip.rg-flagged .rg-flag-title,
    .rg-flag-chip.rg-flagged .rg-report-name,
    .rg-flag-chip.rg-flagged .rg-report-stat-value,
    .rg-flag-chip.rg-flagged .rg-report-gauge-center { color: #f87171 !important; }
    .rg-flag-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; color: #94a3b8; }
    .rg-mirror-note { font-size: .74rem; color: #64748b; }

    /* ---------- Create a Report (floating modal) ----------
       Deliberately a LIGHT/near-transparent backdrop (not the usual dark
       dimmed scrim) so the report list behind it stays visible and the
       dialog reads as "floating" on top rather than blocking the page. */
    .rg-report-backdrop {
        position: fixed; inset: 0; z-index: 1080;
        background: rgba(2, 6, 12, 0.18);
        backdrop-filter: blur(2px);
        display: flex; align-items: flex-start; justify-content: center;
        padding: 2.5rem 1rem; overflow-y: auto;
    }
    .rg-report-backdrop.fr-hidden { display: none !important; }
    .rg-report-dialog {
        width: 100%; max-width: 960px;
        background: #121826;
        border: 1px solid #334155;
        border-radius: 14px;
        box-shadow: 0 18px 60px rgba(0,0,0,.55);
        max-height: 92vh;
        display: flex; flex-direction: column;
    }
    .rg-report-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 18px; border-bottom: 1px solid #263349; }
    .rg-report-body { padding: 16px 18px; overflow-y: auto; }
    .rg-report-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #263349; }
    .rg-report-input {
        background: #0f1522; border: 1px solid #334155; color: #e2e8f0;
        border-radius: 8px; font-size: .875rem;
    }
    .rg-report-input:focus { background: #0f1522; color: #fff; border-color: #10b981; box-shadow: none; }
    .rg-report-input option { background: #1a1f2b; color: #e2e8f0; }

    .rg-check-dd { position: relative; }
    .rg-check-dd-toggle {
        width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px; text-align: left;
        background: #0f1522; border: 1px solid #334155; color: #e2e8f0; border-radius: 8px; padding: 9px 12px; font-size: .875rem;
    }
    .rg-check-dd-toggle:hover { border-color: #475569; }
    .rg-check-dd-menu {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0;
        background: #1a1f2b; border: 1px solid #334155; border-radius: 10px;
        box-shadow: 0 10px 26px rgba(0,0,0,.5); padding: 6px; z-index: 30; max-height: 230px; overflow-y: auto;
    }
    .rg-check-dd-menu.fr-hidden { display: none !important; }
    .rg-check-item { display: flex; align-items: center; gap: 9px; padding: 7px 9px; border-radius: 7px; cursor: pointer; font-size: .85rem; color: #cbd5e1; }
    .rg-check-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .rg-check-item input { accent-color: #10b981; flex: 0 0 auto; }

    .cr-picker-label { font-size: .74rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; margin-bottom: .35rem; }
    .cr-empty-hint { border: 1px dashed #334155; border-radius: 10px; padding: 22px; text-align: center; color: #94a3b8; }

    /* ---------- Row actions: three-dot dropdown (View / Print / Delete) ---------- */
    .fr-action-dd { position: relative; display: inline-block; }
    .fr-action-dd-toggle {
        width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
        background: transparent; border: none; outline: none; color: #cbd5e1; border-radius: 8px;
    }
    .fr-action-dd-toggle:hover, .fr-action-dd-toggle:focus { background: rgba(255,255,255,.08); color: #fff; box-shadow: none; }
    .fr-action-dd-menu {
        position: absolute; top: calc(100% + 4px); right: 0; min-width: 150px;
        background: #1a1f2b; border: 1px solid #334155; border-radius: 10px;
        box-shadow: 0 10px 26px rgba(0,0,0,.5); padding: 6px; z-index: 40; text-align: left;
    }
    .fr-action-dd-menu.fr-hidden { display: none !important; }
    .fr-action-item {
        display: flex; align-items: center; gap: 9px; width: 100%; padding: 8px 10px;
        border-radius: 7px; cursor: pointer; font-size: .85rem; color: #cbd5e1;
        background: none; border: none; text-align: left;
    }
    .fr-action-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .fr-action-item.text-danger:hover { background: rgba(239,68,68,.12); color: #f87171 !important; }

    /* ===================== MOBILE (phones) ===================== */
    @media (max-width: 767px) {
        .page-header { gap: .5rem; margin-bottom: 1rem !important; }
        .page-header-title h4 { font-size: 1.05rem; }

        .fr-card { padding: .9rem !important; border-radius: 10px; }
        .fr-panel { padding: 11px; }

        /* ---- report list -> stacked cards ---- */
        .table-responsive { overflow: visible; }
        .fr-table { min-width: 0; display: block; font-size: .78rem; }
        .fr-table thead { display: none; }
        .fr-table, .fr-table tbody { display: block; width: 100%; }

        .fr-table tbody tr {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-areas:
                "id       detection"
                "problem  problem"
                "date     status"
                "actions  actions";
            column-gap: 12px;
            background: transparent;
            border: 1px solid #263349;
            border-radius: 14px;
            padding: 14px 14px 10px;
            margin-bottom: 12px;
        }
        .fr-table tbody tr:last-child { margin-bottom: 0; }

        .fr-table tbody td {
            display: block;
            width: 100%;
            padding: 0;
            border: none !important;
            white-space: normal;
            overflow-wrap: anywhere;
            background: transparent !important;
        }

        #fr-table-body td[data-label="Report ID"]  { grid-area: id; font-size: .95rem; margin-bottom: 10px; }
        #fr-table-body td[data-label="Detection"]  { grid-area: detection; text-align: right; margin-bottom: 10px; }
        #fr-table-body td[data-label="Problem"]    { grid-area: problem; margin-bottom: 8px; }
        #fr-table-body td[data-label="Date"]       { grid-area: date; }
        #fr-table-body td[data-label="Status"]     { grid-area: status; text-align: right; }
        #fr-table-body td[data-label="Action"] {
            grid-area: actions; text-align: right;
            padding-top: 10px; margin-top: 6px;
            border-top: 1px solid #1d2636 !important;
        }

        .fr-table td[data-label]:not([data-label="Report ID"]):not([data-label="Detection"]):not([data-label="Status"]):not([data-label="Action"])::before {
            content: attr(data-label);
            display: block;
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 3px;
        }

        /* ---- detail view (my report vs technician resolution) ---- */
        #fr-detail-view .fr-card { padding: .9rem !important; }
        .rg-report-top { gap: 10px; }
        .rg-report-thumb, #fr-d-image-missing, #cr-d-image-missing { width: 72px; height: 72px; }
        .rg-confidence-gauge { width: 118px; height: 70px; }
        .rg-report-name { font-size: 1rem; }
        .rg-report-stat { padding: 8px 10px; }

        /* ---- Create a Report (floating modal) ---- */
        .rg-report-backdrop { padding: 1rem .6rem; }
        .rg-report-dialog { border-radius: 12px; }
        .rg-report-head { padding: 12px 14px; }
        .rg-report-body { padding: 12px 14px; }
        .rg-report-foot { flex-wrap: wrap; padding: 10px 14px; }
        .rg-report-foot .btn { flex: 1 1 auto; }

        /* ---- lightbox ---- */
        .fr-lightbox { padding: 1rem .75rem; }
    }
</style>

<div class="nxl-content">
    <div class="page-header mb-4">
        <div class="page-header-title">
            <h4 class="m-b-10 fw-bold">Report Problem</h4>
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid">

            {{-- ============ LIST ============ --}}
            <div id="fr-list-view" class="fr-card p-3 p-md-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h6 class="mb-0 fw-bold text-white">My submitted reports</h6>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="openCreateReportModal()">
                        <i class="fa-solid fa-flag me-1"></i> Create a Report
                    </button>
                </div>

                @if(count($reports) === 0)
                    <p class="text-secondary mb-0">You haven't reported any detection yet. Use "Create a Report" above to report one of your past scans.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0 fr-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Detection</th>
                                    <th>Problem</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="fr-table-body">
                                @foreach($reports as $report)
                                    <tr>
                                        <td class="fw-bold" data-label="Report ID">#{{ $report['report_id'] }}</td>
                                        <td data-label="Detection">{{ $report['detection']['class_name'] }}</td>
                                        <td data-label="Problem">{{ $report['problem_summary'] }}</td>
                                        <td class="text-secondary" data-label="Date">{{ $report['date'] }}</td>
                                        <td data-label="Status">
                                            <span class="badge {{ $statuses[$report['status']]['class'] }}">
                                                {{ $statuses[$report['status']]['label'] }}
                                            </span>
                                        </td>
                                        <td class="text-end" data-label="Action">
                                            <div class="fr-action-dd">
                                                <button type="button" class="fr-action-dd-toggle" onclick="frToggleActionDropdown(this)" title="Actions">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>
                                                <div class="fr-action-dd-menu fr-hidden">
                                                    <button type="button" class="fr-action-item"
                                                            onclick="frCloseAllActionDropdowns(); frOpenReport('{{ $report['report_id'] }}')">
                                                        <i class="fa-solid fa-eye"></i> View
                                                    </button>
                                                    <button type="button" class="fr-action-item"
                                                            onclick="frCloseAllActionDropdowns(); frPrintReport('{{ $report['report_id'] }}')">
                                                        <i class="fa-solid fa-print"></i> Print
                                                    </button>
                                                    <button type="button" class="fr-action-item text-danger"
                                                            data-delete-url="{{ route('farmer.reports.destroy', $report['id']) }}"
                                                            data-report-code="{{ $report['report_id'] }}"
                                                            onclick="frCloseAllActionDropdowns(); frDeleteReport(this.dataset.deleteUrl, this.dataset.reportCode, this.closest('tr'))">
                                                        <i class="fa-solid fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- ============ DETAIL: my detection (left) vs technician's resolution (right) ============ --}}
            <div id="fr-detail-view" class="fr-hidden">
                <div class="fr-card p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="mb-0 fw-bold text-white">Report <span id="fr-d-id">#—</span></h6>
                            <span id="fr-d-model" class="fr-model-chip fr-hidden"></span>
                            <span id="fr-d-status" class="badge bg-warning text-dark">Pending</span>
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="frBackToList()">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back
                        </button>
                    </div>

                    <div class="row g-4">
                        {{-- LEFT: the detection as it was shown, styled the same as the
                             "Report the Problem" modal — thumb + type badge + gauge up
                             top, severity/damage chips below — red where I said it was
                             wrong. --}}
                        <div class="col-lg-6">
                            <div class="fr-col-head">What I reported</div>

                            <div class="rg-report-top mb-3">
                                <div class="rg-thumb-wrap">
                                    <img id="fr-d-image" class="rg-report-thumb fr-hidden" alt="Reported detection" onclick="frZoom(this.src, frDetailBoxes, frDetailSource)">
                                </div>
                                <div id="fr-d-image-missing" class="rg-report-thumb d-flex align-items-center justify-content-center text-secondary fr-hidden"><i class="fas fa-image"></i></div>
                                <div id="fr-block-name" class="rg-flag-block rg-flag-chip rg-report-top-info" data-section="name">
                                    <div id="fr-type-badge" class="rg-type-badge">
                                        <i class="fa-solid fa-bug" id="fr-type-badge-icon"></i>
                                        <span id="fr-type-badge-text">Pest</span>
                                    </div>
                                    <div id="fr-d-class" class="rg-report-name">—</div>
                                </div>
                                <div id="fr-block-gauge" class="rg-confidence-gauge rg-flag-block rg-flag-chip" data-section="name">
                                    <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
                                        <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100"></path>
                                        <path class="gauge-fill" id="fr-confidence-fill" d="M14,100 A86,86 0 0 1 186,100"></path>
                                    </svg>
                                    <div class="rg-report-gauge-center">
                                        <span id="fr-d-confidence">0%</span>
                                        <small>Confidence</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div id="fr-block-severity" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="severity">
                                        <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-gauge-high me-1"></i>Severity</div>
                                        <div id="fr-d-severity" class="rg-report-stat-value">—</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div id="fr-block-damagelevel" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="damagelevel">
                                        <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-wheat-awn me-1"></i>Damage Level</div>
                                        <div id="fr-d-damage" class="rg-report-stat-value">—</div>
                                    </div>
                                </div>
                            </div>

                            <div class="small text-light mb-1">My message</div>
                            <div id="fr-d-message" class="small fst-italic text-secondary mb-3">—</div>

                            <div id="fr-d-support-wrap" class="mb-3 fr-hidden">
                                <div class="small text-light mb-1">My supporting photo</div>
                                <img id="fr-d-support" class="fr-thumb" alt="Supporting photo" onclick="frZoom(this.src)">
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="small text-light">Detection information</div>
                                <span class="small text-secondary">Red = I marked this wrong</span>
                            </div>
                            <div id="fr-d-info"></div>
                        </div>

                        {{-- RIGHT: what the technician decided --}}
                        <div class="col-lg-6">
                            <div class="fr-col-head">Technician's resolution</div>

                            {{-- Still waiting --}}
                            <div id="fr-pending-box" class="fr-wait-box fr-hidden">
                                <div class="fw-bold text-white mb-1"><i class="fa-solid fa-clock me-2 text-warning"></i>Waiting for technician review</div>
                                <p class="small text-light mb-0">A technician will check your report. Their correction and guidance will appear here once they're done.</p>
                            </div>

                            {{-- Reviewed --}}
                            <div id="fr-review-box" class="fr-hidden">
                                <div class="mb-3 small text-light">
                                    Reviewed by <span id="fr-r-by" class="fw-bold text-white">—</span>
                                    · <span id="fr-r-at" class="text-secondary">—</span>
                                </div>

                                {{-- Technician asked for another photo --}}
                                <div id="fr-r-need-image" class="fr-wait-box mb-3 fr-hidden">
                                    <div class="fw-bold text-white mb-1"><i class="fa-solid fa-camera me-2 text-info"></i>The technician needs another photo</div>
                                    <p class="small text-light mb-0">Please take a clearer photo of the affected plant and submit a new report.</p>
                                </div>

                                {{-- Original vs corrected --}}
                                <div id="fr-r-correction" class="row g-2 align-items-stretch mb-3">
                                    <div class="col-6">
                                        <div class="fr-panel h-100">
                                            <div class="fr-flag-title mb-1" style="font-size:.72rem; color:#94a3b8;">Original AI detection</div>
                                            <div id="fr-r-orig-class" class="fw-bold text-light">—</div>
                                            <div class="small text-secondary">Confidence: <span id="fr-r-orig-conf">—</span></div>
                                            <div class="small text-secondary">Severity: <span id="fr-r-orig-sev" class="text-danger">—</span></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="fr-panel h-100" style="border-color:#1f4d3a; background: rgba(16,185,129,.07);">
                                            <div class="fr-flag-title mb-1" style="font-size:.72rem; color:#94a3b8;">Technician-corrected detection</div>
                                            <div id="fr-r-new-class" class="fw-bold text-success">—</div>
                                            <div class="small text-secondary">Severity: <span id="fr-r-new-sev" class="text-warning">—</span></div>
                                        </div>
                                    </div>
                                </div>

                                <div id="fr-r-titles-wrap" class="mb-3 fr-hidden">
                                    <div class="small text-light mb-1">Information the technician corrected</div>
                                    <div id="fr-r-titles" class="small text-warning">—</div>
                                </div>

                                <div class="fr-resolve-box mb-2">
                                    <div class="fw-bold text-white small mb-1"><i class="fa-solid fa-clipboard-check me-2 text-success"></i>Technician notes</div>
                                    <p id="fr-r-notes" class="small text-light mb-0">—</p>
                                </div>
                                <div id="fr-r-advice-wrap" class="fr-resolve-box">
                                    <div class="fw-bold text-white small mb-1"><i class="fa-solid fa-lightbulb me-2 text-success"></i>Guidance for you</div>
                                    <p id="fr-r-advice" class="small text-light mb-0">—</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div id="fr-lightbox" class="fr-lightbox fr-hidden" onclick="frCloseZoom()">
    <div class="fr-lightbox-inner" onclick="event.stopPropagation()">
        <span id="fr-lightbox-badge" class="fr-model-chip fr-hidden"></span>
        <div class="rg-thumb-wrap">
            <img id="fr-lightbox-img" src="" alt="Enlarged photo">
            <canvas id="fr-lightbox-canvas" class="rg-thumb-canvas fr-hidden"></canvas>
        </div>
    </div>
</div>

{{-- ============ Create a Report (floating modal) ============
     Pick a pest/disease name from History, then pick which scan ID of
     that name, and the full snapshot for that exact scan loads on the
     left. The right side collects the same inputs as the "Report the
     Problem" modal on the detection page: What is wrong, description,
     suggested correct pest/disease, and an optional supporting photo.
     Submits to the same endpoint used there, so it reaches the same
     technician queue (scoped to this farmer's own barangay). --}}
<div id="create-report-modal" class="rg-report-backdrop fr-hidden" onclick="if (event.target === this) closeCreateReportModal()">
    <div class="rg-report-dialog">
        <div class="rg-report-head">
            <h6 class="mb-0 fw-bold text-white">Create a Report</h6>
            <button type="button" class="btn btn-sm btn-link text-secondary p-0" onclick="closeCreateReportModal()" title="Close">
                <i class="fa-solid fa-xmark fa-lg"></i>
            </button>
        </div>

        <div class="rg-report-body">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="cr-picker-label">1. Which pest/disease?</div>
                    <select id="cr-name-select" class="form-select rg-report-input" onchange="crOnNameChange()">
                        <option value="">— Select a pest/disease —</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="cr-picker-label">2. Which scan (ID)?</div>
                    <select id="cr-id-select" class="form-select rg-report-input" onchange="crOnIdChange()" disabled>
                        <option value="">— Select the name first —</option>
                    </select>
                </div>
            </div>

            <div id="cr-empty-hint" class="cr-empty-hint mb-3">
                <i class="fa-solid fa-arrow-up-9-1 mb-2 d-block fs-4"></i>
                Choose a pest/disease and a scan ID above to load that detection.
            </div>

            <div id="cr-detail-wrap" class="row g-3 fr-hidden">
                {{-- LEFT: the exact scan chosen above, read-only. --}}
                <div class="col-lg-6">
                    <div class="fr-panel h-100">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="small fw-bold text-white">Detection information</div>
                                <span id="cr-d-model" class="fr-model-chip fr-hidden"></span>
                            </div>
                            <span class="rg-mirror-note">Red underline = marked as wrong</span>
                        </div>

                        <div class="rg-report-top mb-3">
                            <div class="rg-thumb-wrap">
                                <img id="cr-d-image" class="rg-report-thumb fr-hidden" alt="Selected scan" onclick="frZoom(this.src, crSelectedInstance ? crSelectedInstance.boxes : null, crSelectedInstance ? crSelectedInstance.source : null)">
                            </div>
                            <div id="cr-d-image-missing" class="rg-report-thumb d-flex align-items-center justify-content-center text-secondary fr-hidden"><i class="fas fa-image"></i></div>
                            <div id="cr-block-name" class="rg-flag-block rg-flag-chip rg-report-top-info" data-section="name">
                                <div id="cr-type-badge" class="rg-type-badge">
                                    <i class="fa-solid fa-bug" id="cr-type-badge-icon"></i>
                                    <span id="cr-type-badge-text">Pest</span>
                                </div>
                                <div id="cr-d-class" class="rg-report-name">—</div>
                            </div>
                            <div id="cr-block-gauge" class="rg-confidence-gauge rg-flag-block rg-flag-chip" data-section="name">
                                <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
                                    <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100"></path>
                                    <path class="gauge-fill" id="cr-confidence-fill" d="M14,100 A86,86 0 0 1 186,100"></path>
                                </svg>
                                <div class="rg-report-gauge-center">
                                    <span id="cr-d-confidence">0%</span>
                                    <small>Confidence</small>
                                </div>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div id="cr-block-severity" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="severity">
                                    <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-gauge-high me-1"></i>Severity</div>
                                    <div id="cr-d-severity" class="rg-report-stat-value">—</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div id="cr-block-damagelevel" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="damagelevel">
                                    <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-wheat-awn me-1"></i>Damage Level</div>
                                    <div id="cr-d-damage" class="rg-report-stat-value">—</div>
                                </div>
                            </div>
                        </div>

                        <div id="cr-d-info"></div>
                    </div>
                </div>

                {{-- RIGHT: same inputs as "Report the Problem" on the detection page. --}}
                <div class="col-lg-6">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white">What is wrong?</label>
                        <div class="rg-check-dd" id="cr-problem-dd">
                            <button type="button" class="rg-check-dd-toggle" onclick="toggleCrProblemDropdown()">
                                <span id="cr-problem-summary" class="text-secondary">Select all that apply</span>
                                <i class="fa-solid fa-chevron-down small"></i>
                            </button>
                            <div id="cr-problem-menu" class="rg-check-dd-menu fr-hidden">
                                @foreach($problemTypes as $problem)
                                    <label class="rg-check-item"><input type="checkbox" class="cr-problem-check" value="{{ $problem }}"> {{ $problem }}</label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white" for="cr-description">
                            Please describe the problem <span class="text-danger">*</span>
                        </label>
                        <textarea id="cr-description" rows="3" class="form-control rg-report-input"
                                  placeholder="Describe what looks wrong with this result..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-white" for="cr-suggested">Suggested correct pest/disease (optional)</label>
                        <select id="cr-suggested" class="form-select rg-report-input">
                            <option value="">— Select if you know —</option>
                        </select>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small fw-bold text-white" for="cr-image">Upload supporting image (optional)</label>
                        <input type="file" id="cr-image" accept="image/*" class="form-control rg-report-input">
                    </div>
                </div>
            </div>
        </div>

        <div class="rg-report-foot">
            <button type="button" class="btn btn-secondary" onclick="closeCreateReportModal()">Cancel</button>
            <button type="button" id="cr-submit-btn" class="btn btn-success fw-bold" onclick="submitCreateReport()">
                <i class="fa-solid fa-paper-plane me-2"></i> Submit Report
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const frReports  = @json($reports);
const frSections = @json($sections);
const frStatuses = @json($statuses);

// ---------- YOLO11n box overlay + model name (same approach as History) ----------
// The stored photo is always the plain photo. Its boxes travel separately in
// detection.boxes / instance.boxes ({boxes, src_w, src_h}, in the original
// photo's pixel coordinates) and are painted on a canvas ONLY inside the
// lightbox, once it is actually visible — never on the small thumbnails.
const FR_MODEL_LABELS = { yolo11n: 'YOLO11n', groq: 'Groq AI', model: 'MobileNetV2' };

// Tracks whichever boxes/source are currently loaded into the detail
// view's thumbnail, so its onclick (fixed markup, can't read live state
// otherwise) always zooms with the right overlay.
let frDetailBoxes = null;
let frDetailSource = null;

// Fills a model-name chip (or hides it when the engine is unknown).
function frSetModelBadge(badgeId, source) {
    const badge = document.getElementById(badgeId);
    if (!badge) return;
    const label = FR_MODEL_LABELS[source] || null;
    if (label) {
        badge.textContent = label;
        badge.classList.remove('fr-hidden');
    } else {
        badge.classList.add('fr-hidden');
    }
}

// Boxes are in the original photo's coordinate space; the lightbox <img>
// renders the whole photo (object-fit: contain), so scale/offset come from
// the photo's own size, not just the element box.
function frContainRect(boxW, boxH, srcW, srcH) {
    const scale = Math.min(boxW / srcW, boxH / srcH);
    const renderW = srcW * scale, renderH = srcH * scale;
    return { x: (boxW - renderW) / 2, y: (boxH - renderH) / 2, width: renderW, height: renderH };
}

function frDrawLightboxBoxes() {
    const canvas = document.getElementById('fr-lightbox-canvas');
    const img = document.getElementById('fr-lightbox-img');
    if (!canvas || !img) return;

    // While the lightbox is display:none the <img> reads as 0x0 — nothing to
    // draw against yet. A later call (image load / next frame / resize) does it.
    const w = img.clientWidth, h = img.clientHeight;
    if (!w || !h) return;

    canvas.width = w;
    canvas.height = h;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);

    const data = window.frLightboxBoxes;
    const srcW = Number(data && data.src_w), srcH = Number(data && data.src_h);
    if (!data || !Array.isArray(data.boxes) || !data.boxes.length || !srcW || !srcH) {
        canvas.classList.add('fr-hidden');
        return;
    }
    canvas.classList.remove('fr-hidden');

    const rect = frContainRect(w, h, srcW, srcH);
    const scaleX = rect.width / srcW;
    const scaleY = rect.height / srcH;

    data.boxes.forEach(d => {
        if (!d || !d.box) return;
        const x = rect.x + d.box.x * scaleX;
        const y = rect.y + d.box.y * scaleY;
        const bw = d.box.width * scaleX;
        const bh = d.box.height * scaleY;

        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, bw, bh);

        const label = d.label || d.className || '';
        if (!label) return;
        ctx.font = '600 13px system-ui, sans-serif';
        const textW = ctx.measureText(label).width + 10;
        const labelH = 19;
        ctx.fillStyle = '#10b981';
        ctx.fillRect(x, Math.max(0, y - labelH), textW, labelH);
        ctx.fillStyle = '#06281f';
        ctx.fillText(label, x + 5, Math.max(13, y - 5));
    });
}

// Redraw whenever the enlarged photo's rendered size changes for any reason:
// the lightbox just having opened (0x0 -> real size), the image finishing
// loading, or a window resize.
if (window.ResizeObserver) {
    new ResizeObserver(() => frDrawLightboxBoxes()).observe(document.getElementById('fr-lightbox-img'));
}

window.frBackToList = function() {
    document.getElementById('fr-detail-view').classList.add('fr-hidden');
    document.getElementById('fr-list-view').classList.remove('fr-hidden');
};

// ==================== ROW ACTIONS: three-dot dropdown ====================
window.frToggleActionDropdown = function(btn) {
    const menu = btn.closest('.fr-action-dd')?.querySelector('.fr-action-dd-menu');
    if (!menu) return;
    const isOpen = !menu.classList.contains('fr-hidden');
    frCloseAllActionDropdowns();
    if (!isOpen) menu.classList.remove('fr-hidden');
};
window.frCloseAllActionDropdowns = function() {
    document.querySelectorAll('.fr-action-dd-menu').forEach(m => m.classList.add('fr-hidden'));
};
document.addEventListener('click', function (e) {
    if (!e.target.closest('.fr-action-dd')) frCloseAllActionDropdowns();
});

// ==================== DELETE (real delete — removes the row in the DB) ====================
window.frDeleteReport = async function(url, code, rowEl) {
    if (!confirm('Delete report ' + code + '? This cannot be undone.')) return;

    try {
        const response = await fetch(url, {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            }
        });

        if (response.status === 401 || response.status === 419) {
            alert('Your login session has expired. The page will now refresh so you can log back in.');
            window.location.reload();
            return;
        }

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'The report could not be deleted.');
        }

        window.location.reload();
    } catch (err) {
        alert(err.message || 'Something went wrong while deleting the report.');
    }
};

// Same half-circle arc length used everywhere else this gauge appears
// (detection page's report modal, technician's review screen).
const FR_CONFIDENCE_ARC_LEN = Math.PI * 86;

function frUpdateTypeBadge(isPest) {
    const badge = document.getElementById('fr-type-badge');
    const icon = document.getElementById('fr-type-badge-icon');
    const text = document.getElementById('fr-type-badge-text');
    if (!badge || !icon || !text) return;
    badge.classList.toggle('is-disease', !isPest);
    icon.className = isPest ? 'fa-solid fa-bug' : 'fa-solid fa-disease';
    text.textContent = isPest ? 'Pest' : 'Disease';
}

function frUpdateGauge(confidence) {
    const fill = document.getElementById('fr-confidence-fill');
    const valueEl = document.getElementById('fr-d-confidence');
    const centerEl = document.querySelector('#fr-block-gauge .rg-report-gauge-center');
    if (!fill || !valueEl) return;
    const pct = Math.max(0, Math.min(100, confidence || 0));
    fill.style.strokeDasharray = `${FR_CONFIDENCE_ARC_LEN}`;
    fill.style.strokeDashoffset = `${FR_CONFIDENCE_ARC_LEN * (1 - pct / 100)}`;
    valueEl.textContent = `${pct}%`;

    let gaugeColor = '#10b981';
    if (pct < 50) gaugeColor = '#ef4444';
    else if (pct < 80) gaugeColor = '#f59e0b';
    fill.style.stroke = gaugeColor;
    if (centerEl) centerEl.style.color = gaugeColor;
}

function frSeverityColor(pct) {
    const n = Number(pct);
    if (!Number.isFinite(n)) return 'text-light';
    if (n === 0) return 'text-success';
    if (n <= 30) return 'text-info';
    if (n <= 50) return 'text-warning';
    return 'text-danger';
}

// Mirrors how History renders user_detections.image_path: show the stored
// data URI if there is one, otherwise a placeholder tile.
function frSetImage(imgId, missingId, src) {
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

window.frZoom = function(src, boxesData, source) {
    if (!src) return;
    const img = document.getElementById('fr-lightbox-img');
    const canvas = document.getElementById('fr-lightbox-canvas');
    window.frLightboxBoxes = boxesData || null;

    // Clear any overlay left from the previous photo, then show the
    // lightbox FIRST so the <img> has a real size to measure.
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    canvas.classList.add('fr-hidden');
    frSetModelBadge('fr-lightbox-badge', source);

    img.onload = frDrawLightboxBoxes;
    img.src = src;
    document.getElementById('fr-lightbox').classList.remove('fr-hidden');
    // Already-decoded (cached / same data URI) images don't fire onload again.
    requestAnimationFrame(frDrawLightboxBoxes);
};
window.frCloseZoom = function() {
    document.getElementById('fr-lightbox').classList.add('fr-hidden');
    window.frLightboxBoxes = null;
};

window.frOpenReport = function(id) {
    const r = frReports.find(x => x.report_id === id);
    if (!r) return;

    document.getElementById('fr-d-id').textContent = '#' + r.report_id;
    const meta = frStatuses[r.status] || frStatuses.pending;
    const badge = document.getElementById('fr-d-status');
    badge.textContent = meta.label;
    badge.className = 'badge ' + meta.class;

    frSetImage('fr-d-image', 'fr-d-image-missing', r.detection.image);
    frDetailBoxes = r.detection.boxes || null;
    frDetailSource = r.detection.source || null;
    frSetModelBadge('fr-d-model', frDetailSource);
    document.getElementById('fr-d-class').textContent = r.detection.class_name;

    // 'nutrient' only ever appears in info for a disease result (the
    // detection page hides that section entirely for pests before
    // snapshotting it), so its presence/absence tells us which type
    // badge to show — same heuristic the technician's page uses.
    frUpdateTypeBadge(!(r.info && ('nutrient' in r.info)));
    frUpdateGauge(r.detection.confidence);

    const sevColor = frSeverityColor(r.detection.severity_percent);
    const sevEl = document.getElementById('fr-d-severity');
    sevEl.textContent = r.detection.severity_label || '—';
    sevEl.className = 'rg-report-stat-value ' + sevColor;
    const dmgEl = document.getElementById('fr-d-damage');
    dmgEl.textContent = Number.isFinite(Number(r.detection.severity_percent)) ? r.detection.severity_percent + '%' : '—';
    dmgEl.className = 'rg-report-stat-value ' + sevColor;

    document.getElementById('fr-d-message').textContent = '"' + (r.message || '') + '"';

    document.getElementById('fr-d-support-wrap').classList.toggle('fr-hidden', !r.support_image);
    if (r.support_image) frSetImage('fr-d-support', null, r.support_image);

    frRenderInfo(r);
    frRenderReview(r);

    document.getElementById('fr-list-view').classList.add('fr-hidden');
    document.getElementById('fr-detail-view').classList.remove('fr-hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

function frEscape(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

// Same icon + color pairing used on the detection page for each
// knowledge-base block, and the same section keys the report modal wrote
// into flagged_sections — so "red" here means exactly what it meant there.
const frSectionMeta = {
    description:     { icon: 'fa-circle-info',        color: 'text-white'   },
    treatment:       { icon: 'fa-spray-can-sparkles', color: 'text-success' },
    causes:          { icon: 'fa-question-circle',    color: 'text-warning' },
    prevention:      { icon: 'fa-shield-heart',       color: 'text-info'    },
    damage:          { icon: 'fa-wheat-awn',          color: 'text-danger'  },
    natural_enemies: { icon: 'fa-bug-slash',          color: 'text-success' },
    nutrient:        { icon: 'fa-leaf',               color: 'text-warning' },
    grain:           { icon: 'fa-seedling',           color: 'text-danger'  }
};

function frRenderInfo(r) {
    const flagged = r.flagged_sections || [];
    const info = r.info || {};

    document.querySelectorAll('#fr-block-name, #fr-block-gauge, #fr-block-severity, #fr-block-damagelevel')
        .forEach(block => block.classList.toggle('rg-flagged', flagged.includes(block.dataset.section)));

    let html = '';
    ['description','treatment','causes','nutrient','damage','grain','natural_enemies','prevention'].forEach(key => {
        if (!info[key]) return;
        const meta = frSectionMeta[key] || { icon: 'fa-circle-info', color: 'text-white' };
        html += `<div class="rg-flag-block rg-flag-row small ${flagged.includes(key) ? 'rg-flagged' : ''}" data-section="${key}">
                    <strong class="rg-flag-title d-block mb-2 ${meta.color}"><i class="fa-solid ${meta.icon} me-2"></i>${frSections[key]}</strong>
                    <div class="small text-light">${frEscape(info[key])}</div>
                 </div>`;
    });

    document.getElementById('fr-d-info').innerHTML = html;
}

function frRenderReview(r) {
    const pendingBox = document.getElementById('fr-pending-box');
    const reviewBox  = document.getElementById('fr-review-box');

    if (!r.review) {
        pendingBox.classList.remove('fr-hidden');
        reviewBox.classList.add('fr-hidden');
        return;
    }

    pendingBox.classList.add('fr-hidden');
    reviewBox.classList.remove('fr-hidden');

    document.getElementById('fr-r-by').textContent = r.review.reviewed_by || '—';
    document.getElementById('fr-r-at').textContent = r.review.reviewed_at || '—';

    const needImage = r.review.assessment === 'need_image';
    document.getElementById('fr-r-need-image').classList.toggle('fr-hidden', !needImage);
    document.getElementById('fr-r-correction').classList.toggle('fr-hidden', needImage);

    document.getElementById('fr-r-orig-class').textContent = r.detection.class_name;
    document.getElementById('fr-r-orig-conf').textContent = r.detection.confidence + '%';
    document.getElementById('fr-r-orig-sev').textContent = r.detection.severity_label;

    document.getElementById('fr-r-new-class').textContent =
        r.review.corrected_name || (r.review.assessment === 'correct' ? 'Confirmed correct' : 'No change');
    document.getElementById('fr-r-new-sev').textContent =
        r.review.corrected_severity || r.detection.severity_label;

    const titles = r.review.corrected_sections || [];
    document.getElementById('fr-r-titles-wrap').classList.toggle('fr-hidden', titles.length === 0);
    document.getElementById('fr-r-titles').textContent = titles.map(k => frSections[k] || k).join(', ');

    document.getElementById('fr-r-notes').textContent = r.review.notes || '—';
    document.getElementById('fr-r-advice-wrap').classList.toggle('fr-hidden', !r.review.advice);
    document.getElementById('fr-r-advice').textContent = r.review.advice || '';
}

// ==================== PRINT ====================
// Builds a clean, light, print-friendly page that mirrors the same data and
// layout as the "What I reported" panel (thumbnail, type badge, name,
// confidence, severity/damage, detection information — red where the
// farmer marked it wrong) plus "Technician's resolution", from the SAME
// frReports data the on-screen detail view uses, so print never drifts
// from what's shown on screen.
function frPrintEscape(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

window.frPrintReport = function(id) {
    const r = frReports.find(x => x.report_id === id);
    if (!r) return;

    const isPest   = !(r.info && ('nutrient' in r.info));
    const typeLabel = isPest ? 'Pest' : 'Disease';
    const typeColor = isPest ? '#059669' : '#dc2626';
    const confPct  = Math.max(0, Math.min(100, r.detection.confidence || 0));
    const sevPct   = Number.isFinite(Number(r.detection.severity_percent)) ? Number(r.detection.severity_percent) : null;
    const flagged  = r.flagged_sections || [];
    const info     = r.info || {};
    const meta     = frStatuses[r.status] || frStatuses.pending;

    // Same half-circle arc geometry as the on-screen gauge, and the same
    // confidence-based color thresholds (red/amber/green), so print shows
    // the identical gauge instead of just a number.
    const PRINT_ARC_LEN = Math.PI * 86;
    const confDashOffset = PRINT_ARC_LEN * (1 - confPct / 100);
    let confColor = '#10b981';
    if (confPct < 50) confColor = '#ef4444';
    else if (confPct < 80) confColor = '#f59e0b';

    // Same severity color thresholds as frSeverityColor(), mapped to print-
    // friendly hex colors (that function returns Bootstrap text- classes
    // tuned for the dark theme).
    function printSevColor(pct) {
        const n = Number(pct);
        if (!Number.isFinite(n)) return '#475569';
        if (n === 0) return '#16a34a';
        if (n <= 30) return '#0284c7';
        if (n <= 50) return '#d97706';
        return '#dc2626';
    }
    const sevColor = printSevColor(sevPct);

    const infoRowsHtml = ['description','treatment','causes','nutrient','damage','grain','natural_enemies','prevention']
        .filter(key => info[key])
        .map(key => {
            const isFlagged = flagged.includes(key);
            return `
              <div style="border:1px solid ${isFlagged ? '#fca5a5' : '#e2e8f0'}; background:${isFlagged ? '#fef2f2' : '#f8fafc'}; border-radius:8px; padding:10px 12px; margin-bottom:8px;">
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; color:${isFlagged ? '#dc2626' : '#334155'}; margin-bottom:4px;">
                  ${frPrintEscape(frSections[key] || key)}${isFlagged ? ' • marked as wrong' : ''}
                </div>
                <div style="font-size:13px; color:#1e293b; white-space:pre-wrap;">${frPrintEscape(info[key])}</div>
              </div>`;
        }).join('');

    let reviewHtml;
    if (!r.review) {
        reviewHtml = `<p style="color:#64748b; font-size:13px;">Waiting for technician review — no resolution yet.</p>`;
    } else {
        const rv = r.review;
        const needImage = rv.assessment === 'need_image';
        reviewHtml = `
          <div style="font-size:12px; color:#475569; margin-bottom:10px;">
            Reviewed by <strong>${frPrintEscape(rv.reviewed_by || '—')}</strong> · ${frPrintEscape(rv.reviewed_at || '—')}
          </div>
          ${needImage ? `
            <div style="border:1px solid #93c5fd; background:#eff6ff; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:13px; color:#1e3a8a;">
              The technician requested another photo. Please submit a new report with a clearer photo.
            </div>` : `
            <div style="display:flex; gap:10px; margin-bottom:12px;">
              <div style="flex:1; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px;">
                <div style="font-size:11px; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Original AI detection</div>
                <div style="font-weight:700; color:#1e293b;">${frPrintEscape(r.detection.class_name)}</div>
                <div style="font-size:12px; color:#475569;">Confidence: ${confPct}%</div>
                <div style="font-size:12px; color:#475569;">Severity: ${frPrintEscape(r.detection.severity_label || '—')}</div>
              </div>
              <div style="flex:1; border:1px solid #bbf7d0; background:#f0fdf4; border-radius:8px; padding:10px 12px;">
                <div style="font-size:11px; text-transform:uppercase; color:#64748b; margin-bottom:4px;">Technician-corrected detection</div>
                <div style="font-weight:700; color:#15803d;">${frPrintEscape(rv.corrected_name || (rv.assessment === 'correct' ? 'Confirmed correct' : 'No change'))}</div>
                <div style="font-size:12px; color:#475569;">Severity: ${frPrintEscape(rv.corrected_severity || r.detection.severity_label || '—')}</div>
              </div>
            </div>
            ${(rv.corrected_sections || []).length ? `<div style="font-size:12px; color:#92400e; margin-bottom:12px;"><strong>Information corrected:</strong> ${frPrintEscape((rv.corrected_sections || []).map(k => frSections[k] || k).join(', '))}</div>` : ''}
          `}
          <div style="border:1px solid #bbf7d0; background:#f0fdf4; border-radius:8px; padding:10px 12px; margin-bottom:8px;">
            <div style="font-size:12px; font-weight:700; color:#166534; margin-bottom:4px;">Technician notes</div>
            <div style="font-size:13px; color:#1e293b; white-space:pre-wrap;">${frPrintEscape(rv.notes || '—')}</div>
          </div>
          ${rv.advice ? `
          <div style="border:1px solid #bbf7d0; background:#f0fdf4; border-radius:8px; padding:10px 12px;">
            <div style="font-size:12px; font-weight:700; color:#166534; margin-bottom:4px;">Guidance for you</div>
            <div style="font-size:13px; color:#1e293b; white-space:pre-wrap;">${frPrintEscape(rv.advice)}</div>
          </div>` : ''}
        `;
    }

    const html = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Report ${frPrintEscape(r.report_id)}</title>
<style>
  * { box-sizing: border-box; }
  body { font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color:#1e293b; margin:0; padding:28px; background:#fff; }
  .pr-header { display:flex; justify-content:space-between; align-items:flex-start; border-bottom:2px solid #1e293b; padding-bottom:12px; margin-bottom:18px; }
  .pr-title { font-size:20px; font-weight:800; margin:0; }
  .pr-status { display:inline-block; font-size:11px; font-weight:700; padding:4px 10px; border-radius:999px; background:#f1f5f9; color:#334155; margin-top:6px; }
  .pr-top { display:flex; align-items:center; gap:16px; margin-bottom:16px; flex-wrap:wrap; }
  .pr-thumb { width:96px; height:96px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0; }
  .pr-badge { display:inline-flex; align-items:center; gap:6px; font-weight:700; font-size:12px; padding:5px 12px; border-radius:999px; margin-bottom:6px; }
  .pr-name { font-size:17px; font-weight:800; }
  .pr-conf { text-align:center; }
  .pr-conf-gauge { position:relative; width:150px; height:86px; }
  .pr-conf-gauge svg { width:100%; height:100%; display:block; overflow:visible; }
  .pr-conf-center { position:absolute; left:0; right:0; bottom:0; text-align:center; }
  .pr-conf-num { font-size:20px; font-weight:800; line-height:1.1; }
  .pr-conf-label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.03em; }
  .pr-stats { display:flex; gap:10px; margin-bottom:16px; }
  .pr-stat { flex:1; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px 12px; }
  .pr-stat-label { font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:#64748b; margin-bottom:3px; }
  .pr-stat-value { font-size:15px; font-weight:800; }
  .pr-section-title { font-size:13px; font-weight:800; text-transform:uppercase; letter-spacing:.03em; color:#334155; margin:20px 0 10px; border-bottom:1px solid #e2e8f0; padding-bottom:6px; }
  .pr-msg { font-style:italic; color:#475569; font-size:13px; margin-bottom:14px; }
  @media print { body { padding:0; } }
</style>
</head>
<body>
  <div class="pr-header">
    <div>
      <p class="pr-title">Report ${frPrintEscape(r.report_id)}</p>
      <span class="pr-status">${frPrintEscape(meta.label)}</span>
    </div>
    <div style="text-align:right; font-size:12px; color:#64748b;">
      <div>Submitted: ${frPrintEscape(r.date || '—')}</div>
    </div>
  </div>

  <div class="pr-section-title" style="margin-top:0;">What I reported</div>
  <div class="pr-top">
    ${r.detection.image ? `<img class="pr-thumb" src="${r.detection.image}">` : ''}
    <div>
      <div class="pr-badge" style="background:${isPest ? 'rgba(5,150,105,.12)' : 'rgba(220,38,38,.12)'}; color:${typeColor};">${typeLabel}</div>
      <div class="pr-name">${frPrintEscape(r.detection.class_name)}</div>
    </div>
    <div class="pr-conf" style="margin-left:auto;">
      <div class="pr-conf-gauge">
        <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
          <path d="M14,100 A86,86 0 0 1 186,100" style="fill:none; stroke:#e2e8f0; stroke-width:16; stroke-linecap:round;"></path>
          <path d="M14,100 A86,86 0 0 1 186,100" style="fill:none; stroke:${confColor}; stroke-width:16; stroke-linecap:round; stroke-dasharray:${PRINT_ARC_LEN}; stroke-dashoffset:${confDashOffset};"></path>
        </svg>
        <div class="pr-conf-center">
          <div class="pr-conf-num" style="color:${confColor};">${confPct}%</div>
          <div class="pr-conf-label">Confidence</div>
        </div>
      </div>
    </div>
  </div>

  <div class="pr-stats">
    <div class="pr-stat">
      <div class="pr-stat-label">Severity</div>
      <div class="pr-stat-value" style="color:${sevColor};">${frPrintEscape(r.detection.severity_label || '—')}</div>
    </div>
    <div class="pr-stat">
      <div class="pr-stat-label">Damage Level</div>
      <div class="pr-stat-value" style="color:${sevColor};">${sevPct !== null ? sevPct + '%' : '—'}</div>
    </div>
  </div>

  <div class="pr-msg">"${frPrintEscape(r.message || '')}"</div>

  ${r.support_image ? `<div style="margin-bottom:16px;"><div class="pr-stat-label" style="margin-bottom:6px;">My supporting photo</div><img src="${r.support_image}" style="width:120px; height:120px; object-fit:cover; border-radius:10px; border:1px solid #e2e8f0;"></div>` : ''}

  <div class="pr-section-title">Detection information</div>
  ${infoRowsHtml || '<p style="color:#64748b; font-size:13px;">No details recorded.</p>'}

  <div class="pr-section-title">Technician's resolution</div>
  ${reviewHtml}

</body>
</html>`;

    const win = window.open('', '_blank', 'width=860,height=900');
    if (!win) { alert('Please allow pop-ups to print this report.'); return; }
    win.document.open();
    win.document.write(html);
    win.document.close();
    win.onload = function () {
        win.focus();
        win.print();
    };
};

// ==================== CREATE A REPORT ====================
// Source data: every one of this farmer's past scans, grouped by
// pest/disease name (FarmerReportController@myDetectionsForReport) — the
// same snapshot shape History already shows.
const crDetections   = @json($myDetections);
const crDiseaseNames = @json($diseaseNames);
const crPestNames    = @json($pestNames);
const CR_ARC_LEN = Math.PI * 86;

let crSelectedGroup = null;
let crSelectedInstance = null;

window.openCreateReportModal = function() {
    const nameSelect = document.getElementById('cr-name-select');
    nameSelect.innerHTML = '<option value="">— Select a pest/disease —</option>';
    crDetections.forEach(group => {
        const opt = document.createElement('option');
        opt.value = group.class_key;
        const n = group.instances.length;
        opt.textContent = `${group.class_name} (${group.is_pest ? 'Pest' : 'Disease'}) — ${n} scan${n === 1 ? '' : 's'}`;
        nameSelect.appendChild(opt);
    });

    const idSelect = document.getElementById('cr-id-select');
    idSelect.innerHTML = '<option value="">— Select the name first —</option>';
    idSelect.disabled = true;

    document.getElementById('cr-detail-wrap').classList.add('fr-hidden');
    document.getElementById('cr-empty-hint').classList.remove('fr-hidden');

    crResetForm();

    const suggested = document.getElementById('cr-suggested');
    suggested.innerHTML = '<option value="">— Select if you know —</option>';
    const addGroup = (label, map) => {
        const entries = Object.entries(map || {});
        if (!entries.length) return;
        const g = document.createElement('optgroup');
        g.label = label;
        entries.forEach(([key, name]) => {
            const o = document.createElement('option');
            o.value = key;
            o.textContent = name;
            g.appendChild(o);
        });
        suggested.appendChild(g);
    };
    addGroup('Diseases', crDiseaseNames);
    addGroup('Pests', crPestNames);

    if (!crDetections.length) {
        document.getElementById('cr-empty-hint').innerHTML =
            '<i class="fa-solid fa-circle-info mb-2 d-block fs-4"></i>You have no scans in your History yet. Scan a plant first, then come back here to report it.';
    }

    document.getElementById('create-report-modal').classList.remove('fr-hidden');
};

window.closeCreateReportModal = function() {
    closeCrProblemDropdown();
    document.getElementById('create-report-modal').classList.add('fr-hidden');
};

window.crOnNameChange = function() {
    const key = document.getElementById('cr-name-select').value;
    const idSelect = document.getElementById('cr-id-select');

    crSelectedGroup = crDetections.find(g => g.class_key === key) || null;
    crSelectedInstance = null;
    document.getElementById('cr-detail-wrap').classList.add('fr-hidden');
    document.getElementById('cr-empty-hint').classList.remove('fr-hidden');

    if (!crSelectedGroup) {
        idSelect.innerHTML = '<option value="">— Select the name first —</option>';
        idSelect.disabled = true;
        return;
    }

    idSelect.disabled = false;
    idSelect.innerHTML = '<option value="">— Select a scan ID —</option>';
    crSelectedGroup.instances.forEach(inst => {
        const o = document.createElement('option');
        o.value = inst.id;
        o.textContent = `ID #${inst.id} — ${inst.date || '—'}`;
        idSelect.appendChild(o);
    });
};

window.crOnIdChange = function() {
    const id = document.getElementById('cr-id-select').value;
    if (!crSelectedGroup || !id) {
        crSelectedInstance = null;
        document.getElementById('cr-detail-wrap').classList.add('fr-hidden');
        document.getElementById('cr-empty-hint').classList.remove('fr-hidden');
        return;
    }
    crSelectedInstance = crSelectedGroup.instances.find(i => String(i.id) === String(id)) || null;
    if (!crSelectedInstance) return;

    renderCrDetail(crSelectedGroup, crSelectedInstance);
    document.getElementById('cr-empty-hint').classList.add('fr-hidden');
    document.getElementById('cr-detail-wrap').classList.remove('fr-hidden');
    crApplyFlags();
};

function crEscape(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function renderCrDetail(group, inst) {
    frSetImage('cr-d-image', 'cr-d-image-missing', inst.image);
    frSetModelBadge('cr-d-model', inst.source || null);

    const badge = document.getElementById('cr-type-badge');
    const icon = document.getElementById('cr-type-badge-icon');
    const text = document.getElementById('cr-type-badge-text');
    badge.classList.toggle('is-disease', !group.is_pest);
    icon.className = group.is_pest ? 'fa-solid fa-bug' : 'fa-solid fa-disease';
    text.textContent = group.is_pest ? 'Pest' : 'Disease';

    document.getElementById('cr-d-class').textContent = group.class_name;

    const pct = Math.max(0, Math.min(100, inst.confidence || 0));
    const fill = document.getElementById('cr-confidence-fill');
    fill.style.strokeDasharray = `${CR_ARC_LEN}`;
    fill.style.strokeDashoffset = `${CR_ARC_LEN * (1 - pct / 100)}`;
    let gColor = '#10b981';
    if (pct < 50) gColor = '#ef4444'; else if (pct < 80) gColor = '#f59e0b';
    fill.style.stroke = gColor;
    document.getElementById('cr-d-confidence').textContent = pct + '%';
    const gaugeCenter = document.querySelector('#cr-block-gauge .rg-report-gauge-center');
    if (gaugeCenter) gaugeCenter.style.color = gColor;

    const sevColor = crSeverityColor(inst.severity_percent);
    const sevEl = document.getElementById('cr-d-severity');
    sevEl.textContent = inst.severity_label || '—';
    sevEl.className = 'rg-report-stat-value ' + sevColor;
    const dmgEl = document.getElementById('cr-d-damage');
    dmgEl.textContent = Number.isFinite(Number(inst.severity_percent)) ? inst.severity_percent + '%' : '—';
    dmgEl.className = 'rg-report-stat-value ' + sevColor;

    const kb = inst.kb || {};
    const rows = [
        ['description', 'Description / About', 'fa-circle-info', 'text-white', kb.description],
        ['treatment', 'Recommended Treatments', 'fa-spray-can-sparkles', 'text-success', kb.treatments],
        ['causes', 'Common Causes / Biology', 'fa-question-circle', 'text-warning', kb.causes],
    ];
    if (group.is_pest) {
        rows.push(['damage', 'Damage Symptoms', 'fa-wheat-awn', 'text-danger', kb.grain_damage]);
        rows.push(['natural_enemies', 'Natural Enemies', 'fa-bug-slash', 'text-success', kb.natural_enemies]);
    } else {
        rows.push(['nutrient', 'Nutrient Deficiency', 'fa-leaf', 'text-warning', kb.nutrient_deficiency]);
        rows.push(['grain', 'Grain / Paddy Damage', 'fa-seedling', 'text-danger', kb.grain_damage]);
    }
    rows.push(['prevention', 'Prevention Tips', 'fa-shield-heart', 'text-info', kb.prevention]);

    let html = '';
    rows.forEach(([key, label, icon2, color, value]) => {
        html += `<div class="rg-flag-block rg-flag-row small" data-section="${key}">
                    <strong class="rg-flag-title d-block mb-2 ${color}"><i class="fa-solid ${icon2} me-2"></i>${label}</strong>
                    <div class="small text-light">${crEscape(value || '—')}</div>
                 </div>`;
    });
    document.getElementById('cr-d-info').innerHTML = html;
}

function crSeverityColor(pct) {
    const n = Number(pct);
    if (!Number.isFinite(n)) return 'text-light';
    if (n === 0) return 'text-success';
    if (n <= 30) return 'text-info';
    if (n <= 50) return 'text-warning';
    return 'text-danger';
}

// ---- "What is wrong?" dropdown — same behavior/section map as the
// detection page's "Report a Problem" modal. ----
const crProblemSectionMap = {
    'Wrong pest/disease detection': ['name'],
    'Wrong severity level':         ['severity'],
    'Wrong damage level':           ['damagelevel'],
    'Wrong management/treatment':   ['treatment'],
    'Wrong causes':                 ['causes'],
    'Wrong symptoms':               ['description', 'damage', 'grain'],
    'Wrong natural enemies':        ['natural_enemies'],
    'Wrong prevention information': ['prevention'],
    'Other system problem':         []
};

window.toggleCrProblemDropdown = function() {
    document.getElementById('cr-problem-menu').classList.toggle('fr-hidden');
};
function closeCrProblemDropdown() {
    document.getElementById('cr-problem-menu')?.classList.add('fr-hidden');
}
function crSelectedProblems() {
    return Array.from(document.querySelectorAll('.cr-problem-check:checked')).map(cb => cb.value);
}
function crUpdateProblemSummary() {
    const chosen = crSelectedProblems();
    const summary = document.getElementById('cr-problem-summary');
    if (!summary) return;
    if (!chosen.length) {
        summary.textContent = 'Select all that apply';
        summary.className = 'text-secondary';
    } else {
        summary.textContent = chosen.length === 1 ? chosen[0] : `${chosen.length} problems selected`;
        summary.className = 'text-white';
    }
}
function crApplyFlags() {
    const chosen = crSelectedProblems();
    const flagged = new Set();
    chosen.forEach(p => (crProblemSectionMap[p] || []).forEach(s => flagged.add(s)));

    document.querySelectorAll('#create-report-modal .rg-flag-block').forEach(block => {
        block.classList.toggle('rg-flagged', flagged.has(block.dataset.section));
    });

    window.crCurrentFlags = Array.from(flagged);
}
document.addEventListener('change', function (e) {
    if (e.target.classList.contains('cr-problem-check')) {
        crUpdateProblemSummary();
        crApplyFlags();
    }
});
document.addEventListener('click', function (e) {
    const dd = document.getElementById('cr-problem-dd');
    if (dd && !dd.contains(e.target)) closeCrProblemDropdown();
});

function crResetForm() {
    document.querySelectorAll('.cr-problem-check').forEach(cb => cb.checked = false);
    crUpdateProblemSummary();
    document.getElementById('cr-description').value = '';
    document.getElementById('cr-image').value = '';
    window.crCurrentFlags = [];
}

function crCollectInfoSnapshot(group, inst) {
    const kb = inst.kb || {};
    const info = {};
    info.description = kb.description || '—';
    info.treatment = kb.treatments || '—';
    info.causes = kb.causes || '—';
    if (group.is_pest) {
        info.damage = kb.grain_damage || '—';
        info.natural_enemies = kb.natural_enemies || '—';
    } else {
        info.nutrient = kb.nutrient_deficiency || '—';
        info.grain = kb.grain_damage || '—';
    }
    info.prevention = kb.prevention || '—';
    info.name = group.class_name;
    info.severity = inst.severity_label || '—';
    info.damagelevel = (Number.isFinite(Number(inst.severity_percent)) ? inst.severity_percent : 0) + '%';
    return info;
}

function crReadSupportImage() {
    const input = document.getElementById('cr-image');
    const file = input?.files?.[0];
    if (!file) return Promise.resolve(null);
    return new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(file);
    });
}

window.submitCreateReport = async function() {
    if (!crSelectedGroup || !crSelectedInstance) {
        alert('Please choose the pest/disease and the scan ID first.');
        return;
    }
    const problems = crSelectedProblems();
    const description = document.getElementById('cr-description').value.trim();

    if (!problems.length) {
        alert('Please choose at least one option under "What is wrong?".');
        return;
    }
    if (!description) {
        alert('Please describe the problem before submitting.');
        return;
    }

    const submitBtn = document.getElementById('cr-submit-btn');
    const original = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Submitting...';

    try {
        const payload = {
            class_key:        crSelectedGroup.class_key,
            class_name:       crSelectedGroup.class_name,
            confidence:       crSelectedInstance.confidence || 0,
            severity_label:   crSelectedInstance.severity_label || null,
            severity_percent: Number.isFinite(Number(crSelectedInstance.severity_percent)) ? Number(crSelectedInstance.severity_percent) : 0,
            source:           crSelectedInstance.source || 'model',
            image_base64:     crSelectedInstance.image || null,
            support_image:    await crReadSupportImage(),
            info:             crCollectInfoSnapshot(crSelectedGroup, crSelectedInstance),
            problem_types:    problems,
            flagged_sections: window.crCurrentFlags || [],
            message:          description,
            suggested_class:  document.getElementById('cr-suggested').value || null
        };

        // The scan's YOLO11n boxes ({boxes, src_w, src_h}, same shape History
        // stores) travel with the report so the enlarged photo can show them.
        // Without this the report keeps only the plain photo and the zoom
        // has nothing to draw.
        const scanBoxes = crSelectedInstance.boxes;
        if (scanBoxes && Array.isArray(scanBoxes.boxes) && scanBoxes.boxes.length && scanBoxes.src_w && scanBoxes.src_h) {
            payload.detection_boxes = scanBoxes.boxes;
            payload.boxes_src_w     = scanBoxes.src_w;
            payload.boxes_src_h     = scanBoxes.src_h;
        }

        const response = await fetch("{{ route('farmer.reports.store') }}", {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
            body: JSON.stringify(payload)
        });

        if (response.status === 401 || response.status === 419) {
            alert('Your login session has expired. The page will now refresh so you can log back in.');
            window.location.reload();
            return;
        }

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'The report could not be saved.');
        }

        closeCreateReportModal();
        alert('Report #' + result.report_code + ' submitted! A technician in your area will review it.');
        window.location.reload();
    } catch (err) {
        alert(err.message || 'Something went wrong while submitting your report.');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = original;
    }
};
</script>
@endsection