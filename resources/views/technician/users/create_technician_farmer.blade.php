@extends('layouts.technician')

@section('title', 'RICEGUARD AI • Create Account')

@section('content')
<div class="page-header d-flex justify-content-between align-items-start">
    <div class="page-header-title">
        <h5 class="m-b-10">Create New Account</h5>
        <p class="text-muted mb-0">Add a new {{ implode(' / ', array_map('ucfirst', $allowedRoles)) }} account to the system</p>
    </div>
    <a href="{{ route('technician.users') }}" class="btn btn-outline-light btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back to User Accounts
    </a>
</div>

<div class="row g-4 justify-content-center cr-layout">
    {{-- ===================== LEFT: FORM ===================== --}}
    <div class="col-xl-6 col-lg-7">
        <div class="card cr-card">
            <div class="card-body p-4 p-lg-5">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger d-flex align-items-start gap-2">
                        <i class="fas fa-circle-exclamation mt-1"></i>
                        <div>
                            <strong class="d-block mb-1">
                                {{ $errors->count() === 1 ? 'Please fix the issue below' : 'Please fix the issues below' }}
                            </strong>
                            <span class="small">The fields highlighted in red need your attention.</span>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('technician.account.store') }}" id="createAccountForm">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label">Account Role</label>
                        <select name="role" id="cr-role-select" class="form-control @error('role') is-invalid @enderror" required>
                            <option value="" disabled selected>Select role...</option>
                            @foreach ($allowedRoles as $roleOption)
                                <option value="{{ $roleOption }}" {{ old('role') === $roleOption ? 'selected' : '' }}>
                                    {{ ucfirst($roleOption) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control @error('full_name') is-invalid @enderror" value="{{ old('full_name') }}" required>
                        @error('full_name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
                        @error('email')
                            <div class="invalid-feedback">
                                <i class="fas fa-circle-exclamation me-1"></i>{{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" value="{{ old('phone') }}">
                        @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- ===================== FARMER-ONLY FIELDS ===================== --}}
                    <div id="cr-farmer-fields" style="display:none;">
                        <hr class="border-secondary my-4">
                        <h6 class="text-muted mb-3">Farm Details</h6>

                        <div class="mb-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" name="dob" class="form-control @error('dob') is-invalid @enderror" value="{{ old('dob') }}">
                            @error('dob')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Farmer Category</label>
                            <input type="text" name="farmer_category" class="form-control @error('farmer_category') is-invalid @enderror" value="{{ old('farmer_category') }}" placeholder="e.g. Rice Farmer">
                            @error('farmer_category')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Farm Name</label>
                            <input type="text" name="farm_name" class="form-control @error('farm_name') is-invalid @enderror" value="{{ old('farm_name') }}">
                            @error('farm_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Farm Size (hectares)</label>
                            <input type="number" step="0.01" min="0" name="farm_size" class="form-control @error('farm_size') is-invalid @enderror" value="{{ old('farm_size') }}">
                            @error('farm_size')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Water Source</label>
                            <input type="text" name="water_source" class="form-control @error('water_source') is-invalid @enderror" value="{{ old('water_source') }}">
                            @error('water_source')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <hr class="border-secondary my-4">
                    </div>

                    {{-- For a normal technician (never for developer), Province + City
                         are locked to the acting user's own account instead of being
                         freely chosen — same fix as the admin-side Create Account page.
                         userLog() scopes the Technicians tab by exactly these two
                         columns matching the actor's own, so letting this form collect
                         a different province/city used to silently create a technician
                         account that could never show up on this user's own User
                         Accounts page. (A Farmer's own visibility here is actually
                         governed by Barangay/Assignment, not Province/City — but
                         locking these two to your own account is still correct and
                         keeps this page consistent with the admin one.) The <select>
                         is kept in the DOM (pre-filled with exactly one option, your
                         own) so it still posts province_id/city_id normally and every
                         cascading-dropdown script below keeps working unchanged — it's
                         just visually replaced with a plain read-only line. --}}
                    <div class="mb-4" id="cr-province-locked-display" style="{{ ($isDeveloperActor ?? true) ? 'display:none;' : '' }}">
                        <label class="form-label">Province</label>
                        <div class="form-control" style="background:rgba(255,255,255,.04); cursor:not-allowed;">{{ $actorProvinceName ?? '' }}</div>
                        <small class="cr-field-hint">Fixed to your own account's coverage area.</small>
                    </div>
                    <div class="mb-4" id="cr-province-group" style="{{ ($isDeveloperActor ?? true) ? '' : 'display:none;' }}">
                        <label class="form-label">Province</label>
                        <select name="province_id" id="cr-province-select" class="form-control @error('province_id') is-invalid @enderror" required>
                            <option value="" disabled selected>Select province...</option>
                        </select>
                        @error('province_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4" id="cr-city-locked-display" style="{{ ($isDeveloperActor ?? true) ? 'display:none;' : '' }}">
                        <label class="form-label">City / Municipality</label>
                        <div class="form-control" style="background:rgba(255,255,255,.04); cursor:not-allowed;">{{ $actorCityName ?? '' }}</div>
                        <small class="cr-field-hint">Fixed to your own account's coverage area.</small>
                    </div>
                    <div class="mb-4" id="cr-city-group" style="{{ ($isDeveloperActor ?? true) ? '' : 'display:none;' }}">
                        <label class="form-label">City / Municipality</label>
                        <select name="city_id" id="cr-city-select" class="form-control @error('city_id') is-invalid @enderror" disabled required>
                            <option value="" disabled selected>Select province first...</option>
                        </select>
                        @error('city_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- No Admin option exists on this technician-side form, so
                         Barangay always stays required — Technician and Farmer
                         coverage is both barangay-level. --}}
                    <div class="mb-4" id="cr-barangay-group">
                        <label class="form-label">Barangay <span class="text-danger">*</span></label>
                        <select name="barangay_id" id="cr-barangay-select" class="form-control @error('barangay_id') is-invalid @enderror" disabled required>
                            <option value="" disabled selected>Select city first...</option>
                        </select>
                        @error('barangay_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <input type="hidden" name="latitude" id="cr-latitude" value="{{ old('latitude') }}">
                    <input type="hidden" name="longitude" id="cr-longitude" value="{{ old('longitude') }}">
                    @error('latitude')
                        <div class="text-danger small mb-3 mt-n2">{{ $message }}</div>
                    @enderror
                    @error('longitude')
                        <div class="text-danger small mb-3 mt-n2">{{ $message }}</div>
                    @enderror

                    <button type="submit" class="btn btn-primary w-100 py-3">
                        Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== RIGHT: LIVE LOCATION PREVIEW ===================== --}}
    <div class="col-xl-5 col-lg-5">
        <div class="card cr-map-card cr-sticky">
            <div class="card-body p-4">
                <div class="cr-map-header">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-map-location-dot me-2"></i>Location Preview</h6>
                        <p class="text-muted small mb-0" id="cr-location-hint">
                            Select a Province, City, and Barangay to auto-pin this account.
                        </p>
                    </div>
                    <span class="cr-badge cr-badge-empty" id="cr-pin-badge">Not set</span>
                </div>

                <div id="cr-location-map" class="cr-map"></div>

                <div class="cr-map-footer">
                    <div class="cr-coords" id="cr-coords-readout">No location set yet</div>
                    <div class="cr-map-actions">
                        <button type="button" id="cr-reset-btn" class="btn btn-sm btn-outline-light" style="display:none;">
                            <i class="fas fa-rotate-left me-1"></i>Reset to auto
                        </button>
                        <button type="button" id="cr-locate-btn" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-location-crosshairs me-1"></i>My location
                        </button>
                    </div>
                </div>

                <p class="cr-map-footnote">
                    <i class="fas fa-circle-info me-1"></i>
                    The pin is set automatically from the address you choose. You can drag it or click anywhere on the map to fine-tune it manually.
                </p>
            </div>
        </div>
    </div>
</div>

<style>
    .cr-card { border-radius: 14px; }

    .cr-map-card {
        border-radius: 14px;
        border-left: 3px solid #4f8cff;
    }

    @media (min-width: 992px) {
        .cr-sticky { position: sticky; top: 20px; }
    }

    .cr-map-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 14px;
    }

    .cr-badge {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .cr-badge-auto    { background: rgba(46, 204, 113, 0.15); color: #2ecc71; }
    .cr-badge-manual  { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
    .cr-badge-loading { background: rgba(79, 140, 255, 0.15); color: #4f8cff; }
    .cr-badge-empty   { background: rgba(148, 158, 176, 0.15); color: #9198a8; }

    .cr-map {
        height: 340px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.08);
    }

    .cr-map-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }

    .cr-coords {
        font-family: 'Courier New', monospace;
        font-size: 0.82rem;
        color: #9198a8;
    }

    .cr-map-actions { display: flex; gap: 8px; }

    .cr-map-footnote {
        margin: 14px 0 0;
        font-size: 0.78rem;
        color: #9198a8;
    }
</style>

{{-- Leaflet is loaded here explicitly rather than assumed from the layout,
     since this page doesn't otherwise use a map. Browsers cache the CDN
     file, so if layouts.technician ever adds Leaflet globally too, this
     is harmless — it just resolves from cache instead of re-downloading. --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    // ==========================================
    // CREATE ACCOUNT — Role toggle, Province/City/Barangay cascading
    // dropdowns, and an auto-geocoded location preview map.
    // ==========================================
    (function () {
        const CR_BASE_URL = "{{ url('/') }}";

        // A normal technician's Province + City are fixed to their own
        // account (see AdminUserController@createAccount) — only a
        // developer would get a free picker (this view never actually
        // renders for a developer, but the flag is checked the same way
        // as the admin-side page for consistency/safety).
        const IS_DEVELOPER_ACTOR   = @json($isDeveloperActor ?? true);
        const LOCKED_PROVINCE_ID   = @json($actorProvinceId ?? null);
        const LOCKED_PROVINCE_NAME = @json($actorProvinceName ?? '');
        const LOCKED_CITY_ID       = @json($actorCityId ?? null);
        const LOCKED_CITY_NAME     = @json($actorCityName ?? '');

        const roleSelect     = document.getElementById('cr-role-select');
        const farmerFields   = document.getElementById('cr-farmer-fields');
        const provinceSelect = document.getElementById('cr-province-select');
        const citySelect     = document.getElementById('cr-city-select');
        const barangaySelect = document.getElementById('cr-barangay-select');

        if (!provinceSelect || !citySelect || !barangaySelect) return;

        function toggleFarmerFields() {
            if (!roleSelect || !farmerFields) return;
            farmerFields.style.display = roleSelect.value === 'farmer' ? 'block' : 'none';
        }

        if (roleSelect) {
            roleSelect.addEventListener('change', function () {
                toggleFarmerFields();
                scheduleGeocode();
            });
            toggleFarmerFields(); // respect old('role') on validation-error reload
        }

        function loadProvinces() {
            provinceSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
            fetch(`${CR_BASE_URL}/locations/provinces`)
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
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
                    console.error('[Create Account] Failed to load provinces:', err);
                    provinceSelect.innerHTML = '<option value="" disabled selected>Failed to load provinces</option>';
                });
        }

        function loadCities(provinceId) {
            citySelect.disabled = true;
            citySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
            barangaySelect.disabled = true;
            barangaySelect.innerHTML = '<option value="" disabled selected>Select city first...</option>';

            fetch(`${CR_BASE_URL}/locations/cities/${provinceId}`)
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
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
                    console.error('[Create Account] Failed to load cities:', err);
                    citySelect.innerHTML = '<option value="" disabled selected>Failed to load cities</option>';
                });
        }

        function loadBarangays(cityId) {
            barangaySelect.disabled = true;
            barangaySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';

            fetch(`${CR_BASE_URL}/locations/barangays/${cityId}`)
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
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
                    console.error('[Create Account] Failed to load barangays:', err);
                    barangaySelect.innerHTML = '<option value="" disabled selected>Failed to load barangays</option>';
                });
        }

        provinceSelect.addEventListener('change', function () {
            loadCities(this.value);
            scheduleGeocode();
        });

        citySelect.addEventListener('change', function () {
            loadBarangays(this.value);
            scheduleGeocode();
        });

        barangaySelect.addEventListener('change', function () {
            scheduleGeocode();
        });

        if (IS_DEVELOPER_ACTOR) {
            loadProvinces();
        } else {
            // Locked mode: skip the free province/city fetch entirely.
            // Pre-fill both selects with a single option — your own
            // province/city — so they still submit correctly, then go
            // straight to loading barangays for that fixed city so
            // Barangay (the only field this role actually picks) is
            // immediately usable.
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

            if (LOCKED_CITY_ID) {
                loadBarangays(LOCKED_CITY_ID);
            }
        }

        // ==========================================
        // LOCATION PREVIEW MAP — auto-geocoded from Province + City +
        // Barangay (Technician and Farmer are both barangay-level here).
        // The pin can still be dragged, clicked, or replaced with the
        // browser's current location if auto-detection is off or fails.
        // ==========================================
        const mapEl        = document.getElementById('cr-location-map');
        const latInput      = document.getElementById('cr-latitude');
        const lngInput      = document.getElementById('cr-longitude');
        const locateBtn     = document.getElementById('cr-locate-btn');
        const resetBtn      = document.getElementById('cr-reset-btn');
        const pinBadge      = document.getElementById('cr-pin-badge');
        const coordsReadout = document.getElementById('cr-coords-readout');
        const locationHint  = document.getElementById('cr-location-hint');
        const form          = document.getElementById('createAccountForm');

        if (!mapEl || !window.L) return;

        const DEFAULT_LAT = 10.8986;
        const DEFAULT_LNG = 123.4143;

        const map = L.map('cr-location-map', { zoomControl: true, scrollWheelZoom: false })
            .setView([DEFAULT_LAT, DEFAULT_LNG], 9);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let marker = null;
        let autoLat = null;
        let autoLng = null;

        function setBadge(state) {
            if (!pinBadge) return;
            pinBadge.classList.remove('cr-badge-auto', 'cr-badge-manual', 'cr-badge-empty', 'cr-badge-loading');
            if (state === 'auto') {
                pinBadge.textContent = 'Auto-pinned';
                pinBadge.classList.add('cr-badge-auto');
            } else if (state === 'manual') {
                pinBadge.textContent = 'Manually adjusted';
                pinBadge.classList.add('cr-badge-manual');
            } else if (state === 'loading') {
                pinBadge.textContent = 'Locating…';
                pinBadge.classList.add('cr-badge-loading');
            } else {
                pinBadge.textContent = 'Not set';
                pinBadge.classList.add('cr-badge-empty');
            }
        }

        function setCoordsReadout(lat, lng) {
            if (!coordsReadout) return;
            coordsReadout.textContent = (lat === null || lng === null)
                ? 'No location set yet'
                : lat.toFixed(6) + ', ' + lng.toFixed(6);
        }

        function placePin(lat, lng, opts) {
            opts = opts || {};
            const manual = !!opts.manual;

            latInput.value = lat;
            lngInput.value = lng;

            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng], { draggable: true }).addTo(map);
                marker.on('dragend', function () {
                    const pos = marker.getLatLng();
                    placePin(pos.lat, pos.lng, { manual: true });
                });
            }

            map.setView([lat, lng], opts.zoom || Math.max(map.getZoom(), 14));
            setCoordsReadout(lat, lng);
            setBadge(manual ? 'manual' : 'auto');
            if (resetBtn) resetBtn.style.display = (manual && autoLat !== null) ? 'inline-flex' : 'none';
            if (locationHint) locationHint.classList.remove('text-danger');
        }

        map.on('click', function (e) {
            placePin(e.latlng.lat, e.latlng.lng, { manual: true });
        });

        if (locateBtn) {
            locateBtn.addEventListener('click', function () {
                if (!navigator.geolocation) {
                    alert('Geolocation is not supported by this browser. Please click on the map instead.');
                    return;
                }
                const original = locateBtn.innerHTML;
                locateBtn.disabled = true;
                locateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                navigator.geolocation.getCurrentPosition(function (pos) {
                    placePin(pos.coords.latitude, pos.coords.longitude, { manual: true, zoom: 16 });
                    locateBtn.disabled = false;
                    locateBtn.innerHTML = original;
                }, function () {
                    alert('Could not get your current location. Please click on the map instead.');
                    locateBtn.disabled = false;
                    locateBtn.innerHTML = original;
                });
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                if (autoLat !== null && autoLng !== null) {
                    placePin(autoLat, autoLng, { manual: false });
                }
            });
        }

        let geocodeTimer = null;
        let geocodeSeq = 0;

        function buildGeocodeQuery() {
            const provinceText = provinceSelect.selectedOptions[0] ? provinceSelect.selectedOptions[0].textContent.trim() : '';
            const cityText = citySelect.selectedOptions[0] ? citySelect.selectedOptions[0].textContent.trim() : '';
            const barangayText = barangaySelect.selectedOptions[0] ? barangaySelect.selectedOptions[0].textContent.trim() : '';

            if (!provinceText || !citySelect.value || !barangaySelect.value) return null;

            return { query: `Barangay ${barangayText}, ${cityText}, ${provinceText}, Philippines`, label: `${barangayText}, ${cityText}` };
        }

        function scheduleGeocode() {
            clearTimeout(geocodeTimer);
            const built = buildGeocodeQuery();

            if (!built) {
                if (locationHint) locationHint.textContent = "Select a Province, City, and Barangay to auto-pin this account's location.";
                return;
            }

            if (locationHint) locationHint.textContent = `Locating ${built.label}…`;
            setBadge('loading');

            const mySeq = ++geocodeSeq;
            geocodeTimer = setTimeout(function () { runGeocode(built, mySeq); }, 600);
        }

        function runGeocode(built, mySeq) {
            const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=${encodeURIComponent(built.query)}`;

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(results => {
                    if (mySeq !== geocodeSeq) return;

                    if (!results || !results.length) {
                        if (locationHint) locationHint.textContent = `Couldn't auto-locate ${built.label} — please click the map to set the pin manually.`;
                        setBadge('empty');
                        return;
                    }

                    autoLat = parseFloat(results[0].lat);
                    autoLng = parseFloat(results[0].lon);
                    placePin(autoLat, autoLng, { manual: false, zoom: 15 });
                    if (locationHint) locationHint.textContent = `Auto-pinned from ${built.label}. Drag the marker if it needs fine-tuning.`;
                })
                .catch(err => {
                    if (mySeq !== geocodeSeq) return;
                    console.error('[Create Account] Geocoding failed:', err);
                    if (locationHint) locationHint.textContent = `Couldn't auto-locate ${built.label} — please click the map to set the pin manually.`;
                    setBadge('empty');
                });
        }

        setTimeout(function () { map.invalidateSize(); }, 200);

        if (form) {
            form.addEventListener('submit', function (e) {
                if (!latInput.value || !lngInput.value) {
                    e.preventDefault();
                    if (locationHint) locationHint.classList.add('text-danger');
                    mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    alert("This account still needs a pinned location. Please wait for auto-locating to finish, or click the map to set it manually.");
                }
            });
        }
    })();
</script>
@endsection