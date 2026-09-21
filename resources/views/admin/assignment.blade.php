{{--
    resources/views/admin/assignment.blade.php

    Assignment Management page.

    Expects (all optional — page renders safely with defaults if omitted):
      $stats            => ['admins' => int, 'admins_new' => int, 'technicians' => int,
                             'technicians_new' => int, 'total_areas' => int, 'total_areas_new' => int,
                             'farmers_covered' => int]
      $assignments      => paginator/collection of assignment rows. Each row is expected to expose:
                             ->user->full_name, ->user->email, ->user_type (admin|technician),
                             ->province->name, ->city->name, ->barangay->name,
                             ->start_date, ->end_date, ->status, ->id
      $mapMarkers       => array of ['name'=>, 'lat'=>, 'lng'=>, 'type'=>'admin|technician']
      $mapCenterLat / $mapCenterLng => float, default center of the map

    Backend endpoints this view calls (add these routes/controller if not present yet):
      GET  /admin/assignments/users?role={admin|technician|farmer}   -> JSON list of users for that role
      POST /admin/assignments/store                                  -> create an assignment
      All calls are wrapped with Route::has() checks below so this view never throws
      a RouteNotFoundException if those routes haven't been registered yet.
--}}
@extends('layouts.admin')

@section('title', 'RICEGUARD AI • Assignment Management')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
{{-- MapTiler key for the Area Map Overview below. Falls back to reading
     config directly in case a controller on an older deploy hasn't been
     updated to pass $mapTilerKey yet — either way, if no key is configured
     this simply renders empty and the map JS falls back to plain OSM tiles. --}}
<meta name="maptiler-key" content="{{ $mapTilerKey ?? config('services.maptiler.key') }}">

@php
    $usersEndpoint  = \Illuminate\Support\Facades\Route::has('admin.assignment.users')
        ? route('admin.assignment.users')
        : url('/admin/assignments/users');

    $storeEndpoint  = \Illuminate\Support\Facades\Route::has('admin.assignment.store')
        ? route('admin.assignment.store')
        : url('/admin/assignments/store');

    $updateEndpointBase = \Illuminate\Support\Facades\Route::has('admin.assignment.update')
        ? url('/admin/assignments') // {id}/update appended per-row below via route() when available
        : url('/admin/assignments');

    $locationsBase  = url('/');
@endphp

<div class="assign-mgmt">

    {{-- ===================== PAGE HEADER ===================== --}}
    <div class="am-page-header">
        <div class="am-page-header-title">
            <div class="am-title-row">
                <span class="am-title-icon"><i class="fas fa-shield-halved"></i></span>
                <div>
                    <h5 class="m-b-2">Assignment Management</h5>
                    <p class="text-muted mb-0">Assign administrators and technicians to their areas of responsibility.</p>
                </div>
            </div>
        </div>
        <button type="button" class="btn am-btn-primary" id="am-new-assignment-btn">
            <i class="fas fa-plus me-1"></i> New Assignment
        </button>
    </div>

    {{-- ===================== STAT CARDS ===================== --}}
    {{-- Administrators card + data are developer/superadmin only. A normal
         admin never sees admin counts here, same as the Admin tab already
         being hidden from them on the User Accounts page.

         "Farmers Covered" was removed (it was a pure display metric, not
         used anywhere else in the app) — the column width below is now
         computed from however many cards are actually left, so 3 cards
         (developer) or 2 cards (admin) each spread out evenly instead of
         leaving an empty gap where the 4th card used to be. --}}
    @php
        $amStatCardCount = ($isDeveloper ?? false) ? 3 : 2;
        $amStatColClass = $amStatCardCount === 2 ? 'col-6 col-lg-6' : 'col-6 col-lg-4';
    @endphp
    <div class="row g-3 am-stats-row">
        @if ($isDeveloper ?? false)
        <div class="{{ $amStatColClass }}">
            <div class="am-stat-card">
                <div class="am-stat-icon am-icon-blue"><i class="fas fa-user-shield"></i></div>
                <div class="am-stat-body">
                    <span class="am-stat-label">Administrators</span>
                    <span class="am-stat-value">{{ $stats['admins'] ?? 0 }}</span>
                    <span class="am-stat-sub">Assigned</span>
                    <span class="am-stat-trend"><i class="fas fa-arrow-trend-up"></i> {{ $stats['admins_new'] ?? 0 }} this month</span>
                </div>
            </div>
        </div>
        @endif
        <div class="{{ $amStatColClass }}">
            <div class="am-stat-card">
                <div class="am-stat-icon am-icon-green"><i class="fas fa-user-check"></i></div>
                <div class="am-stat-body">
                    <span class="am-stat-label">Technicians</span>
                    <span class="am-stat-value">{{ $stats['technicians'] ?? 0 }}</span>
                    <span class="am-stat-sub">Assigned</span>
                    <span class="am-stat-trend"><i class="fas fa-arrow-trend-up"></i> {{ $stats['technicians_new'] ?? 0 }} this month</span>
                </div>
            </div>
        </div>
        <div class="{{ $amStatColClass }}">
            <div class="am-stat-card">
                <div class="am-stat-icon am-icon-purple"><i class="fas fa-people-group"></i></div>
                <div class="am-stat-body">
                    <span class="am-stat-label">Total Areas</span>
                    <span class="am-stat-value">{{ $stats['total_areas'] ?? 0 }}</span>
                    <span class="am-stat-sub">Barangays</span>
                    <span class="am-stat-trend"><i class="fas fa-arrow-trend-up"></i> {{ $stats['total_areas_new'] ?? 0 }} this month</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== MAIN PANELS ===================== --}}
    <div class="row g-4 am-main-row">

        {{-- ---------- ASSIGN USER TO AREA ---------- --}}
        <div class="col-lg-5">
            <div class="am-panel h-100">
                <div class="am-panel-header">
                    <h6 class="mb-0">Assign User to Area</h6>
                    <p class="text-muted mb-0 small">Select a user and assign them to an area.</p>
                </div>

                @if (session('success'))
                    <div class="am-alert am-alert-success">
                        <i class="fas fa-circle-check me-2"></i>{{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="am-alert am-alert-danger">
                        <i class="fas fa-circle-exclamation me-2"></i>{{ session('error') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="am-alert am-alert-danger">
                        <i class="fas fa-circle-exclamation me-2"></i>
                        {{ $errors->count() === 1 ? 'Please fix the issue below.' : 'Please fix the issues below.' }}
                    </div>
                @endif

                <form method="POST" action="{{ $storeEndpoint }}" id="am-assign-form">
                    @csrf

                    {{-- Row 1: User Type | User | Province | City/Municipality --}}
                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">User Type</label>
                            <select name="user_type" id="am-user-type" class="form-control am-input" required>
                                @foreach (($allowedUserTypes ?? ['technician']) as $type)
                                    <option value="{{ $type }}" {{ $loop->first ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">User <span class="text-danger">*</span></label>
                            <div class="am-user-select" id="am-user-select">
                                <button type="button" class="am-user-select-trigger" id="am-user-select-trigger">
                                    <span id="am-user-select-label" class="am-truncate">Select Technician</span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                                <div class="am-user-select-panel" id="am-user-select-panel">
                                    <div class="am-user-select-search">
                                        <i class="fas fa-magnifying-glass"></i>
                                        <input type="text" id="am-user-search" placeholder="Search technician...">
                                    </div>
                                    <div class="am-user-select-list" id="am-user-select-list">
                                        <div class="am-user-select-empty">Loading...</div>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="user_id" id="am-user-id" required>
                        </div>

                        {{-- A normal admin only ever assigns technicians who
                             already live in the admin's own Province + City
                             (usersByRole() enforces that pool), so these two
                             fields are locked to the admin's own account
                             instead of freely chosen — picking a different
                             city here used to silently move the technician's
                             own record there too (see store()'s location
                             sync), making them vanish from userLog(). The
                             <select> elements stay in the DOM, pre-filled
                             with exactly one option, so the rest of this
                             script (and the POST) needs no special-casing.
                             Developer keeps the original free picker. --}}
                        <div class="col-12 col-md-6 col-lg-3" id="am-province-locked-display" style="{{ ($isDeveloper ?? false) ? 'display:none;' : '' }}">
                            <label class="form-label">Province</label>
                            <div class="form-control am-input" style="cursor:not-allowed;">{{ $actorProvinceName ?? '' }}</div>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3" id="am-province-group" style="{{ ($isDeveloper ?? false) ? '' : 'display:none;' }}">
                            <label class="form-label">Province <span class="text-danger" id="am-province-required-mark">*</span></label>
                            <select name="province_id" id="am-province-select" class="form-control am-input" required>
                                <option value="" disabled selected>Select province...</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3" id="am-city-locked-display" style="{{ ($isDeveloper ?? false) ? 'display:none;' : '' }}">
                            <label class="form-label">City / Municipality</label>
                            <div class="form-control am-input" style="cursor:not-allowed;">{{ $actorCityName ?? '' }}</div>
                            <small class="am-field-hint">Fixed to your own account's coverage area.</small>
                        </div>
                        <div class="col-12 col-md-6 col-lg-3" id="am-city-group" style="{{ ($isDeveloper ?? false) ? '' : 'display:none;' }}">
                            <label class="form-label">City / Municipality <span class="text-danger" id="am-city-required-mark">*</span></label>
                            <select name="city_id" id="am-city-select" class="form-control am-input" disabled required>
                                <option value="" disabled selected>Select province first...</option>
                            </select>
                        </div>
                    </div>

                    {{-- Province + City are always collected here now (for BOTH
                         Administrator and Technician assignments) — this is what
                         sets/updates the user's assigned area, and the pin on the
                         Area Map Overview to the right updates live as you pick. --}}
                    <small class="am-field-hint" id="am-location-sync-hint">
                        Sets/updates this user's assigned Province + City<span id="am-location-sync-hint-brgy"> and Barangay</span> — the map pin to the right moves automatically as you pick.
                    </small>
                    <input type="hidden" name="latitude" id="am-latitude">
                    <input type="hidden" name="longitude" id="am-longitude">

                    {{-- Row 2: Barangay | Start Date | End Date --}}
                    <div class="row g-3 mt-0">
                        <div class="col-12 col-md-6 col-lg-4" id="am-barangay-group">
                            <label class="form-label">Barangay <span class="text-danger" id="am-barangay-required-mark">*</span></label>
                            <select name="barangay_id" id="am-barangay-select" class="form-control am-input" disabled required>
                                <option value="" disabled selected>Select city first...</option>
                            </select>
                            <small class="am-field-hint" id="am-barangay-hint" style="display:none;">
                                Not needed for Administrators — an admin's scope is the whole province + city, not a single barangay.
                            </small>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="am-start-date" class="form-control am-input" required>
                        </div>

                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" id="am-end-date" class="form-control am-input">
                        </div>
                    </div>

                    {{-- Row 3: Status — placed right after End Date. Drives the
                         Status badge/column in the Current Assignments table below,
                         and is editable later from that table's row-edit modal too.
                         The location pin is no longer a separate manual readout
                         here — it's fully automatic (City for Administrator, Barangay
                         for Technician/Farmer) and shown live as the amber preview
                         marker on the Area Map Overview to the right, same pattern
                         as the Location Preview map on the Create Account page. --}}
                    <div class="row g-3 mt-0">
                        <div class="col-12 col-md-6 col-lg-4">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" id="am-status-select" class="form-control am-input" required>
                                <option value="active" selected>Active</option>
                                <option value="pending">Pending</option>
                                <option value="ended">Ended</option>
                            </select>
                            <small class="am-field-hint">Use Pending for a future-dated assignment that hasn't started yet.</small>
                        </div>
                    </div>

                    <div class="am-form-actions">
                        <button type="reset" class="btn am-btn-outline" id="am-reset-btn">Reset</button>
                        <button type="submit" class="btn am-btn-primary" id="am-assign-btn">Assign</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ---------- AREA MAP OVERVIEW ---------- --}}
        <div class="col-lg-7">
            <div class="am-panel h-100 am-map-panel" id="am-map-panel">
                <div class="am-panel-header am-map-header">
                    <h6 class="mb-0">Area Map Overview</h6>
                    <div class="am-map-legend">
                        @if ($isDeveloper ?? false)
                        <span class="am-legend-item"><span class="am-dot am-dot-blue"></span> Administrator</span>
                        @endif
                        <span class="am-legend-item"><span class="am-dot am-dot-green"></span> Technician</span>
                        <span class="am-legend-item"><span class="am-dot" style="background:#f59e0b;"></span> New assignment (preview)</span>
                        <span class="am-legend-item am-legend-checkbox">
                            <input type="checkbox" id="am-coverage-toggle" checked> Coverage Area
                        </span>
                    </div>
                </div>

                <div class="am-map-wrapper" id="am-map-wrapper">
                    <div id="am-assignment-map"></div>
                    <div class="am-map-controls" id="am-map-controls">
                        <button type="button" class="am-map-ctrl-btn" id="am-zoom-in" title="Zoom in"><i class="fas fa-plus"></i></button>
                        <button type="button" class="am-map-ctrl-btn" id="am-zoom-out" title="Zoom out"><i class="fas fa-minus"></i></button>
                        {{-- Real map-style toggle, stacked directly below +/- like
                             Google Maps' own layers control — not a form <select>. --}}
                        <div class="am-layer-control" id="am-layer-control">
                            <button type="button" class="am-map-ctrl-btn" id="am-layer-toggle" title="Map layers">
                                <i class="fas fa-layer-group"></i>
                            </button>
                            <div class="am-layer-menu" id="am-layer-menu">
                                <button type="button" class="am-layer-option" data-style="streets"><i class="fas fa-road"></i>Streets</button>
                                <button type="button" class="am-layer-option" data-style="hybrid"><i class="fas fa-satellite"></i>Hybrid</button>
                            </div>
                        </div>
                    </div>
                    {{-- MapTiler tiles load first when MAPTILER_API_KEY is
                         configured; if that key is missing, invalid, or the
                         tiles simply fail to load, the map silently swaps to
                         the same plain OpenStreetMap layer the Create Account
                         page's Location Preview map uses. This note only
                         appears if BOTH sources fail (offline, tile domains
                         blocked on this network). --}}
                    <div class="am-map-fallback-note" id="am-map-fallback-note" style="display:none;">
                        <i class="fas fa-triangle-exclamation me-1"></i> Map tiles aren't loading
                    </div>
                </div>

                <div class="am-map-footer">
                    <button type="button" class="am-view-full-map" id="am-view-full-map">
                        View Full Map <i class="fas fa-up-right-from-square ms-1"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== CURRENT ASSIGNMENTS TABLE ===================== --}}
    <div class="am-panel am-table-panel">
        <div class="am-panel-header">
            <h6 class="mb-0">Current Assignments</h6>
            <p class="text-muted mb-0 small">List of all users and their assigned areas.</p>
        </div>

        <div class="am-table-toolbar">
            <div class="am-search-box">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" id="am-table-search" placeholder="Search user or area...">
            </div>
            <div class="am-filters">
                <select class="form-control am-input am-filter" id="am-filter-usertype">
                    <option value="">All User Types</option>
                    <option value="admin">Administrator</option>
                    <option value="technician">Technician</option>
                </select>
                <select class="form-control am-input am-filter" id="am-filter-province">
                    <option value="">All Provinces</option>
                </select>
                <select class="form-control am-input am-filter" id="am-filter-city">
                    <option value="">All Municipalities</option>
                </select>
                <select class="form-control am-input am-filter" id="am-filter-barangay">
                    <option value="">All Barangays</option>
                </select>
                <button type="button" class="btn am-btn-outline am-export-btn" id="am-export-btn">
                    <i class="fas fa-download me-1"></i> Export
                </button>
            </div>
        </div>

        <div class="am-table-scroll">
            <table class="am-table" id="am-assignments-table">
                <thead>
                    <tr>
                        <th rowspan="2">User</th>
                        <th rowspan="2">User Type</th>
                        <th rowspan="2">Assigned By</th>
                        <th colspan="3" class="am-th-group">Assigned Area</th>
                        <th colspan="2" class="am-th-group">Assignment Period</th>
                        <th rowspan="2">Status</th>
                        <th rowspan="2">Actions</th>
                    </tr>
                    <tr>
                        <th class="am-th-sub">Province</th>
                        <th class="am-th-sub">City / Municipality</th>
                        <th class="am-th-sub">Barangay</th>
                        <th class="am-th-sub">From</th>
                        <th class="am-th-sub">To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse (($assignments ?? []) as $item)
                        @php
                            $utype = $item->user_type ?? ($item->user->role ?? 'technician');
                            $initials = collect(explode(' ', $item->user->full_name ?? '?'))
                                ->map(fn($p) => mb_substr($p, 0, 1))
                                ->take(2)
                                ->implode('');
                            $hasPin = $item->user && $item->user->latitude !== null && $item->user->longitude !== null;
                        @endphp
                        <tr>
                            <td>
                                <div class="am-user-cell">
                                    <span class="am-avatar {{ $utype === 'admin' ? 'am-avatar-blue' : 'am-avatar-green' }}">{{ strtoupper($initials) }}</span>
                                    <div>
                                        {{-- Every username is underlined/clickable now.
                                             Clicking flies the Area Map Overview to that
                                             user's pin when one exists; if the user never
                                             set a location, the click shows a small
                                             "no location set" note instead of doing nothing. --}}
                                        <div class="am-user-name am-user-name-linked"
                                             @if ($hasPin)
                                                 data-lat="{{ $item->user->latitude }}"
                                                 data-lng="{{ $item->user->longitude }}"
                                                 title="Locate {{ $item->user->full_name }} on the map"
                                             @else
                                                 data-no-pin="1"
                                                 title="No saved location for {{ $item->user->full_name }}"
                                             @endif>
                                            {{ $item->user->full_name ?? '—' }}
                                        </div>
                                        <div class="am-user-email">{{ $item->user->email ?? '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="am-badge {{ $utype === 'admin' ? 'am-badge-blue' : 'am-badge-green' }}">
                                    {{ ucfirst($utype) }}
                                </span>
                            </td>
                            <td>
                                @php
                                    // "Me" whenever the currently logged-in actor is the one
                                    // who made this assignment (developer assigning someone,
                                    // or an admin assigning their own technician) — otherwise
                                    // show the actual name of whoever assigned it, so e.g. a
                                    // developer can see which admin assigned a given technician.
                                    $assignedByIsSelf = (int) ($item->assigned_by ?? 0) === (int) auth()->id();
                                    $assignedByName   = $item->assignedBy->full_name ?? null;
                                @endphp
                                <span class="am-badge am-badge-muted">
                                    {{ $assignedByIsSelf ? 'Me' : ($assignedByName ?? '—') }}
                                </span>
                            </td>
                            <td>{{ $item->province->name ?? '—' }}</td>
                            <td>{{ $item->city->name ?? '—' }}</td>
                            {{-- Barangay is meaningless for an admin-type assignment
                                 (their scope is the whole province + city), so it's
                                 left blank here instead of showing a "—" placeholder. --}}
                            <td>{{ $utype === 'admin' ? '' : ($item->barangay->name ?? '—') }}</td>
                            <td>{{ $item->start_date ? \Illuminate\Support\Carbon::parse($item->start_date)->format('M j, Y') : '—' }}</td>
                            <td>{{ $item->end_date ? \Illuminate\Support\Carbon::parse($item->end_date)->format('M j, Y') : '—' }}</td>
                            <td>
                                @php
                                    $status = $item->status ?? 'active';
                                    $statusBadgeClass = match ($status) {
                                        'active'  => 'am-badge-green',
                                        'pending' => 'am-badge-amber',
                                        default   => 'am-badge-muted', // ended
                                    };
                                @endphp
                                <span class="am-badge {{ $statusBadgeClass }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>
                            <td>
                                <div class="am-row-actions">
                                    <button type="button"
                                            class="am-icon-btn am-icon-btn-edit am-row-edit"
                                            title="Edit"
                                            data-id="{{ $item->id ?? '' }}"
                                            data-user="{{ $item->user->full_name ?? '—' }}"
                                            data-start="{{ optional($item->start_date)->format('Y-m-d') }}"
                                            data-end="{{ optional($item->end_date)->format('Y-m-d') }}"
                                            data-status="{{ $item->status ?? 'active' }}"
                                            data-update-url="{{ \Illuminate\Support\Facades\Route::has('admin.assignment.update') ? route('admin.assignment.update', $item->id ?? 0) : url('/admin/assignments/'.($item->id ?? 0).'/update') }}">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                    @if (\Illuminate\Support\Facades\Route::has('admin.assignment.delete'))
                                        <a href="{{ route('admin.assignment.delete', $item->id) }}"
                                           class="am-icon-btn am-icon-btn-delete am-row-delete"
                                           title="Delete"
                                           onclick="return confirm('Remove this assignment?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    @else
                                        <button type="button" class="am-icon-btn am-icon-btn-delete" title="Add the admin.assignment.delete route first" disabled>
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="am-empty-row">No assignments found yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="am-table-footer">
            @if (isset($assignments) && method_exists($assignments, 'total'))
                <span class="am-entries-label">
                    Showing {{ $assignments->firstItem() ?? 0 }} to {{ $assignments->lastItem() ?? 0 }} of {{ $assignments->total() }} entries
                </span>
                <div class="am-pagination">
                    {{ $assignments->links() }}
                </div>
            @else
                <span class="am-entries-label">Showing 0 to 0 of 0 entries</span>
            @endif
        </div>
    </div>
</div>

{{-- ===================== STYLES ===================== --}}
<style>
.assign-mgmt {
    --am-bg: #0e1116;
    --am-panel: #161b22;
    --am-panel-2: #12161d;
    --am-border: #30363d;
    --am-text: #e6edf3;
    --am-muted: #8b94a3;
    --am-green: #22c55e;
    --am-green-dark: #16a34a;
    --am-blue: #3b82f6;
    --am-purple: #a78bfa;
    --am-amber: #f59e0b;
    color: var(--am-text);
    font-family: inherit;
}
.assign-mgmt * { box-sizing: border-box; }

/* ---------- header ---------- */
.am-page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
.am-title-row { display: flex; align-items: flex-start; gap: .75rem; }
.am-title-icon { width: 38px; height: 38px; border-radius: 10px; background: rgba(34,197,94,.15); color: var(--am-green); display: flex; align-items: center; justify-content: center; font-size: 16px; flex-shrink: 0; }
.am-page-header h5 { color: var(--am-text); font-weight: 700; }
.am-page-header .text-muted { color: var(--am-muted) !important; }

.am-btn-primary { background: var(--am-green); border: 1px solid var(--am-green); color: #05170c; font-weight: 600; padding: .55rem 1.1rem; border-radius: 8px; transition: background .15s; }
.am-btn-primary:hover { background: var(--am-green-dark); color: #05170c; }
.am-btn-outline { background: transparent; border: 1px solid var(--am-border); color: var(--am-text); font-weight: 500; padding: .55rem 1.1rem; border-radius: 8px; }
.am-btn-outline:hover { background: rgba(255,255,255,.05); color: var(--am-text); }

/* ---------- stat cards ---------- */
.am-stats-row { margin-bottom: 1.25rem; }
.am-stat-card { background: var(--am-panel); border: 1px solid var(--am-border); border-radius: 12px; padding: 1.1rem; display: flex; gap: .85rem; height: 100%; }
.am-stat-icon { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.am-icon-blue { background: rgba(59,130,246,.15); color: var(--am-blue); }
.am-icon-green { background: rgba(34,197,94,.15); color: var(--am-green); }
.am-icon-purple { background: rgba(167,139,250,.15); color: var(--am-purple); }
.am-icon-amber { background: rgba(245,158,11,.15); color: var(--am-amber); }
.am-stat-body { display: flex; flex-direction: column; min-width: 0; }
.am-stat-label { font-size: .78rem; color: var(--am-muted); font-weight: 600; }
.am-stat-value { font-size: 1.55rem; font-weight: 800; color: var(--am-text); line-height: 1.2; }
.am-stat-sub { font-size: .72rem; color: var(--am-muted); }
.am-stat-trend { font-size: .72rem; color: var(--am-green); margin-top: .15rem; }

/* ---------- panels ---------- */
.am-main-row { margin-bottom: 1.25rem; }
.am-panel { background: var(--am-panel); border: 1px solid var(--am-border); border-radius: 12px; padding: 1.25rem; overflow: visible; }
.am-panel-header { margin-bottom: 1rem; }
.am-panel-header h6 { color: var(--am-text); font-weight: 700; }

.am-alert { border-radius: 8px; padding: .65rem .9rem; font-size: .85rem; margin-bottom: 1rem; }
.am-alert-success { background: rgba(34,197,94,.12); color: #7ee2a8; border: 1px solid rgba(34,197,94,.3); }
.am-alert-danger { background: rgba(239,68,68,.12); color: #f7a5a5; border: 1px solid rgba(239,68,68,.3); }

.assign-mgmt .form-label { color: var(--am-muted); font-size: .78rem; font-weight: 600; margin-bottom: .35rem; }
.am-input, .assign-mgmt select.form-control, .assign-mgmt input.form-control {
    background: var(--am-panel-2) !important; border: 1px solid var(--am-border) !important; color: var(--am-text) !important;
    border-radius: 8px; font-size: .85rem; padding: .5rem .7rem; height: auto;
}
.am-input:focus { border-color: var(--am-green) !important; box-shadow: 0 0 0 3px rgba(34,197,94,.15) !important; }
.am-input:disabled { opacity: .5; }
.am-form-actions { display: flex; justify-content: flex-end; gap: .6rem; margin-top: 1.25rem; }

/* custom user searchable select */
.am-user-select { position: relative; }
.am-user-select-trigger {
    width: 100%; display: flex; align-items: center; justify-content: space-between; gap: .5rem;
    background: var(--am-panel-2); border: 1px solid var(--am-border); color: var(--am-text);
    border-radius: 8px; padding: .5rem .7rem; font-size: .85rem; text-align: left;
}
.am-user-select-trigger i { color: var(--am-muted); font-size: .75rem; }
.am-truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.am-user-select-panel {
    display: none; position: absolute; z-index: 500; top: calc(100% + 6px); left: 0;
    width: max(260px, 100%); max-width: min(320px, calc(100vw - 32px));
    background: var(--am-panel-2); border: 1px solid var(--am-border); border-radius: 10px;
    box-shadow: 0 12px 28px rgba(0,0,0,.5); overflow: hidden;
}
.am-user-select.am-open .am-user-select-panel { display: block; }
.am-user-select-search { display: flex; align-items: center; gap: .5rem; padding: .6rem .7rem; border-bottom: 1px solid var(--am-border); }
.am-user-select-search i { color: var(--am-muted); font-size: .78rem; }
.am-user-select-search input { flex: 1; background: transparent; border: none; outline: none; color: var(--am-text); font-size: .82rem; }
.am-user-select-list { max-height: 240px; overflow-y: auto; }
.am-user-select-empty { padding: .9rem; text-align: center; color: var(--am-muted); font-size: .8rem; }

/* ---------- muted field (e.g. Barangay when User Type = Administrator) ---------- */
.am-field-muted { opacity: .45; }
.am-field-muted .am-input { cursor: not-allowed; }
.am-field-hint { display: block; margin-top: .35rem; font-size: .7rem; color: var(--am-muted); line-height: 1.3; }
.am-user-option { display: flex; align-items: center; gap: .6rem; padding: .55rem .7rem; cursor: pointer; }
.am-user-option:hover, .am-user-option.am-active { background: rgba(34,197,94,.1); }
.am-user-option .am-avatar { width: 30px; height: 30px; font-size: .68rem; }
.am-option-text { min-width: 0; }
.am-option-name { font-size: .82rem; color: var(--am-text); font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.am-option-sub { font-size: .7rem; color: var(--am-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.am-avatar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .72rem; font-weight: 700; color: #fff; flex-shrink: 0; }
.am-avatar-blue { background: linear-gradient(135deg,#3b82f6,#1d4ed8); }
.am-avatar-green { background: linear-gradient(135deg,#22c55e,#15803d); }

/* ---------- map ---------- */
.am-map-panel { display: flex; flex-direction: column; }
.am-map-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .6rem; }
.am-map-legend { display: flex; align-items: center; gap: 1rem; font-size: .75rem; color: var(--am-muted); flex-wrap: wrap; }
.am-legend-item { display: flex; align-items: center; gap: .4rem; }
.am-legend-checkbox { display: flex; align-items: center; gap: .35rem; }
.am-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.am-dot-blue { background: var(--am-blue); }
.am-dot-green { background: var(--am-green); }

.am-map-wrapper { position: relative; flex: 1; min-height: 380px; border-radius: 10px; overflow: hidden; border: 1px solid var(--am-border); background: var(--am-bg); }
#am-assignment-map { position: absolute; inset: 0; width: 100%; height: 100%; background: var(--am-bg); }
.am-map-controls { position: absolute; top: 12px; right: 12px; z-index: 500; display: flex; flex-direction: column; gap: 4px; }
/* Defensive: make sure real assignment pins always paint above tiles and
   any overlay, regardless of what other CSS on the page touches z-index. */
.am-map-wrapper .leaflet-marker-pane { z-index: 650; }
.am-map-wrapper .leaflet-tile-pane { z-index: 200; }
.am-map-ctrl-btn { width: 32px; height: 32px; background: var(--am-panel); border: 1px solid var(--am-border); color: var(--am-text); border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: .8rem; box-shadow: 0 4px 10px rgba(0,0,0,.4); }
.am-map-ctrl-btn:hover { background: var(--am-border); }

/* Real map-style toggle — sits in the same vertical stack as the +/-
   zoom buttons (am-map-controls is top-right), opening its flyout to
   the LEFT so it never runs off the edge of the map. */
.am-layer-control { position: relative; }
.am-layer-control.am-layer-disabled .am-map-ctrl-btn { color: var(--am-muted); cursor: not-allowed; opacity: .55; }
.am-layer-menu {
    display: none;
    position: absolute;
    top: 0;
    right: calc(100% + 8px);
    background: var(--am-panel);
    border: 1px solid var(--am-border);
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(0,0,0,.45);
    overflow: hidden;
    min-width: 128px;
    z-index: 600;
}
.am-layer-control.am-layer-open .am-layer-menu { display: block; }
.am-layer-option {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: .5rem .75rem;
    background: transparent; border: none; border-bottom: 1px solid var(--am-border);
    font-size: .75rem; font-weight: 600; color: var(--am-text); text-align: left; cursor: pointer;
}
.am-layer-option:last-child { border-bottom: none; }
.am-layer-option:hover { background: rgba(255,255,255,.06); }
.am-layer-option i { width: 14px; text-align: center; color: var(--am-muted); font-size: .72rem; }
.am-layer-option.am-layer-active { background: rgba(34,197,94,.12); color: var(--am-green); }
.am-layer-option.am-layer-active i { color: var(--am-green); }
.am-map-fallback-note { position: absolute; bottom: 10px; left: 10px; z-index: 500; background: rgba(245,158,11,.15); color: #fbbf24; border: 1px solid rgba(245,158,11,.35); font-size: .7rem; padding: .3rem .6rem; border-radius: 6px; }

.am-map-footer { display: flex; justify-content: flex-end; margin-top: .75rem; }
.am-view-full-map { background: transparent; border: none; color: var(--am-green); font-size: .8rem; font-weight: 600; }
.am-view-full-map:hover { text-decoration: underline; }

.am-map-panel.am-fullscreen { position: fixed; inset: 16px; z-index: 2000; box-shadow: 0 20px 60px rgba(0,0,0,.7); }
.am-map-panel.am-fullscreen .am-map-wrapper { min-height: 0; }

/* ---------- table ---------- */
.am-table-panel { margin-top: .25rem; }
.am-table-toolbar {
    display: flex !important; flex-wrap: wrap; align-items: center; justify-content: space-between;
    gap: .75rem; margin-bottom: 1rem;
}
.am-search-box {
    display: flex !important; align-items: center; gap: .5rem; background: var(--am-panel-2);
    border: 1px solid var(--am-border); border-radius: 8px; padding: .5rem .8rem;
    flex: 1 1 230px; max-width: 320px;
}
.am-search-box i { color: var(--am-muted); font-size: .8rem; flex: 0 0 auto; }
.am-search-box input { background: transparent; border: none; outline: none; color: var(--am-text); font-size: .82rem; width: 100%; }
.am-filters { display: flex !important; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: flex-end; flex: 2 1 auto; }
.am-filter.am-filter {
    flex: 0 1 150px !important; width: auto !important; min-width: 120px; max-width: 170px;
}
.am-export-btn { flex: 0 0 auto !important; white-space: nowrap; }
@media (max-width: 640px) {
    .am-search-box { flex-basis: 100%; max-width: none; }
    .am-filters { justify-content: flex-start; }
    .am-filter.am-filter { flex-basis: calc(50% - .25rem); max-width: none; }
}

.am-table-scroll { overflow-x: auto; border: 1px solid var(--am-border); border-radius: 10px; }
.am-table { width: 100%; border-collapse: collapse; font-size: .82rem; min-width: 900px; }
.am-table thead th { background: var(--am-panel-2); color: var(--am-muted); font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; font-weight: 700; padding: .65rem .8rem; border-bottom: 1px solid var(--am-border); text-align: left; }
.am-th-group { text-align: center; border-left: 1px solid var(--am-border); }
.am-th-sub { text-align: center; border-left: 1px solid var(--am-border); font-size: .68rem; }
.am-table tbody td { padding: .7rem .8rem; border-bottom: 1px solid var(--am-border); color: var(--am-text); vertical-align: middle; }
.am-table tbody tr:hover { background: rgba(255,255,255,.02); }
.am-empty-row { text-align: center; color: var(--am-muted); padding: 2rem !important; }

.am-user-cell { display: flex; align-items: center; gap: .6rem; }
.am-user-name { font-weight: 600; font-size: .82rem; }
.am-user-name-linked { text-decoration: underline; text-underline-offset: 2px; cursor: pointer; color: #60a5fa; }
.am-user-name-linked:hover { color: #93c5fd; }
.am-no-pin-note { font-size: .7rem; color: var(--am-muted); font-style: italic; margin-top: 2px; }
.am-user-email { font-size: .72rem; color: var(--am-muted); }

.am-badge { display: inline-block; padding: .25rem .6rem; border-radius: 999px; font-size: .7rem; font-weight: 700; }
.am-badge-blue { background: rgba(59,130,246,.15); color: #60a5fa; }
.am-badge-green { background: rgba(34,197,94,.15); color: #4ade80; }
.am-badge-amber { background: rgba(245,158,11,.15); color: #fbbf24; }
.am-badge-muted { background: rgba(148,163,184,.15); color: #94a3b8; }

/* ---------- row-edit modal ---------- */
.am-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 1050;
    display: flex; align-items: center; justify-content: center; padding: 1rem;
}
.am-modal { background: var(--am-panel); border: 1px solid var(--am-border); border-radius: 12px; width: 100%; max-width: 420px; color: var(--am-text); box-shadow: 0 20px 50px rgba(0,0,0,.5); }
.am-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.1rem; border-bottom: 1px solid var(--am-border); }
.am-modal-header h6 { margin: 0; font-weight: 700; }
.am-modal-close { background: none; border: none; color: var(--am-muted); font-size: 1rem; cursor: pointer; }
.am-modal-close:hover { color: var(--am-text); }
.am-modal-body { padding: 1.1rem; display: flex; flex-direction: column; gap: .85rem; }
.am-modal-footer { display: flex; justify-content: flex-end; gap: .5rem; padding: 0 1.1rem 1.1rem; }
.am-modal-error { font-size: .75rem; color: #f87171; display: none; }

.am-row-actions { display: flex; gap: .4rem; }
.am-icon-btn { width: 28px; height: 28px; border-radius: 6px; border: 1px solid var(--am-border); background: var(--am-panel-2); color: var(--am-text); display: inline-flex; align-items: center; justify-content: center; font-size: .72rem; }
.am-icon-btn-edit:hover { background: rgba(59,130,246,.15); color: #60a5fa; border-color: rgba(59,130,246,.35); }
.am-icon-btn-delete:hover { background: rgba(239,68,68,.15); color: #f87171; border-color: rgba(239,68,68,.35); }

.am-table-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .6rem; margin-top: 1rem; }
.am-entries-label { font-size: .78rem; color: var(--am-muted); }
.am-pagination :is(a, span) { color: var(--am-text) !important; }

@media (max-width: 991px) {
    .am-map-wrapper { min-height: 300px; }
}
</style>

{{-- ===================== SCRIPTS ===================== --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
(function () {
    const CSRF_TOKEN   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const USERS_URL    = @json($usersEndpoint);
    const LOCATIONS_URL = @json($locationsBase);

    /* ============================================================
       "New Assignment" button — jumps to the assign form
    ============================================================ */
    const newAssignmentBtn = document.getElementById('am-new-assignment-btn');
    if (newAssignmentBtn) {
        newAssignmentBtn.addEventListener('click', function () {
            document.getElementById('am-assign-form').scrollIntoView({ behavior: 'smooth', block: 'start' });
            const trigger = document.getElementById('am-user-select-trigger');
            if (trigger) trigger.focus();
        });
    }

    /* ============================================================
       USER TYPE -> USER dropdown (role-scoped, searchable)
    ============================================================ */
    const userTypeSelect  = document.getElementById('am-user-type');
    const userSelectWrap  = document.getElementById('am-user-select');
    const userTrigger     = document.getElementById('am-user-select-trigger');
    const userLabel       = document.getElementById('am-user-select-label');
    const userPanel       = document.getElementById('am-user-select-panel');
    const userSearchInput = document.getElementById('am-user-search');
    const userListEl      = document.getElementById('am-user-select-list');
    const userIdInput     = document.getElementById('am-user-id');

    const roleLabels = { admin: 'Administrator', technician: 'Technician', farmer: 'Farmer' };
    let currentUsers = [];

    function initials(name) {
        return (name || '?').trim().split(/\s+/).map(p => p[0]).slice(0, 2).join('').toUpperCase();
    }

    function renderUserList(list) {
        userListEl.innerHTML = '';
        if (!list.length) {
            userListEl.innerHTML = '<div class="am-user-select-empty">No users found.</div>';
            return;
        }
        list.forEach(function (u) {
            const row = document.createElement('div');
            row.className = 'am-user-option';
            row.dataset.id = u.id;
            row.innerHTML = `
                <span class="am-avatar ${u.role === 'admin' ? 'am-avatar-blue' : 'am-avatar-green'}">${initials(u.full_name)}</span>
                <span class="am-option-text">
                    <span class="am-option-name">${(roleLabels[u.role] ? (u.role === 'technician' ? 'Tech. ' : (u.role === 'admin' ? 'Admin. ' : '')) : '')}${u.full_name}</span>
                    <span class="am-option-sub">${u.email ?? ''}</span>
                </span>`;
            row.addEventListener('click', function () {
                userIdInput.value = u.id;
                userLabel.textContent = u.full_name;
                userSelectWrap.classList.remove('am-open');
            });
            userListEl.appendChild(row);
        });
    }

    function loadUsersForRole(role) {
        userIdInput.value = '';
        userLabel.textContent = 'Select ' + (roleLabels[role] || 'User');
        userListEl.innerHTML = '<div class="am-user-select-empty">Loading...</div>';

        // cache: 'no-store' + a cache-busting "_ts" param: this endpoint is
        // hit repeatedly with the same URL (?role=technician) as users are
        // created/assigned elsewhere in the app, and both the browser's own
        // HTTP cache and this app's service worker (/sw.js) can otherwise
        // serve back a stale list until a hard refresh forces past them.
        fetch(`${USERS_URL}?role=${encodeURIComponent(role)}&_ts=${Date.now()}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            cache: 'no-store'
        })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                currentUsers = Array.isArray(data) ? data : (data.users || []);
                renderUserList(currentUsers);
            })
            .catch(err => {
                console.error('[Assignment] Failed to load users for role', role, err);
                currentUsers = [];
                userListEl.innerHTML = '<div class="am-user-select-empty">Could not load users. Check the /admin/assignments/users endpoint.</div>';
            });
    }

    if (userTrigger) {
        userTrigger.addEventListener('click', function () {
            const opening = !userSelectWrap.classList.contains('am-open');
            userSelectWrap.classList.toggle('am-open');
            if (opening) {
                userSearchInput.focus();
                // Keep the panel on-screen: reset, measure, then nudge left if it overflows.
                userPanel.style.left = '0px';
                userPanel.style.right = 'auto';
                requestAnimationFrame(function () {
                    const rect = userPanel.getBoundingClientRect();
                    const overflowRight = rect.right - (window.innerWidth - 8);
                    if (overflowRight > 0) {
                        userPanel.style.left = 'auto';
                        userPanel.style.right = '0px';
                    }
                });
            }
        });
    }

    document.addEventListener('click', function (e) {
        if (userSelectWrap && !userSelectWrap.contains(e.target)) {
            userSelectWrap.classList.remove('am-open');
        }
    });

    if (userSearchInput) {
        userSearchInput.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            const filtered = currentUsers.filter(u =>
                (u.full_name || '').toLowerCase().includes(q) || (u.email || '').toLowerCase().includes(q)
            );
            renderUserList(filtered);
        });
    }

    if (userTypeSelect) {
        userTypeSelect.addEventListener('change', function () {
            loadUsersForRole(this.value);
        });
        loadUsersForRole(userTypeSelect.value);
    }

    /* ============================================================
       PROVINCE -> CITY -> BARANGAY cascading dropdowns
       (reuses the same /locations/* endpoints used elsewhere)
    ============================================================ */
    const provinceSelect = document.getElementById('am-province-select');
    const citySelect      = document.getElementById('am-city-select');
    const barangaySelect  = document.getElementById('am-barangay-select');

    // Locked for a normal admin (see the blade markup above) — only
    // developer gets the free province/city picker here.
    const IS_DEVELOPER_ACTOR   = @json($isDeveloper ?? false);
    const LOCKED_PROVINCE_ID   = @json($actorProvinceId ?? null);
    const LOCKED_PROVINCE_NAME = @json($actorProvinceName ?? '');
    const LOCKED_CITY_ID       = @json($actorCityId ?? null);
    const LOCKED_CITY_NAME     = @json($actorCityName ?? '');

    /* ------------------------------------------------------------
       Pin/geocode state — declared here, BEFORE isAdminType() and the
       cascading-dropdown listeners below, because updateLocationFieldsVisibility()
       is invoked synchronously during setup (see the bottom of this
       section) and it calls scheduleAssignmentGeocode() -> clearTimeout(geocodeTimer)
       immediately. `let`/`const` bindings are not hoisted the way `var`
       or function declarations are: referencing geocodeTimer (or
       latInput/lngInput) before this block runs throws
       "Cannot access '...' before initialization" (a temporal-dead-zone
       ReferenceError), which is exactly the crash this fixes. Keep this
       block above line ~930 (the initial updateLocationFieldsVisibility()
       call) if you ever move things around again.
    ------------------------------------------------------------ */
    const latInput = document.getElementById('am-latitude');
    const lngInput  = document.getElementById('am-longitude');

    let previewMarker = null;
    let geocodeTimer = null;
    let geocodeSeq = 0;

    /* ------------------------------------------------------------
       Barangay is muted/hidden whenever "Administrator" is picked —
       an admin's scope is the whole province + city (see
       AdminUserController@userLog), never a single barangay, so the
       field is disabled and its value is cleared server won't even
       receive it (disabled fields aren't submitted). Technician stays
       exactly as before: barangay required, fully interactive.
    ------------------------------------------------------------ */
    function isAdminType() {
        return userTypeSelect && userTypeSelect.value === 'admin';
    }

    /**
     * Province + City are now ALWAYS shown and required, for BOTH
     * Administrator and Technician assignments — a developer/admin actor
     * uses them to actively set/update the area (and, via geocoding, the
     * map pin) for whoever they're assigning. See
     * AdminAssignmentController@store, which now takes province_id/city_id
     * straight from this form for every user_type instead of only ever
     * deriving them from the target admin's own record.
     *
     * Barangay is still the one field that's meaningless for an
     * Administrator assignment (an admin's scope is the whole province +
     * city, never a single barangay), so it's the only thing this toggle
     * still hides/mutes.
     */
    function updateLocationFieldsVisibility() {
        const barangayGroup = document.getElementById('am-barangay-group');
        const barangayHint  = document.getElementById('am-barangay-hint');
        const barangayMark  = document.getElementById('am-barangay-required-mark');
        const brgyHintWord  = document.getElementById('am-location-sync-hint-brgy');

        if (isAdminType()) {
            // Barangay — fully hidden, not just dimmed.
            if (barangayGroup) barangayGroup.style.display = 'none';
            barangaySelect.value = '';
            barangaySelect.disabled = true;
            barangaySelect.removeAttribute('required');
            if (barangayHint) barangayHint.style.display = 'block';
            if (barangayMark) barangayMark.style.display = 'none';
            if (brgyHintWord) brgyHintWord.style.display = 'none';
        } else {
            // Barangay
            if (barangayGroup) barangayGroup.style.display = '';
            barangaySelect.setAttribute('required', 'required');
            if (barangayHint) barangayHint.style.display = 'none';
            if (barangayMark) barangayMark.style.display = 'inline';
            // Re-enable it normally based on whatever city is already picked.
            barangaySelect.disabled = !citySelect.value;
            if (brgyHintWord) brgyHintWord.style.display = 'inline';
        }

        // The user-type change also affects what a valid geocode query
        // looks like (city-level vs barangay-level), so re-run it.
        scheduleAssignmentGeocode();
    }

    function loadProvinces() {
        provinceSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        fetch(`${LOCATIONS_URL}/locations/provinces`)
            .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .then(provinces => {
                provinceSelect.innerHTML = '<option value="" disabled selected>Select province...</option>';
                provinces.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    provinceSelect.appendChild(opt);
                });
            })
            .catch(err => {
                console.error('[Assignment] Failed to load provinces:', err);
                provinceSelect.innerHTML = '<option value="" disabled selected>Failed to load provinces</option>';
            });
    }

    function loadCities(provinceId) {
        citySelect.disabled = true;
        citySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        barangaySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="" disabled selected>Select city first...</option>';

        fetch(`${LOCATIONS_URL}/locations/cities/${provinceId}`)
            .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .then(cities => {
                citySelect.innerHTML = '<option value="" disabled selected>Select city/municipality...</option>';
                cities.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    citySelect.appendChild(opt);
                });
                citySelect.disabled = false;
            })
            .catch(err => {
                console.error('[Assignment] Failed to load cities:', err);
                citySelect.innerHTML = '<option value="" disabled selected>Failed to load cities</option>';
            });
    }

    function loadBarangays(cityId) {
        if (isAdminType()) return; // barangay stays muted for Administrator assignments

        barangaySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';

        fetch(`${LOCATIONS_URL}/locations/barangays/${cityId}`)
            .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .then(barangays => {
                barangaySelect.innerHTML = '<option value="" disabled selected>Select barangay...</option>';
                barangays.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.name;
                    barangaySelect.appendChild(opt);
                });
                barangaySelect.disabled = false;
            })
            .catch(err => {
                console.error('[Assignment] Failed to load barangays:', err);
                barangaySelect.innerHTML = '<option value="" disabled selected>Failed to load barangays</option>';
            });
    }

    provinceSelect.addEventListener('change', function () { loadCities(this.value); scheduleAssignmentGeocode(); });
    citySelect.addEventListener('change', function () { loadBarangays(this.value); scheduleAssignmentGeocode(); });
    barangaySelect.addEventListener('change', function () { scheduleAssignmentGeocode(); });

    // Labeled wrapper for the synchronous init calls below. If one of
    // these throws (e.g. a variable referenced before it's declared
    // further down the file, or a missing #am-* element on the page),
    // this pins the blame on the exact init step instead of leaving only
    // a raw "Uncaught ReferenceError" stack that jumps across several
    // function calls with no indication of which one actually started it.
    function safeInit(label, fn) {
        try {
            fn();
        } catch (err) {
            console.error(`[Assignment] Init step "${label}" failed:`, err);
        }
    }

    if (IS_DEVELOPER_ACTOR) {
        safeInit('loadProvinces', loadProvinces);
    } else {
        // Locked mode: pre-fill both selects with a single option — the
        // admin's own province/city — instead of fetching the full list,
        // then load barangays straight away for that fixed city so
        // Barangay (the only field this actor actually picks) is usable
        // immediately.
        safeInit('lockedProvinceCity', function () {
            provinceSelect.innerHTML = '';
            const lockedProvinceOpt = document.createElement('option');
            lockedProvinceOpt.value = LOCKED_PROVINCE_ID || '';
            lockedProvinceOpt.textContent = LOCKED_PROVINCE_NAME;
            lockedProvinceOpt.selected = true;
            provinceSelect.appendChild(lockedProvinceOpt);

            citySelect.innerHTML = '';
            const lockedCityOpt = document.createElement('option');
            lockedCityOpt.value = LOCKED_CITY_ID || '';
            lockedCityOpt.textContent = LOCKED_CITY_NAME;
            lockedCityOpt.selected = true;
            citySelect.appendChild(lockedCityOpt);
            citySelect.disabled = false;

            if (LOCKED_CITY_ID && !isAdminType()) {
                loadBarangays(LOCKED_CITY_ID);
            }
        });
    }

    if (userTypeSelect) {
        userTypeSelect.addEventListener('change', function () {
            safeInit('updateLocationFieldsVisibility (user type change)', updateLocationFieldsVisibility);
        });
    }
    safeInit('updateLocationFieldsVisibility (initial)', updateLocationFieldsVisibility);

    /* ============================================================
       ASSIGNMENT LOCATION PIN — no manual entry anywhere on this
       form. The pin is auto-geocoded (Nominatim, same pattern as the
       Location Preview map on the Create Account page) straight from
       whatever is currently selected above:
         - Administrator -> pinned at CITY level (an admin's coverage
           is the whole city, never a single barangay).
         - Technician / Farmer -> pinned at BARANGAY level.
       There is no separate readout box for this anymore — the result
       is shown live as the amber preview marker on the Area Map
       Overview to the right, exactly where the assigned user's pin
       is about to move to. Resolved lat/lng are written straight into
       the hidden latitude/longitude inputs and submitted with the
       form; the backend (AdminAssignmentController@store) uses them
       to update the assigned user's own account location, which is
       what makes their pin move/update perfectly next time this page
       (or their own "My Assignment" page) is loaded — no manual
       location step required, same as registration already does via
       create_admin_technician_farmer.blade.php.

       (latInput/lngInput/previewMarker/geocodeTimer/geocodeSeq are
       declared earlier, right after barangaySelect above — see the note
       there for why.)
    ============================================================ */
    function placePreviewPin(lat, lng, label) {
        latInput.value = lat;
        lngInput.value = lng;

        // assignmentMap is created later in this same script (MAP
        // section below) — by the time this runs (always after an async
        // fetch resolves, or a debounce timer fires) it already exists.
        if (typeof assignmentMap === 'undefined' || !assignmentMap) return;

        if (previewMarker) {
            previewMarker.setLatLng([lat, lng]);
        } else {
            previewMarker = L.circleMarker([lat, lng], {
                radius: 10, color: '#f59e0b', weight: 3, fillColor: '#f59e0b', fillOpacity: .25,
                dashArray: '4,3'
            }).addTo(assignmentMap);
        }
        previewMarker.bindTooltip(`New assignment location — ${label}`, { permanent: false });
        assignmentMap.invalidateSize();
        assignmentMap.flyTo([lat, lng], Math.max(assignmentMap.getZoom(), 13), { duration: .8 });
    }

    function clearPreviewPin() {
        latInput.value = '';
        lngInput.value = '';
        if (previewMarker && typeof assignmentMap !== 'undefined' && assignmentMap) {
            assignmentMap.removeLayer(previewMarker);
            previewMarker = null;
        }
    }

    // Cache of the last resolved CITY bounding box, keyed by
    // "provinceId|cityId" — see ensureAssignmentCityViewbox() below.
    let amCityViewboxCache = { key: null, viewbox: null };

    function buildAssignmentGeocodeQuery() {
        const provinceText = provinceSelect.selectedOptions[0] ? provinceSelect.selectedOptions[0].textContent.trim() : '';
        const cityText = citySelect.selectedOptions[0] ? citySelect.selectedOptions[0].textContent.trim() : '';
        const barangayText = barangaySelect.selectedOptions[0] ? barangaySelect.selectedOptions[0].textContent.trim() : '';

        if (!provinceText || !citySelect.value) return null;

        const cityQuery = `${cityText}, ${provinceText}, Philippines`;

        // Administrator -> city-level pin, same as create_admin_technician_farmer.blade.php.
        if (isAdminType()) {
            return { cityQuery, query: cityQuery, label: `${cityText}, ${provinceText}`, isAdmin: true };
        }

        // Technician / Farmer -> barangay-level pin.
        if (!barangaySelect.value) return null;
        return {
            cityQuery,
            query: `Barangay ${barangayText}, ${cityText}, ${provinceText}, Philippines`,
            label: `${barangayText}, ${cityText}`,
            isAdmin: false,
        };
    }

    function scheduleAssignmentGeocode() {
        clearTimeout(geocodeTimer);
        const built = buildAssignmentGeocodeQuery();

        if (!built) {
            clearPreviewPin();
            return;
        }

        const mySeq = ++geocodeSeq;
        geocodeTimer = setTimeout(function () { runAssignmentGeocode(built, mySeq); }, 600);
    }

    // Same fix as the Create Account page: without a timeout, an
    // unreachable/hanging Nominatim request just leaves this fetch()
    // pending forever, silently leaving the preview pin (and the
    // latitude/longitude hidden inputs actually submitted with the form)
    // empty with no feedback at all.
    const AM_GEOCODE_TIMEOUT_MS = 7000;

    function amNominatimUrl(query, viewbox) {
        let url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=${encodeURIComponent(query)}`;
        if (viewbox) url += `&bounded=1&viewbox=${viewbox}`;
        return url;
    }

    function amFetchJson(url) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), AM_GEOCODE_TIMEOUT_MS);

        return fetch(url, { headers: { 'Accept': 'application/json' }, signal: controller.signal })
            .then(res => { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .finally(() => clearTimeout(timeoutId));
    }

    // Nominatim boundingbox is [southLat, northLat, westLon, eastLon]
    // (strings); its viewbox param wants "westLon,northLat,eastLon,southLat".
    function amBboxToViewbox(bbox) {
        return `${bbox[2]},${bbox[1]},${bbox[3]},${bbox[0]}`;
    }

    // Resolves (and caches) the bounding box of the currently selected
    // City + Province, so the barangay search below can be constrained to
    // fall INSIDE it. Plenty of barangay names repeat across different
    // cities/provinces nationwide (e.g. several "Poblacion" or "San
    // Isidro" barangays) — an unconstrained barangay-only search can
    // return a real place, just the wrong municipality. Bounding to the
    // already-confirmed city fixes that, exactly like the Create Account
    // page's location map.
    function ensureAssignmentCityViewbox(built) {
        const key = `${provinceSelect.value}|${citySelect.value}`;
        if (amCityViewboxCache.key === key && amCityViewboxCache.viewbox) {
            return Promise.resolve(amCityViewboxCache.viewbox);
        }

        return amFetchJson(amNominatimUrl(built.cityQuery)).then(results => {
            if (!results || !results.length || !results[0].boundingbox) return null;
            const viewbox = amBboxToViewbox(results[0].boundingbox);
            amCityViewboxCache = { key, viewbox };
            return viewbox;
        });
    }

    function runAssignmentGeocode(built, mySeq) {
        const handleResult = (results) => {
            if (mySeq !== geocodeSeq) return; // superseded by a newer selection

            if (!results || !results.length) {
                clearPreviewPin();
                return;
            }

            placePreviewPin(parseFloat(results[0].lat), parseFloat(results[0].lon), built.label);
        };

        const handleError = (err) => {
            if (mySeq !== geocodeSeq) return;
            console.error('[Assignment] Geocoding failed:', err && err.name === 'AbortError' ? 'timed out' : err);
            clearPreviewPin();
        };

        if (built.isAdmin) {
            // Admin: the city-level result IS the target pin — one lookup
            // is enough, but still cache its box for if the user type gets
            // switched to Technician/Farmer afterwards.
            amFetchJson(amNominatimUrl(built.cityQuery))
                .then(results => {
                    if (results && results.length && results[0].boundingbox) {
                        amCityViewboxCache = {
                            key: `${provinceSelect.value}|${citySelect.value}`,
                            viewbox: amBboxToViewbox(results[0].boundingbox),
                        };
                    }
                    handleResult(results);
                })
                .catch(handleError);
            return;
        }

        // Technician/Farmer: resolve the city's box first (cached per
        // city), then search the barangay bounded to it.
        ensureAssignmentCityViewbox(built)
            .then(viewbox => amFetchJson(amNominatimUrl(built.query, viewbox)))
            .then(handleResult)
            .catch(handleError);
    }

    /* ============================================================
       RESET
    ============================================================ */
    document.getElementById('am-reset-btn').addEventListener('click', function () {
        setTimeout(function () {
            userIdInput.value = '';
            userLabel.textContent = 'Select ' + (roleLabels[userTypeSelect.value] || 'User');
            citySelect.innerHTML = '<option value="" disabled selected>Select province first...</option>';
            citySelect.disabled = true;
            barangaySelect.innerHTML = '<option value="" disabled selected>Select city first...</option>';
            barangaySelect.disabled = true;
            loadUsersForRole(userTypeSelect.value);
            updateLocationFieldsVisibility();
            clearPreviewPin();
        }, 0);
    });

    /* ============================================================
       ROW EDIT — proper modal with real Start/End date inputs and a
       Status <select> (Pending / Active / Ended), submitting a real
       POST to admin.assignment.update. Replaces the old prompt()-chain,
       which offered no actual dropdown and silently coerced anything
       that wasn't literally "ended" into "active".
    ============================================================ */
    function openEditAssignmentModal(btn) {
        const id = btn.dataset.id;
        if (!id) return;

        const updateUrl = btn.dataset.updateUrl || `${LOCATIONS_URL}/admin/assignments/${id}/update`;
        const userName = btn.dataset.user || 'this assignment';

        const backdrop = document.createElement('div');
        backdrop.className = 'am-modal-backdrop';
        backdrop.innerHTML = `
            <div class="am-modal">
                <div class="am-modal-header">
                    <h6>Edit Assignment — ${userName}</h6>
                    <button type="button" class="am-modal-close" id="am-edit-modal-close">&times;</button>
                </div>
                <div class="am-modal-body">
                    <div>
                        <label class="form-label">Start Date</label>
                        <input type="date" class="form-control am-input" id="am-edit-start" value="${btn.dataset.start || ''}">
                    </div>
                    <div>
                        <label class="form-label">End Date</label>
                        <input type="date" class="form-control am-input" id="am-edit-end" value="${btn.dataset.end || ''}">
                    </div>
                    <div>
                        <label class="form-label">Status</label>
                        <select class="form-control am-input" id="am-edit-status">
                            <option value="pending">Pending</option>
                            <option value="active">Active</option>
                            <option value="ended">Ended</option>
                        </select>
                    </div>
                    <div class="am-modal-error" id="am-edit-error">Start date is required.</div>
                </div>
                <div class="am-modal-footer">
                    <button type="button" class="btn am-btn-outline" id="am-edit-cancel">Cancel</button>
                    <button type="button" class="btn am-btn-primary" id="am-edit-save">Save Changes</button>
                </div>
            </div>`;
        document.body.appendChild(backdrop);

        const statusSelect = backdrop.querySelector('#am-edit-status');
        statusSelect.value = btn.dataset.status || 'active';

        function closeModal() { backdrop.remove(); }

        backdrop.querySelector('#am-edit-modal-close').addEventListener('click', closeModal);
        backdrop.querySelector('#am-edit-cancel').addEventListener('click', closeModal);
        backdrop.addEventListener('click', function (e) { if (e.target === backdrop) closeModal(); });

        backdrop.querySelector('#am-edit-save').addEventListener('click', function () {
            const startVal = backdrop.querySelector('#am-edit-start').value;
            const endVal = backdrop.querySelector('#am-edit-end').value;
            const errorEl = backdrop.querySelector('#am-edit-error');

            if (!startVal) {
                errorEl.style.display = 'block';
                return;
            }
            if (endVal && endVal < startVal) {
                errorEl.textContent = 'End date cannot be before the start date.';
                errorEl.style.display = 'block';
                return;
            }
            errorEl.style.display = 'none';

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = updateUrl;
            form.innerHTML = `
                <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                <input type="hidden" name="start_date" value="${startVal}">
                <input type="hidden" name="end_date" value="${endVal}">
                <input type="hidden" name="status" value="${statusSelect.value}">
            `;
            document.body.appendChild(form);
            form.submit();
        });
    }

    document.querySelectorAll('.am-row-edit').forEach(function (btn) {
        btn.addEventListener('click', function () { openEditAssignmentModal(btn); });
    });

    /* ============================================================
       EXPORT — the button previously had no handler wired up at all.
       Exports the Current Assignments table (as currently rendered on
       this page) to a downloadable CSV.
    ============================================================ */
    const exportBtn = document.getElementById('am-export-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            const table = document.getElementById('am-assignments-table');
            const rows = table ? table.querySelectorAll('tbody tr') : [];

            if (!rows.length || (rows.length === 1 && rows[0].querySelector('.am-empty-row'))) {
                alert('There are no assignments to export.');
                return;
            }

            const header = ['User', 'Email', 'User Type', 'Province', 'City / Municipality', 'Barangay', 'From', 'To', 'Status'];
            const csvRows = [header];

            rows.forEach(function (row) {
                if (row.querySelector('.am-empty-row')) return;
                const cells = row.querySelectorAll('td');
                if (cells.length < 8) return;

                const userName = cells[0].querySelector('.am-user-name')?.textContent.trim() || '';
                const userEmail = cells[0].querySelector('.am-user-email')?.textContent.trim() || '';
                const userType = cells[1].textContent.trim();
                const province = cells[2].textContent.trim();
                const city = cells[3].textContent.trim();
                const barangay = cells[4].textContent.trim();
                const from = cells[5].textContent.trim();
                const to = cells[6].textContent.trim();
                const status = cells[7].textContent.trim();

                csvRows.push([userName, userEmail, userType, province, city, barangay, from, to, status]);
            });

            const csvContent = csvRows.map(function (r) {
                return r.map(function (val) {
                    const escaped = String(val).replace(/"/g, '""');
                    return /[",\n]/.test(escaped) ? `"${escaped}"` : escaped;
                }).join(',');
            }).join('\r\n');

            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `assignments-${new Date().toISOString().slice(0, 10)}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        });
    }

    /* ============================================================
       MAP — MapTiler tiles by default (when MAPTILER_API_KEY is set
       in .env), with the plain OpenStreetMap layer as an automatic
       fallback. Both are just Leaflet tile layers, so the rest of
       the page (markers, flyTo, watchdog note) works identically no
       matter which one ends up on screen.
    ============================================================ */
    const mapMarkers   = @json($mapMarkers ?? []);
    const mapCenterLat = {{ $mapCenterLat ?? 10.8986 }};
    const mapCenterLng = {{ $mapCenterLng ?? 123.4143 }};
    const maptilerKey  = (document.querySelector('meta[name="maptiler-key"]')?.content || '').trim();

    const assignmentMap = L.map('am-assignment-map', {
        center: [mapCenterLat, mapCenterLng],
        zoom: 10,
        zoomControl: false,
        attributionControl: false
    });

    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    });

    // Only two selectable styles now — "Streets" (vector streets-v2) and
    // "Hybrid" (MapTiler's satellite-imagery-plus-roads/labels style,
    // mapId "hybrid"). MapTiler's raster tiles for both are served at
    // 512px with zoomOffset -1 so they line up with Leaflet's default
    // 256px zoom levels. Plain OSM is kept as an automatic, non-selectable
    // fallback only — see switchMapStyle() below.
    const AM_LAYER_DEFS = {
        streets: { style: 'streets-v2', ext: 'png', tileSize: 512, zoomOffset: -1 },
        hybrid:  { style: 'hybrid',     ext: 'png', tileSize: 512, zoomOffset: -1 },
    };
    const AM_DEFAULT_STYLE = 'streets';

    function buildMaptilerLayer(styleKey) {
        const def = AM_LAYER_DEFS[styleKey];
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
        const note = document.getElementById('am-map-fallback-note');
        if (!note) return;
        note.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i> ' + message;
        note.style.display = 'block';
    }

    function hideFallbackNote() {
        const note = document.getElementById('am-map-fallback-note');
        if (note) note.style.display = 'none';
    }

    // Swap the map onto a given layer, watch for the first tile to load
    // within a few seconds, and fall back (or, for OSM, finally give up
    // with a visible note) if nothing ever renders.
    function activateLayer(layer, onFail) {
        if (activeTileLayer) assignmentMap.removeLayer(activeTileLayer);
        clearTimeout(watchdogTimer);
        activeTileLayer = layer;

        let tileLoaded = false;
        let handled = false; // tileerror fires once PER failed tile request, not once overall

        layer.once('tileload', function () { tileLoaded = true; hideFallbackNote(); });
        layer.on('tileerror', function () {
            // A single bad tile isn't fatal (e.g. an edge tile at the
            // extent of coverage) — only act if NOTHING has loaded yet.
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

    // Lets the admin pick whichever base map style suits what they're
    // looking at (e.g. Satellite to visually confirm a barangay's actual
    // terrain, Streets for everyday use). Every style falls back to plain
    // OSM automatically if it fails to load, so switching styles can
    // never leave the map blank.
    function switchMapStyle(styleKey) {
        if (!AM_LAYER_DEFS[styleKey] || !maptilerKey) {
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

    /* ------------------------------------------------------------
       REAL MAP LAYER CONTROL — same control cluster as the +/- zoom
       buttons, opening a small flyout with the two available styles.
       Styled and positioned like Google Maps' own map-type toggle
       instead of a plain form <select>.
    ------------------------------------------------------------ */
    const amLayerControl = document.getElementById('am-layer-control');
    const amLayerToggle   = document.getElementById('am-layer-toggle');
    const amLayerMenu     = document.getElementById('am-layer-menu');

    const savedAmStyle = localStorage.getItem('riceguard-map-style');
    const initialAmStyle = (savedAmStyle && AM_LAYER_DEFS[savedAmStyle]) ? savedAmStyle : AM_DEFAULT_STYLE;

    function amSetActiveOption(styleKey) {
        if (!amLayerMenu) return;
        amLayerMenu.querySelectorAll('.am-layer-option').forEach(function (btn) {
            btn.classList.toggle('am-layer-active', btn.dataset.style === styleKey);
        });
    }

    if (amLayerControl && amLayerToggle && amLayerMenu) {
        if (!maptilerKey) {
            // Nothing to switch between without a key — both styles need
            // MapTiler tiles, so disable the control rather than offer a
            // choice that can never actually render.
            amLayerControl.classList.add('am-layer-disabled');
            amLayerToggle.title = 'Map layers unavailable (no MapTiler key configured)';
        } else {
            amLayerToggle.addEventListener('click', function (e) {
                e.stopPropagation();
                amLayerControl.classList.toggle('am-layer-open');
            });

            amLayerMenu.querySelectorAll('.am-layer-option').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const styleKey = btn.dataset.style;
                    localStorage.setItem('riceguard-map-style', styleKey);
                    switchMapStyle(styleKey);
                    amSetActiveOption(styleKey);
                    amLayerControl.classList.remove('am-layer-open');
                });
            });

            document.addEventListener('click', function (e) {
                if (!amLayerControl.contains(e.target)) {
                    amLayerControl.classList.remove('am-layer-open');
                }
            });
        }

        amSetActiveOption(initialAmStyle);
    }

    switchMapStyle(initialAmStyle);

    let markersLayer = L.layerGroup().addTo(assignmentMap);

    // A real teardrop pin as an inline SVG, not a rotated-square CSS hack.
    // The old version used a 26x26 square rotated -45deg to fake a
    // teardrop, but iconAnchor: [13, 26] anchors to the UNROTATED box —
    // Leaflet has no idea the div is visually rotated, so the shape you
    // actually see ends up offset from the true lat/lng point. Combined
    // with the small size and dark map background, that's what made pins
    // look "missing" even though data-wise they were there. An SVG pin
    // has its point built into the artwork itself, so the anchor
    // ([half width, full height] = the tip) is exact, plus it's bigger
    // and higher-contrast so it actually reads as a map pin.
    function buildPinIcon(color) {
        const svg = `
            <svg width="34" height="44" viewBox="0 0 34 44" xmlns="http://www.w3.org/2000/svg"
                 style="display:block;filter:drop-shadow(0 3px 4px rgba(0,0,0,.6));">
                <path d="M17 0C7.6 0 0 7.6 0 17c0 11.5 14.3 24.8 16.1 26.4a1.3 1.3 0 0 0 1.8 0C19.7 41.8 34 28.5 34 17 34 7.6 26.4 0 17 0z"
                      fill="${color}" stroke="#fff" stroke-width="2"/>
                <circle cx="17" cy="17" r="6.5" fill="#fff"/>
            </svg>`;
        return L.divIcon({
            className: 'am-map-pin',
            html: svg,
            iconSize: [34, 44],
            iconAnchor: [17, 44],
            popupAnchor: [0, -40]
        });
    }

    const blueIcon = buildPinIcon('#3b82f6');
    const greenIcon = buildPinIcon('#22c55e');

    function addMarkers() {
        markersLayer.clearLayers();

        mapMarkers.forEach(function (m) {
            if (typeof m.lat !== 'number' || typeof m.lng !== 'number') return;
            const icon = m.type === 'admin' ? blueIcon : greenIcon;
            L.marker([m.lat, m.lng], { icon, riseOnHover: true })
                .addTo(markersLayer)
                .bindTooltip(m.name || '', { permanent: false });
        });
    }

    // Markers placed while the map container hasn't settled to its final
    // size yet (e.g. still inside a flex/grid layout pass) can render at
    // the wrong pixel position on the first paint. invalidateSize() first
    // forces Leaflet to re-measure the container before we drop the pins.
    assignmentMap.invalidateSize();
    addMarkers();

    /* ------------------------------------------------------------
       CURRENT ASSIGNMENTS TABLE — every username is underlined and
       clickable. Clicking flies the Area Map Overview to that user's
       pin when they have a saved latitude/longitude; if they don't
       (data-no-pin), it shows a brief "no location set" note next to
       the name instead of doing nothing.
    ------------------------------------------------------------ */
    let noPinNoteTimer = null;
    document.querySelectorAll('.am-user-name-linked').forEach(function (el) {
        el.addEventListener('click', function () {
            if (el.dataset.noPin) {
                // No coordinates to fly to — say so, briefly, right next
                // to the name, instead of silently doing nothing.
                clearTimeout(noPinNoteTimer);
                document.querySelectorAll('.am-no-pin-note').forEach(n => n.remove());

                const note = document.createElement('div');
                note.className = 'am-no-pin-note';
                note.textContent = 'No saved location for this user';
                el.insertAdjacentElement('afterend', note);

                noPinNoteTimer = setTimeout(function () { note.remove(); }, 2200);
                return;
            }

            const lat = parseFloat(el.dataset.lat);
            const lng = parseFloat(el.dataset.lng);
            if (isNaN(lat) || isNaN(lng)) return;

            // Scroll the map into view first (useful on mobile / narrow
            // screens where the table is far below the map), then fly to
            // the pin once it's on-screen.
            const mapPanel = document.getElementById('am-map-panel');
            if (mapPanel) mapPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });

            setTimeout(function () {
                assignmentMap.invalidateSize();
                assignmentMap.flyTo([lat, lng], 16, { duration: 1.2 });

                // Brief highlight ring so it's obvious which pin matched,
                // without needing to add/track a persistent marker ref.
                const pulse = L.circleMarker([lat, lng], {
                    radius: 20, color: '#22c55e', weight: 3, fillOpacity: 0
                }).addTo(assignmentMap);
                setTimeout(function () { assignmentMap.removeLayer(pulse); }, 1800);
            }, 300);
        });
    });

    document.getElementById('am-zoom-in').addEventListener('click', () => assignmentMap.zoomIn());
    document.getElementById('am-zoom-out').addEventListener('click', () => assignmentMap.zoomOut());

    document.getElementById('am-coverage-toggle').addEventListener('change', function () {
        // Hook point: toggle a coverage-area layer here if/when you add one.
    });

    document.getElementById('am-view-full-map').addEventListener('click', function () {
        const panel = document.getElementById('am-map-panel');
        panel.classList.toggle('am-fullscreen');
        setTimeout(() => assignmentMap.invalidateSize(), 260);
    });

    window.addEventListener('resize', function () { assignmentMap.invalidateSize(); });
    setTimeout(function () {
        assignmentMap.invalidateSize();
        addMarkers(); // re-place pins once the container's final size is confirmed
    }, 200);
})();
</script>
@endsection