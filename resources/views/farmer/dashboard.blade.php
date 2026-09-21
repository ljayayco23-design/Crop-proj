@extends('layouts.farmer')
@section('title', 'Farmer Dashboard • RICEGUARD AI')

@php
    $user = Auth::user();
    $user_id = $user->id;
    $total_detections = DB::table('user_detections')->where('user_id', $user_id)->count();
    $affected = DB::table('user_detections')->where('user_id', $user_id)->where('class_key', 'not like', '%healthy%')->count();
    $healthy = DB::table('user_detections')->where('user_id', $user_id)->where('class_key', 'like', '%healthy%')->count();
    $recent = DB::table('user_detections')->where('user_id', $user_id)->orderBy('created_at', 'desc')->limit(5)->get();

    // Field Map context extracted dynamically within view layer
    $userLat  = $user->latitude;
    $userLng  = $user->longitude;
    $farmName = $user->farm_name ?? ($user->full_name . "'s Farm");
    $farmSize = $user->farm_size;

    $otherFarms = DB::table('users')->where('role', 'farmer')
        ->where('status', 'approved')
        ->where('id', '!=', $user_id)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->select('farm_name', 'farm_size', 'latitude', 'longitude')
        ->get();

    // Logged-in farmer's own Province / City / Barangay, joined for display
    $userLocation = DB::table('barangays')
        ->join('cities', 'barangays.city_id', '=', 'cities.id')
        ->join('provinces', 'cities.province_id', '=', 'provinces.id')
        ->where('barangays.id', $user->barangay_id)
        ->select(
            'provinces.id as province_id', 'provinces.name as province_name',
            'cities.id as city_id', 'cities.name as city_name',
            'barangays.id as barangay_id', 'barangays.name as barangay_name'
        )
        ->first();

    $userAddressDisplay = $userLocation
        ? "{$userLocation->barangay_name}, {$userLocation->city_name}, {$userLocation->province_name}"
        : 'Not yet set';
@endphp

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .dashboard-map-wrapper { position: relative; height: 100%; min-height: 380px; width: 100%; border-radius: 8px; overflow: hidden; background-color: #0e1116; }
    #dashboard-map { height: 100% !important; width: 100% !important; position: absolute; top:0; left:0;}
    .custom-clean-tooltip { background: #161b22 !important; border: 1px solid #30363d !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important; border-radius: 8px !important; opacity: 1 !important; }
    .custom-clean-tooltip::before { border-top-color: #30363d !important; }
    .farm-pin-icon i { color: #10b981; font-size: 32px; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.8)); }
    .other-farm-pin i { color: #38bdf8; font-size: 26px; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.8)); }
    .section-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; display: flex; flex-direction: column; }
    .scrollable-list { max-height: 320px; overflow-y: auto; }
    .scrollable-list::-webkit-scrollbar { width: 4px; }
    .scrollable-list::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
</style>

<div class="page-header mb-4">
    <h4 class="fw-bold text-white mb-1">🌾 RICEGUARD AI Dashboard</h4>
    <p class="text-secondary mb-0">Welcome back, {{ $user->full_name }} • Real-time farm overview</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-camera-retro fa-2x text-primary"></i>
                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-2 py-1">Season</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Total Detections</h6>
            <h2 class="fw-bold text-white mb-0">{{ number_format($total_detections) }}</h2>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-bug fa-2x text-danger"></i>
                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1">Attention</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Affected Plants</h6>
            <h2 class="fw-bold text-danger mb-0">{{ number_format($affected) }}</h2>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-seedling fa-2x text-success"></i>
                <span class="badge bg-success bg-opacity-25 text-success border border-success px-2 py-1">Good</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Healthy Plants</h6>
            <h2 class="fw-bold text-success mb-0">{{ number_format($healthy) }}</h2>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-history fa-2x text-warning"></i>
                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning px-2 py-1">Active</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">History Logs</h6>
            <h2 class="fw-bold text-white mb-0">{{ number_format($total_detections) }}</h2>
        </div>
    </div>
</div>

<div class="row g-4 mb-4 align-items-stretch">
    <div class="col-xl-3 col-lg-4">
        <div class="section-card h-100 p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-cloud-sun text-info me-2"></i> Area Climate</h6>
            </div>
            <div class="p-4 text-center flex-grow-1 d-flex flex-column justify-content-center">
                <div class="display-3 fw-bold text-info mb-2" id="weather-temp-dash">
                    <i class="fas fa-spinner fa-spin fs-4 text-secondary"></i>
                </div>
                <h5 class="text-secondary mb-4" id="weather-cond-dash">Loading...</h5>
                <div class="d-flex justify-content-between px-2 bg-dark bg-opacity-50 p-3 rounded-3 border border-secondary">
                    <div class="text-white small" id="weather-hum-dash"><i class="fa-solid fa-droplet text-info me-1"></i> --% Hum</div>
                    <div class="text-white small" id="weather-wind-dash"><i class="fa-solid fa-wind text-info me-1"></i> -- km/h</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5 col-lg-8">
        <div class="section-card h-100 p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-map-location-dot text-success me-2"></i> Field Intelligence</h6>
                <select id="dashboard-map-style" onchange="switchDashboardMapStyle(this.value)" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: 130px; font-size: 0.75rem;">
                    <option value="hybrid">Satellite</option>
                    <option value="streets">Streets</option>
                    <option value="topo">Topo Map</option>
                    <option value="dark">Dark Mode</option>
                </select>
            </div>
            <div class="p-2 bg-dark bg-opacity-25 flex-grow-1">
                <div class="dashboard-map-wrapper shadow">
                    <div id="dashboard-map"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-12">
        <div class="section-card h-100 p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-clock-rotate-left text-success me-2"></i> Recent Scans</h6>
                <a href="{{ route('farmer.history') }}" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 0.75rem;">View All</a>
            </div>
            <div class="p-0 scrollable-list flex-grow-1">
                @if($recent->isEmpty())
                    <div class="text-center py-5">
                        <i class="fa-solid fa-seedling fa-3x text-secondary mb-3 opacity-50"></i>
                        <p class="text-secondary small mb-2">No detections recorded yet.</p>
                        <a href="{{ route('farmer.detection') }}" class="btn btn-sm btn-success mt-2">Upload Image</a>
                    </div>
                @else
                    <div class="list-group list-group-flush bg-transparent">
                        @foreach($recent as $item)
                        <div class="list-group-item bg-transparent border-secondary py-3 px-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fa-solid fa-leaf {{ str_contains($item->class_key, 'healthy') ? 'text-success' : 'text-danger' }} fa-lg"></i>
                                <div>
                                    <strong class="text-white text-capitalize" style="font-size: 0.9rem;">{{ str_replace('_', ' ', $item->class_key) }}</strong>
                                    <small class="d-block text-secondary" style="font-size: 0.75rem;">{{ \Carbon\Carbon::parse($item->created_at)->format('M d, Y • h:i A') }}</small>
                                </div>
                            </div>
                            <span class="badge {{ str_contains($item->class_key, 'healthy') ? 'bg-success' : 'bg-danger' }} px-2 py-1" style="font-size: 0.7rem;">{{ $item->confidence }}%</span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="section-card p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-location-dot text-success me-2"></i> Farm Address</h6>
                <button type="button" id="address-edit-btn" class="btn btn-sm btn-outline-success py-0 px-2" style="font-size: 0.75rem;" onclick="toggleAddressEdit(true)">
                    <i class="fa-solid fa-pen me-1"></i> Edit
                </button>
            </div>
            <div class="p-4">
                <div id="address-display-view">
                    <p class="text-white mb-0 fs-6" id="address-display-text">{{ $userAddressDisplay }}</p>
                </div>
                <div id="address-edit-view" class="d-none">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label text-secondary small mb-1">Province</label>
                            <select id="profile-province-select" class="form-select form-select-sm bg-dark text-white border-secondary">
                                <option value="" disabled>Select province...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small mb-1">City / Municipality</label>
                            <select id="profile-city-select" class="form-select form-select-sm bg-dark text-white border-secondary" disabled>
                                <option value="" disabled>Select province first...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-secondary small mb-1">Barangay</label>
                            <select id="profile-barangay-select" class="form-select form-select-sm bg-dark text-white border-secondary" disabled>
                                <option value="" disabled>Select city first...</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-success" id="address-save-btn" onclick="saveAddress()">
                            <i class="fa-solid fa-check me-1"></i> Save
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light border-secondary" onclick="toggleAddressEdit(false)">Cancel</button>
                    </div>
                    <div id="address-edit-error" class="text-danger small mt-2 d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="section-card p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3"><h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-bolt text-warning me-2"></i> Quick Actions</h6></div>
            <div class="p-4 row g-3">
                <div class="col-md-4"><a href="{{ route('farmer.detection') }}" class="btn btn-success w-100 py-3 fw-bold shadow-sm"><i class="fa-solid fa-upload fa-lg d-block mb-2"></i> Upload Image</a></div>
                <div class="col-md-4"><a href="{{ route('farmer.camera') }}" class="btn btn-outline-light border-secondary w-100 py-3 fw-bold shadow-sm"><i class="fa-solid fa-camera fa-lg d-block mb-2"></i> Live Camera</a></div>
                <div class="col-md-4"><a href="{{ route('farmer.history') }}" class="btn btn-outline-light border-secondary w-100 py-3 fw-bold shadow-sm"><i class="fa-solid fa-history fa-lg d-block mb-2"></i> View History</a></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const MAPTILER_KEY = '{{ env("MAPTILER_API_KEY") }}';

// 1. Initialize Map Styles
const mapTiles = {
    "hybrid": L.tileLayer(`https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "streets": L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "topo": L.tileLayer(`https://api.maptiler.com/maps/topo-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "dark": L.tileLayer(`https://api.maptiler.com/maps/dataviz-dark/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true })
};

let currentTile = mapTiles["hybrid"];
const farmLat = {{ $userLat ?? 10.8986 }};
const farmLng = {{ $userLng ?? 123.4143 }};

const dashMap = L.map('dashboard-map', {
    center: [farmLat, farmLng],
    zoom: 15,
    layers: [currentTile],
    attributionControl: false
});

function switchDashboardMapStyle(styleKey) {
    if (mapTiles[styleKey]) {
        dashMap.removeLayer(currentTile);
        currentTile = mapTiles[styleKey];
        dashMap.addLayer(currentTile);
    }
}

// 2. Reverse Geocoding for Map Tooltips
function attachAddressTooltip(marker, lat, lng, farmNameStr, farmSizeStr, colorHex) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(res => res.json())
        .then(data => {
            const exactLocation = (data && data.display_name) ? data.display_name : "Address not registered";
            marker.bindTooltip(`
                <div style="text-align:left; max-width: 240px; font-family: system-ui, sans-serif; padding: 4px;">
                    <strong style="color: ${colorHex}; font-size: 13px; display:block; margin-bottom:2px;"><i class="fa-solid fa-tractor me-1"></i> ${farmNameStr}</strong>
                    <span style="font-size: 11px; color: #fff; display:block; margin-bottom:4px;">📐 Area: <b>${farmSizeStr} ha</b></span>
                    <div style="border-top: 1px solid #444; padding-top: 4px; font-size: 10px; color: #bbb; line-height: 1.3;">
                        <i class="fa-solid fa-map-pin me-1" style="color: #ef4444;"></i> ${exactLocation}
                    </div>
                </div>
            `, { direction: 'top', className: 'custom-clean-tooltip' });
        })
        .catch(() => {
            marker.bindTooltip(`<strong>${farmNameStr}</strong><br>Size: ${farmSizeStr} ha`, { direction: 'top', className: 'custom-clean-tooltip' });
        });
}

// 3. Plot Pins
if ({{ $userLat ? 'true' : 'false' }}) {
    const mainFarmIcon = L.divIcon({
        className: 'farm-pin-icon',
        html: '<i class="fa-solid fa-location-dot"></i>',
        iconSize: [32, 32], iconAnchor: [16, 32]
    });
    const userMarker = L.marker([farmLat, farmLng], { icon: mainFarmIcon }).addTo(dashMap);
    attachAddressTooltip(userMarker, farmLat, farmLng, "{{ $farmName }}", "{{ $farmSize ?? 'N/A' }}", "#10b981");
}

const otherFarmsData = @json($otherFarms ?? []);
otherFarmsData.forEach(farm => {
    if (farm.latitude && farm.longitude) {
        const oLat = parseFloat(farm.latitude);
        const oLng = parseFloat(farm.longitude);
        const otherIcon = L.divIcon({
            className: 'other-farm-pin',
            html: '<i class="fa-solid fa-location-dot"></i>',
            iconSize: [26, 26], iconAnchor: [13, 26]
        });
        const otherMarker = L.marker([oLat, oLng], { icon: otherIcon }).addTo(dashMap);
        attachAddressTooltip(otherMarker, oLat, oLng, farm.farm_name || "Neighboring Farm", farm.farm_size || "N/A", "#38bdf8");
    }
});

// 4. Fetch Dynamic Weather
fetch(`{{ route('farmer.field_map.weather') }}?lat=${farmLat}&lon=${farmLng}`)
    .then(res => res.json())
    .then(data => {
        if(data.error) throw new Error(data.error);
        document.getElementById('weather-temp-dash').innerHTML = `${data.temp}°C`;
        document.getElementById('weather-cond-dash').innerText = data.condition;
        document.getElementById('weather-hum-dash').innerHTML = `<i class="fa-solid fa-droplet text-info me-1"></i> ${data.humidity}% Hum`;
        document.getElementById('weather-wind-dash').innerHTML = `<i class="fa-solid fa-wind text-info me-1"></i> ${data.wind} km/h`;
    })
    .catch(err => {
        document.getElementById('weather-temp-dash').innerHTML = '--°C';
        document.getElementById('weather-cond-dash').innerText = 'Weather offline';
    });

setTimeout(() => { dashMap.invalidateSize(); }, 400);
</script>

<script>
// ==========================================
// FARM ADDRESS — inline Province/City/Barangay editor
// (Same cascading pattern used on the registration form)
// ==========================================
const ADDR_BASE_URL = "{{ url('/') }}";
const currentAddress = {
    province_id: @json($userLocation->province_id ?? null),
    city_id: @json($userLocation->city_id ?? null),
    barangay_id: @json($userLocation->barangay_id ?? null)
};

const addrProvinceSelect  = document.getElementById('profile-province-select');
const addrCitySelect      = document.getElementById('profile-city-select');
const addrBarangaySelect  = document.getElementById('profile-barangay-select');

function toggleAddressEdit(show) {
    document.getElementById('address-display-view').classList.toggle('d-none', show);
    document.getElementById('address-edit-view').classList.toggle('d-none', !show);
    document.getElementById('address-edit-btn').classList.toggle('d-none', show);
    document.getElementById('address-edit-error').classList.add('d-none');
    if (show) loadAddrProvinces();
}

function loadAddrProvinces() {
    addrProvinceSelect.innerHTML = '<option value="" disabled>Loading...</option>';
    fetch(`${ADDR_BASE_URL}/locations/provinces`)
        .then(res => res.json())
        .then(provinces => {
            addrProvinceSelect.innerHTML = '<option value="" disabled>Select province...</option>';
            provinces.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                if (currentAddress.province_id && String(p.id) === String(currentAddress.province_id)) {
                    opt.selected = true;
                }
                addrProvinceSelect.appendChild(opt);
            });
            if (currentAddress.province_id) {
                loadAddrCities(currentAddress.province_id, currentAddress.city_id);
            }
        });
}

function loadAddrCities(provinceId, preselectCityId) {
    addrCitySelect.disabled = true;
    addrCitySelect.innerHTML = '<option value="" disabled>Loading...</option>';
    addrBarangaySelect.disabled = true;
    addrBarangaySelect.innerHTML = '<option value="" disabled>Select city first...</option>';

    fetch(`${ADDR_BASE_URL}/locations/cities/${provinceId}`)
        .then(res => res.json())
        .then(cities => {
            addrCitySelect.innerHTML = '<option value="" disabled>Select city/municipality...</option>';
            cities.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                if (preselectCityId && String(c.id) === String(preselectCityId)) {
                    opt.selected = true;
                }
                addrCitySelect.appendChild(opt);
            });
            addrCitySelect.disabled = false;
            if (preselectCityId) {
                loadAddrBarangays(preselectCityId, currentAddress.barangay_id);
            }
        });
}

function loadAddrBarangays(cityId, preselectBarangayId) {
    addrBarangaySelect.disabled = true;
    addrBarangaySelect.innerHTML = '<option value="" disabled>Loading...</option>';

    fetch(`${ADDR_BASE_URL}/locations/barangays/${cityId}`)
        .then(res => res.json())
        .then(barangays => {
            addrBarangaySelect.innerHTML = '<option value="" disabled>Select barangay...</option>';
            barangays.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.name;
                if (preselectBarangayId && String(b.id) === String(preselectBarangayId)) {
                    opt.selected = true;
                }
                addrBarangaySelect.appendChild(opt);
            });
            addrBarangaySelect.disabled = false;
        });
}

addrProvinceSelect.addEventListener('change', function () {
    loadAddrCities(this.value, null);
});

addrCitySelect.addEventListener('change', function () {
    loadAddrBarangays(this.value, null);
});

function saveAddress() {
    const errorBox = document.getElementById('address-edit-error');
    errorBox.classList.add('d-none');

    const province_id  = addrProvinceSelect.value;
    const city_id       = addrCitySelect.value;
    const barangay_id   = addrBarangaySelect.value;

    if (!province_id || !city_id || !barangay_id) {
        errorBox.textContent = 'Please complete Province, City, and Barangay.';
        errorBox.classList.remove('d-none');
        return;
    }

    const saveBtn = document.getElementById('address-save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Saving...';

    fetch(`{{ route('farmer.profile.address') }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ province_id, city_id, barangay_id })
    })
    .then(res => res.json().then(data => ({ status: res.status, body: data })))
    .then(({ status, body }) => {
        if (status === 200 && body.success) {
            document.getElementById('address-display-text').textContent = body.address;
            currentAddress.province_id = province_id;
            currentAddress.city_id = city_id;
            currentAddress.barangay_id = barangay_id;
            toggleAddressEdit(false);
        } else {
            errorBox.textContent = body.message || 'Failed to save address. Please try again.';
            errorBox.classList.remove('d-none');
        }
    })
    .catch(() => {
        errorBox.textContent = 'Something went wrong. Please check your connection and try again.';
        errorBox.classList.remove('d-none');
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i> Save';
    });
}
</script>



<script>
// 1. Initialize System Readiness Checker based on current connection
window.isSystemReady = navigator.onLine ? false : true;


// 3. Reusable function to handle caching safely
function syncOfflineData() {
    if (!('caches' in window)) {
        window.isSystemReady = true;
        return;
    }

    // Instantly lock the system while syncing
    window.isSystemReady = false; 
    console.log("🔄 Starting offline data sync...");

    const offlineResources = [
        // HTML Pages
        "{{ route('farmer.dashboard') }}",
        "{{ route('farmer.announcement') }}",
        "{{ route('farmer.camera') }}",
        "{{ route('farmer.detection') }}",
        "{{ route('farmer.history') }}",
        "{{ route('farmer.live_com') }}",
        "{{ route('farmer.field_map') }}",
        
        // AI Models (Crucial for offline fallback detection)
        "{{ asset('model/model.json') }}",
        "{{ asset('model/metadata.json') }}",
        "{{ asset('model/weights.bin') }}",
        
        // External Libraries (TensorFlow & Teachable Machine)
        "https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.21.0/dist/tf.min.js",
        "https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.4/dist/teachablemachine-image.min.js",
        "https://unpkg.com/leaflet@1.9.4/dist/leaflet.css",
        "https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    ];

    caches.open('cropsense-offline-v4').then(cache => {
        const fetchPromises = offlineResources.map(resource => {
            return fetch(resource)
                .then(response => {
                    if (response.ok) {
                        return cache.put(resource, response);
                    }
                })
                .catch(err => console.warn('[SW Warmup] Failed:', resource));
        });

        // Wait for ALL background caching to complete
        Promise.all(fetchPromises).then(() => {
            window.isSystemReady = true; // Unlock the system
            console.log("✅ Offline data synced. System is ready.");
        });
    });
}

// 4. Trigger on Page Load
window.addEventListener('load', () => {
    if (navigator.onLine) {
        syncOfflineData();
    }
});

// 5. Trigger REAL-TIME when the user goes ONLINE
window.addEventListener('online', () => {
    console.log("🌐 Connection restored! Re-syncing data...");
    syncOfflineData(); // This instantly locks logout and runs the sync
});

// 6. Trigger REAL-TIME when the user goes OFFLINE
window.addEventListener('offline', () => {
    console.log("⚠️ Connection lost. Switching to offline mode.");
    window.isSystemReady = true; // Instantly unlock so offline features (like offline logout) work normally
});
</script>

@endsection