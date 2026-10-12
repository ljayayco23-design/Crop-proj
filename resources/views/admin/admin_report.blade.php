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
    .fr-lightbox img { max-width: 92vw; max-height: 88vh; border-radius: 12px; display: block; }
    /* Report photos stay plain everywhere; YOLO11n boxes are painted on this
       canvas only while the photo is enlarged (same as History). */
    .sr-lightbox-wrap { position: relative; display: inline-block; max-width: 100%; }
    .sr-lightbox-canvas { position: absolute; left: 0; top: 0; pointer-events: none; border-radius: 12px; }
    /* Model name chip in the modal header, next to the report ID — never on
       top of the photo. */
    .sr-model-chip {
        display: inline-flex; align-items: center;
        background: rgba(16,185,129,.16); color: #34d399;
        border: 1px solid rgba(16,185,129,.4);
        font-size: .68rem; font-weight: 800; letter-spacing: .03em;
        padding: 3px 9px; border-radius: 6px; text-transform: uppercase;
        white-space: nowrap;
    }

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

    /* ---------- Three filter cards (same look as the farmer Feedback & Support cards) ---------- */
    .sr-cards { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin-bottom: 18px; }
    @media (max-width: 1199.98px) { .sr-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
    .sr-card-tab {
        display: flex; align-items: center; gap: 10px; width: 100%; text-align: left;
        background: #121826; border: 1px solid #263349; border-radius: 12px;
        padding: 14px; color: #e2e8f0; cursor: pointer;
        transition: border-color .15s ease, box-shadow .15s ease, transform .15s ease, background-color .15s ease;
    }
    .sr-card-tab:hover { border-color: #3b4c66; transform: translateY(-1px); }
    .sr-card-tab:focus-visible { outline: 2px solid #10b981; outline-offset: 2px; }
    .sr-card-tab.active {
        border-color: #10b981; background: rgba(16,185,129,.07);
        box-shadow: 0 0 0 1px #10b981 inset, 0 8px 24px rgba(16,185,129,.12);
    }
    .sr-card-icon {
        width: 42px; height: 42px; border-radius: 12px; flex: 0 0 auto;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem; color: #fff;
    }
    .sr-icon-ai { background: #ef4444; }
    .sr-icon-kb { background: #3b82f6; }
    .sr-icon-sys { background: #8b5cf6; }
    .sr-icon-sug { background: #f59e0b; }
    .sr-icon-fb { background: #06b6d4; }
    .sr-card-body { flex: 1 1 auto; min-width: 0; }
    .sr-card-title { font-weight: 800; color: #fff; font-size: .95rem; line-height: 1.25; display: block; }
    .sr-card-desc { font-size: .76rem; color: #94a3b8; line-height: 1.35; margin-top: 2px; display: block; }
    .sr-card-count {
        flex: 0 0 auto; font-size: .72rem; font-weight: 800; padding: 3px 10px; border-radius: 999px;
        background: rgba(148,163,184,.15); color: #cbd5e1;
    }
    .sr-card-tab.active .sr-card-count { background: rgba(16,185,129,.2); color: #34d399; }

    /* ---------- Resolve / Assigned Tech panels (inside the View modal) ---------- */
    .sr-tech-list { display: flex; flex-direction: column; gap: 6px; max-height: 190px; overflow-y: auto; }
    .sr-tech-item {
        display: flex; align-items: center; gap: 10px; padding: 9px 11px; border-radius: 9px; cursor: pointer;
        border: 1px solid #334155; background: #0f1522; color: #e2e8f0; font-size: .85rem;
    }
    .sr-tech-item:hover { border-color: #475569; }
    .sr-tech-item.selected { border-color: #38bdf8; background: rgba(56,189,248,.1); }
    .sr-tech-item input { accent-color: #38bdf8; }
    .sr-tech-sub { font-size: .72rem; color: #94a3b8; }
    .sr-prio { display: inline-flex; padding: 3px 10px; border-radius: 999px; font-size: .74rem; font-weight: 700; }
    .sr-prio-low { background: rgba(148,163,184,.18); color: #cbd5e1; }
    .sr-prio-medium { background: rgba(250,204,21,.18); color: #facc15; }
    .sr-prio-high { background: rgba(251,146,60,.18); color: #fb923c; }
    .sr-prio-urgent { background: rgba(239,68,68,.2); color: #f87171; }

    /* ---------- Farmer suggestions / general feedback tables ---------- */
    .sg-table thead th {
        font-size: .74rem; text-transform: uppercase; letter-spacing: .04em;
        color: #94a3b8; border-bottom: 1px solid #263349; white-space: nowrap;
    }
    .sg-table tbody td { border-bottom: 1px solid #1d2636; color: #e2e8f0; font-size: .86rem; vertical-align: middle; }
    .sg-table tbody tr:hover { background: rgba(255,255,255,.03); }
    .sg-msg { max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sg-status-declined { background: rgba(239,68,68,.18); color: #f87171; }
    .sg-cat { background: rgba(59,130,246,.18); color: #60a5fa; }
    .sg-count { font-size: .72rem; font-weight: 800; padding: 3px 10px; border-radius: 999px; background: rgba(148,163,184,.15); color: #cbd5e1; }
    .sg-message-box { background: #0f1522; border: 1px solid #263349; border-radius: 10px; padding: 12px; white-space: pre-wrap; word-break: break-word; font-size: .88rem; color: #e2e8f0; }
    .sg-shot { max-width: 100%; max-height: 220px; border-radius: 10px; border: 1px solid #334155; cursor: zoom-in; }

    @media (max-width: 767.98px) {
        .sr-cards { grid-template-columns: 1fr; gap: 10px; margin-bottom: 14px; }
        .sr-card-tab { padding: 12px; gap: 12px; }
        .sr-card-icon { width: 42px; height: 42px; font-size: 1.1rem; }
    }

    /* =====================================================================
       MOBILE (<768px): stack the header/toolbar full-width and collapse the
       reports table into compact grid-cards — the same data-label -> CSS
       Grid pattern used on the User Log page — so phones never need
       horizontal scrolling. Tablets/desktop keep the table above untouched.
    ===================================================================== */
    @media (max-width: 767.98px) {
        .fr-card.p-3.p-md-4 { padding: 14px !important; }

        /* Wrong Detection summary: stack donut above legend, full width */
        .wd-donut-wrap { margin: 0 auto; }
        #wd-legend { min-width: 0 !important; width: 100%; }

        /* Toolbar: status/type/search filters go full width, stacked */
        .fr-card .d-flex.flex-wrap.gap-2.justify-content-between.align-items-center.mb-3 { flex-direction: column; align-items: stretch !important; }
        .fr-card .d-flex.flex-wrap.gap-2.justify-content-between.align-items-center.mb-3 > .d-flex { width: 100%; flex-direction: column; }
        #sr-filter-status, #sr-filter-type, #sr-filter-search { width: 100% !important; }

        .fr-table thead { display: none; }
        .fr-table, .fr-table tbody { display: block; width: 100%; }

        .fr-table tbody tr {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-areas:
                "id       type"
                "category category"
                "desc     desc"
                "date     status"
                "actions  actions";
            column-gap: 12px;
            /* Card used to have its own dark gradient fill, which sat as a
               visibly different shade on top of the .fr-card panel behind
               it. Drop the fill so the card blends into the panel — the
               border (color-matched, not a bright line) is what separates
               one report from the next now. */
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
            /* Belt-and-suspenders: the theme's own table/cell styles can
               still paint a background on individual <td>s even with the
               row itself transparent — that stray fill is what was
               showing up as a leftover dark box (e.g. under the Action
               button). Strip it so every cell matches the card. */
            background: transparent !important;
        }

        .fr-table tbody td[data-label="Report ID"] { grid-area: id; font-size: .95rem; margin-bottom: 10px; }
        .fr-table tbody td[data-label="Type"]       { grid-area: type; text-align: right; margin-bottom: 10px; }
        .fr-table tbody td[data-label="Category"]   { grid-area: category; margin-bottom: 8px; }
        .fr-table tbody td[data-label="Description"] { grid-area: desc; margin-bottom: 10px; }
        .fr-table tbody td[data-label="Date"]       { grid-area: date; }
        .fr-table tbody td[data-label="Status"]     { grid-area: status; text-align: right; }
        .fr-table tbody td[data-label="Action"] {
            grid-area: actions;
            text-align: right;
            padding-top: 10px;
            margin-top: 6px;
            border-top: 1px solid #1d2636 !important;
        }

        .fr-table tbody td[data-label]:not([data-label="Report ID"]):not([data-label="Type"]):not([data-label="Status"]):not([data-label="Action"])::before {
            content: attr(data-label);
            display: block;
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 3px;
        }

        .sr-desc { max-width: 100%; }

        /* Report modal: full-bleed on phones, no side gutters */
        .rg-report-backdrop { padding: 0; align-items: stretch; }
        .rg-report-dialog { max-width: 100%; height: 100%; max-height: 100%; border-radius: 0; }
        .rg-report-thumb { width: 72px; height: 72px; }
        .rg-confidence-gauge { width: 110px; height: 64px; }
    }
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

    {{-- Three cards = quick Type filter (click again to clear). Counts come from the escalated reports. --}}
    <div class="sr-cards" id="sr-cards">
        <button type="button" class="sr-card-tab" data-type="AI Model" onclick="srPickCard('AI Model')">
            <span class="sr-card-icon sr-icon-ai"><i class="fa-solid fa-robot"></i></span>
            <span class="sr-card-body">
                <span class="sr-card-title">AI Model</span>
                <span class="sr-card-desc">Wrong detection, severity or damage level.</span>
            </span>
            <span class="sr-card-count" id="sr-count-ai_model">0</span>
        </button>
        <button type="button" class="sr-card-tab" data-type="Knowledge Base" onclick="srPickCard('Knowledge Base')">
            <span class="sr-card-icon sr-icon-kb"><i class="fa-solid fa-book-open"></i></span>
            <span class="sr-card-body">
                <span class="sr-card-title">Knowledge Base</span>
                <span class="sr-card-desc">Wrong treatment, causes, symptoms or prevention.</span>
            </span>
            <span class="sr-card-count" id="sr-count-knowledge_base">0</span>
        </button>
        <button type="button" class="sr-card-tab" data-type="System" onclick="srPickCard('System')">
            <span class="sr-card-icon sr-icon-sys"><i class="fa-solid fa-gears"></i></span>
            <span class="sr-card-body">
                <span class="sr-card-title">System</span>
                <span class="sr-card-desc">Other system problems.</span>
            </span>
            <span class="sr-card-count" id="sr-count-system">0</span>
        </button>
        <button type="button" class="sr-card-tab" data-panel="suggestions" onclick="sgPickCard('suggestions')">
            <span class="sr-card-icon sr-icon-sug"><i class="fa-solid fa-lightbulb"></i></span>
            <span class="sr-card-body">
                <span class="sr-card-title">Suggest Improvement</span>
                <span class="sr-card-desc">Ideas sent by farmers.</span>
            </span>
            <span class="sr-card-count" id="sg-card-count-suggestions">0</span>
        </button>
        <button type="button" class="sr-card-tab" data-panel="feedback" onclick="sgPickCard('feedback')">
            <span class="sr-card-icon sr-icon-fb"><i class="fa-solid fa-comments"></i></span>
            <span class="sr-card-body">
                <span class="sr-card-title">General Feedback</span>
                <span class="sr-card-desc">Ratings and comments from farmers.</span>
            </span>
            <span class="sr-card-count" id="sg-card-count-feedback">0</span>
        </button>
    </div>

    {{-- ============ Panel 1: escalated reports (AI Model / Knowledge Base / System cards) ============ --}}
    <div id="sr-panel-reports">

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
                        @php
                            // Exact model behind each detection (same names as the
                            // detection page). AI-Model rows show it in the Type
                            // badge instead of the generic "AI Model" text; the
                            // Type filter still works off the category below.
                            $modelLabels = ['yolo11n' => 'YOLO11n', 'groq' => 'Groq AI', 'model' => 'MobileNetV2'];
                        @endphp
                        @foreach($reports as $report)
                            @php
                                $rowModel = $report['type_key'] === 'ai_model'
                                    ? ($modelLabels[$report['detection']['source'] ?? ''] ?? null)
                                    : null;
                            @endphp
                            <tr data-id="{{ $report['id'] }}"
                                data-status="{{ $report['admin_status'] }}"
                                data-type="{{ $report['type'] }}"
                                data-search="{{ strtolower($report['sr_code'].' '.$report['report_id'].' '.$report['farmer_name'].' '.$report['detection']['class_name'].' '.$report['category'].' '.$report['description']) }}">
                                <td class="fw-bold" data-label="Report ID">#{{ $report['sr_code'] }}</td>
                                <td data-label="Type"><span class="sr-badge sr-type-{{ $report['type_key'] }}" title="{{ $report['type'] }}">{{ $rowModel ?? $report['type'] }}</span></td>
                                <td data-label="Category">{{ $report['category'] }}</td>
                                <td class="sr-desc" data-label="Description">{{ $report['description'] }}</td>
                                <td class="text-secondary" data-label="Date">{{ $report['escalated_date'] }}</td>
                                <td data-label="Status">
                                    <span class="sr-badge sr-status-badge sr-status-{{ $report['admin_status'] }}">{{ $statuses[$report['admin_status']]['label'] }}</span>
                                </td>
                                <td class="text-end" data-label="Action">
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

    </div>{{-- /#sr-panel-reports --}}

    {{-- ============ Panel 2: Farmer suggestions (Suggest Improvement card) ============ --}}
    <div id="sr-panel-suggestions" class="fr-card p-3 p-md-4 fr-hidden">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-lightbulb text-warning me-2"></i>Farmer Suggestions</h6>
                <span class="sg-count" id="sg-sug-count">0</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <select id="sg-sug-status" class="form-select form-select-sm fr-input" style="width: 165px;" onchange="sgRender()">
                    <option value="">All Status</option>
                    <option value="new">New</option>
                    <option value="assigned">Assigned</option>
                    <option value="in_progress">In Progress</option>
                    <option value="resolved">Resolved</option>
                    <option value="declined">Not Feasible</option>
                </select>
                <input type="text" id="sg-sug-search" class="form-control form-control-sm fr-input"
                       style="width: 210px;" placeholder="Search code, farmer..." oninput="sgRender()">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless mb-0 sg-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Farmer</th><th>Category</th><th>Suggestion</th>
                        <th>Date</th><th>Status</th><th>Assigned to</th><th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="sg-sug-body"></tbody>
            </table>
            <div id="sg-sug-empty" class="text-center text-secondary py-4 fr-hidden">No farmer suggestions yet.
            @if(!empty($sgDiag))
                <div class="small mt-1">
                    @if(!$sgDiag['table'])
                        The farmer_feedback table does not exist. Run php artisan migrate.
                    @else
                        {{ $sgDiag['total'] }} entries exist in total, {{ $sgDiag['in_area'] }} from farmers in your area
                        (your province ID: {{ $sgDiag['province_id'] ?? 'empty' }}, city ID: {{ $sgDiag['city_id'] ?? 'empty' }}).
                    @endif
                </div>
            @endif
            </div>
        </div>
    </div>

    {{-- ============ Panel 3: General feedback (General Feedback card) ============ --}}
    <div id="sr-panel-feedback" class="fr-card p-3 p-md-4 fr-hidden">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <div class="d-flex align-items-center gap-2">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-comments text-info me-2"></i>General Feedback</h6>
                <span class="sg-count" id="sg-fb-count">0</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <select id="sg-fb-feature" class="form-select form-select-sm fr-input" style="width: 165px;" onchange="sgRender()">
                    <option value="">All Features</option>
                    @foreach($feedbackFeatures ?? [] as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                <input type="text" id="sg-fb-search" class="form-control form-control-sm fr-input"
                       style="width: 210px;" placeholder="Search code, farmer..." oninput="sgRender()">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless mb-0 sg-table">
                <thead>
                    <tr>
                        <th>ID</th><th>Farmer</th><th>Feature</th><th>Rating</th>
                        <th>Comment</th><th>Date</th><th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody id="sg-fb-body"></tbody>
            </table>
            <div id="sg-fb-empty" class="text-center text-secondary py-4 fr-hidden">No general feedback yet.
            @if(!empty($sgDiag))
                <div class="small mt-1">
                    @if(!$sgDiag['table'])
                        The farmer_feedback table does not exist. Run php artisan migrate.
                    @else
                        {{ $sgDiag['total'] }} entries exist in total, {{ $sgDiag['in_area'] }} from farmers in your area
                        (your province ID: {{ $sgDiag['province_id'] ?? 'empty' }}, city ID: {{ $sgDiag['city_id'] ?? 'empty' }}).
                    @endif
                </div>
            @endif
            </div>
        </div>
    </div>
</div>

{{-- Shared three-dot menu (positioned under whichever row's button was clicked). --}}
<div id="sr-action-menu" class="sr-menu fr-hidden">
    <button type="button" class="sr-menu-item" onclick="srAction('view')">
        <i class="fa-solid fa-eye text-info"></i> View
    </button>
    <button type="button" class="sr-menu-item" onclick="srAction('delete')">
        <i class="fa-solid fa-trash text-danger"></i> Delete
    </button>
</div>

{{-- ============ View modal: farmer's report (left) + technician's resolution (right) ============ --}}
<div id="sr-modal" class="rg-report-backdrop fr-hidden" onclick="if (event.target === this) srCloseModal()">
    <div class="rg-report-dialog">
        <div class="rg-report-head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 class="mb-0 fw-bold text-white">System Report <span id="sr-m-id">#SR-000</span></h6>
                <span id="sr-m-model" class="sr-model-chip fr-hidden"></span>
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
                        <img id="sr-d-image" class="rg-report-thumb fr-hidden" alt="Reported detection" onclick="srZoom(this.src, srDetailBoxes)">
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

                </div>

                {{-- RIGHT: the technician's resolution --}}
                <div class="col-lg-6">

                    {{-- Admin actions: each button reveals its own panel (and its submit button). --}}
                    <div class="d-flex gap-2 mb-2">
                        <button type="button" id="sr-btn-resolve" class="btn btn-sm btn-outline-success flex-fill" onclick="srShowPanel('resolve')">
                            <i class="fa-solid fa-circle-check me-1"></i> Resolve
                        </button>
                        <button type="button" id="sr-btn-assign" class="btn btn-sm btn-outline-info flex-fill" onclick="srShowPanel('assign')">
                            <i class="fa-solid fa-user-gear me-1"></i> Assigned Tech
                        </button>
                    </div>

                    <div id="sr-panel-resolve" class="fr-panel mb-3 fr-hidden">
                        <label class="form-label small fw-bold text-white" for="sr-resolve-status">Status</label>
                        <select id="sr-resolve-status" class="form-select form-select-sm fr-input mb-3"></select>

                        <label class="form-label small fw-bold text-white" for="sr-resolve-notes">Notes <span class="text-danger">*</span></label>
                        <textarea id="sr-resolve-notes" rows="3" maxlength="2000" class="form-control fr-input mb-3"
                                  placeholder="Write what was done or decided..."></textarea>

                        <button type="button" id="sr-resolve-submit" class="btn btn-success btn-sm fw-bold w-100" onclick="srSubmitResolve()">
                            <i class="fa-solid fa-paper-plane me-1"></i> Submit
                        </button>
                    </div>

                    <div id="sr-panel-assign" class="fr-panel mb-3 fr-hidden">
                        <div class="small fw-bold text-white mb-2">Technicians in your province &amp; city</div>
                        <div id="sr-tech-list" class="sr-tech-list mb-3"></div>

                        <label class="form-label small fw-bold text-white" for="sr-assign-priority">Priority</label>
                        <select id="sr-assign-priority" class="form-select form-select-sm fr-input mb-3">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>

                        <label class="form-label small fw-bold text-white" for="sr-assign-notes">Notes</label>
                        <textarea id="sr-assign-notes" rows="3" maxlength="2000" class="form-control fr-input mb-3"
                                  placeholder="Instructions for the technician..."></textarea>

                        <button type="button" id="sr-assign-submit" class="btn btn-info btn-sm fw-bold w-100" onclick="srSubmitAssign()">
                            <i class="fa-solid fa-user-check me-1"></i> Assign
                        </button>
                    </div>

                    <div id="sr-current-admin" class="small mb-3 fr-hidden"></div>

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

{{-- ============ Suggestion / feedback modal ============ --}}
<div id="sg-modal" class="rg-report-backdrop fr-hidden" onclick="if (event.target === this) sgCloseModal()">
    <div class="rg-report-dialog" style="max-width: 920px;">
        <div class="rg-report-head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h6 class="mb-0 fw-bold text-white" id="sg-m-title">Suggestion</h6>
                <span id="sg-m-code" class="text-secondary small">#—</span>
                <span id="sg-m-status" class="sr-badge sr-status-pending">New</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="sgCloseModal()" title="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="rg-report-body">
            <div class="row g-4">
                {{-- LEFT: what the farmer sent --}}
                <div class="col-lg-6">
                    <div class="fr-col-head">Farmer's entry</div>
                    <div class="small text-secondary mb-3">
                        by <span id="sg-m-farmer" class="text-light">—</span> · <span id="sg-m-submitted">—</span>
                    </div>

                    <div class="mb-3">
                        <div class="small text-light mb-1" id="sg-m-label-key">Category</div>
                        <span id="sg-m-label" class="sr-badge sg-cat">—</span>
                    </div>

                    <div id="sg-m-rating-wrap" class="mb-3 fr-hidden">
                        <div class="small text-light mb-1">Rating</div>
                        <div id="sg-m-rating">—</div>
                    </div>

                    <div class="small text-light mb-1">Message</div>
                    <div id="sg-m-message" class="sg-message-box mb-3">—</div>

                    <div id="sg-m-shot-wrap" class="mb-3 fr-hidden">
                        <div class="small text-light mb-1">Screenshot</div>
                        <img id="sg-m-shot" class="sg-shot" alt="Screenshot" onclick="srZoom(this.src)">
                    </div>
                </div>

                {{-- RIGHT: admin actions + outcome --}}
                <div class="col-lg-6">
                    <div id="sg-actions" class="fr-hidden">
                        <div class="fr-col-head">Admin actions</div>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-sm btn-outline-info flex-fill" onclick="sgShowPanel('assign')">
                                <i class="fa-solid fa-user-gear me-1"></i> Assign Tech
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success flex-fill" onclick="sgShowPanel('resolve')">
                                <i class="fa-solid fa-circle-check me-1"></i> Resolve
                            </button>
                        </div>

                        <div id="sg-panel-assign" class="fr-panel mb-3 fr-hidden">
                            <div class="small fw-bold text-white mb-2">Technicians in your province &amp; city</div>
                            <div id="sg-tech-list" class="sr-tech-list mb-3"></div>

                            <label class="form-label small fw-bold text-white" for="sg-assign-priority">Priority</label>
                            <select id="sg-assign-priority" class="form-select form-select-sm fr-input mb-3">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>

                            <label class="form-label small fw-bold text-white" for="sg-assign-notes">Notes (private, for the technician)</label>
                            <textarea id="sg-assign-notes" rows="3" maxlength="2000" class="form-control fr-input mb-3"
                                      placeholder="Instructions for the technician..."></textarea>

                            <button type="button" id="sg-assign-submit" class="btn btn-info btn-sm fw-bold w-100" onclick="sgSubmitAssign()">
                                <i class="fa-solid fa-user-check me-1"></i> Assign
                            </button>
                        </div>

                        <div id="sg-panel-resolve" class="fr-panel mb-3 fr-hidden">
                            <label class="form-label small fw-bold text-white" for="sg-resolve-status">Status</label>
                            <select id="sg-resolve-status" class="form-select form-select-sm fr-input mb-3"></select>

                            <label class="form-label small fw-bold text-white" for="sg-resolve-notes">Notes <span class="text-danger">*</span></label>
                            <textarea id="sg-resolve-notes" rows="3" maxlength="2000" class="form-control fr-input mb-3"
                                      placeholder="What was done or decided (the farmer will see this)..."></textarea>

                            <button type="button" id="sg-resolve-submit" class="btn btn-success btn-sm fw-bold w-100" onclick="sgSubmitResolve()">
                                <i class="fa-solid fa-paper-plane me-1"></i> Submit
                            </button>
                        </div>

                        <div id="sg-current" class="small mb-3 fr-hidden"></div>
                    </div>

                    <div id="sg-support-wrap" class="fr-hidden">
                        <div class="fr-col-head" id="sg-support-head">Support</div>
                        <div class="fr-resolve-box">
                            <div class="mb-2"><span id="sg-support-badge" class="sr-badge sr-status-resolved">Resolved</span></div>
                            <p id="sg-support-notes" class="small text-light mb-2">—</p>
                            <div class="small text-secondary" id="sg-support-meta">—</div>
                        </div>
                    </div>

                    <div id="sg-empty-right" class="small text-secondary fr-hidden">General feedback is view-only.</div>
                </div>
            </div>
        </div>

        <div class="rg-report-foot">
            <button type="button" class="btn btn-sm btn-secondary" onclick="sgCloseModal()">Close</button>
        </div>
    </div>
</div>

<div id="sr-lightbox" class="fr-lightbox fr-hidden" onclick="srCloseZoom()">
    <div class="sr-lightbox-wrap">
        <img id="sr-lightbox-img" src="" alt="Enlarged photo">
        <canvas id="sr-lightbox-canvas" class="sr-lightbox-canvas"></canvas>
    </div>
</div>
@endsection

@section('scripts')
<script>
const srReports   = @json($reports);
const srSections  = @json($sections);
const srStatuses  = @json($statuses);
const srStatusUrl = "{{ route('admin.system_report.status', ['report' => '__ID__']) }}";
const SR_CSRF     = "{{ csrf_token() }}";

// Same engine names the detection page / technician page show. (The key used
// to be misspelled "yollo11n", so YOLO11n reports never matched a label.)
const srEngineLabels = { model: 'MobileNetV2', yolo11n: 'YOLO11n', groq: 'Groq AI' };

// The open report's YOLO11n boxes ({boxes:[...], src_w, src_h} or null), so the
// thumbnail's onclick can hand them to the lightbox.
let srDetailBoxes = null;

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
document.addEventListener('DOMContentLoaded', () => srSyncCards());

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
    srSyncCards();
};

// ==================== THREE CARDS (Type filter) ====================
let sgPanel = 'reports';   // which section is showing below the cards: reports | suggestions | feedback
function srSyncCards() {
    const type = document.getElementById('sr-filter-type')?.value || '';
    document.querySelectorAll('.sr-card-tab').forEach(c => {
        const on = c.dataset.panel
            ? sgPanel === c.dataset.panel
            : (sgPanel === 'reports' && c.dataset.type === type);
        c.classList.toggle('active', on);
    });
    ['ai_model', 'knowledge_base', 'system'].forEach(key => {
        const el = document.getElementById('sr-count-' + key);
        if (el) el.textContent = srReports.filter(r => r.type_key === key).length;
    });
}
window.srShowSection = function (name) {
    sgPanel = name;
    ['reports', 'suggestions', 'feedback'].forEach(k =>
        document.getElementById('sr-panel-' + k)?.classList.toggle('fr-hidden', k !== name));
    srSyncCards();
};

window.sgPickCard = function (name) {
    srShowSection(sgPanel === name ? 'reports' : name);   // click the active card again to go back
};
window.srPickCard = function (type) {
    srShowSection('reports');
    const select = document.getElementById('sr-filter-type');
    select.value = (select.value === type) ? '' : type;   // click the active card again to clear
    srApplyFilters();
};

// ==================== RESOLVE / ASSIGN / DELETE ====================
const srResolveUrl = "{{ route('admin.system_report.resolve', ['report' => '__ID__']) }}";
const srAssignUrl  = "{{ route('admin.system_report.assign', ['report' => '__ID__']) }}";
const srDeleteUrl  = "{{ route('admin.system_report.destroy', ['report' => '__ID__']) }}";
const srTechsUrl   = "{{ route('admin.system_report.technicians') }}";

let srTechs = null;          // cached technician list
let srPickedTech = null;     // technician id chosen in the Assigned Tech panel

async function srRequest(url, method, body) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': SR_CSRF,
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body ? JSON.stringify(body) : undefined
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.success === false) {
        const firstErr = data.errors ? Object.values(data.errors)[0][0] : null;
        throw new Error(firstErr || data.message || 'Something went wrong. Please try again.');
    }
    return data;
}

// Each button reveals ITS panel (with its submit button); clicking it again hides it.
window.srShowPanel = function (which) {
    const r = srFind(srModalId);
    const resolveP = document.getElementById('sr-panel-resolve');
    const assignP  = document.getElementById('sr-panel-assign');
    const open = (which === 'resolve' ? resolveP : assignP);
    const wasHidden = open.classList.contains('fr-hidden');

    resolveP.classList.add('fr-hidden');
    assignP.classList.add('fr-hidden');
    if (!wasHidden) return;
    open.classList.remove('fr-hidden');

    if (which === 'resolve' && r) {
        const sel = document.getElementById('sr-resolve-status');
        sel.innerHTML = Object.entries(srStatuses)
            .map(([key, m]) => `<option value="${key}">${srEscape(m.label)}</option>`).join('');
        sel.value = r.admin_status;
        document.getElementById('sr-resolve-notes').value = r.admin_notes || '';
    }
    if (which === 'assign' && r) {
        document.getElementById('sr-assign-priority').value = r.assigned_priority || 'medium';
        document.getElementById('sr-assign-notes').value = r.assigned_notes || '';
        srPickedTech = r.assigned_tech_id || null;
        srLoadTechs();
    }
};

async function srLoadTechs() {
    const box = document.getElementById('sr-tech-list');
    if (srTechs === null) {
        box.innerHTML = '<div class="small text-secondary">Loading technicians...</div>';
        try {
            srTechs = await srRequest(srTechsUrl, 'GET');
        } catch (e) {
            box.innerHTML = `<div class="small text-danger">${srEscape(e.message)}</div>`;
            return;
        }
    }
    srRenderTechs();
}

function srRenderTechs() {
    const box = document.getElementById('sr-tech-list');
    if (!srTechs.length) {
        box.innerHTML = '<div class="small text-secondary">No approved technician in your province and city yet.</div>';
        return;
    }
    box.innerHTML = srTechs.map(t => `
        <label class="sr-tech-item ${Number(srPickedTech) === Number(t.id) ? 'selected' : ''}">
            <input type="radio" name="sr-tech" value="${t.id}" ${Number(srPickedTech) === Number(t.id) ? 'checked' : ''}>
            <span><span class="fw-bold">${srEscape(t.name)}</span>
            ${t.barangay ? `<span class="sr-tech-sub d-block">${srEscape(t.barangay)}</span>` : ''}</span>
        </label>`).join('');
    box.querySelectorAll('input[name="sr-tech"]').forEach(inp => inp.addEventListener('change', () => {
        srPickedTech = Number(inp.value);
        srRenderTechs();
    }));
}

// Small summary under the buttons once something has been saved on this report.
function srRenderCurrentAdmin(r) {
    const el = document.getElementById('sr-current-admin');
    let html = '';
    if (r.assigned_tech_name) {
        html += `<div class="mb-1"><i class="fa-solid fa-user-check text-info me-1"></i>Assigned to <strong class="text-white">${srEscape(r.assigned_tech_name)}</strong>
                 <span class="sr-prio sr-prio-${srEscape(r.assigned_priority || 'medium')} ms-1">${srEscape((r.assigned_priority || 'medium').toUpperCase())}</span></div>`;
    }
    if (r.admin_notes) {
        html += `<div class="text-secondary"><i class="fa-solid fa-note-sticky me-1"></i>Admin notes: ${srEscape(r.admin_notes)}</div>`;
    }
    el.innerHTML = html;
    el.classList.toggle('fr-hidden', !html);
}

window.srSubmitResolve = async function () {
    const r = srFind(srModalId);
    if (!r) return;
    const notes = document.getElementById('sr-resolve-notes').value.trim();
    if (!notes) { alert('Please write a note before submitting.'); return; }

    const btn = document.getElementById('sr-resolve-submit');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';
    try {
        const data = await srRequest(srResolveUrl.replace('__ID__', r.id), 'POST', {
            status: document.getElementById('sr-resolve-status').value,
            notes
        });
        r.admin_status = data.status;
        r.admin_notes = data.notes;
        srPaintStatus(r);
        srRenderCurrentAdmin(r);
        document.getElementById('sr-panel-resolve').classList.add('fr-hidden');
    } catch (e) {
        alert(e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
};

window.srSubmitAssign = async function () {
    const r = srFind(srModalId);
    if (!r) return;
    if (!srPickedTech) { alert('Please choose a technician first.'); return; }

    const btn = document.getElementById('sr-assign-submit');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Assigning...';
    try {
        const data = await srRequest(srAssignUrl.replace('__ID__', r.id), 'POST', {
            technician_id: srPickedTech,
            priority: document.getElementById('sr-assign-priority').value,
            notes: document.getElementById('sr-assign-notes').value.trim()
        });
        r.assigned_tech_id = srPickedTech;
        r.assigned_tech_name = data.tech_name;
        r.assigned_priority = data.priority;
        r.assigned_notes = data.notes;
        r.admin_status = data.status;
        srPaintStatus(r);
        srRenderCurrentAdmin(r);
        document.getElementById('sr-panel-assign').classList.add('fr-hidden');
    } catch (e) {
        alert(e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
};

async function srDeleteReport(r) {
    if (!confirm('Delete report ' + r.sr_code + ' from the System Reports list?')) return;
    try {
        await srRequest(srDeleteUrl.replace('__ID__', r.id), 'DELETE');
        document.querySelector(`#sr-table-body tr[data-id="${r.id}"]`)?.remove();
        const i = srReports.findIndex(x => Number(x.id) === Number(r.id));
        if (i > -1) srReports.splice(i, 1);
        if (srModalId !== null && Number(srModalId) === Number(r.id)) srCloseModal();
        srApplyFilters();
    } catch (e) {
        alert(e.message);
    }
}

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

    if (action === 'delete') {
        await srDeleteReport(r);
        return;
    }

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

// Boxes are in the ORIGINAL photo's pixel space (src_w x src_h); the photo is
// drawn whole inside the lightbox, so scale/offset come from its own size.
let srLightboxBoxes = null;

function srDrawLightboxBoxes() {
    const canvas = document.getElementById('sr-lightbox-canvas');
    const img = document.getElementById('sr-lightbox-img');
    if (!canvas || !img) return;

    // While the lightbox is display:none the <img> reads 0x0 — a later call
    // (image load / next frame / resize) does the drawing.
    const w = img.clientWidth, h = img.clientHeight;
    if (!w || !h) return;

    canvas.width = w;
    canvas.height = h;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);

    const data = srLightboxBoxes;
    const srcW = Number(data && data.src_w), srcH = Number(data && data.src_h);
    if (!data || !Array.isArray(data.boxes) || !data.boxes.length || !srcW || !srcH) return;

    const scale = Math.min(w / srcW, h / srcH);
    const rw = srcW * scale, rh = srcH * scale;
    const ox = (w - rw) / 2, oy = (h - rh) / 2;

    data.boxes.forEach(d => {
        if (!d || !d.box) return;
        const x = ox + d.box.x * scale;
        const y = oy + d.box.y * scale;
        const bw = d.box.width * scale;
        const bh = d.box.height * scale;

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

// Redraw whenever the enlarged photo's rendered size changes (lightbox just
// opened, image finished loading, window resized).
if (window.ResizeObserver) {
    new ResizeObserver(() => srDrawLightboxBoxes()).observe(document.getElementById('sr-lightbox-img'));
}

// boxes: the report's detection.boxes (or null — supporting photo, older
// reports, other engines). The lightbox is shown FIRST so the photo has a
// real size to measure, then the overlay is drawn.
window.srZoom = function (src, boxes) {
    if (!src) return;
    const img = document.getElementById('sr-lightbox-img');
    const canvas = document.getElementById('sr-lightbox-canvas');
    canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);

    srLightboxBoxes = boxes || null;
    img.onload = srDrawLightboxBoxes;
    img.src = src;
    document.getElementById('sr-lightbox').classList.remove('fr-hidden');
    // Already-decoded (cached / same data URI) photos don't fire onload again.
    requestAnimationFrame(srDrawLightboxBoxes);
};
window.srCloseZoom = function () {
    document.getElementById('sr-lightbox').classList.add('fr-hidden');
    srLightboxBoxes = null;
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
    // AI-Model reports show the exact model that made the detection.
    tb.textContent = (r.type_key === 'ai_model' && srEngineLabels[r.detection.source]) || r.type;
    tb.title = r.type;

    document.getElementById('sr-m-code').textContent = '#' + r.report_id;
    document.getElementById('sr-m-farmer').textContent = r.farmer_name;
    document.getElementById('sr-m-submitted').textContent = r.submitted_at || '—';
    document.getElementById('sr-m-escalated').textContent = r.escalated_at || '—';

    srSetImage('sr-d-image', 'sr-d-image-missing', r.detection.image);
    srDetailBoxes = r.detection.boxes || null;
    document.getElementById('sr-d-class').textContent = r.detection.class_name;

    const sevColor = srSeverityColor(r.detection.severity_percent);
    const sevEl = document.getElementById('sr-d-severity');
    sevEl.textContent = r.detection.severity_label || '—';
    sevEl.className = 'rg-report-stat-value ' + sevColor;
    const dmgEl = document.getElementById('sr-d-damage');
    dmgEl.textContent = Number.isFinite(Number(r.detection.severity_percent)) ? r.detection.severity_percent + '%' : '—';
    dmgEl.className = 'rg-report-stat-value ' + sevColor;

    // Model name next to the report ID (hidden when the engine is unknown).
    const modelChip = document.getElementById('sr-m-model');
    const modelLabel = srEngineLabels[r.detection.source] || null;
    modelChip.textContent = modelLabel || '';
    modelChip.classList.toggle('fr-hidden', !modelLabel);

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

    // Resolve / Assigned Tech panels start closed every time a report is opened.
    document.getElementById('sr-panel-resolve').classList.add('fr-hidden');
    document.getElementById('sr-panel-assign').classList.add('fr-hidden');
    srRenderCurrentAdmin(r);

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

// =====================================================================
// FARMER SUGGESTIONS + GENERAL FEEDBACK
// (reuses srRequest / srTechs / srTechsUrl / srEscape / srZoom from above)
// =====================================================================
const sgSuggestions     = @json($suggestions ?? []);
const sgFeedbacks       = @json($feedbacks ?? []);
const sgSupportStatuses = @json($supportStatuses ?? []);
const sgAssignUrl  = "{{ route('admin.suggestions.assign',  ['feedback' => '__ID__']) }}";
const sgResolveUrl = "{{ route('admin.suggestions.resolve', ['feedback' => '__ID__']) }}";

let sgOpenRef = null;       // { type: 'suggestion'|'general', id }
let sgPickedTech = null;

const sgStatusMeta = {
    new:         { label: 'New',          cls: 'sr-status-pending' },
    assigned:    { label: 'Assigned',     cls: 'sr-status-open' },
    in_progress: { label: 'In Progress',  cls: 'sr-status-in_progress' },
    resolved:    { label: 'Resolved',     cls: 'sr-status-resolved' },
    declined:    { label: 'Not Feasible', cls: 'sg-status-declined' }
};

// Support status wins (a technician or the admin worked on it); otherwise
// "assigned" if a technician was picked; otherwise brand new.
function sgStatusKey(e) {
    if (e.support_status) return e.support_status;
    return e.assigned_tech_id ? 'assigned' : 'new';
}
function sgBadge(key) {
    const m = sgStatusMeta[key] || sgStatusMeta.new;
    return `<span class="sr-badge ${m.cls}">${srEscape(m.label)}</span>`;
}
function sgTrunc(s, n) { s = String(s || ''); return s.length > n ? s.slice(0, n - 1) + '…' : s; }
function sgStars(n) {
    n = Number(n) || 0;
    let h = '';
    for (let i = 1; i <= 5; i++) h += `<i class="fa-${i <= n ? 'solid text-warning' : 'regular text-secondary'} fa-star"></i> `;
    return h;
}
function sgFindEntry(ref) {
    if (!ref) return null;
    const list = ref.type === 'suggestion' ? sgSuggestions : sgFeedbacks;
    return list.find(x => Number(x.id) === Number(ref.id)) || null;
}

window.sgRender = function () {
    // ----- suggestions -----
    const st   = document.getElementById('sg-sug-status').value;
    const term = document.getElementById('sg-sug-search').value.trim().toLowerCase();
    const sRows = sgSuggestions.filter(e =>
        (!st || sgStatusKey(e) === st) &&
        (!term || [e.code, e.farmer_name, e.category_label, e.message, e.assigned_tech_name].join(' ').toLowerCase().includes(term)));

    document.getElementById('sg-sug-count').textContent = sgSuggestions.length;
    document.getElementById('sg-card-count-suggestions').textContent = sgSuggestions.length;
    document.getElementById('sg-sug-empty').classList.toggle('fr-hidden', sRows.length > 0);
    document.getElementById('sg-sug-body').innerHTML = sRows.map(e => `
        <tr>
            <td class="fw-bold">#${srEscape(e.code || e.id)}</td>
            <td>${srEscape(e.farmer_name)}</td>
            <td><span class="sr-badge sg-cat">${srEscape(e.category_label || '—')}</span></td>
            <td class="sg-msg" title="${srEscape(e.message)}">${srEscape(sgTrunc(e.message, 70))}</td>
            <td class="text-secondary">${srEscape(e.date)}</td>
            <td>${sgBadge(sgStatusKey(e))}</td>
            <td>${e.assigned_tech_name ? srEscape(e.assigned_tech_name) : '<span class="text-secondary">—</span>'}</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-light" onclick="sgOpenModal('suggestion', ${e.id})"><i class="fa-solid fa-eye me-1"></i>View</button></td>
        </tr>`).join('');

    // ----- general feedback -----
    const ft    = document.getElementById('sg-fb-feature').value;
    const fterm = document.getElementById('sg-fb-search').value.trim().toLowerCase();
    const fRows = sgFeedbacks.filter(e =>
        (!ft || e.feature === ft) &&
        (!fterm || [e.code, e.farmer_name, e.feature_label, e.message].join(' ').toLowerCase().includes(fterm)));

    document.getElementById('sg-fb-count').textContent = sgFeedbacks.length;
    document.getElementById('sg-card-count-feedback').textContent = sgFeedbacks.length;
    document.getElementById('sg-fb-empty').classList.toggle('fr-hidden', fRows.length > 0);
    document.getElementById('sg-fb-body').innerHTML = fRows.map(e => `
        <tr>
            <td class="fw-bold">#${srEscape(e.code || e.id)}</td>
            <td>${srEscape(e.farmer_name)}</td>
            <td><span class="sr-badge sg-cat">${srEscape(e.feature_label || '—')}</span></td>
            <td style="white-space:nowrap">${sgStars(e.rating)} <span class="text-secondary small ms-1">${srEscape(e.rating_label || '')}</span></td>
            <td class="sg-msg" title="${srEscape(e.message)}">${srEscape(sgTrunc(e.message, 70))}</td>
            <td class="text-secondary">${srEscape(e.date)}</td>
            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-light" onclick="sgOpenModal('general', ${e.id})"><i class="fa-solid fa-eye me-1"></i>View</button></td>
        </tr>`).join('');
};
sgRender();

// ---------- modal ----------
window.sgOpenModal = function (type, id) {
    const e = sgFindEntry({ type, id });
    if (!e) return;
    sgOpenRef = { type, id: e.id };
    const isSg = type === 'suggestion';

    document.getElementById('sg-m-title').textContent = isSg ? 'Suggestion' : 'General Feedback';
    document.getElementById('sg-m-code').textContent  = '#' + (e.code || e.id);
    document.getElementById('sg-m-farmer').textContent    = e.farmer_name || 'Unknown farmer';
    document.getElementById('sg-m-submitted').textContent = e.submitted_at || e.date || '';
    document.getElementById('sg-m-label-key').textContent = isSg ? 'Category' : 'Feature';
    document.getElementById('sg-m-label').textContent     = (isSg ? e.category_label : e.feature_label) || '—';
    document.getElementById('sg-m-message').textContent   = e.message || '—';

    const ratingWrap = document.getElementById('sg-m-rating-wrap');
    ratingWrap.classList.toggle('fr-hidden', isSg);
    if (!isSg) document.getElementById('sg-m-rating').innerHTML = sgStars(e.rating) + `<span class="text-secondary small ms-1">${srEscape(e.rating_label || '')}</span>`;

    const shotWrap = document.getElementById('sg-m-shot-wrap');
    shotWrap.classList.toggle('fr-hidden', !(isSg && e.screenshot));
    if (isSg && e.screenshot) document.getElementById('sg-m-shot').src = e.screenshot;

    document.getElementById('sg-actions').classList.toggle('fr-hidden', !isSg);
    document.getElementById('sg-empty-right').classList.toggle('fr-hidden', isSg);
    document.getElementById('sg-panel-assign').classList.add('fr-hidden');
    document.getElementById('sg-panel-resolve').classList.add('fr-hidden');

    sgPaintModal(e);

    const modal = document.getElementById('sg-modal');
    modal.classList.remove('fr-hidden');
    modal.scrollTop = 0;
    document.body.style.overflow = 'hidden';
};

window.sgCloseModal = function () {
    document.getElementById('sg-modal').classList.add('fr-hidden');
    document.body.style.overflow = '';
    sgOpenRef = null;
};

// Status badge, "assigned to" summary and the support (outcome) box.
function sgPaintModal(e) {
    const isSg = e.type === 'suggestion';

    const sb = document.getElementById('sg-m-status');
    if (isSg) {
        const m = sgStatusMeta[sgStatusKey(e)] || sgStatusMeta.new;
        sb.className = 'sr-badge ' + m.cls;
        sb.textContent = m.label;
    }
    sb.classList.toggle('fr-hidden', !isSg);

    const cur = document.getElementById('sg-current');
    let html = '';
    if (e.assigned_tech_name) {
        const p = e.assigned_priority || 'medium';
        html += `<div class="mb-1"><i class="fa-solid fa-user-check text-info me-1"></i>Assigned to <strong class="text-white">${srEscape(e.assigned_tech_name)}</strong>
                 <span class="sr-prio sr-prio-${srEscape(p)} ms-1">${srEscape(p.toUpperCase())}</span>
                 ${e.assigned_date ? `<span class="text-secondary ms-1">· ${srEscape(e.assigned_date)}</span>` : ''}</div>`;
    }
    if (e.assigned_notes) {
        html += `<div class="text-secondary"><i class="fa-solid fa-note-sticky me-1"></i>Notes to technician: ${srEscape(e.assigned_notes)}</div>`;
    }
    cur.innerHTML = html;
    cur.classList.toggle('fr-hidden', !html);

    const wrap = document.getElementById('sg-support-wrap');
    const has = isSg && !!e.support_status;
    wrap.classList.toggle('fr-hidden', !has);
    if (has) {
        const key = e.support_status;
        const m = sgStatusMeta[key] || sgStatusMeta.resolved;
        const by = e.support_by || 'Technician';
        document.getElementById('sg-support-head').textContent = by + "'s support";
        const badge = document.getElementById('sg-support-badge');
        badge.className = 'sr-badge ' + m.cls;
        badge.textContent = (sgSupportStatuses[key] && sgSupportStatuses[key].label) || m.label;
        document.getElementById('sg-support-notes').textContent = e.support_notes || '—';
        document.getElementById('sg-support-meta').textContent  = [by, e.support_updated_at].filter(Boolean).join(' · ');
    }
}

window.sgShowPanel = function (which) {
    const e = sgFindEntry(sgOpenRef);
    if (!e) return;
    const assignP  = document.getElementById('sg-panel-assign');
    const resolveP = document.getElementById('sg-panel-resolve');
    const target   = which === 'assign' ? assignP : resolveP;
    const wasHidden = target.classList.contains('fr-hidden');

    assignP.classList.add('fr-hidden');
    resolveP.classList.add('fr-hidden');
    if (!wasHidden) return;
    target.classList.remove('fr-hidden');

    if (which === 'assign') {
        document.getElementById('sg-assign-priority').value = e.assigned_priority || 'medium';
        document.getElementById('sg-assign-notes').value    = e.assigned_notes || '';
        sgPickedTech = e.assigned_tech_id || null;
        sgLoadTechs();
    } else {
        const sel = document.getElementById('sg-resolve-status');
        sel.innerHTML = Object.entries(sgSupportStatuses)
            .map(([key, m]) => `<option value="${key}">${srEscape(m.label)}</option>`).join('');
        sel.value = e.support_status || 'resolved';
        document.getElementById('sg-resolve-notes').value = e.support_notes || '';
    }
};

// ---------- assign ----------
async function sgLoadTechs() {
    const box = document.getElementById('sg-tech-list');
    if (srTechs === null) {
        box.innerHTML = '<div class="small text-secondary">Loading technicians...</div>';
        try {
            srTechs = await srRequest(srTechsUrl, 'GET');
        } catch (err) {
            box.innerHTML = `<div class="small text-danger">${srEscape(err.message)}</div>`;
            return;
        }
    }
    sgRenderTechs();
}

function sgRenderTechs() {
    const box = document.getElementById('sg-tech-list');
    if (!srTechs || !srTechs.length) {
        box.innerHTML = '<div class="small text-secondary">No approved technician in your province and city yet.</div>';
        return;
    }
    box.innerHTML = srTechs.map(t => `
        <label class="sr-tech-item ${Number(sgPickedTech) === Number(t.id) ? 'selected' : ''}">
            <input type="radio" name="sg-tech" value="${t.id}" ${Number(sgPickedTech) === Number(t.id) ? 'checked' : ''}>
            <span><span class="fw-bold">${srEscape(t.name)}</span>
            ${t.barangay ? `<span class="sr-tech-sub d-block">${srEscape(t.barangay)}</span>` : ''}</span>
        </label>`).join('');
    box.querySelectorAll('input[name="sg-tech"]').forEach(inp => inp.addEventListener('change', () => {
        sgPickedTech = Number(inp.value);
        sgRenderTechs();
    }));
}

// Replace the cached row with the fresh one the server sent back, then repaint.
function sgApplyServerRow(row) {
    const e = sgFindEntry(sgOpenRef);
    if (!e || !row) return;
    Object.assign(e, row);
    sgRender();
    sgPaintModal(e);
}

window.sgSubmitAssign = async function () {
    const e = sgFindEntry(sgOpenRef);
    if (!e) return;
    if (!sgPickedTech) { alert('Please choose a technician first.'); return; }

    const btn = document.getElementById('sg-assign-submit');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Assigning...';
    try {
        const data = await srRequest(sgAssignUrl.replace('__ID__', e.id), 'POST', {
            technician_id: sgPickedTech,
            priority: document.getElementById('sg-assign-priority').value,
            notes: document.getElementById('sg-assign-notes').value.trim()
        });
        sgApplyServerRow(data.suggestion);
        document.getElementById('sg-panel-assign').classList.add('fr-hidden');
    } catch (err) {
        alert(err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
};

// ---------- resolve (admin does it himself) ----------
window.sgSubmitResolve = async function () {
    const e = sgFindEntry(sgOpenRef);
    if (!e) return;
    const notes = document.getElementById('sg-resolve-notes').value.trim();
    if (!notes) { alert('Please write a note before submitting.'); return; }

    const btn = document.getElementById('sg-resolve-submit');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';
    try {
        const data = await srRequest(sgResolveUrl.replace('__ID__', e.id), 'POST', {
            status: document.getElementById('sg-resolve-status').value,
            notes
        });
        sgApplyServerRow(data.suggestion);
        document.getElementById('sg-panel-resolve').classList.add('fr-hidden');
    } catch (err) {
        alert(err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
};

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!document.getElementById('sr-lightbox').classList.contains('fr-hidden')) return;   // lightbox closes first (handler below)
    if (!document.getElementById('sg-modal').classList.contains('fr-hidden')) sgCloseModal();
});

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (!document.getElementById('sr-lightbox').classList.contains('fr-hidden')) { srCloseZoom(); return; }
    if (!document.getElementById('sr-modal').classList.contains('fr-hidden')) { srCloseModal(); return; }
    srCloseMenu();
});
</script>
@endsection