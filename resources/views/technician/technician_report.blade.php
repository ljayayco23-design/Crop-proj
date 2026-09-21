{{--
    resources/views/technician/technician_report.blade.php

    Technician side of the detection-feedback loop: the farmer reports queue
    plus the review screen. Everything shown here is the SNAPSHOT the farmer
    submitted (FarmerReportController@present) — the detection image, the
    classification, the severity, and the knowledge-base text exactly as the
    farmer saw it, with red borders on whatever they marked wrong.

    NOTE: change the @extends below if your other technician pages use a
    different layout name (match technician/users/technician_log).
--}}
@extends('layouts.technician')

@section('title', 'RICEGUARD AI • Farmer Reports')

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

    .fr-input {
        background: #0f1522; border: 1px solid #334155; color: #e2e8f0;
        border-radius: 8px; font-size: .85rem;
    }
    .fr-input:focus { background: #0f1522; color: #fff; border-color: #10b981; box-shadow: none; }
    .fr-input option { background: #1a1f2b; color: #e2e8f0; }

    /* Thumbnails behave like the History page ones: click to enlarge. */
    .fr-thumb {
        width: 92px; height: 92px; object-fit: cover; border-radius: 10px;
        background: #0b0f17; cursor: pointer; border: 2px solid transparent;
        transition: border-color .15s ease, transform .15s ease;
    }
    .fr-thumb:hover { border-color: #3b82f6; transform: scale(1.04); }
    .fr-thumb-missing {
        width: 92px; height: 92px; border-radius: 10px;
        background: rgba(148,163,184,.15); color: #94a3b8;
        display: flex; align-items: center; justify-content: center;
    }

    /* ---------- Mirrored detection panel ----------
       Same markup/classes as the "Report the Problem" modal on the
       farmer's detection page (resources/views/farmer/index.blade.php),
       reused verbatim so a technician sees literally the same look — a
       thumbnail + type badge + name + confidence gauge up top, severity /
       damage chips below, then the full knowledge-base text. Wherever the
       farmer marked something wrong, that exact block turns red here too,
       via the same .rg-flagged toggle and the same flagged_sections keys
       the farmer's report modal wrote. */
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

    .rg-confidence-gauge { position: relative; width: 150px; height: 86px; flex: 0 0 auto; }
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

    /* "Flagged" = the farmer ticked the matching problem. Two flavors,
       identical to the report modal: a boxed inset-red-outline card for
       the header name/severity/damage chips, and a red-underlined row
       for the full knowledge-base text blocks. */
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

    .fr-mirror-note { font-size: .74rem; color: #64748b; }

    /* ---------- Farmer's Report card (right side) ----------
       Kept the same fields the technician already relied on — just given
       a warmer, more "flagged report" visual identity: a red accent edge,
       an icon header, and a quoted message block instead of plain text. */
    .fr-report-card {
        background: linear-gradient(165deg, rgba(239,68,68,.07), rgba(18,24,38,0) 55%);
        border: 1px solid #263349;
        border-left: 3px solid #ef4444;
        border-radius: 10px;
    }
    .fr-report-card-head {
        display: flex; align-items: center; gap: 8px;
        font-weight: 700; color: #fff; font-size: .95rem; margin-bottom: 14px;
    }
    .fr-report-card-head i { color: #f87171; }
    .fr-report-row { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px; font-size: .85rem; margin-bottom: 8px; }
    .fr-report-label { color: #94a3b8; font-weight: 600; flex: 0 0 auto; }
    .fr-report-value { color: #e2e8f0; }
    .fr-report-message {
        background: rgba(255,255,255,.045);
        border-left: 2px solid #334155;
        border-radius: 0 8px 8px 0;
        padding: 10px 12px;
        font-style: italic;
        color: #e2e8f0;
        font-size: .85rem;
        margin-bottom: 12px;
    }
    .fr-report-footer {
        font-size: .76rem; color: #64748b;
        border-top: 1px dashed #263349; padding-top: 10px; margin-top: 6px;
    }

    /* Checkbox dropdown (which information titles are wrong) */
    .fr-check-dd { position: relative; }
    .fr-check-dd-toggle {
        width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 8px;
        background: #0f1522; border: 1px solid #334155; color: #e2e8f0;
        border-radius: 8px; padding: 8px 12px; font-size: .85rem; text-align: left;
    }
    .fr-check-dd-menu {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 30;
        background: #1a1f2b; border: 1px solid #334155; border-radius: 10px;
        box-shadow: 0 10px 26px rgba(0,0,0,.5); padding: 6px; max-height: 220px; overflow-y: auto;
    }
    .fr-check-item {
        display: flex; align-items: center; gap: 9px; padding: 7px 9px;
        border-radius: 7px; cursor: pointer; font-size: .84rem; color: #cbd5e1;
    }
    .fr-check-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .fr-check-item input { accent-color: #10b981; }

    .fr-radio { display: flex; align-items: center; gap: 9px; font-size: .85rem; color: #cbd5e1; padding: 5px 0; cursor: pointer; }
    .fr-radio input { accent-color: #10b981; }

    /* Image lightbox */
    .fr-lightbox {
        position: fixed; inset: 0; z-index: 1090; background: rgba(2,6,12,.85);
        display: flex; align-items: center; justify-content: center; padding: 1.5rem;
    }
    .fr-lightbox img { max-width: 92vw; max-height: 88vh; border-radius: 12px; }

    /* ---------- Review header ---------- */
    .fr-detail-card {
        border-top: 3px solid #10b981;
        background: linear-gradient(180deg, rgba(16,185,129,.05), transparent 140px), #121826;
    }
    .fr-eyebrow { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #64748b; }
    .fr-d-status-pill { border-radius: 999px; padding: 5px 12px; font-weight: 700; font-size: .74rem; letter-spacing: .02em; }

    /* ---------- Left panel: detection info ---------- */
    .fr-section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 14px; flex-wrap: wrap; }
    .fr-section-eyebrow { display: flex; align-items: center; gap: 8px; color: #cbd5e1; font-weight: 700; font-size: .82rem; }
    .fr-section-eyebrow .fr-section-icon {
        width: 26px; height: 26px; border-radius: 8px; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center;
        background: rgba(59,130,246,.15); color: #60a5fa; font-size: .75rem;
    }
    .fr-mirror-note {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.25);
        color: #f87171; font-size: .68rem; font-weight: 700; letter-spacing: .02em;
        padding: 4px 9px; border-radius: 999px;
    }
    .fr-mirror-note::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.25); }

    /* ---------- Right panel: farmer's report — redesigned ---------- */
    .fr-report-card { background: linear-gradient(165deg, rgba(239,68,68,.09), rgba(18,24,38,0) 60%); border: 1px solid #263349; border-radius: 12px; overflow: hidden; }
    .fr-report-card-top {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 16px; background: rgba(239,68,68,.08); border-bottom: 1px solid #263349;
    }
    .fr-report-avatar {
        width: 38px; height: 38px; border-radius: 50%; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(135deg, #ef4444, #b91c1c); color: #fff;
        font-weight: 800; font-size: .95rem; box-shadow: 0 4px 10px rgba(239,68,68,.3);
    }
    .fr-report-card-top-text { min-width: 0; }
    .fr-report-card-top-text .fr-report-name-lg { color: #fff; font-weight: 700; font-size: .92rem; line-height: 1.2; }
    .fr-report-card-top-text .fr-report-sub { color: #94a3b8; font-size: .74rem; }
    .fr-report-card-body { padding: 16px; }
    .fr-report-field { display: flex; gap: 10px; margin-bottom: 12px; align-items: flex-start; }
    .fr-report-field:last-child { margin-bottom: 0; }
    .fr-report-field-icon {
        width: 26px; height: 26px; border-radius: 7px; flex: 0 0 auto; margin-top: 1px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(255,255,255,.06); color: #94a3b8; font-size: .72rem;
    }
    .fr-report-field-body { min-width: 0; flex: 1 1 auto; }
    .fr-report-field-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; color: #64748b; margin-bottom: 2px; }
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
    .fr-report-photo-btn { display: inline-block; position: relative; }
    .fr-report-photo-btn::after {
        content: "\f00e"; font-family: "Font Awesome 6 Free"; font-weight: 900; font-size: .6rem;
        position: absolute; bottom: 4px; right: 4px; background: rgba(0,0,0,.6); color: #fff;
        width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
    }

    /* ---------- Technician assessment: selectable cards instead of plain radios ---------- */
    .fr-assess-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
    .fr-assess-option {
        display: flex; align-items: center; gap: 10px; cursor: pointer;
        border: 1px solid #263349; border-radius: 10px; padding: 10px 12px;
        background: rgba(255,255,255,.02); transition: border-color .15s ease, background-color .15s ease;
    }
    .fr-assess-option:hover { border-color: #3b4a63; background: rgba(255,255,255,.04); }
    .fr-assess-radio { accent-color: #10b981; flex: 0 0 auto; width: 15px; height: 15px; }
    .fr-assess-option-inner { display: flex; align-items: center; gap: 9px; font-size: .84rem; color: #cbd5e1; }
    .fr-assess-option-inner i { width: 18px; text-align: center; color: #64748b; }
    .fr-assess-radio:checked ~ .fr-assess-option-inner { color: #fff; font-weight: 700; }
    .fr-assess-radio:checked ~ .fr-assess-option-inner i { color: #10b981; }
    .fr-assess-option:has(.fr-assess-radio:checked) { border-color: #10b981; background: rgba(16,185,129,.08); }
    .fr-assess-hint { font-size: .72rem; color: #64748b; margin-top: 10px; border-top: 1px dashed #263349; padding-top: 10px; }
    .fr-assess-hint span { color: #38bdf8; font-weight: 600; }

    .fr-input-label { display: flex; align-items: center; gap: 6px; }
    .fr-input-label i { color: #64748b; font-size: .78rem; }

    .fr-save-btn {
        background: linear-gradient(135deg, #10b981, #059669); border: none; color: #06281f; font-weight: 800;
        box-shadow: 0 8px 20px rgba(16,185,129,.28); transition: transform .15s ease, box-shadow .15s ease;
    }
    .fr-save-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(16,185,129,.38); color: #06281f; }

    /* ---------- "Solution" button (opens the assessment modal) ---------- */
    .fr-solution-btn {
        background: linear-gradient(135deg, #10b981, #059669); border: none; color: #06281f; font-weight: 800;
        font-size: .8rem; padding: 7px 14px; border-radius: 8px;
        box-shadow: 0 6px 16px rgba(16,185,129,.25); transition: transform .15s ease, box-shadow .15s ease;
    }
    .fr-solution-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(16,185,129,.35); color: #06281f; }

    /* ---------- Three-dot dropdown on the resolve-data panel ---------- */
    .fr-dots-dd { position: relative; }
    .fr-dots-toggle {
        width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center;
        background: transparent; border: none; outline: none; color: #94a3b8; border-radius: 8px;
    }
    .fr-dots-toggle:hover, .fr-dots-toggle:focus { background: rgba(255,255,255,.08); color: #fff; box-shadow: none; }
    .fr-dots-menu {
        position: absolute; top: calc(100% + 4px); right: 0; z-index: 40; min-width: 150px;
        background: #1a1f2b; border: 1px solid #334155; border-radius: 10px;
        box-shadow: 0 10px 26px rgba(0,0,0,.5); padding: 6px;
    }
    .fr-dots-item {
        display: flex; align-items: center; gap: 9px; width: 100%; padding: 8px 10px;
        border-radius: 7px; cursor: pointer; font-size: .85rem; color: #cbd5e1;
        background: none; border: none; text-align: left;
    }
    .fr-dots-item:hover { background: rgba(255,255,255,.06); color: #fff; }
    .fr-dots-item.text-danger:hover { background: rgba(239,68,68,.12); color: #f87171; }
    .fr-dots-item i { width: 16px; text-align: center; }

    /* ---------- Technician resolve data: underline-only fields, no boxes ----------
       Used for the notes/advice/corrected-detection "input type" output so it
       reads like a form-review sheet instead of a stack of bordered cards. */
    .fr-resolve-field { border-bottom: 1px solid #263349; padding: 9px 2px 11px; }
    .fr-resolve-field:last-child { border-bottom: none; padding-bottom: 2px; }
    .fr-resolve-label {
        font-size: .68rem; text-transform: uppercase; letter-spacing: .05em;
        font-weight: 700; color: #64748b; margin-bottom: 3px;
    }
    .fr-resolve-value { font-size: .85rem; color: #e2e8f0; }
    .fr-resolve-value.text-muted-italic { color: #64748b; font-style: italic; }
    .fr-resolve-split { display: flex; flex-wrap: wrap; gap: 18px; }
    .fr-resolve-split > div { flex: 1 1 180px; min-width: 0; }

    /* Section title colors — mirrors the farmer page's safeSet() colors
       (text-white/success/warning/info/danger). Two classes deep so they
       reliably beat the plain gray .rg-flag-title rule; the flagged/red
       state above still wins over these since it's !important. */
    .rg-flag-title.text-white { color: #fff; }
    .rg-flag-title.text-success { color: #22c55e; }
    .rg-flag-title.text-warning { color: #f59e0b; }
    .rg-flag-title.text-info { color: #38bdf8; }
    .rg-flag-title.text-danger { color: #ef4444; }

    /* ---------- Escalated to admin (same look as the admin System Report table) ---------- */
    .sr-desc { max-width: 320px; }
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

    /* ---------- Technician resolution (bottom panel + View modal) ---------- */
    .fr-col-head {
        font-size: .74rem; text-transform: uppercase; letter-spacing: .05em;
        color: #94a3b8; margin-bottom: .5rem; font-weight: 700;
    }
    .fr-resolve-box { border: 1px solid #1f4d3a; background: rgba(16,185,129,.07); border-radius: 10px; padding: 12px; }
    .fr-wait-box { border: 1px solid #334155; background: rgba(148,163,184,.06); border-radius: 10px; padding: 14px; }
    .sr-assess { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: .78rem; font-weight: 700; }
    .sr-assess.correct          { background: rgba(34,197,94,.16);  color: #4ade80; }
    .sr-assess.incorrect        { background: rgba(239,68,68,.16);  color: #f87171; }
    .sr-assess.info_incorrect   { background: rgba(245,158,11,.16); color: #fbbf24; }
    .sr-assess.cannot_determine { background: rgba(148,163,184,.18); color: #cbd5e1; }
    .sr-assess.need_image       { background: rgba(56,189,248,.16); color: #38bdf8; }

    /* ---------- Floating View modal (summary left, resolution right) ---------- */
    .fe-backdrop {
        position: fixed; inset: 0; z-index: 1080;
        background: rgba(2, 6, 12, 0.35); backdrop-filter: blur(2px);
        display: flex; align-items: flex-start; justify-content: center;
        padding: 2.5rem 1rem; overflow-y: auto;
    }
    .fe-backdrop.fr-hidden { display: none !important; }
    .fe-dialog {
        width: 100%; max-width: 1000px; background: #121826;
        border: 1px solid #334155; border-radius: 14px;
        box-shadow: 0 18px 60px rgba(0,0,0,.55);
        max-height: 92vh; display: flex; flex-direction: column;
    }
    .fe-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 18px; border-bottom: 1px solid #263349; }
    .fe-body { padding: 16px 18px; overflow-y: auto; }
    .fe-foot { display: flex; align-items: center; justify-content: flex-end; gap: 8px; padding: 12px 18px; border-top: 1px solid #263349; }
</style>

<div class="nxl-content">
    <div class="page-header mb-4 d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div class="page-header-title">
            <h4 class="m-b-10 fw-bold">Farmer Reports</h4>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <select id="fr-filter-status" class="form-select form-select-sm fr-input" style="width: 165px;" onchange="frApplyFilters()">
                <option value="">All Status</option>
                @foreach($statuses as $key => $meta)
                    <option value="{{ $key }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <select id="fr-filter-type" class="form-select form-select-sm fr-input" style="width: 195px;" onchange="frApplyFilters()">
                <option value="">All Types</option>
                @foreach(collect($reports)->pluck('problem_summary')->unique() as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
            <input type="text" id="fr-filter-search" class="form-control form-control-sm fr-input"
                   style="width: 210px;" placeholder="Search report ID, farmer..." oninput="frApplyFilters()">
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid">

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            {{-- ============ LIST VIEW ============ --}}
            <div id="fr-list-view">

                {{-- FIRST container: reports escalated to the admin. Same table as the
                     admin's System Report page; the status is the ADMIN's status, so it
                     changes here when the admin changes it there. --}}
                <div id="fe-card" class="fr-card p-3 p-md-4 mb-3">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                        <h6 class="mb-0 fw-bold text-white">
                            <i class="fa-solid fa-arrow-up-right-from-square me-2 text-danger"></i>Escalated to admin
                        </h6>
                        <span class="small text-secondary">Status follows what the admin sets on the System Report page.</span>
                    </div>

                    @if(count($escalatedReports) === 0)
                        <p class="text-secondary mb-0">Nothing has been escalated to the admin yet.</p>
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
                                <tbody id="fe-table-body">
                                    @foreach($escalatedReports as $esc)
                                        <tr data-esc-id="{{ $esc['id'] }}">
                                            <td class="fw-bold">#{{ $esc['sr_code'] }}</td>
                                            <td><span class="sr-badge sr-type-{{ $esc['type_key'] }}">{{ $esc['type'] }}</span></td>
                                            <td>{{ $esc['category'] }}</td>
                                            <td class="sr-desc">{{ $esc['description'] }}</td>
                                            <td class="text-secondary">{{ $esc['escalated_date'] }}</td>
                                            <td>
                                                <span class="sr-badge sr-status-badge sr-status-{{ $esc['admin_status'] }}">{{ $adminStatuses[$esc['admin_status']]['label'] }}</span>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-info" onclick="frOpenEscModal({{ $esc['id'] }})">
                                                    <i class="fa-solid fa-eye me-1"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                {{-- SECOND container: the farmer reports (unchanged). --}}
                <div class="fr-card p-3 p-md-4">
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
                    <h6 class="mb-0 fw-bold text-white">Reports from farmers</h6>
                </div>

                @if(count($reports) === 0)
                    <p class="text-secondary mb-0">No farmer reports in your area yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-borderless mb-0 fr-table">
                            <thead>
                                <tr>
                                    <th>Report ID</th>
                                    <th>Detection</th>
                                    <th>Problem</th>
                                    <th>Farmer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="fr-table-body">
                                @foreach($reports as $report)
                                    <tr data-report="{{ $report['report_id'] }}"
                                        data-status="{{ $report['status'] }}"
                                        data-type="{{ $report['problem_summary'] }}"
                                        data-search="{{ strtolower($report['report_id'].' '.$report['farmer_name'].' '.$report['detection']['class_name']) }}">
                                        <td class="fw-bold">#{{ $report['report_id'] }}</td>
                                        <td>{{ $report['detection']['class_name'] }}</td>
                                        <td>{{ $report['problem_summary'] }}</td>
                                        <td>{{ $report['farmer_name'] }}</td>
                                        <td class="text-secondary">{{ $report['date'] }}</td>
                                        <td>
                                            <span class="badge {{ $statuses[$report['status']]['class'] }}">
                                                {{ $statuses[$report['status']]['label'] }}
                                            </span>
                                            @if(!empty($report['escalated']))
                                                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger border-opacity-50 ms-1">
                                                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Escalated
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-info"
                                                    onclick="frOpenReport('{{ $report['report_id'] }}')">
                                                <i class="fa-solid fa-eye me-1"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        <div id="fr-empty" class="text-center text-secondary py-4 fr-hidden">No reports match your filters.</div>
                    </div>
                @endif
                </div>
            </div>

            {{-- ============ REVIEW VIEW ============ --}}
            <div id="fr-detail-view" class="fr-hidden">
                {{-- Real POST. The controller re-scopes {report} to this
                     technician's own queue before updating anything. --}}
                <form id="fr-review-form" method="POST" action="">
                    @csrf
                    <div class="fr-card fr-detail-card p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <div class="fr-eyebrow mb-1">Farmer Report Review</div>
                                <div class="d-flex align-items-center gap-2">
                                    <h6 class="mb-0 fw-bold text-white">Report <span id="fr-d-id">#—</span></h6>
                                    <span id="fr-d-status" class="badge bg-warning text-dark fr-d-status-pill">Pending</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="frBackToList()">
                                <i class="fa-solid fa-arrow-left me-1"></i> Back
                            </button>
                        </div>

                        <div class="row g-3">
                            {{-- LEFT: the exact detection info the farmer saw, laid out and
                                 flagged the same way as the "Report the Problem" modal on the
                                 detection page — red where the farmer marked it wrong. --}}
                            <div class="col-lg-7">
                                <div class="fr-panel h-100">
                                    <div class="fr-section-head">
                                        <div class="fr-section-eyebrow">
                                            <span class="fr-section-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                                            Detection information shown to the farmer
                                        </div>
                                        <span class="fr-mirror-note">Red = marked wrong</span>
                                    </div>

                                    <div class="rg-report-top mb-3">
                                        <img id="fr-d-image" class="rg-report-thumb fr-hidden" alt="Reported detection" onclick="frZoom(this.src)">
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
                                                <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100" style="fill:none;"></path>
                                                <path class="gauge-fill" id="fr-confidence-fill" d="M14,100 A86,86 0 0 1 186,100" style="fill:none;"></path>
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

                                    <div class="small text-secondary mb-3">Engine: <span id="fr-d-source" class="text-light">—</span></div>

                                    <div id="fr-d-info"></div>
                                </div>
                            </div>

                            {{-- RIGHT: farmer's report summary up top, the technician's saved
                                 resolution underneath it. The assessment + corrected-detection
                                 inputs that used to live here now live in the "Solution" modal
                                 (opened from the button below), so this column is read-only. --}}
                            <div class="col-lg-5">
                                <div class="fr-panel mb-3" id="fr-side-summary-panel">
                                    <div class="fr-section-eyebrow mb-3">
                                        <span class="fr-section-icon"><i class="fa-solid fa-flag"></i></span>
                                        Farmer's Report Summary
                                    </div>
                                    <div id="fr-side-summary"></div>
                                </div>

                                <div class="fr-panel" id="fr-side-resolution-panel">
                                    <div class="fr-section-head">
                                        <div class="fr-section-eyebrow">
                                            <span class="fr-section-icon"><i class="fa-solid fa-clipboard-check"></i></span>
                                            Technician Resolution
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" id="fr-solution-btn" class="fr-solution-btn" onclick="frOpenSolutionModal()">
                                                <i class="fa-solid fa-kit-medical me-1"></i> Solution
                                            </button>
                                            <div class="fr-dots-dd" id="fr-resolve-dots-dd">
                                                <button type="button" class="fr-dots-toggle" title="More actions" onclick="frToggleResolveDots(event)">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>
                                                <div id="fr-resolve-dots-menu" class="fr-dots-menu fr-hidden">
                                                    <button type="button" class="fr-dots-item" onclick="frToggleResolveDots(); frOpenSolutionModal();">
                                                        <i class="fa-solid fa-pen"></i> Update
                                                    </button>
                                                    <button type="button" class="fr-dots-item text-danger" onclick="frToggleResolveDots(); frDeleteResolution();">
                                                        <i class="fa-solid fa-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="fr-side-resolution"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {{-- ============ SOLUTION MODAL ============
                 The technician assessment, corrected-detection inputs, notes,
                 advice and the Escalate/Save buttons — everything that used to
                 sit in the right column now lives here. This modal sits
                 outside <form id="fr-review-form">, so every field/button in
                 it points back at that form via the HTML `form="fr-review-form"`
                 attribute — same submit behaviour, just rendered as a floating
                 overlay instead of inline. Opened by the "Solution" button or
                 by "Update" in the resolve-data three-dot menu. --}}
            <div id="fr-solution-modal" class="fe-backdrop fr-hidden" onclick="if (event.target === this) frCloseSolutionModal()">
                <div class="fe-dialog">
                    <div class="fe-head">
                        <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-kit-medical me-2 text-success"></i>Solution</h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="frCloseSolutionModal()" title="Close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="fe-body">
                        <div class="fr-panel mb-3">
                            <div class="fr-section-eyebrow mb-3">
                                <span class="fr-section-icon"><i class="fa-solid fa-clipboard-check"></i></span>
                                Technician Assessment
                            </div>
                            <div class="fr-assess-grid">
                                <label class="fr-assess-option">
                                    <input type="radio" class="fr-assess-radio" name="assessment" value="correct" onchange="frOnAssessmentChange()" form="fr-review-form">
                                    <span class="fr-assess-option-inner"><i class="fa-solid fa-circle-check"></i> AI result is correct</span>
                                </label>
                                <label class="fr-assess-option">
                                    <input type="radio" class="fr-assess-radio" name="assessment" value="incorrect" onchange="frOnAssessmentChange()" form="fr-review-form">
                                    <span class="fr-assess-option-inner"><i class="fa-solid fa-circle-xmark"></i> AI result is incorrect</span>
                                </label>
                                <label class="fr-assess-option">
                                    <input type="radio" class="fr-assess-radio" name="assessment" value="info_incorrect" onchange="frOnAssessmentChange()" form="fr-review-form">
                                    <span class="fr-assess-option-inner"><i class="fa-solid fa-pen-to-square"></i> Information is incorrect</span>
                                </label>
                                <label class="fr-assess-option">
                                    <input type="radio" class="fr-assess-radio" name="assessment" value="need_image" onchange="frOnAssessmentChange()" form="fr-review-form">
                                    <span class="fr-assess-option-inner"><i class="fa-solid fa-camera-retro"></i> Need another image</span>
                                </label>
                                <label class="fr-assess-option">
                                    <input type="radio" class="fr-assess-radio" name="assessment" value="cannot_determine" onchange="frOnAssessmentChange()" form="fr-review-form">
                                    <span class="fr-assess-option-inner"><i class="fa-solid fa-circle-question"></i> Cannot determine</span>
                                </label>
                            </div>
                            <div class="fr-assess-hint">
                                <span>"Need another image"</span> sets the report to Waiting for Farmer; every other choice resolves it.
                            </div>
                        </div>
                        <div class="fr-panel">
                            {{-- Only appears once the technician says something is wrong. --}}
                            <div id="fr-corrected-block" class="fr-hidden mb-3">
                                <div class="fw-bold text-white small mb-2">Corrected Detection</div>
                                <div class="row g-2">
                                    <div id="fr-corrected-class-wrap" class="col-sm-6">
                                        <label class="form-label small text-secondary mb-1">Pest / Disease</label>
                                        <select name="corrected_class" id="fr-corrected-class" class="form-select form-select-sm fr-input" form="fr-review-form">
                                            <option value="">— Select —</option>
                                            <optgroup label="Diseases">
                                                @foreach($diseaseNames as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                            <optgroup label="Pests">
                                                @foreach($pestNames as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        </select>
                                    </div>
                                    <div id="fr-corrected-severity-wrap" class="col-sm-6">
                                        <label class="form-label small text-secondary mb-1">Severity</label>
                                        <select name="corrected_severity" id="fr-corrected-severity" class="form-select form-select-sm fr-input" form="fr-review-form">
                                            <option value="">— Select —</option>
                                            @foreach($severityLevels as $level)
                                                <option value="{{ $level }}">{{ ucfirst(strtolower($level)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label small text-secondary mb-1">Information that needs correction</label>
                                        <div class="fr-check-dd" id="fr-titles-dd">
                                            <button type="button" class="fr-check-dd-toggle" onclick="frToggleTitlesDropdown()">
                                                <span id="fr-titles-summary" class="text-secondary">Select information titles</span>
                                                <i class="fa-solid fa-chevron-down small"></i>
                                            </button>
                                            <div id="fr-titles-menu" class="fr-check-dd-menu fr-hidden">
                                                @foreach($sections as $key => $title)
                                                    <label class="fr-check-item">
                                                        <input type="checkbox" class="fr-title-check" name="corrected_sections[]" value="{{ $key }}" form="fr-review-form"> {{ $title }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label small text-secondary mb-1 fr-input-label" for="fr-notes">
                                    <i class="fa-solid fa-note-sticky"></i> Technician Notes <span class="text-danger">*</span>
                                </label>
                                <textarea name="notes" id="fr-notes" rows="2" class="form-control fr-input" placeholder="What did you find in the image?" form="fr-review-form"></textarea>
                            </div>
                            <div class="mb-0">
                                <label class="form-label small text-secondary mb-1 fr-input-label" for="fr-advice">
                                    <i class="fa-solid fa-comment-medical"></i> Advice to Farmer
                                </label>
                                <textarea name="advice" id="fr-advice" rows="2" class="form-control fr-input" placeholder="What should the farmer do next?" form="fr-review-form"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="fe-foot">
                        {{-- Saves the assessment/notes/advice above AND sends the
                             report to the admin's System Report page. --}}
                        <button type="button" id="fr-escalate-btn" class="btn btn-sm btn-outline-danger" onclick="frEscalate()">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Escalate to Admin
                        </button>
                        <button type="submit" class="btn btn-sm fr-save-btn" form="fr-review-form">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save Review &amp; Resolve
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

{{-- ============ View modal: farmer's report summary (left) + technician resolution (right) ============ --}}
<div id="fe-modal" class="fe-backdrop fr-hidden" onclick="if (event.target === this) frCloseEscModal()">
    <div class="fe-dialog">
        <div class="fe-head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 id="fe-m-title" class="mb-0 fw-bold text-white">Report</h6>
                <span id="fe-m-admin" class="sr-badge sr-status-pending fr-hidden">Pending</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="frCloseEscModal()" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="fe-body">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="fr-col-head">Farmer's report summary</div>
                    <div id="fe-summary"></div>
                </div>
                <div class="col-lg-6">
                    <div class="fr-col-head">Technician resolution</div>
                    <div id="fe-resolution"></div>
                </div>
            </div>
        </div>
        <div class="fe-foot">
            <span id="fe-m-footnote" class="small text-secondary me-auto"></span>
            <button type="button" class="btn btn-sm btn-secondary" onclick="frCloseEscModal()">Close</button>
        </div>
    </div>
</div>

{{-- Click-to-enlarge, same idea as the History page's image modal. --}}
<div id="fr-lightbox" class="fr-lightbox fr-hidden" onclick="frCloseZoom()">
    <img id="fr-lightbox-img" src="" alt="Enlarged photo">
</div>
@endsection

@section('scripts')
<script>
// Rows + shared registries, straight from FarmerReportController.
const frReports  = @json($reports);
const frSections = @json($sections);
const frStatuses = @json($statuses);
// Route template; the real id is swapped in when a report is opened.
const frReviewUrlTemplate = "{{ route('technician.reports.review', ['report' => '__ID__']) }}";
const frEscalateUrlTemplate = "{{ route('technician.reports.escalate', ['report' => '__ID__']) }}";
const frClearReviewUrlTemplate = "{{ route('technician.reports.review.clear', ['report' => '__ID__']) }}";
// Reports escalated to the admin (admin-side columns included) + live status feed.
const frEscalated     = @json($escalatedReports);
const frAdminStatuses = @json($adminStatuses);
const frEscStatusUrl  = "{{ route('technician.reports.escalations') }}";

// Same icon + color pairing the farmer's detection page uses for each
// knowledge-base block (see safeSet('description', ...) etc. in
// resources/views/farmer/detection/index.blade.php) — kept here so the
// technician sees the literal same look, not a plain generic title.
const frSectionMeta = {
    description:     { icon: 'fa-circle-info',       color: 'text-white'   },
    treatment:        { icon: 'fa-spray-can-sparkles', color: 'text-success' },
    causes:            { icon: 'fa-question-circle',   color: 'text-warning' },
    prevention:        { icon: 'fa-shield-heart',       color: 'text-info'    },
    damage:            { icon: 'fa-wheat-awn',          color: 'text-danger'  },
    natural_enemies:   { icon: 'fa-bug-slash',          color: 'text-success' },
    nutrient:          { icon: 'fa-leaf',               color: 'text-warning' },
    grain:             { icon: 'fa-seedling',           color: 'text-danger'  }
};

// Same engine names shown in the detection page's model switcher — "On-device
// model" doesn't exist there, so this mirrors the actual three options
// (MobileNetV2 / yollo11n / Groq AI) instead of a made-up generic label.
const frEngineLabels = {
    model:    'MobileNetV2 (On-Device)',
    yollo11n: 'yollo11n',
    groq:     'Groq AI'
};

let frCurrent = null;

// Same half-circle arc length as the farmer's "Report the Problem" gauge
// (resources/views/farmer/index.blade.php), so the two render identically.
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

// ==================== LIST ====================
window.frApplyFilters = function() {
    const status = document.getElementById('fr-filter-status').value;
    const type   = document.getElementById('fr-filter-type').value;
    const term   = document.getElementById('fr-filter-search').value.trim().toLowerCase();

    let shown = 0;
    document.querySelectorAll('#fr-table-body tr').forEach(row => {
        const ok = (!status || row.dataset.status === status)
                && (!type   || row.dataset.type === type)
                && (!term   || row.dataset.search.includes(term));
        row.classList.toggle('fr-hidden', !ok);
        if (ok) shown++;
    });
    const empty = document.getElementById('fr-empty');
    if (empty) empty.classList.toggle('fr-hidden', shown > 0);
};

window.frBackToList = function() {
    document.getElementById('fr-detail-view').classList.add('fr-hidden');
    document.getElementById('fr-list-view').classList.remove('fr-hidden');
    frCloseSolutionModal();
    document.getElementById('fr-resolve-dots-menu')?.classList.add('fr-hidden');
    frCurrent = null;
};

// Same thresholds/colors as the farmer's report page and History, so a
// given severity percent always reads the same color everywhere.
function frSeverityColor(pct) {
    const n = Number(pct);
    if (!Number.isFinite(n)) return 'text-light';
    if (n === 0) return 'text-success';
    if (n <= 30) return 'text-info';
    if (n <= 50) return 'text-warning';
    return 'text-danger';
}

// ==================== IMAGES ====================
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

window.frZoom = function(src) {
    if (!src) return;
    document.getElementById('fr-lightbox-img').src = src;
    document.getElementById('fr-lightbox').classList.remove('fr-hidden');
};
window.frCloseZoom = function() {
    document.getElementById('fr-lightbox').classList.add('fr-hidden');
};

// ==================== REVIEW ====================
window.frOpenReport = function(id) {
    const r = frReports.find(x => x.report_id === id);
    if (!r) return;
    frCurrent = r;

    document.getElementById('fr-review-form').action = frReviewUrlTemplate.replace('__ID__', r.id);

    document.getElementById('fr-d-id').textContent = '#' + r.report_id;
    frPaintStatus(r.status);

    frSetImage('fr-d-image', 'fr-d-image-missing', r.detection.image);
    document.getElementById('fr-d-class').textContent = r.detection.class_name;

    // Fallback-safe: a report's severity fields can be null for an older
    // MODEL-sourced scan, so never show a literal "null" / "null%" — and
    // color the chip the same way the farmer's page and History do.
    const sevColor = frSeverityColor(r.detection.severity_percent);
    const sevEl = document.getElementById('fr-d-severity');
    sevEl.textContent = r.detection.severity_label || '—';
    sevEl.className = 'rg-report-stat-value ' + sevColor;
    const dmgEl = document.getElementById('fr-d-damage');
    dmgEl.textContent = Number.isFinite(Number(r.detection.severity_percent)) ? r.detection.severity_percent + '%' : '—';
    dmgEl.className = 'rg-report-stat-value ' + sevColor;

    document.getElementById('fr-d-source').textContent = frEngineLabels[r.detection.source] || r.detection.source || '—';

    // 'nutrient' only ever appears in info for a disease result (the farmer
    // page hides that section entirely for pests before snapshotting it),
    // so its presence/absence tells us which type badge to show.
    frUpdateTypeBadge(!(r.info && ('nutrient' in r.info)));
    frUpdateGauge(r.detection.confidence);

    frRenderInfo(r);
    frRenderSide(r);
    frResetAssessment(r);
    frPaintEscalateButton(r);

    document.getElementById('fr-list-view').classList.add('fr-hidden');
    document.getElementById('fr-detail-view').classList.remove('fr-hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

function frPaintStatus(statusKey) {
    const meta = frStatuses[statusKey] || frStatuses.pending;
    const badge = document.getElementById('fr-d-status');
    badge.textContent = meta.label;
    badge.className = 'badge fr-d-status-pill ' + meta.class;
}

// Renders the snapshot the farmer submitted, flagged red exactly like the
// "Report the Problem" modal — the name/gauge chip and the severity/damage
// chips get the boxed red outline (.rg-flag-chip.rg-flagged), the
// knowledge-base rows below get the red underline (.rg-flag-row.rg-flagged).
// Same section keys the report modal wrote into flagged_sections.
function frRenderInfo(r) {
    const flagged = r.flagged_sections || [];
    const info = r.info || {};

    // "name" covers both the name chip AND the confidence gauge chip
    // (data-section="name" on both), same as the farmer's report modal.
    document.querySelectorAll('#fr-block-name, #fr-block-gauge, #fr-block-severity, #fr-block-damagelevel')
        .forEach(block => {
            const isFlagged = flagged.includes(block.dataset.section);
            block.classList.toggle('rg-flagged', isFlagged);
        });

    // Knowledge-base text, in the detection page's order. Sections the
    // farmer's result didn't have are simply absent from info.
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

function frEscape(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}

function frResetAssessment(r) {
    document.querySelectorAll('input[name="assessment"]').forEach(x => x.checked = false);
    document.querySelectorAll('.fr-title-check').forEach(x => x.checked = false);
    document.getElementById('fr-corrected-class').value = '';
    document.getElementById('fr-corrected-severity').value = '';
    document.getElementById('fr-notes').value = (r.review && r.review.notes) || '';
    document.getElementById('fr-advice').value = (r.review && r.review.advice) || '';

    // Re-opening an already-reviewed report shows what was saved, so it can
    // be corrected and saved again.
    if (r.review && r.review.assessment) {
        const prev = document.querySelector(`input[name="assessment"][value="${r.review.assessment}"]`);
        if (prev) prev.checked = true;
        if (r.review.corrected_class) document.getElementById('fr-corrected-class').value = r.review.corrected_class;
        if (r.review.corrected_severity) document.getElementById('fr-corrected-severity').value = r.review.corrected_severity;
        (r.review.corrected_sections || []).forEach(key => {
            const cb = document.querySelector(`.fr-title-check[value="${key}"]`);
            if (cb) cb.checked = true;
        });
    }
    frOnAssessmentChange();
}

// "AI result is correct" keeps the panel minimal — just notes/advice and
// Save. Anything else reveals the corrected-detection controls.
window.frOnAssessmentChange = function() {
    const picked = document.querySelector('input[name="assessment"]:checked');
    const value = picked ? picked.value : '';

    const needsCorrection = value === 'incorrect' || value === 'info_incorrect';
    document.getElementById('fr-corrected-block').classList.toggle('fr-hidden', !needsCorrection);

    // "Information is incorrect" only touches the KB text, not the class or
    // severity — so those two selects stay out of the way.
    const showClass = value === 'incorrect';
    document.getElementById('fr-corrected-class-wrap').classList.toggle('fr-hidden', !showClass);
    document.getElementById('fr-corrected-severity-wrap').classList.toggle('fr-hidden', !showClass);

    frUpdateTitlesSummary();
};

window.frToggleTitlesDropdown = function() {
    document.getElementById('fr-titles-menu').classList.toggle('fr-hidden');
};

function frSelectedTitles() {
    return Array.from(document.querySelectorAll('.fr-title-check:checked')).map(cb => cb.value);
}

function frUpdateTitlesSummary() {
    const chosen = frSelectedTitles();
    const el = document.getElementById('fr-titles-summary');
    if (!el) return;
    if (!chosen.length) {
        el.textContent = 'Select information titles';
        el.className = 'text-secondary';
    } else {
        el.textContent = chosen.length === 1 ? frSections[chosen[0]] : chosen.length + ' titles selected';
        el.className = 'text-white';
    }
}

document.querySelectorAll('.fr-title-check').forEach(cb => cb.addEventListener('change', frUpdateTitlesSummary));
document.addEventListener('click', function (e) {
    const dd = document.getElementById('fr-titles-dd');
    if (dd && !dd.contains(e.target)) document.getElementById('fr-titles-menu').classList.add('fr-hidden');
});

// ==================== SUMMARY + RESOLUTION ====================
// Built as HTML strings so the exact same summary / resolution renders in the
// bottom panel of the review screen AND in the floating View modal.
const frAssessmentLabels = {
    correct:          'AI result is correct',
    incorrect:        'AI result is incorrect',
    info_incorrect:   'Information is incorrect',
    need_image:       'Need another image',
    cannot_determine: 'Cannot determine'
};

function frAttr(text) {
    return frEscape(text).replace(/"/g, '&quot;');
}

// The "Farmer's Report" card: who, when, what problem, their description,
// their suggestion and supporting photo.
function frSummaryHtml(r) {
    const problems = r.problem_types || [];
    const tags = problems.length
        ? problems.map(p => `<span class="fr-report-tag">${frEscape(p)}</span>`).join('')
        : '—';
    const initial = (r.farmer_name || '?').trim().charAt(0).toUpperCase();

    let html = `<div class="fr-panel fr-report-card h-100 p-0">
        <div class="fr-report-card-top">
            <div class="fr-report-avatar">${frEscape(initial)}</div>
            <div class="fr-report-card-top-text">
                <div class="fr-report-name-lg"><i class="fa-solid fa-flag me-1"></i> Farmer's Report</div>
                <div class="fr-report-sub">Submitted by <span class="text-light">${frEscape(r.farmer_name)}</span> · <span>${frEscape(r.submitted_at)}</span></div>
            </div>
        </div>
        <div class="fr-report-card-body">
            <div class="fr-report-field">
                <span class="fr-report-field-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <div class="fr-report-field-body">
                    <div class="fr-report-field-label">Problem</div>
                    <div class="fr-report-tags">${tags}</div>
                </div>
            </div>
            <div class="fr-report-field">
                <span class="fr-report-field-icon"><i class="fa-solid fa-quote-left"></i></span>
                <div class="fr-report-field-body">
                    <div class="fr-report-field-label">Description</div>
                    <div class="fr-report-message">"${frEscape(r.message)}"</div>
                </div>
            </div>`;

    if (r.suggested_name) {
        html += `<div class="fr-report-field">
                <span class="fr-report-field-icon"><i class="fa-solid fa-lightbulb"></i></span>
                <div class="fr-report-field-body">
                    <div class="fr-report-field-label">Farmer suggests</div>
                    <span class="fr-report-suggest-tag">${frEscape(r.suggested_name)}</span>
                </div>
            </div>`;
    }

    if (r.support_image) {
        html += `<div class="fr-report-field">
                <span class="fr-report-field-icon"><i class="fa-solid fa-camera"></i></span>
                <div class="fr-report-field-body">
                    <div class="fr-report-field-label">Supporting photo from farmer</div>
                    <span class="fr-report-photo-btn">
                        <img class="fr-thumb" src="${frAttr(r.support_image)}" alt="Supporting photo" onclick="frZoom(this.src)">
                    </span>
                </div>
            </div>`;
    }

    return html + '</div></div>';
}

// The technician's saved resolve data. Rendered as underlined fields (no
// boxes) so it reads like a review sheet rather than a stack of cards.
function frResolutionHtml(r) {
    const rv = r.review;
    if (!rv) {
        return `<div class="fr-wait-box">
            <div class="fw-bold text-white mb-1"><i class="fa-solid fa-clock me-2 text-warning"></i>No solution saved yet</div>
            <p class="small text-light mb-0">Click "Solution" to add the technician's assessment, notes and advice.</p>
        </div>`;
    }

    const needImage = rv.assessment === 'need_image';
    const titles = (rv.corrected_sections || []).map(k => frSections[k] || k);
    const det = r.detection || {};

    let html = `<div>
        <div class="mb-2 small text-light">Reviewed by <span class="fw-bold text-white">${frEscape(rv.reviewed_by || '—')}</span>
            · <span class="text-secondary">${frEscape(rv.reviewed_at || '—')}</span></div>
        <div class="mb-2"><span class="sr-assess ${frAttr(rv.assessment || '')}">${frEscape(frAssessmentLabels[rv.assessment] || rv.assessment || '—')}</span></div>`;

    if (needImage) {
        html += `<div class="fr-resolve-field">
            <div class="fr-resolve-label"><i class="fa-solid fa-camera me-1"></i>Status</div>
            <div class="fr-resolve-value">Another photo was requested from the farmer.</div>
        </div>`;
    } else {
        html += `<div class="fr-resolve-split">
            <div class="fr-resolve-field">
                <div class="fr-resolve-label">Original AI Detection</div>
                <div class="fr-resolve-value">${frEscape(det.class_name)}</div>
                <div class="small text-secondary mt-1">Confidence: ${frEscape(String(det.confidence ?? 0))}% · Severity: <span class="text-danger">${frEscape(det.severity_label || '—')}</span></div>
            </div>
            <div class="fr-resolve-field">
                <div class="fr-resolve-label">Technician-Corrected Detection</div>
                <div class="fr-resolve-value text-success fw-bold">${frEscape(rv.corrected_name || (rv.assessment === 'correct' ? 'Confirmed correct' : 'No change'))}</div>
                <div class="small text-secondary mt-1">Severity: <span class="text-warning">${frEscape(rv.corrected_severity || det.severity_label || '—')}</span></div>
            </div>
        </div>`;
    }

    if (titles.length) {
        html += `<div class="fr-resolve-field">
            <div class="fr-resolve-label">Information Marked For Correction</div>
            <div class="fr-resolve-value text-warning">${frEscape(titles.join(', '))}</div>
        </div>`;
    }

    html += `<div class="fr-resolve-field">
        <div class="fr-resolve-label"><i class="fa-solid fa-note-sticky me-1"></i>Technician Notes</div>
        <div class="fr-resolve-value">${frEscape(rv.notes || '—')}</div>
    </div>`;

    html += `<div class="fr-resolve-field">
        <div class="fr-resolve-label"><i class="fa-solid fa-comment-medical me-1"></i>Advice To Farmer</div>
        <div class="fr-resolve-value ${rv.advice ? '' : 'text-muted-italic'}">${frEscape(rv.advice || 'No advice given.')}</div>
    </div>`;

    return html + '</div>';
}

// Side panels of the review screen: farmer's summary on top, the
// technician's saved resolve data underneath. The "Solution" button only
// shows while nothing has been saved yet — once a solution exists, the
// resolve-data three-dot menu's "Update" does the same job.
function frRenderSide(r) {
    document.getElementById('fr-side-summary').innerHTML = frSummaryHtml(r);
    document.getElementById('fr-side-resolution').innerHTML = frResolutionHtml(r);

    const solutionBtn = document.getElementById('fr-solution-btn');
    if (solutionBtn) solutionBtn.classList.toggle('fr-hidden', !!(r.review));
}

// ==================== SOLUTION MODAL ====================
// Holds the assessment radios + corrected-detection inputs + notes/advice +
// the Escalate/Save buttons. Opened by the "Solution" button or by "Update"
// in the resolve-data three-dot menu; every field inside it is wired to
// #fr-review-form via the HTML `form` attribute, so both buttons still
// submit the same form the modal just floats over.
window.frOpenSolutionModal = function () {
    const modal = document.getElementById('fr-solution-modal');
    if (!modal) return;
    modal.classList.remove('fr-hidden');
    modal.scrollTop = 0;
};

window.frCloseSolutionModal = function () {
    document.getElementById('fr-solution-modal').classList.add('fr-hidden');
};

// ==================== RESOLVE-DATA THREE-DOT MENU ====================
window.frToggleResolveDots = function (e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('fr-resolve-dots-menu');
    if (menu) menu.classList.toggle('fr-hidden');
};
document.addEventListener('click', function (e) {
    const dd = document.getElementById('fr-resolve-dots-dd');
    const menu = document.getElementById('fr-resolve-dots-menu');
    if (dd && menu && !dd.contains(e.target)) menu.classList.add('fr-hidden');
});

// "Delete" in the resolve-data three-dot menu: clears the saved solution on
// the server (technician.reports.review.clear) and puts the report back to
// Pending, exactly as if it had never been reviewed. Blocked server-side
// once a report has been escalated.
window.frDeleteResolution = function () {
    if (!frCurrent || !frCurrent.review) {
        alert('There is no saved solution to delete yet.');
        return;
    }
    if (!confirm('Delete the saved solution for this report?\n\nThe assessment, notes and advice will be removed and the report will go back to Pending.')) return;

    const url = frClearReviewUrlTemplate.replace('__ID__', frCurrent.id);
    const token = document.querySelector('#fr-review-form input[name="_token"]')?.value || '';

    fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token
        }
    })
        .then(res => res.json().then(data => ({ ok: res.ok, data })))
        .then(({ ok, data }) => {
            if (!ok || !data.success) {
                alert((data && data.message) || 'Could not delete this solution.');
                return;
            }

            frCurrent.review = null;
            frCurrent.status = 'pending';
            const idx = frReports.findIndex(x => Number(x.id) === Number(frCurrent.id));
            if (idx !== -1) { frReports[idx].review = null; frReports[idx].status = 'pending'; }

            frPaintStatus('pending');
            frResetAssessment(frCurrent);
            frRenderSide(frCurrent);
        })
        .catch(() => alert('Could not delete this solution. Please check your connection and try again.'));
};

// ==================== VIEW MODAL (escalated-list "View") ====================
let frEscModalId = null;

function frShowEscModal(r) {
    frEscModalId = r.id;
    document.getElementById('fe-m-title').textContent = r.sr_code ? 'System Report #' + r.sr_code : 'Report #' + r.report_id;

    const badge = document.getElementById('fe-m-admin');
    if (r.escalated && r.admin_status) {
        const meta = frAdminStatuses[r.admin_status] || frAdminStatuses.pending;
        badge.className = 'sr-badge sr-status-' + r.admin_status;
        badge.textContent = 'Admin: ' + meta.label;
    } else {
        badge.className = 'sr-badge fr-hidden';
    }

    document.getElementById('fe-summary').innerHTML = frSummaryHtml(r);
    document.getElementById('fe-resolution').innerHTML = frResolutionHtml(r);
    document.getElementById('fe-m-footnote').textContent = r.escalated_at ? 'Escalated ' + r.escalated_at : '';

    const modal = document.getElementById('fe-modal');
    modal.classList.remove('fr-hidden');
    modal.scrollTop = 0;
}

window.frOpenEscModal = function (id) {
    const r = frEscalated.find(x => Number(x.id) === Number(id));
    if (r) frShowEscModal(r);
};

window.frCloseEscModal = function () {
    document.getElementById('fe-modal').classList.add('fr-hidden');
    frEscModalId = null;
};

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!document.getElementById('fr-lightbox').classList.contains('fr-hidden')) { frCloseZoom(); return; }
    if (!document.getElementById('fr-solution-modal').classList.contains('fr-hidden')) { frCloseSolutionModal(); return; }
    if (!document.getElementById('fe-modal').classList.contains('fr-hidden')) frCloseEscModal();
});

// ==================== ADMIN STATUS (live) ====================
// The status in the "Escalated to admin" list is the ADMIN's status. It is
// read from the database on every page load, and re-checked every 15s here so
// a change the admin makes shows up without refreshing.
function frPaintAdminStatus(id, status) {
    const meta = frAdminStatuses[status] || frAdminStatuses.pending;
    const r = frEscalated.find(x => Number(x.id) === Number(id));
    if (r) r.admin_status = status;

    const row = document.querySelector(`#fe-table-body tr[data-esc-id="${id}"]`);
    const badge = row ? row.querySelector('.sr-status-badge') : null;
    if (badge) {
        badge.className = 'sr-badge sr-status-badge sr-status-' + status;
        badge.textContent = meta.label;
    }

    if (frEscModalId !== null && Number(frEscModalId) === Number(id)) {
        const mb = document.getElementById('fe-m-admin');
        mb.className = 'sr-badge sr-status-' + status;
        mb.textContent = 'Admin: ' + meta.label;
    }
}

async function frPollEscalations() {
    if (document.hidden || !frEscalated.length) return;
    try {
        const res = await fetch(frEscStatusUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store'
        });
        if (!res.ok) return;
        const data = await res.json();
        Object.keys(data).forEach(id => frPaintAdminStatus(id, data[id]));
    } catch (e) { /* offline / logged out: try again next tick */ }
}
setInterval(frPollEscalations, 15000);
document.addEventListener('visibilitychange', function () { if (!document.hidden) frPollEscalations(); });

// Already-escalated reports show a disabled "Escalated" state instead.
function frPaintEscalateButton(r) {
    const btn = document.getElementById('fr-escalate-btn');
    if (!btn) return;
    if (r.escalated) {
        btn.disabled = true;
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Escalated to Admin';
    } else {
        btn.disabled = false;
        btn.className = 'btn btn-sm btn-outline-danger';
        btn.innerHTML = '<i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Escalate to Admin';
    }
}

// Escalate = save whatever is filled in below, then send the report to the
// admin. It submits the SAME form as "Save Review & Resolve" (so the same
// validation runs below), only pointed at the escalate route instead.
window.frEscalate = function() {
    if (!frCurrent || frCurrent.escalated) return;
    if (!confirm('Escalate this report to the admin?\n\nYour assessment, notes and advice will be saved and sent to the admin together with the farmer\'s report.')) return;

    const form = document.getElementById('fr-review-form');
    form.dataset.escalating = '1';
    form.action = frEscalateUrlTemplate.replace('__ID__', frCurrent.id);
    form.requestSubmit();
};

// Client-side guard so the farmer-facing result is never saved half-filled;
// the controller validates the same rules server-side. Runs for BOTH
// "Save Review & Resolve" and "Escalate to Admin".
document.getElementById('fr-review-form').addEventListener('submit', function (e) {
    const form = e.currentTarget;
    const escalating = form.dataset.escalating === '1';

    const stop = (message) => {
        e.preventDefault();
        // Validation stopped an escalate: point the form back at the normal
        // review route so "Save Review & Resolve" still works afterwards.
        if (escalating && frCurrent) {
            form.action = frReviewUrlTemplate.replace('__ID__', frCurrent.id);
        }
        delete form.dataset.escalating;
        alert(message);
    };

    const picked = document.querySelector('input[name="assessment"]:checked');
    if (!picked) return stop('Please choose a technician assessment first.');
    if (escalating && picked.value === 'need_image') {
        return stop('A report that needs another image from the farmer cannot be escalated yet. Choose a different assessment, or use Save Review & Resolve.');
    }
    if (picked.value === 'incorrect' && !document.getElementById('fr-corrected-class').value) {
        return stop('Please select the correct pest/disease.');
    }
    if (!document.getElementById('fr-notes').value.trim()) {
        return stop('Please add your technician notes.');
    }
});
</script>
@endsection