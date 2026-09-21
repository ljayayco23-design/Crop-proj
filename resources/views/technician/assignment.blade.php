{{--
    resources/views/technician/assignment.blade.php

    "My Assignment" — technician-side page.

    This is a SEPARATE file from resources/views/admin/assignment.blade.php
    on purpose: the admin page is a full Assignment Management console
    (assign users, edit/delete anyone's assignment, admin-only stats).
    This page is just the technician's own read-only view of "where has an
    admin/developer assigned me", reusing the exact same controller/route
    (AdminAssignmentController@index -> technician.assignment) but a much
    smaller, technician-scoped template.

    Expects (all optional — page renders safely with defaults if omitted):
      $assignments            => paginator of the LOGGED-IN technician's own
                                  assignment rows only (already scoped to
                                  user_id = auth()->id() in the controller).
                                  Each row exposes: ->province->name,
                                  ->city->name, ->barangay->name,
                                  ->start_date, ->end_date, ->status, ->id,
                                  ->assignedBy->full_name, ->assigned_by,
                                  ->user (the technician themselves, used
                                  only for their saved lat/lng pin).
      $mapMarkers              => array of ['name'=>, 'lat'=>, 'lng'=>, 'type'=>'technician']
                                  already scoped to this technician only.
      $technicianOwnAssignment => the technician's current active Assignment,
                                  or null if they haven't been assigned yet.
      $mapTilerKey             => string|null, MapTiler API key for the
                                  Streets/Hybrid tile layers (falls back to
                                  plain OpenStreetMap automatically).

    Nothing here writes anything — no assign/edit/delete form on this page.
--}}
@extends('layouts.technician')

@section('title', 'RICEGUARD AI • My Assignment')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
{{-- Same MapTiler key + fallback pattern as the admin Assignment Management
     map and the Create Account Location Preview map — if this is blank or
     the tiles fail to load, the script below silently drops to plain OSM. --}}
<meta name="maptiler-key" content="{{ $mapTilerKey ?? config('services.maptiler.key') }}">

@php
    $taHasOwnAssignment = ($technicianOwnAssignment ?? null) !== null;
@endphp

<div class="my-assign">

    {{-- ===================== PAGE HEADER ===================== --}}
    <div class="ta-page-header">
        <div class="ta-title-row">
            <span class="ta-title-icon"><i class="fas fa-map-location-dot"></i></span>
            <div>
                <h5 class="m-b-2">My Assignment</h5>
                <p class="text-muted mb-0">Areas an administrator has assigned you to cover.</p>
            </div>
        </div>
        @if ($taHasOwnAssignment)
            <span class="ta-status-pill ta-status-{{ $technicianOwnAssignment->status ?? 'active' }}">
                <i class="fas fa-circle"></i> {{ ucfirst($technicianOwnAssignment->status ?? 'active') }}
            </span>
        @endif
    </div>

    @if (session('success'))
        <div class="ta-alert ta-alert-success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="ta-alert ta-alert-danger">{{ session('error') }}</div>
    @endif

    {{-- ===================== AREA MAP OVERVIEW ===================== --}}
    <div class="ta-panel ta-map-panel" id="ta-map-panel">
        <div class="ta-panel-header ta-map-header">
            <h6 class="mb-0">Area Map Overview</h6>
            <div class="ta-map-legend">
                <span class="ta-legend-item"><span class="ta-dot ta-dot-green"></span> My assigned area</span>
            </div>
        </div>

        <div class="ta-map-wrapper" id="ta-map-wrapper">
            <div id="ta-assignment-map"></div>
            <div class="ta-map-controls" id="ta-map-controls">
                <button type="button" class="ta-map-ctrl-btn" id="ta-zoom-in" title="Zoom in"><i class="fas fa-plus"></i></button>
                <button type="button" class="ta-map-ctrl-btn" id="ta-zoom-out" title="Zoom out"><i class="fas fa-minus"></i></button>
                {{-- Same style toggle as the admin map — Streets / Hybrid,
                     both MapTiler, both falling back to OSM automatically. --}}
                <div class="ta-layer-control" id="ta-layer-control">
                    <button type="button" class="ta-map-ctrl-btn" id="ta-layer-toggle" title="Map layers">
                        <i class="fas fa-layer-group"></i>
                    </button>
                    <div class="ta-layer-menu" id="ta-layer-menu">
                        <button type="button" class="ta-layer-option" data-style="streets"><i class="fas fa-road"></i>Streets</button>
                        <button type="button" class="ta-layer-option" data-style="hybrid"><i class="fas fa-satellite"></i>Hybrid</button>
                    </div>
                </div>
            </div>
            <div class="ta-map-fallback-note" id="ta-map-fallback-note" style="display:none;">
                <i class="fas fa-triangle-exclamation me-1"></i> Map tiles aren't loading
            </div>
        </div>

        @unless ($taHasOwnAssignment)
            <p class="ta-map-empty-hint">
                <i class="fas fa-info-circle me-1"></i>
                You haven't been assigned to an area yet — once an administrator assigns you, your pin will show up here.
            </p>
        @endunless
    </div>

    {{-- ===================== ASSIGNED AREAS TABLE ===================== --}}
    <div class="ta-panel ta-table-panel">
        <div class="ta-panel-header">
            <h6 class="mb-0">Assigned Areas</h6>
            <p class="text-muted mb-0 small">Areas assigned to you, and who assigned them.</p>
        </div>

        <div class="ta-table-scroll">
            <table class="ta-table" id="ta-assignments-table">
                <thead>
                    <tr>
                        <th>Area</th>
                        <th>Assigned By</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($assignments ?? []) as $item)
                        @php
                            $hasPin = $item->user && $item->user->latitude !== null && $item->user->longitude !== null;
                            $assignedByIsSelf = (int) ($item->assigned_by ?? 0) === (int) auth()->id();
                            $assignedByName   = $item->assignedBy->full_name ?? null;
                            $status = $item->status ?? 'active';
                            $statusBadgeClass = match ($status) {
                                'active'  => 'ta-badge-green',
                                'pending' => 'ta-badge-amber',
                                default   => 'ta-badge-muted', // ended
                            };
                            $reportRouteExists = \Illuminate\Support\Facades\Route::has('technician.assignment.report');
                        @endphp
                        <tr>
                            <td>
                                <div class="ta-area-cell">
                                    <span class="ta-area-icon"><i class="fas fa-location-dot"></i></span>
                                    <div>
                                        <div class="ta-area-name">{{ $item->barangay->name ?? '—' }}</div>
                                        <div class="ta-area-sub">{{ $item->city->name ?? '—' }}{{ $item->province ? ', '.$item->province->name : '' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="ta-badge ta-badge-muted">
                                    {{ $assignedByIsSelf ? 'Me' : ($assignedByName ?? '—') }}
                                </span>
                            </td>
                            <td>{{ $item->start_date ? \Illuminate\Support\Carbon::parse($item->start_date)->format('M j, Y') : '—' }}</td>
                            <td>{{ $item->end_date ? \Illuminate\Support\Carbon::parse($item->end_date)->format('M j, Y') : '—' }}</td>
                            <td>
                                <span class="ta-badge {{ $statusBadgeClass }}">{{ ucfirst($status) }}</span>
                            </td>
                            <td class="ta-actions-cell">
                                {{-- Three-dot row menu: Report + Location only,
                                     nothing else on this page. --}}
                                <div class="ta-dropdown" id="ta-dropdown-{{ $item->id }}">
                                    <button type="button" class="ta-dots-btn" title="Actions">
                                        <i class="fas fa-ellipsis-vertical"></i>
                                    </button>
                                    <div class="ta-dropdown-menu">
                                        @if ($reportRouteExists)
                                            <a href="{{ route('technician.assignment.report', $item->id) }}" class="ta-menu-item">
                                                <i class="fas fa-file-lines"></i> Report
                                            </a>
                                        @else
                                            {{-- Route not wired up yet — kept as a disabled-looking
                                                 placeholder instead of a dead 404 link/crash, same
                                                 defensive Route::has() pattern used across this app. --}}
                                            <button type="button" class="ta-menu-item ta-menu-item-soon">
                                                <i class="fas fa-file-lines"></i> Report
                                            </button>
                                        @endif
                                        <button type="button"
                                                class="ta-menu-item ta-locate-btn"
                                                @if ($hasPin)
                                                    data-lat="{{ $item->user->latitude }}"
                                                    data-lng="{{ $item->user->longitude }}"
                                                @else
                                                    data-no-pin="1"
                                                @endif>
                                            <i class="fas fa-location-crosshairs"></i> Location
                                        </button>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="ta-empty-row">
                                You haven't been assigned to any area yet. Once an administrator assigns you, it will appear here.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ta-table-footer">
            @if (isset($assignments) && method_exists($assignments, 'total'))
                <span class="ta-entries-label">
                    Showing {{ $assignments->firstItem() ?? 0 }} to {{ $assignments->lastItem() ?? 0 }} of {{ $assignments->total() }} entries
                </span>
                <div class="ta-pagination">
                    {{ $assignments->links() }}
                </div>
            @else
                <span class="ta-entries-label">Showing 0 to 0 of 0 entries</span>
            @endif
        </div>
    </div>
</div>

{{-- ===================== STYLES ===================== --}}
<style>
.my-assign {
    --ta-bg: #0e1116;
    --ta-panel: #161b22;
    --ta-panel-2: #12161d;
    --ta-border: #30363d;
    --ta-text: #e6edf3;
    --ta-muted: #8b94a3;
    --ta-green: #22c55e;
    --ta-green-dark: #16a34a;
    --ta-amber: #f59e0b;
    color: var(--ta-text);
    font-family: inherit;
}
.my-assign * { box-sizing: border-box; }

/* ---------- header ---------- */
.ta-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.ta-title-row { display: flex; align-items: flex-start; gap: .75rem; }
.ta-title-icon { width: 38px; height: 38px; border-radius: 10px; background: rgba(34,197,94,.15); color: var(--ta-green); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.ta-page-header h5 { color: var(--ta-text); font-weight: 700; }
.ta-page-header .text-muted { color: var(--ta-muted) !important; }

.ta-status-pill { display: inline-flex; align-items: center; gap: .4rem; padding: .4rem .8rem; border-radius: 999px; font-size: .78rem; font-weight: 700; background: rgba(34,197,94,.12); color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
.ta-status-pill i { font-size: .5rem; }
.ta-status-pending { background: rgba(245,158,11,.12); color: #fbbf24; border-color: rgba(245,158,11,.3); }
.ta-status-ended { background: rgba(148,163,184,.12); color: #94a3b8; border-color: rgba(148,163,184,.3); }

.ta-alert { border-radius: 8px; padding: .65rem .9rem; font-size: .85rem; margin-bottom: 1rem; }
.ta-alert-success { background: rgba(34,197,94,.12); color: #7ee2a8; border: 1px solid rgba(34,197,94,.3); }
.ta-alert-danger { background: rgba(239,68,68,.12); color: #f7a5a5; border: 1px solid rgba(239,68,68,.3); }

/* ---------- panels ---------- */
.ta-panel { background: var(--ta-panel); border: 1px solid var(--ta-border); border-radius: 12px; padding: 1.25rem; overflow: visible; margin-bottom: 1.25rem; }
.ta-panel-header { margin-bottom: 1rem; }
.ta-panel-header h6 { color: var(--ta-text); font-weight: 700; }

/* ---------- map ---------- */
.ta-map-panel { display: flex; flex-direction: column; }
.ta-map-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .6rem; }
.ta-map-legend { display: flex; align-items: center; gap: 1rem; font-size: .75rem; color: var(--ta-muted); flex-wrap: wrap; }
.ta-legend-item { display: flex; align-items: center; gap: .4rem; }
.ta-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.ta-dot-green { background: var(--ta-green); }

.ta-map-wrapper { position: relative; min-height: 380px; border-radius: 10px; overflow: hidden; border: 1px solid var(--ta-border); background: var(--ta-bg); }
#ta-assignment-map { position: absolute; inset: 0; width: 100%; height: 100%; background: var(--ta-bg); }
.ta-map-controls { position: absolute; top: 12px; right: 12px; z-index: 500; display: flex; flex-direction: column; gap: 4px; }
.ta-map-wrapper .leaflet-marker-pane { z-index: 650; }
.ta-map-wrapper .leaflet-tile-pane { z-index: 200; }
.ta-map-ctrl-btn { width: 32px; height: 32px; background: var(--ta-panel); border: 1px solid var(--ta-border); color: var(--ta-text); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: .8rem; box-shadow: 0 4px 10px rgba(0,0,0,.4); }
.ta-map-ctrl-btn:hover { background: var(--ta-border); }

.ta-layer-control { position: relative; }
.ta-layer-control.ta-layer-disabled .ta-map-ctrl-btn { color: var(--ta-muted); cursor: not-allowed; opacity: .55; }
.ta-layer-menu {
    display: none;
    position: absolute;
    top: 0;
    right: calc(100% + 8px);
    background: var(--ta-panel);
    border: 1px solid var(--ta-border);
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(0,0,0,.45);
    overflow: hidden;
    min-width: 128px;
    z-index: 600;
}
.ta-layer-control.ta-layer-open .ta-layer-menu { display: block; }
.ta-layer-option {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: .5rem .75rem;
    background: transparent; border: none; border-bottom: 1px solid var(--ta-border);
    font-size: .75rem; font-weight: 600; color: var(--ta-text); text-align: left; cursor: pointer;
}
.ta-layer-option:last-child { border-bottom: none; }
.ta-layer-option:hover { background: rgba(255,255,255,.06); }
.ta-layer-option i { width: 14px; text-align: center; color: var(--ta-muted); font-size: .72rem; }
.ta-layer-option.ta-layer-active { background: rgba(34,197,94,.12); color: var(--ta-green); }
.ta-layer-option.ta-layer-active i { color: var(--ta-green); }
.ta-map-fallback-note { position: absolute; bottom: 10px; left: 10px; z-index: 500; background: rgba(245,158,11,.15); color: #fbbf24; border: 1px solid rgba(245,158,11,.35); font-size: .7rem; padding: .3rem .6rem; border-radius: 6px; }
.ta-map-empty-hint { margin: .85rem 0 0; font-size: .78rem; color: var(--ta-muted); }

/* ---------- table ---------- */
.ta-table-panel { margin-top: .25rem; }
.ta-table-scroll { overflow-x: auto; border: 1px solid var(--ta-border); border-radius: 10px; }
.ta-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 640px; }
.ta-table thead th { background: var(--ta-panel-2); color: var(--ta-muted); font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; font-weight: 700; padding: .65rem .8rem; border-bottom: 1px solid var(--ta-border); text-align: left; }
.ta-table tbody td { padding: .7rem .8rem; border-bottom: 1px solid var(--ta-border); color: var(--ta-text); vertical-align: middle; }
.ta-table tbody tr:hover { background: rgba(255,255,255,.02); }
.ta-empty-row { text-align: center; color: var(--ta-muted); padding: 2rem !important; }

.ta-area-cell { display: flex; align-items: center; gap: .6rem; }
.ta-area-icon { width: 32px; height: 32px; border-radius: 8px; background: rgba(34,197,94,.15); color: var(--ta-green); display: flex; align-items: center; justify-content: center; font-size: .75rem; flex-shrink: 0; }
.ta-area-name { font-weight: 600; font-size: .82rem; }
.ta-area-sub { font-size: .72rem; color: var(--ta-muted); }

.ta-badge { display: inline-block; padding: .25rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.ta-badge-green { background: rgba(34,197,94,.15); color: #4ade80; }
.ta-badge-amber { background: rgba(245,158,11,.15); color: #fbbf24; }
.ta-badge-muted { background: rgba(148,163,184,.15); color: #94a3b8; }

.ta-table-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .6rem; margin-top: 1rem; }
.ta-entries-label { font-size: .78rem; color: var(--ta-muted); }
.ta-pagination :is(a, span) { color: var(--ta-text) !important; }

/* ---------- row actions: three-dot dropdown ---------- */
.ta-actions-cell { position: relative; }
.ta-dropdown { position: relative; display: inline-block; }
.ta-dots-btn { width: 30px; height: 30px; border-radius: 6px; border: 1px solid var(--ta-border); background: var(--ta-panel-2); color: var(--ta-text); display: inline-flex; align-items: center; justify-content: center; font-size: .85rem; cursor: pointer; }
.ta-dots-btn:hover { background: rgba(255,255,255,.06); }
.ta-dropdown-menu {
    display: none;
    position: absolute;
    top: calc(100% + 4px);
    right: 0;
    background: var(--ta-panel);
    border: 1px solid var(--ta-border);
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(0,0,0,.45);
    overflow: hidden;
    min-width: 150px;
    z-index: 700;
}
.ta-dropdown.ta-dropdown-open .ta-dropdown-menu { display: block; }
.ta-menu-item {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: .55rem .8rem;
    background: transparent; border: none; border-bottom: 1px solid var(--ta-border);
    font-size: .78rem; font-weight: 600; color: var(--ta-text); text-align: left; cursor: pointer;
}
.ta-menu-item:last-child { border-bottom: none; }
.ta-menu-item:hover { background: rgba(255,255,255,.06); }
.ta-menu-item i { width: 14px; text-align: center; color: var(--ta-muted); font-size: .72rem; }
.ta-menu-item-soon { color: var(--ta-muted); cursor: not-allowed; }
.ta-no-pin-note { font-size: .7rem; color: var(--ta-muted); font-style: italic; margin-top: 4px; }

@media (max-width: 767px) {
    .ta-map-wrapper { min-height: 300px; }
}
</style>

{{-- ===================== SCRIPTS ===================== --}}
{{-- Same direct include the admin Assignment Management page uses —
     admin's map works because this tag is right here in that blade
     file, not because layouts.admin provides it. Adding the same two
     tags here, in the same place, is what was actually missing. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function () {
    /* ============================================================
       MAP — same MapTiler-with-OSM-fallback pattern as the admin
       Assignment Management page and the Create Account Location
       Preview map: MapTiler Streets/Hybrid tiles when a key is
       configured, a watchdog that falls back to plain OpenStreetMap
       if tiles don't load, and a final on-screen note only if BOTH
       sources fail.
    ============================================================ */
    const mapMarkers   = @json($mapMarkers ?? []);
    const mapCenterLat = {{ $mapCenterLat ?? 10.8986 }};
    const mapCenterLng = {{ $mapCenterLng ?? 123.4143 }};
    const maptilerKey  = (document.querySelector('meta[name="maptiler-key"]')?.content || '').trim();

    const assignmentMap = L.map('ta-assignment-map', {
        center: [mapCenterLat, mapCenterLng],
        zoom: 10,
        zoomControl: false,
        attributionControl: false
    });

    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    });

    const TA_LAYER_DEFS = {
        streets: { style: 'streets-v2', ext: 'png', tileSize: 512, zoomOffset: -1 },
        hybrid:  { style: 'hybrid',     ext: 'png', tileSize: 512, zoomOffset: -1 },
    };
    const TA_DEFAULT_STYLE = 'streets';

    function buildMaptilerLayer(styleKey) {
        const def = TA_LAYER_DEFS[styleKey];
        if (!def || !maptilerKey) return null;
        return L.tileLayer(`https://api.maptiler.com/maps/${def.style}/{z}/{x}/{y}.${def.ext}?key=${maptilerKey}`, {
            maxZoom: 20,
            tileSize: def.tileSize,
            zoomOffset: def.zoomOffset,
            crossOrigin: true,
            attribution: '&copy; MapTiler &copy; OpenStreetMap contributors'
        });
    }

    let activeTileLayer = null;
    let watchdogTimer = null;

    function showFallbackNote(message) {
        const note = document.getElementById('ta-map-fallback-note');
        if (!note) return;
        note.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i> ' + message;
        note.style.display = 'block';
    }

    function hideFallbackNote() {
        const note = document.getElementById('ta-map-fallback-note');
        if (note) note.style.display = 'none';
    }

    function activateLayer(layer, onFail) {
        if (activeTileLayer) assignmentMap.removeLayer(activeTileLayer);
        clearTimeout(watchdogTimer);
        activeTileLayer = layer;

        let tileLoaded = false;
        let handled = false;

        layer.once('tileload', function () { tileLoaded = true; hideFallbackNote(); });
        layer.on('tileerror', function () {
            if (!tileLoaded) attemptFallback();
        });

        function attemptFallback() {
            if (handled || tileLoaded) return;
            handled = true;
            clearTimeout(watchdogTimer);
            if (onFail) onFail();
        }

        layer.addTo(assignmentMap);
        watchdogTimer = setTimeout(attemptFallback, 6000);
    }

    function switchMapStyle(styleKey) {
        if (!TA_LAYER_DEFS[styleKey] || !maptilerKey) {
            activateLayer(osmLayer, function () {
                showFallbackNote('Map tiles aren\'t loading — check your internet connection or ad blocker');
            });
            return;
        }
        const layer = buildMaptilerLayer(styleKey);
        if (!layer) { activateLayer(osmLayer, null); return; }
        activateLayer(layer, function () {
            activateLayer(osmLayer, function () {
                showFallbackNote('Map tiles aren\'t loading — check your internet connection or ad blocker');
            });
        });
    }

    const taLayerControl = document.getElementById('ta-layer-control');
    const taLayerToggle   = document.getElementById('ta-layer-toggle');
    const taLayerMenu     = document.getElementById('ta-layer-menu');

    const savedTaStyle = localStorage.getItem('riceguard-map-style');
    const initialTaStyle = (savedTaStyle && TA_LAYER_DEFS[savedTaStyle]) ? savedTaStyle : TA_DEFAULT_STYLE;

    function taSetActiveOption(styleKey) {
        if (!taLayerMenu) return;
        taLayerMenu.querySelectorAll('.ta-layer-option').forEach(function (btn) {
            btn.classList.toggle('ta-layer-active', btn.dataset.style === styleKey);
        });
    }

    if (taLayerControl && taLayerToggle && taLayerMenu) {
        if (!maptilerKey) {
            taLayerControl.classList.add('ta-layer-disabled');
            taLayerToggle.title = 'Map layers unavailable (no MapTiler key configured)';
        } else {
            taLayerToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                taLayerControl.classList.toggle('ta-layer-open');
            });

            taLayerMenu.querySelectorAll('.ta-layer-option').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const styleKey = btn.dataset.style;
                    localStorage.setItem('riceguard-map-style', styleKey);
                    switchMapStyle(styleKey);
                    taSetActiveOption(styleKey);
                    taLayerControl.classList.remove('ta-layer-open');
                });
            });

            document.addEventListener('click', function (e) {
                if (!taLayerControl.contains(e.target)) {
                    taLayerControl.classList.remove('ta-layer-open');
                }
            });
        }

        taSetActiveOption(initialTaStyle);
    }

    switchMapStyle(initialTaStyle);

    let markersLayer = L.layerGroup().addTo(assignmentMap);

    function buildPinIcon(color) {
        const svg = `
            <svg width="34" height="44" viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg"
                 style="display:block;filter:drop-shadow(0 3px 4px rgba(0,0,0,.6));">
                <path d="M17 0C7.6 0 0 7.6 0 17c0 11.5 14.3 24.8 16.1 26.4a1.3 1.3 0 0 0 1.8 0C19.7 41.8 34 28.5 34 17 34 7.6 26.4 0 17 0z"
                      fill="${color}" stroke="#fff" stroke-width="2"/>
                <circle cx="17" cy="17" r="6.5" fill="#fff"/>
            </svg>`;
        return L.divIcon({
            className: 'ta-map-pin',
            html: svg,
            iconSize: [34, 44],
            iconAnchor: [17, 44],
            popupAnchor: [0, -40]
        });
    }

    const greenIcon = buildPinIcon('#22c55e');

    function addMarkers() {
        markersLayer.clearLayers();
        mapMarkers.forEach(function (m) {
            if (typeof m.lat !== 'number' || typeof m.lng !== 'number') return;
            L.marker([m.lat, m.lng], { icon: greenIcon, riseOnHover: true })
                .addTo(markersLayer)
                .bindTooltip(m.name || '', { permanent: false });
        });
    }

    assignmentMap.invalidateSize();
    addMarkers();

    document.getElementById('ta-zoom-in').addEventListener('click', () => assignmentMap.zoomIn());
    document.getElementById('ta-zoom-out').addEventListener('click', () => assignmentMap.zoomOut());

    window.addEventListener('resize', function () { assignmentMap.invalidateSize(); });
    setTimeout(function () {
        assignmentMap.invalidateSize();
        addMarkers();
    }, 200);

    /* ============================================================
       ROW ACTIONS — three-dot dropdown per row: Report + Location.
       Only one dropdown open at a time; clicking outside closes it.
    ============================================================ */
    const allDropdowns = document.querySelectorAll('.ta-dropdown');

    function closeAllDropdowns(except) {
        allDropdowns.forEach(function (dd) {
            if (dd !== except) dd.classList.remove('ta-dropdown-open');
        });
    }

    allDropdowns.forEach(function (dd) {
        const btn = dd.querySelector('.ta-dots-btn');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const isOpen = dd.classList.contains('ta-dropdown-open');
            closeAllDropdowns();
            dd.classList.toggle('ta-dropdown-open', !isOpen);
        });
    });

    document.addEventListener('click', function () { closeAllDropdowns(); });

    // "Report" — only wired up once the technician.assignment.report route
    // exists on the backend; until then this is a visible-but-informative
    // placeholder rather than a dead link.
    document.querySelectorAll('.ta-menu-item-soon').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            btn.textContent = '';
            const icon = document.createElement('i');
            icon.className = 'fas fa-clock';
            btn.appendChild(icon);
            btn.append(' Coming soon');
            setTimeout(function () {
                btn.innerHTML = '<i class="fas fa-file-lines"></i> Report';
            }, 1600);
        });
    });

    // "Location" — flies the Area Map Overview to that assignment's saved
    // pin, same behavior as clicking a name on the admin Assignment
    // Management page.
    let noPinNoteTimer = null;
    document.querySelectorAll('.ta-locate-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            closeAllDropdowns();

            if (btn.dataset.noPin) {
                clearTimeout(noPinNoteTimer);
                document.querySelectorAll('.ta-no-pin-note').forEach(n => n.remove());

                const note = document.createElement('div');
                note.className = 'ta-no-pin-note';
                note.textContent = 'No saved location for this area yet';
                btn.closest('td').appendChild(note);

                noPinNoteTimer = setTimeout(function () { note.remove(); }, 2200);
                return;
            }

            const lat = parseFloat(btn.dataset.lat);
            const lng = parseFloat(btn.dataset.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            const mapPanel = document.getElementById('ta-map-panel');
            if (mapPanel) mapPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });

            setTimeout(function () {
                assignmentMap.invalidateSize();
                assignmentMap.flyTo([lat, lng], 16, { duration: 1.2 });

                const pulse = L.circleMarker([lat, lng], {
                    radius: 20, color: '#22c55e', weight: 3, fillOpacity: 0
                }).addTo(assignmentMap);
                setTimeout(function () { assignmentMap.removeLayer(pulse); }, 1800);
            }, 300);
        });
    });
})();
</script>
@endsection