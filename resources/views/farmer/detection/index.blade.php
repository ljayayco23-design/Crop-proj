@extends('layouts.farmer')

@section('title', 'RICEGUARD AI • Rice Disease & Pest Detector')

@section('content')
<style>
    .hidden { display: none !important; }

    /* Upload | Camera mode tabs — compact pill switcher at the top of the
       capture card, mirrors the old engine-switch pill styling. */
    .mode-tabs { gap: 4px; width: fit-content; }
    .mode-tab {
        border: none;
        background: transparent;
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 999px;
        transition: background-color .15s ease, color .15s ease;
    }
    .mode-tab:hover:not(.active) { color: #e2e8f0; }
    .mode-tab.active { background: #10b981; color: #06281f; box-shadow: 0 1px 4px rgba(0,0,0,.35); }

    /* Classify button: while it's disabled (no image yet, or a
       classification is in flight) it must look and feel muted — no
       hover glow, no pointer cursor — instead of looking clickable. */
    #classify-btn:disabled {
        opacity: .55;
        cursor: not-allowed !important;
        pointer-events: none;
        filter: saturate(.6);
    }
    #classify-btn:disabled:hover { background-color: inherit; }

    /* Results panel eases in instead of just popping into view once the
       outer columns have finished sliding. */
    #results-panel { animation: rgResultFadeIn .5s cubic-bezier(.4,0,.2,1); }
    @keyframes rgResultFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* Capture box: hosts upload-empty / image-preview / camera-live /
       camera-error states. Only one .capture-state is visible at a time. */
    #capture-box {
        transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
        border: 2px dashed rgba(16,185,129,.35);
        background: radial-gradient(circle at 50% 15%, rgba(16,185,129,.10), rgba(15,23,42,.35) 72%);
    }
    #capture-box:hover { border-color: #10b981; box-shadow: 0 0 0 4px rgba(16,185,129,.08); }
    .capture-state { min-height: 280px; }
    .capture-state.hidden { display: none !important; }
    #state-upload-empty, #state-camera-error { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2.5rem; cursor: pointer; }
    #state-image-preview img { background: #000; }
    #state-camera-live video { border-radius: 0; }

    /* Upload empty-state: glowing icon badge + gradient CTA + format chips. */
    .rg-upload-icon-badge {
        width: 84px; height: 84px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.1rem;
        background: linear-gradient(135deg, rgba(16,185,129,.25), rgba(16,185,129,.05));
        box-shadow: 0 0 0 1px rgba(16,185,129,.28), 0 10px 26px rgba(16,185,129,.18);
        animation: rgUploadFloat 3s ease-in-out infinite;
    }
    .rg-upload-icon-badge i { font-size: 2rem; color: #10b981; }
    @keyframes rgUploadFloat {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    .rg-upload-btn {
        background: linear-gradient(135deg, #10b981, #059669);
        border: none; color: #06281f; font-weight: 700;
        box-shadow: 0 8px 20px rgba(16,185,129,.28);
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .rg-upload-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(16,185,129,.38); color: #06281f; }
    .rg-upload-hints { display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }
    .rg-upload-chip {
        display: inline-flex; align-items: center;
        background: rgba(255,255,255,.05);
        color: #94a3b8; font-size: .74rem; font-weight: 600;
        padding: 5px 11px; border-radius: 999px;
    }

    /* Compact icon-selects used for the field picker and dialect picker —
       styled to disappear into the header instead of looking like a form
       control, so they don't eat up header space. */
    .compact-picker { display: flex; align-items: center; gap: 4px; }
    .compact-picker select {
        background: transparent;
        border: none;
        color: #e2e8f0;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 2px 4px;
        max-width: 110px;
    }
    .compact-picker select:focus { outline: none; box-shadow: none; }
    .compact-picker select option { background: #1a1f2b; color: #e2e8f0; }

    /* ---------- Pill badges (top bar + area picker) ----------
       Shared "chip" look used by the model-status pill, the dialect
       switcher and the area/field picker — no border anywhere, just a
       soft dark fill, so they read as one consistent badge family. */
    .pill-badge {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(255,255,255,0.06);
        border: none !important;
        border-radius: 999px;
        padding: 7px 14px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #e2e8f0;
        white-space: nowrap;
    }
    .pill-badge.compact-picker { padding: 6px 12px 6px 14px; }
    .pill-badge .compact-picker,
    .pill-badge select { border: none !important; }
    #status.pill-badge { border: none !important; }

    .page-header-top {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
    }
    .page-header-pills { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }

    /* ---------- Sliding detection layout ----------
       Upload card starts centered/full width; once a result exists it
       eases over to the left column while the results card grows in
       from the right — both animate together on the same timing. */
    /* align-items must stay flex-start by default: with only the upload
       card visible (no result yet), stretch would force it to fill the
       row's full cross-size and inflate a huge empty bordered box below
       the content. It only switches to stretch once a second real card
       (the results panel) exists, which is what keeps the two card
       bottoms lined up. --bs-gutter-y is also zeroed here so Bootstrap's
       per-column top margin can't introduce a sub-pixel top mismatch
       between the two cards. */
    .detect-row { display: flex; flex-wrap: wrap; align-items: flex-start; --bs-gutter-y: 0; }
    .detect-row.has-result { align-items: stretch; }
    .detect-row > * { margin-top: 0; }
    .detect-col-upload {
        flex: 0 0 100%;
        max-width: 700px;
        margin: 0 auto;
        transition: flex-basis .6s cubic-bezier(.4,0,.2,1), max-width .6s cubic-bezier(.4,0,.2,1), margin .6s cubic-bezier(.4,0,.2,1);
        will-change: flex-basis, max-width;
    }
    .detect-col-results {
        flex: 0 0 0%;
        max-width: 0;
        opacity: 0;
        overflow: hidden;
        transform: translateX(28px);
        transition: flex-basis .6s cubic-bezier(.4,0,.2,1), max-width .6s cubic-bezier(.4,0,.2,1), opacity .45s ease .12s, transform .6s cubic-bezier(.4,0,.2,1);
        will-change: flex-basis, max-width, opacity, transform;
    }
    .detect-row.has-result .detect-col-upload {
        flex: 0 0 41.6667%;
        max-width: 41.6667%;
        margin: 0;
    }
    .detect-row.has-result .detect-col-results {
        flex: 0 0 58.3333%;
        max-width: 58.3333%;
        opacity: 1;
        overflow: visible;
        transform: translateX(0);
    }
    @media (max-width: 991.98px) {
        .detect-col-upload, .detect-row.has-result .detect-col-upload {
            flex: 0 0 100% !important; max-width: 100% !important; margin: 0 !important;
        }
        .detect-col-results, .detect-row.has-result .detect-col-results {
            flex: 0 0 100% !important; max-width: 100% !important;
        }
    }

    /* ---------- Classification header: half-circle confidence gauge ----------
       A big semicircle arc (not a full ring) with the result icon and the
       "Confidence NN%" label burned into the flat side underneath the arc.
       Color of both the arc and the label shifts with the confidence value. */
    .rg-result-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; }
    .rg-result-head-left { display: flex; align-items: center; gap: 18px; min-width: 0; }
    .rg-confidence-gauge { position: relative; width: 190px; height: 108px; flex: 0 0 auto; }
    .rg-confidence-gauge svg { width: 100%; height: 100%; display: block; overflow: visible; }
    .rg-confidence-gauge .gauge-track { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 16; stroke-linecap: round; }
    .rg-confidence-gauge .gauge-fill {
        fill: none;
        stroke: #10b981;
        stroke-width: 16;
        stroke-linecap: round;
        transition: stroke-dasharray .8s cubic-bezier(.4,0,.2,1), stroke-dashoffset .8s cubic-bezier(.4,0,.2,1), stroke .3s ease;
    }
    .rg-confidence-center {
        position: absolute; left: 0; right: 0; bottom: 4px;
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        color: #10b981;
        transition: color .3s ease;
    }
    .rg-confidence-center i { font-size: 1.3rem; line-height: 1; margin-bottom: 3px; }
    .rg-confidence-center span { font-size: 1rem; font-weight: 800; color: inherit; white-space: nowrap; }
    .rg-result-name-wrap { min-width: 0; }
    .rg-result-eyebrow { font-size: 0.72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #64748b; margin-bottom: 2px; }
    .rg-type-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(16,185,129,0.14);
        color: #10b981;
        font-weight: 700;
        font-size: 0.8rem;
        padding: 7px 14px;
        border-radius: 999px;
        flex: 0 0 auto;
    }
    .rg-type-badge.is-disease { background: rgba(239,68,68,0.14); color: #f87171; }

    /* ---------- About/description card — sits above the tab bar ---------- */
    .rg-about-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.08);
        border-radius: 12px;
        padding: 14px 16px;
    }

    /* ---------- Tab content blocks (Treatment / Causes / Prevention / More Info) ----------
       Borderless by design — a soft fill only, so the tab panels read as one
       continuous surface instead of a stack of boxed cards. */
    .rg-content-block {
        background: rgba(255,255,255,0.03);
        border-radius: 12px;
    }

    /* ---------- Result tabs (Treatment / Causes / Prevention / More Info) ----------
       A single segmented bar, edge-to-edge with the card — flex:1 on every
       button means the four tabs always divide the full width evenly with
       nothing left over on the right, and the same four slots stay in the
       same order/position at any viewport width (no wrap, no reflow). */
    .rg-tabs {
        display: flex;
        flex-wrap: nowrap;
        gap: 4px;
        margin-bottom: 14px;
        background: rgba(255,255,255,0.04);
        border-radius: 12px;
        padding: 4px;
        width: 100%;
    }
    .rg-tab-btn {
        flex: 1 1 0;
        min-width: 0;
        border: none;
        background: transparent;
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 700;
        padding: 10px 8px;
        border-radius: 9px;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background-color .15s ease, color .15s ease;
    }
    .rg-tab-btn:hover:not(.active) { background: rgba(255,255,255,0.09); color: #e2e8f0; }
    .rg-tab-btn.active { background: #10b981; color: #06281f; box-shadow: 0 1px 4px rgba(0,0,0,.35); }
    .rg-tab-panel { display: none; }
    .rg-tab-panel.active { display: block; }

    /* Engine dropdown, sits directly next to the Classify button. */
    .engine-dropdown { position: relative; flex: 0 0 auto; }
    .engine-dropdown-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        height: 100%;
        min-height: 58px;
        padding: 0 14px;
        white-space: nowrap;
    }
    .engine-dropdown-menu {
        position: absolute;
        bottom: calc(100% + 6px);
        left: 0;
        min-width: 190px;
        background: #1a1f2b;
        border: 1px solid #334155;
        border-radius: 10px;
        box-shadow: 0 6px 20px rgba(0,0,0,.45);
        padding: 6px;
        z-index: 20;
    }
    .engine-dropdown-menu.hidden { display: none !important; }
    .engine-option {
        display: flex;
        align-items: center;
        width: 100%;
        border: none;
        background: transparent;
        color: #94a3b8;
        font-size: 0.85rem;
        font-weight: 600;
        padding: 8px 10px;
        border-radius: 8px;
        text-align: left;
        transition: background-color .15s ease, color .15s ease;
    }
    .engine-option:hover:not(.active) { background: rgba(255,255,255,.06); color: #e2e8f0; }
    .engine-option.active { background: #10b981; color: #06281f; }
    .engine-option:disabled { opacity: 0.4; cursor: not-allowed; }

    /* ---------- Report a Problem (farmer detection feedback) ----------
       UI ONLY for now, on purpose: the modal collects the report and shows
       the success popup client-side. Nothing is persisted, no request is
       sent, and it does not reach the technician queue yet — that comes in
       a later step. */
    .rg-report-backdrop {
        position: fixed; inset: 0; z-index: 1080;
        background: rgba(2, 6, 12, .72);
        display: flex; align-items: center; justify-content: center;
        padding: 1rem; overflow-y: auto;
    }
    .rg-report-backdrop.hidden { display: none !important; }
    .rg-report-dialog {
        width: 100%; max-width: 560px;
        background: #121826;
        border: 1px solid #334155;
        border-radius: 14px;
        box-shadow: 0 18px 50px rgba(0,0,0,.6);
        max-height: 92vh;
        display: flex; flex-direction: column;
    }
    .rg-report-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 18px; border-bottom: 1px solid #263349;
    }
    .rg-report-body { padding: 16px 18px; overflow-y: auto; }
    .rg-report-foot {
        display: flex; justify-content: flex-end; gap: 8px;
        padding: 12px 18px; border-top: 1px solid #263349;
    }
    .rg-report-thumb {
        width: 92px; height: 92px; object-fit: cover;
        border-radius: 12px; background: #000; flex: 0 0 auto;
        box-shadow: 0 4px 14px rgba(0,0,0,.4);
    }

    /* ---------- Report modal header: thumb + name/type + confidence gauge ----------
       Mirrors the Detection Report card layout: image on the left, the
       badge/name in the middle, the confidence gauge on the right. */
    .rg-report-top { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .rg-report-top-info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
    .rg-report-type-badge { align-self: flex-start; }
    .rg-report-name { font-size: 1.15rem; font-weight: 800; color: #fff; line-height: 1.2; }
    .rg-report-gauge { width: 128px; height: 74px; }
    .rg-report-gauge-center {
        position: absolute; left: 0; right: 0; bottom: 2px;
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
        color: #10b981;
    }
    .rg-report-gauge-center span { font-size: 1.1rem; font-weight: 800; line-height: 1.1; color: inherit; }
    .rg-report-gauge-center small { font-size: .65rem; font-weight: 600; color: #94a3b8; letter-spacing: .02em; }

    /* Severity / Damage Level stat chips, sitting right below the header row. */
    .rg-report-stat {
        background: rgba(255,255,255,0.04);
        border-radius: 10px;
        padding: 10px 12px;
        height: 100%;
    }
    .rg-report-stat-label { font-size: .72rem; font-weight: 600; color: #94a3b8; margin-bottom: 3px; }
    .rg-report-stat-value { font-size: 1rem; font-weight: 800; color: #fff; }
    .rg-report-input {
        background: #0f1522; border: 1px solid #334155; color: #e2e8f0;
        border-radius: 8px; font-size: .875rem;
    }
    .rg-report-input:focus {
        background: #0f1522; color: #fff;
        border-color: #10b981; box-shadow: none;
    }
    .rg-report-input option { background: #1a1f2b; color: #e2e8f0; }

    /* "What is wrong?" — checkbox dropdown (multi-choice). */
    .rg-check-dd { position: relative; }
    .rg-check-dd-toggle {
        width: 100%; display: flex; align-items: center;
        justify-content: space-between; gap: 8px; text-align: left;
        background: #0f1522; border: 1px solid #334155; color: #e2e8f0;
        border-radius: 8px; padding: 9px 12px; font-size: .875rem;
    }
    .rg-check-dd-toggle:hover { border-color: #475569; }
    .rg-check-dd-menu {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0;
        background: #1a1f2b; border: 1px solid #334155; border-radius: 10px;
        box-shadow: 0 10px 26px rgba(0,0,0,.5);
        padding: 6px; z-index: 30; max-height: 230px; overflow-y: auto;
    }
    .rg-check-dd-menu.hidden { display: none !important; }
    .rg-check-item {
        display: flex; align-items: center; gap: 9px;
        padding: 7px 9px; border-radius: 7px; cursor: pointer;
        font-size: .85rem; color: #cbd5e1;
    }
    .rg-check-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .rg-check-item input { accent-color: #10b981; flex: 0 0 auto; }

    /* Wider dialog: the report modal now mirrors the whole detection
       result, so it needs more room than the success popup. */
    .rg-report-dialog.rg-report-dialog-lg { max-width: 780px; }

    /* A "flagged" block = the farmer ticked the matching problem in the
       dropdown, applied ONLY inside the modal — the real results panel is
       never restyled. Two flavors:
       - .rg-flag-row  = the mirrored info list (description/treatment/...):
         no border box, just a bottom underline that turns red.
       - .rg-flag-chip = the header name/severity/damage chips: a soft
         filled card that gets a red inset outline instead. */
    .rg-flag-block { transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease; }

    .rg-flag-row {
        border-bottom: 1px solid rgba(255,255,255,0.08);
        padding: 10px 2px 12px;
    }
    .rg-flag-row.rg-flagged {
        border-bottom-color: #ef4444;
        background: rgba(239, 68, 68, .06);
    }
    .rg-flag-row.rg-flagged .rg-flag-title,
    .rg-flag-row.rg-flagged strong { color: #f87171 !important; }
    .rg-flag-row.rg-flagged .rg-flag-title::after {
        content: " • marked as wrong";
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .02em;
        color: #f87171;
    }

    .rg-flag-chip.rg-flagged {
        box-shadow: inset 0 0 0 1.5px #ef4444;
        background: rgba(239, 68, 68, .08);
        border-radius: 10px;
    }
    .rg-flag-chip.rg-flagged .rg-flag-title,
    .rg-flag-chip.rg-flagged .rg-report-name,
    .rg-flag-chip.rg-flagged .rg-report-stat-value,
    .rg-flag-chip.rg-flagged .rg-report-gauge-center { color: #f87171 !important; }

    .rg-mirror-note { font-size: .74rem; color: #64748b; }

    /* Mobile Device Adjustments */
    @media (max-width: 576px) {
        /* Shrink capture box so it fits without scrolling */
        #capture-box, .capture-state {
            min-height: 220px !important;
        }
        #state-upload-empty, #state-camera-error {
            padding: 1.5rem !important;
        }
        #state-upload-empty .btn {
            width: 100%; /* Full width button on mobile */
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
        .engine-dropdown-btn span { display: none; }

        /* Scale text */
        .page-header-title h4 { font-size: 1.25rem; }

        .page-header-pills { width: 100%; justify-content: flex-start; }
        .rg-confidence-gauge { width: 140px; height: 84px; }
        .rg-confidence-gauge .gauge-track, .rg-confidence-gauge .gauge-fill { stroke-width: 12; }
        .rg-confidence-center i { font-size: 1.05rem; }
        .rg-confidence-center span { font-size: 0.82rem; }
        .rg-tabs { gap: 0; }
        .rg-tab-btn { padding: 8px 6px; font-size: 0.72rem; }
        .rg-tab-btn i { margin-right: 4px !important; }
    }
</style>

<div class="nxl-content">
    <div class="page-header mb-4">
        <div class="page-header-top">
            <div class="page-header-title">
                <h4 class="m-b-10 fw-bold">Rice Disease & Pest Detector</h4>
            </div>
            <div class="page-header-pills">
                <div class="pill-badge compact-picker" title="Dialect">
                    <i class="fa-solid fa-language text-info small"></i>
                    <select id="language-selector">
                        <option value="tagalog" selected>Tagalog</option>
                        <option value="english">English</option>
                        <option value="cebuano">Cebuano</option>
                        <option value="hiligaynon">Hiligaynon</option>
                    </select>
                </div>
                <div class="pill-badge compact-picker {{ (isset($farmFields) && count($farmFields) > 1) ? '' : 'hidden' }}" title="Farm field">
                    <i class="fa-solid fa-map-marker-alt text-success small"></i>
                    <select id="field-selector">
                        @foreach($farmFields ?? [] as $field)
                            <option value="{{ $field['value'] }}">{{ $field['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="status" class="pill-badge bg-warning text-dark">Model loading...</div>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4 detect-row" id="detect-row">

                <div class="detect-col-upload" id="upload-col">
                   <div class="card bg-dark border-secondary h-100">
    <div class="card-header border-secondary d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Upload Rice Plant Image</h5>
    </div>
    <div class="card-body">

                            <div class="mode-tabs d-flex bg-dark border border-secondary rounded-pill p-1 mb-3">
                                <button type="button" class="mode-tab active" data-mode="upload" onclick="setUploadMode('upload')">
                                    <i class="fa-solid fa-upload me-1"></i> Upload
                                </button>
                                <button type="button" class="mode-tab" data-mode="camera" onclick="setUploadMode('camera')">
                                    <i class="fa-solid fa-camera me-1"></i> Camera
                                </button>
                            </div>

                            <div id="capture-box" class="rounded-3 text-center mb-4 position-relative overflow-hidden">
                                <input type="file" id="file-input" accept="image/*" style="display:none;">
                                <canvas id="camera-canvas" style="display:none;"></canvas>

                                <!-- Upload: nothing selected yet -->
                                <div id="state-upload-empty" class="capture-state" onclick="browsePhoto()">
                                    <div class="rg-upload-icon-badge">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                    </div>
                                    <h5 class="mb-1">Drag & drop your rice photo here</h5>
                                    <p class="text-secondary small mb-3">or click below to choose a file</p>
                                    <button type="button" onclick="event.stopPropagation(); browsePhoto()" class="btn rg-upload-btn px-5 py-2">
                                        <i class="fa-solid fa-folder-open me-2"></i> BROWSE PHOTO
                                    </button>
                                    <div class="rg-upload-hints mt-4">
                                        <span class="rg-upload-chip"><i class="fa-solid fa-file-image me-1"></i>JPG / PNG</span>
                                        <span class="rg-upload-chip"><i class="fa-solid fa-leaf me-1"></i>Clear leaf or plant photo</span>
                                    </div>
                                </div>

                                <!-- Upload: image selected — fills the box; click it to pick another -->
                                <div id="state-image-preview" class="capture-state hidden position-relative" style="cursor:pointer;" onclick="browsePhoto()">
                                    <img id="preview-image" class="w-100" alt="Preview" style="max-height: 380px; object-fit: contain; display:block;">
                                    <button type="button" class="btn btn-sm btn-danger position-absolute" style="top:10px; right:10px;" onclick="event.stopPropagation(); clearPreview()" title="Remove image">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <span class="badge bg-dark bg-opacity-75 position-absolute" style="bottom:10px; left:10px;">
                                        <i class="fa-solid fa-rotate me-1"></i> Click to change
                                    </span>
                                </div>

                                <!-- Camera: live feed -->
                                <div id="state-camera-live" class="capture-state hidden p-0">
                                    <video id="camera-video" autoplay playsinline muted class="w-100" style="max-height:380px; object-fit:cover; background:#000; display:block;"></video>
                                    <div class="p-3 bg-black bg-opacity-50">
                                        <button type="button" class="btn btn-success px-4 py-2 fw-bold" onclick="capturePhoto()">
                                            <i class="fa-solid fa-camera me-2"></i> CAPTURE
                                        </button>
                                    </div>
                                </div>

                                <!-- Camera: unavailable / permission denied -->
                                <div id="state-camera-error" class="capture-state hidden">
                                    <i class="fa-solid fa-video-slash fa-3x text-danger mb-3"></i>
                                    <h6 class="mb-2">Camera unavailable</h6>
                                    <p class="text-muted small mb-3" id="camera-error-message">Please allow camera access, or use Upload instead.</p>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="event.stopPropagation(); setUploadMode('upload')">
                                        <i class="fa-solid fa-upload me-1"></i> Switch to Upload
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex align-items-stretch gap-2">


                                <button type="button" onclick="classifyCurrentImage()" id="classify-btn" class="btn btn-success btn-lg flex-fill py-3 fw-bold shadow" disabled>
                                    <i class="fa-solid fa-magnifying-glass me-2"></i> CLASSIFY IMAGE
                                </button>

                                    <div class="engine-dropdown">
                                    <button type="button" id="engine-dropdown-btn" class="btn btn-dark border border-secondary engine-dropdown-btn" onclick="toggleEngineDropdown()" title="Classification engine">
                                        <i class="fa-solid fa-microchip text-success" id="engine-dropdown-icon"></i>
                                        <span id="engine-dropdown-label">MobileNetV2</span>
                                        <i class="fa-solid fa-chevron-up small ms-1"></i>
                                    </button>
                                    <div id="engine-dropdown-menu" class="engine-dropdown-menu hidden">
                                        <button type="button" class="engine-option active" data-engine="model" onclick="setEngine('model')">
                                            <i class="fa-solid fa-microchip me-2"></i> MobileNetV2 (On-Device)
                                        </button>
                                        <button type="button" class="engine-option" data-engine="yollo11n" onclick="setEngine('yollo11n')">
                                            <i class="fa-solid fa-eye me-2"></i> yollo11n
                                        </button>
                                        <button type="button" class="engine-option" data-engine="groq" onclick="setEngine('groq')">
                                            <i class="fa-solid fa-bolt me-2"></i> Groq AI
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="detect-col-results" id="results-col">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Classification Results</h5>
                        </div>
                        <div class="card-body">

                            <div id="no-result" class="text-center py-5">
                                <i class="fa-solid fa-seedling fa-5x text-secondary mb-4 opacity-50"></i>
                                <h5>No image classified yet</h5>
                                <p class="text-secondary">Upload a clear photo or use the camera to start detection</p>
                            </div>

                            <div id="results-panel" class="hidden">
                                <div class="d-flex justify-content-end mb-4">
                                    <button type="button" onclick="saveCurrentDetection()" class="btn btn-outline-success fw-bold">
                                            <i class="fa-solid fa-floppy-disk me-2"></i> SAVE TO HISTORY
                                    </button>
                                </div>

                                <div class="rg-result-head mb-4">
                                    <div class="rg-result-head-left">
                                        <div class="rg-confidence-gauge" id="confidence-ring">
                                            <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
                                                <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100"></path>
                                                <path class="gauge-fill" id="confidence-ring-fill" d="M14,100 A86,86 0 0 1 186,100"></path>
                                            </svg>
                                            <div class="rg-confidence-center">
                                                <i class="fa-solid fa-leaf" id="confidence-ring-icon"></i>
                                                <span id="confidence-ring-value">Confidence 0%</span>
                                            </div>
                                        </div>
                                        <div class="rg-result-name-wrap">
                                            <div class="rg-result-eyebrow">Classification Result</div>
                                            <div id="top-label" class="h3 mb-0 text-white fw-bold text-truncate"></div>
                                            <div id="top-confidence" class="hidden"></div>
                                        </div>
                                    </div>
                                    <div id="type-badge" class="rg-type-badge">
                                        <i class="fa-solid fa-bug" id="type-badge-icon"></i>
                                        <span id="type-badge-text">Pest</span>
                                    </div>
                                </div>

                                <div id="predictions-list" class="mb-4 p-3 border border-secondary rounded bg-dark"></div>

                                <div class="card mb-4 bg-secondary bg-opacity-10 border-0">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <div id="severity-label" class="h4 mb-1 fw-bold"></div>
                                                <p id="severity-message" class="mb-0 text-light"></p>
                                            </div>
                                            <div class="col-auto text-end">
                                                <small class="text-light">Damage Level</small>
                                                <div id="severity-percent" class="h3 fw-bold text-white mb-0"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- About / Description — sits above the tabs, always visible. -->
                                <div id="description" class="rg-about-card mb-3"></div>

                                <!-- Treatment / Causes / Prevention / More Info tabs -->
                                <div class="rg-tabs" id="result-tabs" role="tablist">
                                    <button type="button" class="rg-tab-btn active" data-tab="treatment" onclick="setResultTab('treatment')">
                                        <i class="fa-solid fa-spray-can-sparkles me-2"></i>Treatment
                                    </button>
                                    <button type="button" class="rg-tab-btn" data-tab="causes" onclick="setResultTab('causes')">
                                        <i class="fa-solid fa-circle-question me-2"></i>Causes
                                    </button>
                                    <button type="button" class="rg-tab-btn" data-tab="prevention" onclick="setResultTab('prevention')">
                                        <i class="fa-solid fa-shield-heart me-2"></i>Prevention
                                    </button>
                                    <button type="button" class="rg-tab-btn" data-tab="more-info" onclick="setResultTab('more-info')">
                                        <i class="fa-solid fa-circle-info me-2"></i>More Info
                                    </button>
                                </div>

                                <div class="rg-tab-panels mb-3">
                                    <div class="rg-tab-panel active" data-panel="treatment">
                                        <div id="treatment" class="p-3 rg-content-block"></div>
                                    </div>
                                    <div class="rg-tab-panel" data-panel="causes">
                                        <div id="causes" class="p-3 rg-content-block"></div>
                                    </div>
                                    <div class="rg-tab-panel" data-panel="prevention">
                                        <div id="prevention" class="p-3 rg-content-block"></div>
                                    </div>
                                    <div class="rg-tab-panel" data-panel="more-info">
                                        <div class="row g-3">
                                            <div id="nutrient-section" class="col-12"><div id="nutrient" class="p-3 rg-content-block"></div></div>
                                            <div id="damage-section" class="col-12 hidden"><div id="damage" class="p-3 rg-content-block"></div></div>
                                            <div id="grain-section" class="col-12"><div id="grain" class="p-3 rg-content-block"></div></div>
                                            <div id="natural-enemies-section" class="col-12 hidden"><div id="natural-enemies" class="p-3 rg-content-block"></div></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Farmer feedback entry point. Deliberately
                                     nested INSIDE #results-panel so it can only
                                     ever be on screen when an actual detection
                                     result is being shown — it disappears with
                                     the panel on clearPreview()/no-result. -->
                                <div id="report-prompt" class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-4 p-3 rounded border border-warning" style="background: rgba(245,158,11,0.08);">
                                    <div class="pe-2">
                                        <div class="fw-bold text-warning mb-1">
                                            <i class="fa-solid fa-triangle-exclamation me-2"></i>Not sure about this result?
                                        </div>
                                        <small class="text-light d-block">If the detection or information seems incorrect, you can report it for review.</small>
                                    </div>
                                    <button type="button" class="btn btn-danger fw-bold" onclick="openReportModal()">
                                        <i class="fa-solid fa-flag me-2"></i> Report a Problem
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============ Report Detection Problem (floating modal) ============
     Hand-rolled overlay instead of a Bootstrap modal so it has no JS
     dependency beyond this page. Front-end only at this stage: Submit
     just validates and swaps to the success popup below. -->
<div id="report-modal" class="rg-report-backdrop hidden" onclick="if (event.target === this) closeReportModal()">
    <div class="rg-report-dialog rg-report-dialog-lg">
        <div class="rg-report-head">
            <h6 class="mb-0 fw-bold text-white">Report Detection Problem</h6>
            <button type="button" class="btn btn-sm btn-link text-secondary p-0" onclick="closeReportModal()" title="Close">
                <i class="fa-solid fa-xmark fa-lg"></i>
            </button>
        </div>

        <div class="rg-report-body">
            <!-- Which detection this report is about — mirrored from the
                 result currently on screen, read-only. Laid out the same
                 way as the Detection Report card: thumbnail, type badge +
                 name, confidence gauge on the right. -->
            <div class="rg-report-top mb-3">
                <img id="report-thumb" class="rg-report-thumb" alt="Detected image">
                <div id="rm-block-name" class="rg-flag-block rg-flag-chip rg-report-top-info" data-section="name">
                    <div id="report-type-badge" class="rg-type-badge rg-report-type-badge">
                        <i class="fa-solid fa-bug" id="report-type-badge-icon"></i>
                        <span id="report-type-badge-text">Pest</span>
                    </div>
                    <div id="report-detection-name" class="rg-report-name">—</div>
                </div>
                <div class="rg-confidence-gauge rg-report-gauge rg-flag-block rg-flag-chip" id="report-confidence-gauge" data-section="name">
                    <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
                        <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100"></path>
                        <path class="gauge-fill" id="report-confidence-fill" d="M14,100 A86,86 0 0 1 186,100"></path>
                    </svg>
                    <div class="rg-report-gauge-center">
                        <span id="report-detection-confidence">0%</span>
                        <small>Confidence</small>
                    </div>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div id="rm-block-severity" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="severity">
                        <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-gauge-high me-1"></i>Severity</div>
                        <div id="report-detection-severity" class="rg-report-stat-value">—</div>
                    </div>
                </div>
                <div class="col-6">
                    <div id="rm-block-damagelevel" class="rg-flag-block rg-flag-chip rg-report-stat" data-section="damagelevel">
                        <div class="rg-flag-title rg-report-stat-label"><i class="fa-solid fa-wheat-awn me-1"></i>Damage Level</div>
                        <div id="report-detection-damage-level" class="rg-report-stat-value">—</div>
                    </div>
                </div>
            </div>

            <!-- Full copy of the detection information, mirrored from the
                 results panel when the modal opens. Ticking a problem above
                 underlines the matching block(s) red so the farmer can see
                 (and the technician will later see) exactly WHAT was wrong.
                 This is a copy only — #results-panel itself is never
                 restyled. -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="form-label small fw-bold text-white mb-0">Detection information being reported</label>
                    <span class="rg-mirror-note">Red underline = marked as wrong</span>
                </div>
                <div class="row g-0">
                    <div class="col-12"><div id="rm-block-description" class="rg-flag-block rg-flag-row small" data-section="description"></div></div>
                    <div class="col-12"><div id="rm-block-treatment" class="rg-flag-block rg-flag-row small" data-section="treatment"></div></div>
                    <div class="col-12"><div id="rm-block-causes" class="rg-flag-block rg-flag-row small" data-section="causes"></div></div>
                    <div id="rm-section-nutrient" class="col-12"><div id="rm-block-nutrient" class="rg-flag-block rg-flag-row small" data-section="nutrient"></div></div>
                    <div id="rm-section-damage" class="col-12 hidden"><div id="rm-block-damage" class="rg-flag-block rg-flag-row small" data-section="damage"></div></div>
                    <div id="rm-section-grain" class="col-12"><div id="rm-block-grain" class="rg-flag-block rg-flag-row small" data-section="grain"></div></div>
                    <div id="rm-section-natural-enemies" class="col-12 hidden"><div id="rm-block-natural-enemies" class="rg-flag-block rg-flag-row small" data-section="natural_enemies"></div></div>
                    <div class="col-12"><div id="rm-block-prevention" class="rg-flag-block rg-flag-row small" data-section="prevention"></div></div>
                </div>
            </div>




                        <!-- What is wrong? — checkbox dropdown, multiple choices allowed. -->
            <div class="mb-3">
                <label class="form-label small fw-bold text-white">What is wrong?</label>
                <div class="rg-check-dd" id="report-problem-dd">
                    <button type="button" class="rg-check-dd-toggle" onclick="toggleReportProblemDropdown()">
                        <span id="report-problem-summary" class="text-secondary">Select all that apply</span>
                        <i class="fa-solid fa-chevron-down small"></i>
                    </button>
                    <div id="report-problem-menu" class="rg-check-dd-menu hidden">
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong pest/disease detection"> Wrong pest/disease detection</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong severity level"> Wrong severity level</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong damage level"> Wrong damage level</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong management/treatment"> Wrong management/treatment</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong causes"> Wrong causes</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong symptoms"> Wrong symptoms</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong natural enemies"> Wrong natural enemies</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Wrong prevention information"> Wrong prevention information</label>
                        <label class="rg-check-item"><input type="checkbox" class="report-problem-check" value="Other system problem"> Other system problem</label>
                    </div>
                </div>
            </div>


            <div class="mb-3">
                <label class="form-label small fw-bold text-white" for="report-description">
                    Please describe the problem <span class="text-danger">*</span>
                </label>
                <textarea id="report-description" rows="3" class="form-control rg-report-input"
                          placeholder="Describe what looks wrong with this result..."></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold text-white" for="report-suggested">Suggested correct pest/disease (optional)</label>
                <!-- Options are filled in openReportModal() from the same
                     diseaseNames/pestNames maps the results panel uses. -->
                <select id="report-suggested" class="form-select rg-report-input">
                    <option value="">— Select if you know —</option>
                </select>
            </div>

            <div class="mb-1">
                <label class="form-label small fw-bold text-white" for="report-image">Upload supporting image (optional)</label>
                <input type="file" id="report-image" accept="image/*" class="form-control rg-report-input">
            </div>
        </div>

        <div class="rg-report-foot">
            <button type="button" class="btn btn-secondary" onclick="closeReportModal()">Cancel</button>
            <button type="button" class="btn btn-success fw-bold" onclick="submitReport()">
                <i class="fa-solid fa-paper-plane me-2"></i> Submit Report
            </button>
        </div>
    </div>
</div>

<!-- ============ Report Submitted (floating popup) ============
     Confirmation only. The report ID is generated client-side as a
     placeholder — no record exists server-side yet, and notifications are
     intentionally left untouched. -->
<div id="report-success-modal" class="rg-report-backdrop hidden">
    <div class="rg-report-dialog" style="max-width: 440px;">
        <div class="rg-report-body text-center">
            <i class="fa-solid fa-circle-check fa-3x text-success mb-3"></i>
            <h5 class="fw-bold text-white mb-2">Report Submitted!</h5>
            <p class="text-light small mb-3">Your report has been submitted successfully.</p>

            <div class="mb-2 small text-light">Report ID: <span id="report-success-id" class="fw-bold text-white">—</span></div>
            <div class="mb-3 small text-light">
                Status: <span class="badge bg-warning text-dark">Waiting for Technician Review</span>
            </div>

            <div class="p-3 rounded text-start small text-light mb-1" style="background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.35);">
                <i class="fa-solid fa-circle-info text-info me-2"></i>
                You will be notified once the technician reviews your report. Thank you for helping improve our system!
            </div>
        </div>
        <div class="rg-report-foot justify-content-center">
            <button type="button" class="btn btn-outline-success fw-bold" onclick="closeReportSuccess()">Back to Detection</button>
        </div>
    </div>
</div>

<!-- ============ Classification engine error (replaces the native
     browser alert() for Model/Groq failures) ============ -->
<div id="engine-error-modal" class="rg-report-backdrop hidden" onclick="if (event.target === this) closeEngineError()">
    <div class="rg-report-dialog" style="max-width: 460px;">
        <div class="rg-report-body text-center">
            <i class="fa-solid fa-triangle-exclamation fa-3x text-danger mb-3"></i>
            <h5 class="fw-bold text-white mb-2" id="engine-error-title">Analysis failed</h5>
            <p class="text-light small mb-0" id="engine-error-message">Something went wrong.</p>
        </div>
        <div class="rg-report-foot justify-content-center">
            <button type="button" class="btn btn-outline-light fw-bold" onclick="closeEngineError()">Try Again</button>
            <button type="button" class="btn btn-success fw-bold hidden" id="engine-error-switch-btn" onclick="engineErrorSwitch()"></button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.21.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.4/dist/teachablemachine-image.min.js"></script>

<script>
// ==================== DATA ====================
const diseaseNames = @json($diseaseNames ?? []);
const pestNames = @json($pestNames ?? []);
const knowledgeBase = @json($knowledgeBase ?? []);

const modelURL = "{{ asset('model/model.json') }}";
const metadataURL = "{{ asset('model/metadata.json') }}";

let model = null;
let currentImage = null;
let currentObjectURL = null;
let lastClassKey = null;
let lastConfidence = 65;
let isModelReady = false;
window.compressedBase64 = null; // Global reference for chatbot image analysis context
let currentGroqData = null; 

let lastPredictions = null;
let isShowingFallback = false;

// Which engine the switch is currently set to. Default: on-device model.
// classifyCurrentImage() reads this and ONLY calls that engine — there is
// no automatic fallback from one to the other anymore.
let selectedEngine = 'model';

// Smallest amount of time the "ANALYZING..." state must stay visible for,
// so a very fast on-device prediction doesn't just flash and disappear.
const MIN_LOADING_MS = 500;
function sleep(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }

window.setEngine = function(engine) {
    // yollo11n isn't wired up yet — just tell the farmer and leave whatever
    // engine was already selected untouched (no dropdown highlight change,
    // no selectedEngine change, nothing else happens).
    if (engine === 'yollo11n') {
        alert("Coming soon, please select another model.");
        closeEngineDropdown();
        return;
    }

    if (engine !== 'model' && engine !== 'groq') return;
    const modelBtn = document.querySelector('.engine-option[data-engine="model"]');
    if (engine === 'model' && modelBtn && modelBtn.disabled) return; // model unavailable

    selectedEngine = engine;
    document.querySelectorAll('.engine-option').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.engine === engine);
    });

    const labelEl = document.getElementById('engine-dropdown-label');
    const iconEl = document.getElementById('engine-dropdown-icon');
    if (labelEl) labelEl.textContent = engine === 'groq' ? 'Groq AI' : 'MobileNetV2';
    if (iconEl) iconEl.className = engine === 'groq' ? 'fa-solid fa-bolt text-primary' : 'fa-solid fa-microchip text-success';

    closeEngineDropdown();
};

window.toggleEngineDropdown = function() {
    document.getElementById('engine-dropdown-menu').classList.toggle('hidden');
};
window.closeEngineDropdown = function() {
    document.getElementById('engine-dropdown-menu').classList.add('hidden');
};
document.addEventListener('click', function (e) {
    const wrapper = document.querySelector('.engine-dropdown');
    if (wrapper && !wrapper.contains(e.target)) closeEngineDropdown();
});

// ==================== RESULT TABS (Treatment / Causes / Prevention / More Info) ====================
window.setResultTab = function(tab) {
    document.querySelectorAll('#result-tabs .rg-tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tab);
    });
    document.querySelectorAll('.rg-tab-panel').forEach(panel => {
        panel.classList.toggle('active', panel.dataset.panel === tab);
    });
};

// ==================== CONFIDENCE GAUGE (half-circle) ====================
// Half-circle gauge, not a full ring: the arc length itself encodes the
// confidence percentage. Path is a semicircle of radius 86, so its total
// length is pi * r (half of a full circle's 2*pi*r).
const CONFIDENCE_ARC_LEN = Math.PI * 86;
function updateConfidenceRing(confidence) {
    const fill = document.getElementById('confidence-ring-fill');
    const valueEl = document.getElementById('confidence-ring-value');
    const centerEl = document.querySelector('.rg-confidence-center');
    if (!fill || !valueEl) return;
    const pct = Math.max(0, Math.min(100, confidence || 0));
    const offset = CONFIDENCE_ARC_LEN * (1 - pct / 100);
    fill.style.strokeDasharray = `${CONFIDENCE_ARC_LEN}`;
    fill.style.strokeDashoffset = `${offset}`;
    valueEl.textContent = `Confidence ${pct}%`;

    let gaugeColor = '#10b981';
    if (pct < 50) gaugeColor = '#ef4444';
    else if (pct < 80) gaugeColor = '#f59e0b';
    fill.style.stroke = gaugeColor;
    if (centerEl) centerEl.style.color = gaugeColor;
}

function updateTypeBadge(isPest) {
    const badge = document.getElementById('type-badge');
    const icon = document.getElementById('type-badge-icon');
    const text = document.getElementById('type-badge-text');
    if (!badge || !icon || !text) return;
    badge.classList.toggle('is-disease', !isPest);
    icon.className = isPest ? 'fa-solid fa-bug' : 'fa-solid fa-disease';
    text.textContent = isPest ? 'Pest' : 'Disease';
}

// Report modal's own half-circle gauge + type badge — same visual language
// as the results panel gauge/badge above, just scoped to its own element
// ids so opening the modal never touches the live results panel.
function updateReportConfidenceGauge(confidence) {
    const fill = document.getElementById('report-confidence-fill');
    const valueEl = document.getElementById('report-detection-confidence');
    const centerEl = document.querySelector('.rg-report-gauge-center');
    if (!fill || !valueEl) return;
    const pct = Math.max(0, Math.min(100, confidence || 0));
    const offset = CONFIDENCE_ARC_LEN * (1 - pct / 100);
    fill.style.strokeDasharray = `${CONFIDENCE_ARC_LEN}`;
    fill.style.strokeDashoffset = `${offset}`;
    valueEl.textContent = `${pct}%`;

    let gaugeColor = '#10b981';
    if (pct < 50) gaugeColor = '#ef4444';
    else if (pct < 80) gaugeColor = '#f59e0b';
    fill.style.stroke = gaugeColor;
    if (centerEl) centerEl.style.color = gaugeColor;
}

function updateReportTypeBadge(isPest) {
    const badge = document.getElementById('report-type-badge');
    const icon = document.getElementById('report-type-badge-icon');
    const text = document.getElementById('report-type-badge-text');
    if (!badge || !icon || !text) return;
    badge.classList.toggle('is-disease', !isPest);
    icon.className = isPest ? 'fa-solid fa-bug' : 'fa-solid fa-disease';
    text.textContent = isPest ? 'Pest Detected' : 'Disease Detected';
}

const uiTranslations = {
    english: { description: "Description / About", treatment: "Treatment", causes: "Causes", prevention: "Prevention", nutrient: "Nutrient / Deficiency", grain: "Grain Impact", damage: "Damage Symptoms", naturalEnemies: "Natural Enemies" },
    tagalog: { description: "Paglalarawan / Tungkol dito", treatment: "Paggamot", causes: "Mga Sanhi", prevention: "Pag-iwas", nutrient: "Kakulangan sa Nutrisyon", grain: "Epekto sa Butil", damage: "Sintomas ng Pinsala", naturalEnemies: "Mga Likas na Kaaway" },
    cebuano: { description: "Paghulagway / Mahitungod", treatment: "Pagtambal", causes: "Mga Hinungdan", prevention: "Pagpugong", nutrient: "Kulang sa Nutrisyon", grain: "Epekto sa Uhay", damage: "Sintomas sa Kadaot", naturalEnemies: "Mga Natural nga Kaaway" },
    hiligaynon: { description: "Paglaragway / Tuhoy Diri", treatment: "Pagbulong", causes: "Mga Rason", prevention: "Pagpangamlig", nutrient: "Kulang sa Nutrisyon", grain: "Epekto sa Uhay", damage: "Sintomas sang Halit", naturalEnemies: "Mga Natural nga Kontra" }
};

async function compressImageFile(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(file);
        
        img.onload = () => {
            URL.revokeObjectURL(objectUrl);
            const canvas = document.createElement('canvas');
            const MAX_WIDTH = 512; 
            const scale = Math.min(MAX_WIDTH / img.width, 1); 
            
            canvas.width = img.width * scale;
            canvas.height = img.height * scale;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            resolve(canvas.toDataURL('image/jpeg', 0.6)); 
        };
        img.onerror = (err) => reject(err);
        img.src = objectUrl;
    });
}

async function loadModel() {
    const statusEl = document.getElementById('status');
    statusEl.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Loading TF model...`;
    try {
        model = await tmImage.load(modelURL, metadataURL);
        statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> Model ready`;
        statusEl.className = "pill-badge bg-success text-white";
        isModelReady = true;
    } catch (e) {
        statusEl.innerHTML = `<i class="fa-solid fa-bolt"></i> Model unavailable`;
        statusEl.className = "pill-badge bg-warning text-dark";

        // The on-device model genuinely can't load in this browser/session,
        // so switch the default over to Groq AI and disable the Model
        // option — this is a one-time availability fallback at load time,
        // not the per-classification auto-fallback that used to happen.
        const modelBtn = document.querySelector('.engine-option[data-engine="model"]');
        if (modelBtn) {
            modelBtn.disabled = true;
            modelBtn.title = 'On-device model unavailable in this browser';
        }
        setEngine('groq');
    }
}

window.browsePhoto = function() { document.getElementById('file-input').click(); };

// ==================== UPLOAD / CAMERA MODE ====================
// Only one .capture-state is ever visible inside #capture-box. Camera
// capture is fully inline now (getUserMedia + canvas snapshot) — there is
// no more redirect to a separate /camera page / sessionStorage handoff.
let currentMode = 'upload'; // 'upload' | 'camera'
let cameraStream = null;
let hasUploadedImage = false;

function showCaptureState(stateId) {
    document.querySelectorAll('.capture-state').forEach(el => el.classList.add('hidden'));
    document.getElementById(stateId).classList.remove('hidden');
}

function setModeTab(mode) {
    document.querySelectorAll('.mode-tab').forEach(btn => btn.classList.toggle('active', btn.dataset.mode === mode));
}

window.setUploadMode = function(mode) {
    if (mode === currentMode) return;
    currentMode = mode;
    setModeTab(mode);

    if (mode === 'camera') {
        startCamera();
    } else {
        stopCamera();
        showCaptureState(hasUploadedImage ? 'state-image-preview' : 'state-upload-empty');
    }
};

async function startCamera() {
    showCaptureState('state-camera-live');
    try {
        cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        document.getElementById('camera-video').srcObject = cameraStream;
    } catch (err) {
        document.getElementById('camera-error-message').textContent =
            err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError'
                ? 'Camera permission was denied. Please allow access in your browser settings.'
                : 'No camera could be accessed on this device.';
        showCaptureState('state-camera-error');
    }
}

function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
}

window.capturePhoto = function() {
    const video = document.getElementById('camera-video');
    const canvas = document.getElementById('camera-canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(blob => {
        const file = new File([blob], 'camera_capture.jpg', { type: 'image/jpeg' });
        handleFile(file);
    }, 'image/jpeg', 0.9);
};

function setupUpload() {
    const dropZone = document.getElementById('capture-box');
    const fileInput = document.getElementById('file-input');

    fileInput.addEventListener('change', e => {
        if (e.target.files[0]) handleFile(e.target.files[0]);
    });

    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.style.borderColor = '#10b981'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = ''; });
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.style.borderColor = '';
        if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
    });
}

async function handleFile(file) {
    if (!file.type.startsWith('image/')) return alert('Please select a valid image file');

    // A file can arrive via browse, drag-and-drop, or camera capture while
    // in any mode — always land back on the Upload tab showing the image.
    stopCamera();
    currentMode = 'upload';
    setModeTab('upload');

    try {
        window.compressedBase64 = await compressImageFile(file); 
        
        document.getElementById('preview-image').src = window.compressedBase64;
        hasUploadedImage = true;
        showCaptureState('state-image-preview');

        currentImage = new Image();
        currentImage.src = window.compressedBase64;
        currentImage.onload = () => document.getElementById('classify-btn').disabled = false;
    } catch (err) {
        alert("Failed to process the image.");
    }
}

function clearPreview() {
    hasUploadedImage = false;
    showCaptureState('state-upload-empty');
    document.getElementById('classify-btn').disabled = true;
    window.compressedBase64 = null;
    currentImage = null;
    currentGroqData = null;
    document.getElementById('results-panel').classList.add('hidden');
    document.getElementById('no-result').classList.remove('hidden');
    document.getElementById('detect-row')?.classList.remove('has-result');
}

// ==================== ENGINE ERROR MODAL ====================
// Themed replacement for the native alert() popup on Model/Groq
// classification failures. switchToEngine/switchLabel are optional —
// omit them for errors where offering the other engine doesn't apply.
let _engineErrorSwitchTarget = null;
window.showEngineError = function(title, message, switchToEngine, switchLabel) {
    document.getElementById('engine-error-title').textContent = title;
    document.getElementById('engine-error-message').textContent = message;

    const switchBtn = document.getElementById('engine-error-switch-btn');
    if (switchToEngine) {
        _engineErrorSwitchTarget = switchToEngine;
        switchBtn.textContent = switchLabel;
        switchBtn.classList.remove('hidden');
    } else {
        _engineErrorSwitchTarget = null;
        switchBtn.classList.add('hidden');
    }

    document.getElementById('engine-error-modal').classList.remove('hidden');
};
window.closeEngineError = function() {
    document.getElementById('engine-error-modal').classList.add('hidden');
};
window.engineErrorSwitch = function() {
    if (_engineErrorSwitchTarget) setEngine(_engineErrorSwitchTarget);
    closeEngineError();
};

window.classifyCurrentImage = async function() {
    if (!currentImage || !window.compressedBase64) return alert("No image selected.");

    const btn = document.getElementById('classify-btn');
    const original = btn.innerHTML;
    const statusEl = document.getElementById('status');
    btn.disabled = true;

    // Scroll back to the top of the page right away so the farmer sees the
    // button's loading state and the incoming result instead of staying
    // scrolled down at the capture box.
    window.scrollTo({ top: 0, behavior: 'smooth' });

    // The engine switch decides everything below — whichever side is
    // active is the ONLY engine that runs. No automatic fallback either way.
    if (selectedEngine === 'model') {
        if (!isModelReady) {
            showEngineError(
                "Model not ready yet",
                "The on-device model isn't ready yet. Wait a moment and try again, or switch to Groq AI.",
                'groq', 'Switch to Groq AI'
            );
            btn.disabled = false;
            return;
        }

        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ANALYZING WITH MODEL...`;
        statusEl.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Model analyzing...`;
        statusEl.className = "pill-badge bg-success text-white";

        try {
            // Setting btn.innerHTML only QUEUES a repaint — it doesn't
            // force the browser to draw it. model.predict() then runs a
            // chunk of synchronous work on the main thread before it hits
            // its first real async boundary, so without this the "loading"
            // label could be replaced again before it was ever actually
            // painted to the screen. A double requestAnimationFrame
            // guarantees one full paint has happened first.
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));

            // model.predict() on a small image can still resolve in a few
            // milliseconds — too fast for the "ANALYZING WITH MODEL..."
            // state to actually be seen. Pair it with a minimum-visible
            // delay so both engines feel consistent.
            const predictStart = performance.now();
            const predictions = await model.predict(currentImage);
            const elapsed = performance.now() - predictStart;
            if (elapsed < MIN_LOADING_MS) await sleep(MIN_LOADING_MS - elapsed);

            displayResults(predictions);
            statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> Model ready`;
            statusEl.className = "pill-badge bg-success text-white";
        } catch (err) {
            statusEl.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Model failed`;
            statusEl.className = "pill-badge bg-danger text-white";
            showEngineError(
                "Model analysis failed",
                "The on-device model failed to analyze this image. You can try again, or switch to Groq AI.",
                'groq', 'Switch to Groq AI'
            );
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
        return;
    }

    // --- Groq AI engine ---
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ANALYZING WITH GROQ...`;
    statusEl.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Groq analyzing...`;
    statusEl.className = "pill-badge bg-info text-dark";

    try {
        const response = await fetch("{{ route('farmer.history.groq') }}", {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
           body: JSON.stringify({ 
                image_base64: window.compressedBase64,
                language: document.getElementById('language-selector').value
            })
        });

        if (response.status === 401 || response.status === 419) {
            alert("Your login session has expired. The page will now refresh so you can log back in.");
            window.location.reload();
            return;
        }

       const rawText = await response.text(); 
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseError) {
            throw new Error("Server crashed or returned non-JSON data.");
        }

        if (result.success && result.data && result.data.class_key) {
            statusEl.innerHTML = `<i class="fa-solid fa-bolt"></i> Groq Ready`;
            statusEl.className = "pill-badge bg-primary text-white";
            displayGroqResults(result.data);
        } else {
            throw new Error(result.message || "Unknown API Error");
        }
    } catch (err) {
        statusEl.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> Groq failed`;
        statusEl.className = "pill-badge bg-danger text-white";
        showEngineError(
            "Groq AI failed to analyze this image",
            err.message + " You can try again, or switch to the on-device Model.",
            'model', 'Switch to MobileNetV2'
        );
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
};

function displayGroqResults(data) {
    currentGroqData = data;
    isShowingFallback = false;
    const lang = document.getElementById('language-selector').value;
    const t = uiTranslations[lang];
    
    document.getElementById('no-result').classList.add('hidden');
    document.getElementById('results-panel').classList.remove('hidden');
    document.getElementById('detect-row')?.classList.add('has-result');
    setResultTab('treatment');

    lastClassKey = data.class_key;
    // Groq's own returned confidence, exactly as it sent it — just clamped
    // into the 0-100 range and coerced to a real number so a stray string
    // or out-of-range value from the API can't distort what's shown/saved.
    // This is the ONLY place Groq's confidence is read from; every spot
    // below uses this same normalized value instead of raw data.confidence,
    // so the gauge, the label, and what gets saved to history always agree.
    lastConfidence = Math.max(0, Math.min(100, Math.round(Number(data.confidence) || 0)));

    const safeSet = (id, html) => { const el = document.getElementById(id); if(el) el.innerHTML = html; };

    document.getElementById('top-label').textContent = data.class_name;
    document.getElementById('top-confidence').innerHTML = `${lastConfidence}%`;
    updateConfidenceRing(lastConfidence);
    updateTypeBadge(!!data.is_pest);

    safeSet('predictions-list', `<div class="d-flex justify-content-between mb-2"><span class="text-primary fw-bold"><i class="fa-solid fa-bolt me-2"></i>Groq AI Primary Diagnosis</span><span class="text-primary">${lastConfidence}%</span></div>`);

    let color = "text-info";
    if (data.severity_label === 'HEALTHY') color = "text-success";
    else if (data.severity_label === 'SEVERE') color = "text-danger";
    else if (data.severity_label === 'MODERATE') color = "text-warning";

    const severityLabelEl = document.getElementById('severity-label');
    if(severityLabelEl) {
        severityLabelEl.textContent = data.severity_label;
        severityLabelEl.className = `h4 mb-1 ${color}`;
    }
    safeSet('severity-percent', data.severity_percent + "%");
    safeSet('severity-message', data.severity_message);

    safeSet('description', `<strong class="text-white"><i class="fa-solid fa-circle-info me-2"></i>${t.description}:</strong><p class="mt-2 mb-0">${data.description || '—'}</p>`);
    safeSet('treatment', `<strong class="text-success"><i class="fa-solid fa-spray-can-sparkles me-2"></i>${t.treatment}:</strong><p class="mt-2 mb-0">${data.treatments || '—'}</p>`);
    safeSet('causes', `<strong class="text-warning"><i class="fa-solid fa-question-circle me-2"></i>${t.causes}:</strong><p class="mt-2 mb-0">${data.causes || '—'}</p>`);
    safeSet('prevention', `<strong class="text-info"><i class="fa-solid fa-shield-heart me-2"></i>${t.prevention}:</strong><p class="mt-2 mb-0">${data.prevention || '—'}</p>`);

    if (data.is_pest) {
        document.getElementById('nutrient-section')?.classList.add('hidden');
        document.getElementById('grain-section')?.classList.add('hidden');
        document.getElementById('damage-section')?.classList.remove('hidden');
        document.getElementById('natural-enemies-section')?.classList.remove('hidden');
        safeSet('damage', `<strong class="text-danger"><i class="fa-solid fa-wheat-awn me-2"></i>${t.damage}:</strong><p class="mt-2 mb-0">${data.pest_damage || '—'}</p>`);
        safeSet('natural-enemies', `<strong class="text-success"><i class="fa-solid fa-bug-slash me-2"></i>${t.naturalEnemies}:</strong><p class="mt-2 mb-0">${data.natural_enemies || '—'}</p>`);
    } else {
        document.getElementById('damage-section')?.classList.add('hidden');
        document.getElementById('natural-enemies-section')?.classList.add('hidden');
        document.getElementById('nutrient-section')?.classList.remove('hidden');
        document.getElementById('grain-section')?.classList.remove('hidden');
        safeSet('nutrient', `<strong class="text-warning"><i class="fa-solid fa-leaf me-2"></i>${t.nutrient}:</strong><p class="mt-2 mb-0">${data.nutrient_deficiency || '—'}</p>`);
        safeSet('grain', `<strong class="text-danger"><i class="fa-solid fa-seedling me-2"></i>${t.grain}:</strong><p class="mt-2 mb-0">${data.grain_damage || '—'}</p>`);
    }
}

function displayResults(predictions) {
    lastPredictions = predictions;
    isShowingFallback = true;
    currentGroqData = null;
    const lang = document.getElementById('language-selector').value;
    const t = uiTranslations[lang];

    document.getElementById('no-result').classList.add('hidden');
    document.getElementById('results-panel').classList.remove('hidden');
    document.getElementById('detect-row')?.classList.add('has-result');
    setResultTab('treatment');
    const safeSet = (id, html) => { const el = document.getElementById(id); if(el) el.innerHTML = html; };

    let filtered = predictions.filter(p => p.probability >= 0.01);
    filtered.sort((a, b) => b.probability - a.probability);
    const top = filtered[0];

    const className = top.className.trim().toLowerCase().replace(/\s+/g, '_');
    lastClassKey = className;
    // MobileNetV2's own top-1 softmax probability for this image, exactly
    // as it came out of model.predict() — just clamped 0-100 the same way
    // Groq's confidence is, so both engines are normalized identically.
    lastConfidence = Math.max(0, Math.min(100, Math.round((top.probability || 0) * 100)));

    // --- AGRONOMIC DATA SEVERITY ESTIMATES ---
    // These reflect real-world potential yield loss/damage for each pest or disease
    const severityEstimates = {
        'healthy_rice_plant': { label: 'HEALTHY', percent: 0, message: 'The plant appears to be in good condition.' },
        'bacterial_leaf_blight': { label: 'SEVERE', percent: 60, message: 'Can cause up to 60% yield loss if left untreated during the tillering stage.' },
        'leaf_blast': { label: 'SEVERE', percent: 80, message: 'Highly destructive; neck blast infections can cause up to 80% yield loss.' },
        'rice_false_smut': { label: 'MODERATE', percent: 30, message: 'Generally causes 10-30% yield loss depending on weather and severity.' },
        'sheath_blight': { label: 'MODERATE', percent: 40, message: 'Often causes 20-50% yield loss, especially in dense, high-fertilizer canopies.' },
        'tungro_virus': { label: 'SEVERE', percent: 85, message: 'Can wipe out crops entirely if infection happens early in the vegetative stage.' },
        'brown_planthopper': { label: 'SEVERE', percent: 90, message: 'Causes severe hopperburn, leading to massive or complete yield loss.' },
        'leaf_folders': { label: 'LOW', percent: 20, message: 'Damage looks severe but usually only results in minor yield loss (up to 20%).' },
        'leafhopper': { label: 'MODERATE', percent: 30, message: 'Direct damage is moderate, but they are dangerous vectors for viral diseases.' },
        'rice_bug': { label: 'SEVERE', percent: 80, message: 'Sucks sap from developing grains, capable of causing up to 80% empty grains.' },
        'rice_gall_midge': { label: 'MODERATE', percent: 40, message: 'Damages tillers (onion shoots), causing moderate yield reduction.' },
        'rice_leaf_roller': { label: 'LOW', percent: 20, message: 'Similar to leaf folders; rarely causes total crop failure.' },
        'rice_stem_borer': { label: 'MODERATE', percent: 30, message: 'Causes deadhearts and whiteheads; typically results in 10-30% yield loss.' },
        'snail': { label: 'SEVERE', percent: 75, message: 'Golden apple snails can completely destroy young seedlings and seedbeds quickly.' }
    };

    const estimate = severityEstimates[className] || { label: 'UNKNOWN', percent: 0, message: 'Severity estimate data unavailable.' };

    let color = "text-secondary";
    if (estimate.label === 'HEALTHY') color = "text-success";
    else if (estimate.label === 'LOW') color = "text-info";
    else if (estimate.label === 'MODERATE') color = "text-warning";
    else if (estimate.label === 'SEVERE') color = "text-danger";

    const severityLabelEl = document.getElementById('severity-label');
    if (severityLabelEl) {
        severityLabelEl.textContent = estimate.label;
        severityLabelEl.className = `h4 mb-1 fw-bold ${color}`;
    }
    safeSet('severity-percent', estimate.percent + "%");
    safeSet('severity-message', estimate.message);
    // -----------------------------------------

    const isPest = Object.keys(pestNames).includes(className);
    const nameMap = isPest ? pestNames : diseaseNames;

    // Display model confidence for NAME identification
    document.getElementById('top-label').textContent = nameMap[className] || top.className;
    document.getElementById('top-confidence').innerHTML = `${lastConfidence}%`;
    updateConfidenceRing(lastConfidence);
    updateTypeBadge(isPest);

    let html = '';
    filtered.forEach(pred => {
        let pName = pred.className.trim().toLowerCase().replace(/\s+/g, '_');
        html += `<div class="d-flex justify-content-between mb-2"><span>${nameMap[pName] || pred.className}</span><span class="text-secondary">${(pred.probability * 100).toFixed(1)}%</span></div>`;
    });
    safeSet('predictions-list', html);

    const kb = knowledgeBase[className] || {};
    
    safeSet('description', `<strong class="text-white"><i class="fa-solid fa-circle-info me-2"></i>${t.description}:</strong><p class="mt-2 mb-0">${kb.description || '—'}</p>`);
    safeSet('treatment', `<strong class="text-success"><i class="fa-solid fa-spray-can-sparkles me-2"></i>${t.treatment}:</strong><p class="mt-2 mb-0">${kb.treatments || '—'}</p>`);
    safeSet('causes', `<strong class="text-warning"><i class="fa-solid fa-question-circle me-2"></i>${t.causes}:</strong><p class="mt-2 mb-0">${kb.causes || '—'}</p>`);
    safeSet('prevention', `<strong class="text-info"><i class="fa-solid fa-shield-heart me-2"></i>${t.prevention}:</strong><p class="mt-2 mb-0">${kb.prevention || '—'}</p>`);

    if (isPest) {
        document.getElementById('nutrient-section')?.classList.add('hidden');
        document.getElementById('grain-section')?.classList.add('hidden');
        document.getElementById('damage-section')?.classList.remove('hidden');
        document.getElementById('natural-enemies-section')?.classList.remove('hidden');
        safeSet('damage', `<strong class="text-danger"><i class="fa-solid fa-wheat-awn me-2"></i>${t.damage}:</strong><p class="mt-2 mb-0">${kb.grain_damage || '—'}</p>`);
        safeSet('natural-enemies', `<strong class="text-success"><i class="fa-solid fa-bug-slash me-2"></i>${t.naturalEnemies}:</strong><p class="mt-2 mb-0">${kb.natural_enemies || '—'}</p>`);
    } else {
        document.getElementById('damage-section')?.classList.add('hidden');
        document.getElementById('natural-enemies-section')?.classList.add('hidden');
        document.getElementById('nutrient-section')?.classList.remove('hidden');
        document.getElementById('grain-section')?.classList.remove('hidden');
        safeSet('nutrient', `<strong class="text-warning"><i class="fa-solid fa-leaf me-2"></i>${t.nutrient}:</strong><p class="mt-2 mb-0">${kb.nutrient_deficiency || '—'}</p>`);
        safeSet('grain', `<strong class="text-danger"><i class="fa-solid fa-seedling me-2"></i>${t.grain}:</strong><p class="mt-2 mb-0">${kb.grain_damage || '—'}</p>`);
    }

    // IMPORTANT: currentGroqData stays null for model-classified results
    // (see clearPreview()/top of this function). It used to be repopulated
    // here with the static fallback KB mislabeled as "Groq data", which
    // then got saved into the shared Groq knowledge base and silently
    // overwrote it with plain fallback text. Model results are saved with
    // no groq_data at all — see saveCurrentDetection().
}

document.getElementById('language-selector').addEventListener('change', function() {
    if (!document.getElementById('results-panel').classList.contains('hidden')) {
        if (!isShowingFallback && currentGroqData) {
            displayGroqResults(currentGroqData);
        } else if (isShowingFallback && lastPredictions) {
            displayResults(lastPredictions);
        }
    }
});

// ==================== REPORT A PROBLEM (UI ONLY) ====================
// Scoped deliberately narrow for now: open/close the modal, collect the
// inputs, and show the success popup. Nothing is POSTed, nothing is stored,
// and nothing appears in the technician's Farmer Reports page yet — wiring
// that up (route + controller + table + notifications) is a later step.
// Which mirrored block(s) each "What is wrong?" option marks red. Keys are
// the checkbox values; values are data-section names in the modal. Blocks
// that are hidden for this result type (e.g. natural enemies on a disease)
// are skipped automatically by applyReportFlags().
const reportProblemSectionMap = {
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

// Copies the live results panel into the modal's mirrored blocks. Reads the
// rendered HTML, so it automatically matches the current dialect AND
// whichever engine (model / Groq) produced the result.
function mirrorDetectionIntoModal() {
    const copy = (fromId, toId) => {
        const from = document.getElementById(fromId);
        const to = document.getElementById(toId);
        if (!from || !to) return;
        to.innerHTML = from.innerHTML;
        // The section heading inside the copied markup is a <strong>; tag it
        // so the flagged styling can turn just the title red.
        to.querySelector('strong')?.classList.add('rg-flag-title');
    };

    copy('description', 'rm-block-description');
    copy('treatment', 'rm-block-treatment');
    copy('causes', 'rm-block-causes');
    copy('nutrient', 'rm-block-nutrient');
    copy('damage', 'rm-block-damage');
    copy('grain', 'rm-block-grain');
    copy('natural-enemies', 'rm-block-natural-enemies');
    copy('prevention', 'rm-block-prevention');

    // Mirror the pest/disease visibility exactly as the panel has it.
    const mirrorVisibility = (panelId, modalId) => {
        const panel = document.getElementById(panelId);
        const modal = document.getElementById(modalId);
        if (!panel || !modal) return;
        modal.classList.toggle('hidden', panel.classList.contains('hidden'));
    };
    mirrorVisibility('nutrient-section', 'rm-section-nutrient');
    mirrorVisibility('grain-section', 'rm-section-grain');
    mirrorVisibility('damage-section', 'rm-section-damage');
    mirrorVisibility('natural-enemies-section', 'rm-section-natural-enemies');

    document.getElementById('report-detection-severity').textContent =
        document.getElementById('severity-label').textContent || '—';
    document.getElementById('report-detection-damage-level').textContent =
        document.getElementById('severity-percent').textContent || '—';
}

// Turns the mirrored blocks red based on the ticked problems.
function applyReportFlags() {
    const chosen = selectedReportProblems();
    const flagged = new Set();
    chosen.forEach(problem => (reportProblemSectionMap[problem] || []).forEach(s => flagged.add(s)));

    document.querySelectorAll('#report-modal .rg-flag-block').forEach(block => {
        const section = block.dataset.section;
        const parentHidden = block.parentElement?.classList.contains('hidden');
        block.classList.toggle('rg-flagged', !parentHidden && flagged.has(section));
    });

    // Kept for the future backend: the exact section keys the farmer marked,
    // so the technician's page can render the same red borders.
    window.currentReportFlags = Array.from(flagged);
}

window.openReportModal = function() {
    // No detection on screen = nothing to report. The button lives inside
    // #results-panel so this is really just a safety net.
    if (!lastClassKey) {
        alert("There is no detection result to report yet.");
        return;
    }

    document.getElementById('report-thumb').src = window.compressedBase64 || '';
    document.getElementById('report-detection-name').textContent =
        document.getElementById('top-label').textContent || '—';

    // Mirror the live badge/gauge exactly as the results panel has them.
    const isPest = !document.getElementById('type-badge')?.classList.contains('is-disease');
    updateReportTypeBadge(isPest);
    updateReportConfidenceGauge(lastConfidence);

    // Rebuild the suggestion list each open from the page's own name maps,
    // so it always matches whatever classes the model/KB currently knows.
    const suggested = document.getElementById('report-suggested');
    suggested.innerHTML = '<option value="">— Select if you know —</option>';
    const addGroup = (groupLabel, map) => {
        const entries = Object.entries(map || {});
        if (!entries.length) return;
        const group = document.createElement('optgroup');
        group.label = groupLabel;
        entries.forEach(([key, label]) => {
            const opt = document.createElement('option');
            opt.value = key;
            opt.textContent = label;
            group.appendChild(opt);
        });
        suggested.appendChild(group);
    };
    addGroup('Diseases', diseaseNames);
    addGroup('Pests', pestNames);

    resetReportForm();
    mirrorDetectionIntoModal();
    applyReportFlags();
    document.getElementById('report-modal').classList.remove('hidden');
};

window.closeReportModal = function() {
    closeReportProblemDropdown();
    document.getElementById('report-modal').classList.add('hidden');
};

function resetReportForm() {
    document.querySelectorAll('.report-problem-check').forEach(cb => cb.checked = false);
    updateReportProblemSummary();
    applyReportFlags();
    document.getElementById('report-description').value = '';
    document.getElementById('report-suggested').value = '';
    document.getElementById('report-image').value = '';
}

window.toggleReportProblemDropdown = function() {
    document.getElementById('report-problem-menu').classList.toggle('hidden');
};
window.closeReportProblemDropdown = function() {
    document.getElementById('report-problem-menu').classList.add('hidden');
};

function selectedReportProblems() {
    return Array.from(document.querySelectorAll('.report-problem-check:checked')).map(cb => cb.value);
}

function updateReportProblemSummary() {
    const chosen = selectedReportProblems();
    const summary = document.getElementById('report-problem-summary');
    if (!summary) return;

    if (!chosen.length) {
        summary.textContent = 'Select all that apply';
        summary.className = 'text-secondary';
    } else {
        summary.textContent = chosen.length === 1 ? chosen[0] : `${chosen.length} problems selected`;
        summary.className = 'text-white';
    }
}

document.querySelectorAll('.report-problem-check').forEach(cb => {
    cb.addEventListener('change', () => {
        updateReportProblemSummary();
        applyReportFlags(); // repaint the red borders on every tick/untick
    });
});

// Click-away close for the checkbox dropdown only (the modal itself is
// closed by its own backdrop handler / Cancel / X).
document.addEventListener('click', function (e) {
    const dd = document.getElementById('report-problem-dd');
    if (dd && !dd.contains(e.target)) closeReportProblemDropdown();
});

// Pulls the plain text of each mirrored section so the report stores the
// knowledge-base text EXACTLY as the farmer saw it (current dialect and
// engine included) — the technician then reviews that same snapshot instead
// of whatever the KB says later on.
function collectReportInfoSnapshot() {
    const blocks = {
        description:     'rm-block-description',
        treatment:       'rm-block-treatment',
        causes:          'rm-block-causes',
        nutrient:        'rm-block-nutrient',
        damage:          'rm-block-damage',
        grain:           'rm-block-grain',
        natural_enemies: 'rm-block-natural-enemies',
        prevention:      'rm-block-prevention'
    };

    const info = {};
    Object.entries(blocks).forEach(([key, id]) => {
        const el = document.getElementById(id);
        if (!el) return;
        // Skip only the sections hidden for this result type (e.g. natural
        // enemies on a disease) — a real "—" or "no data yet" value is
        // still what the farmer actually saw, so it's kept and sent too,
        // instead of being silently dropped from the report.
        if (el.parentElement?.classList.contains('hidden')) return;

        const body = el.querySelector('p');
        const text = (body ? body.textContent : el.textContent).trim();
        if (text) info[key] = text;
    });

    // The three summary values, so the technician sees the same trio.
    info.name        = document.getElementById('top-label').textContent || '';
    info.severity    = document.getElementById('severity-label').textContent || '';
    info.damagelevel = document.getElementById('severity-percent').textContent || '';

    return info;
}

// Reads the optional supporting photo as a data URI, matching how the
// detection image itself is stored (see FarmerHistoryController@saveDetection).
function readSupportImage() {
    const input = document.getElementById('report-image');
    const file = input?.files?.[0];
    if (!file) return Promise.resolve(null);

    return new Promise(resolve => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = () => resolve(null);
        reader.readAsDataURL(file);
    });
}

window.submitReport = async function() {
    const problems = selectedReportProblems();
    const description = document.getElementById('report-description').value.trim();

    if (!problems.length) {
        alert("Please choose at least one option under \"What is wrong?\".");
        return;
    }
    if (!description) {
        alert("Please describe the problem before submitting.");
        return;
    }

    const submitBtn = document.querySelector('#report-modal .btn-success');
    const originalBtn = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> Submitting...';
    }

    try {
        const payload = {
            class_key:        lastClassKey,
            class_name:       document.getElementById('top-label').textContent || lastClassKey,
            confidence:       lastConfidence,
            severity_label:   document.getElementById('severity-label').textContent || null,
            // "90%" -> 90
            severity_percent: parseInt((document.getElementById('severity-percent').textContent || '0').replace('%', ''), 10) || 0,
            source:           selectedEngine,
            image_base64:     window.compressedBase64,
            support_image:    await readSupportImage(),
            info:             collectReportInfoSnapshot(),
            problem_types:    problems,
            // Section keys the farmer marked red — the technician's page
            // repaints the exact same borders from this.
            flagged_sections: window.currentReportFlags || [],
            message:          description,
            suggested_class:  document.getElementById('report-suggested').value || null
        };

        const response = await fetch("{{ route('farmer.reports.store') }}", {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        });

        if (response.status === 401 || response.status === 419) {
            alert("Your login session has expired. The page will now refresh so you can log back in.");
            window.location.reload();
            return;
        }

        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'The report could not be saved.');
        }

        document.getElementById('report-success-id').textContent = '#' + result.report_code;
        closeReportModal();
        document.getElementById('report-success-modal').classList.remove('hidden');
    } catch (err) {
        alert("Failed to submit the report: " + err.message + "\n\nPlease try again.");
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtn;
        }
    }
};

window.closeReportSuccess = function() {
    document.getElementById('report-success-modal').classList.add('hidden');
};

window.saveCurrentDetection = async function() {
    if (!lastClassKey || !window.compressedBase64) {
        alert("No detection to save.");
        return;
    }
    
    // selectedEngine is the switch's own state, so it's the authoritative
    // record of which engine actually produced this classification — used
    // by the history page to badge each scan and to decide whether it gets
    // its own independent Groq snapshot or reads the shared model KB.
    const payload = {
        user_id: {{ Auth::id() }},
        field_key: document.getElementById('field-selector') ? document.getElementById('field-selector').value : 'main',
        class_key: lastClassKey,
        confidence: lastConfidence,
        image_base64: window.compressedBase64,
        // Only ever send real Groq output, and only when Groq was the
        // engine used — never the model's fallback text mislabeled as Groq.
        groq_data: (selectedEngine === 'groq') ? currentGroqData : null,
        source: selectedEngine
    };

    const success = await sendToServer(payload);
    if (success) {
        alert("✅ Detection saved successfully to your history!");
    } else {
        try {
            await saveLocally(payload);
            alert("📱 Network unavailable. Detection saved locally to your device!");
        } catch (e) {
            alert("An error occurred saving offline.");
        }
    }
};

window.sendToServer = async function(payload) {
    try {
        const response = await fetch("{{ route('farmer.history.save') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        });
        return response.ok; 
    } catch (error) {
        return false; 
    }
};

const dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open('CropSenseDB', 1);
    request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('offline_history')) {
            db.createObjectStore('offline_history', { keyPath: 'id', autoIncrement: true });
        }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

async function saveLocally(payload) {
    const db = await dbPromise;
    const tx = db.transaction('offline_history', 'readwrite');
    tx.objectStore('offline_history').add(payload);
    return tx.complete;
}

window.onload = async () => {
    await loadModel();
    setupUpload();
};
</script>
@endsection