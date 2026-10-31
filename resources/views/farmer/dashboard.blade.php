@extends('layouts.farmer')
@section('title', 'Farmer Dashboard • RICEGUARD AI')

@php
    $user = Auth::user();
    $user_id = $user->id;
    // ONE query for the card numbers so Total = Healthy + Affected always adds up.
    // (Every row in user_detections is one saved scan — same thing History lists.)
    $stats = DB::table('user_detections')->where('user_id', $user_id)
        ->selectRaw("COUNT(*) as total, COALESCE(SUM(CASE WHEN class_key LIKE '%healthy%' THEN 1 ELSE 0 END), 0) as healthy")
        ->first();
    $total_detections = (int) ($stats->total ?? 0);
    $healthy  = (int) ($stats->healthy ?? 0);
    $affected = $total_detections - $healthy;
    $recent = DB::table('user_detections')->where('user_id', $user_id)->orderBy('created_at', 'desc')->limit(5)->get();

    // Same display names History uses, so "rice_gall_midg" reads "Rice Gall Midge".
    $classNames = [
        'healthy_rice_plant' => 'Healthy Rice Plant', 'bacterial_leaf_blight' => 'Bacterial Leaf Blight',
        'bacterial_leaf_streak' => 'Bacterial Leaf Streak', 'brown_spot' => 'Brown Spot',
        'downy_mildew' => 'Downy Mildew', 'leaf_blast' => 'Leaf Blast', 'rice_false_smut' => 'Rice False Smut',
        'sheath_blight' => 'Sheath Blight', 'tungro_virus' => 'Tungro Virus',
        'applesnail_eggs' => 'Apple Snail Eggs', 'brown_planthopper' => 'Brown Planthopper',
        'dead_heart' => 'Dead Heart', 'green_leafhopper' => 'Green Leafhopper', 'leaf_folders' => 'Leaf Folders',
        'leafhopper' => 'Leafhopper', 'rice_bug' => 'Rice Bug', 'rice_gall_midg' => 'Rice Gall Midge',
        'rice_hispa' => 'Rice Hispa', 'rice_leaf_roller' => 'Rice Leaf Roller', 'rice_stem_borer' => 'Rice Stem Borer',
        'rice_thrips' => 'Rice Thrips', 'rice_water_weevil' => 'Rice Water Weevil', 'whorl_maggot' => 'Whorl Maggot',
    ];
    $prettyClass = fn ($k) => $classNames[strtolower(trim((string) $k))] ?? ucwords(str_replace('_', ' ', (string) $k));

    // Field Map context extracted dynamically within view layer
    $userLat  = $user->latitude;
    $userLng  = $user->longitude;
    $farmName = $user->farm_name ?: ($user->full_name . "'s Farm");
    $farmSize = $user->farm_size;

    // additional_farms may arrive as an array (cast) or a raw JSON string.
    $toPlots = function ($v) {
        if (is_string($v)) { $v = json_decode($v, true); }
        return is_array($v) ? array_values($v) : [];
    };
    $ownExtraFarms = $toPlots($user->additional_farms ?? []);

    // Neighbouring farms — same rule as the Field Map page (approved farmers with a pin),
    // plus the owner's barangay/city/province as a fallback address line.
    $otherFarms = \App\Models\User::with(['province', 'city', 'barangay'])
        ->where('role', 'farmer')
        ->where('status', 'approved')
        ->where('id', '!=', $user_id)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->get()
        ->map(fn ($f) => [
            'farm_name'        => $f->farm_name,
            'farm_size'        => $f->farm_size,
            'latitude'         => $f->latitude,
            'longitude'        => $f->longitude,
            'address'          => collect([optional($f->barangay)->name, optional($f->city)->name, optional($f->province)->name])->filter()->implode(', '),
            'additional_farms' => $toPlots($f->additional_farms ?? []),
        ])->values();

    // "Reports" card: this farmer's own submitted reports, same as what
    // they'd see on their own Reports page (FarmerReportController@farmerIndex).
    $farmReportsCount = \App\Models\FarmerReport::where('user_id', $user_id)->count();
@endphp

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .dashboard-map-wrapper { position: relative; height: 100%; min-height: 380px; width: 100%; border-radius: 8px; overflow: hidden; background-color: #0e1116; }
    #dashboard-map { height: 100% !important; width: 100% !important; position: absolute; top:0; left:0;}

    /* ---- Pins: glyph is centred in its box so the TIP sits exactly on the coordinate ---- */
    .pin-icon { background: transparent !important; border: 0 !important; display: flex; align-items: flex-end; justify-content: center; }
    .pin-icon i { display: block; line-height: 1; filter: drop-shadow(0px 3px 4px rgba(0,0,0,0.8)); }
    .pin-own i   { color: #10b981; }
    .pin-other i { color: #38bdf8; }

    /* ---- Tooltip: fixed width + wrapping, so long addresses stay INSIDE the border ---- */
    .leaflet-tooltip.custom-clean-tooltip { background: #161b22 !important; border: 1px solid #30363d !important; color: #fff !important; box-shadow: 0 10px 15px -3px rgba(0,0,0,.5) !important; border-radius: 8px !important; opacity: 1 !important; white-space: normal !important; width: 230px; max-width: 230px; padding: 8px 10px; box-sizing: border-box; }
    .leaflet-tooltip-top.custom-clean-tooltip::before { border-top-color: #30363d !important; }
    .custom-clean-tooltip .tt-name { display: block; font-size: 13px; font-weight: 700; margin-bottom: 2px; overflow-wrap: anywhere; line-height: 1.25; }
    .custom-clean-tooltip .tt-size { display: block; font-size: 11px; color: #fff; margin-bottom: 5px; }
    .custom-clean-tooltip .tt-addr { display: flex; gap: 6px; align-items: flex-start; border-top: 1px solid #444; padding-top: 5px; font-size: 10px; color: #bbb; line-height: 1.35; }
    .custom-clean-tooltip .tt-addr i { color: #ef4444; flex: 0 0 auto; margin-top: 2px; }
    .custom-clean-tooltip .tt-addr span { min-width: 0; overflow-wrap: anywhere; word-break: break-word; }

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
    <div class="col-xl-4 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-camera-retro fa-2x text-primary"></i>
                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-2 py-1">All time</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Total Detections</h6>
            <h2 class="fw-bold text-white mb-0">{{ number_format($total_detections) }}</h2>
        </div>
    </div>
    <div class="col-xl-4 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-bug fa-2x text-danger"></i>
                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1">Attention</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Affected Scans</h6>
            <h2 class="fw-bold text-danger mb-0">{{ number_format($affected) }}</h2>
        </div>
    </div>
    <div class="col-xl-4 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-seedling fa-2x text-success"></i>
                <span class="badge bg-success bg-opacity-25 text-success border border-success px-2 py-1">Good</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Healthy Scans</h6>
            <h2 class="fw-bold text-success mb-0">{{ number_format($healthy) }}</h2>
        </div>
    </div>
    <div class="col-xl-4 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-history fa-2x text-warning"></i>
                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning px-2 py-1">Active</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">History Logs</h6>
            <h2 class="fw-bold text-white mb-0">{{ number_format($total_detections) }}</h2>
        </div>
    </div>
    <div class="col-xl-4 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-flag fa-2x" style="color:#ec4899;"></i>
                <span class="badge px-2 py-1" style="background: rgba(236,72,153,0.15); color:#ec4899; border:1px solid #ec4899;">Reports</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">My Reports</h6>
            <h2 class="fw-bold text-white mb-0">{{ number_format($farmReportsCount) }}</h2>
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
                                    <strong class="text-white " style="font-size: 0.9rem;">{{ $prettyClass($item->class_key) }}</strong>
                                    <small class="d-block text-secondary" style="font-size: 0.75rem;">{{ \Carbon\Carbon::parse($item->created_at)->format('M d, Y • h:i A') }}</small>
                                </div>
                            </div>
                            <span class="badge {{ str_contains($item->class_key, 'healthy') ? 'bg-success' : 'bg-danger' }} px-2 py-1" style="font-size: 0.7rem;">{{ (int) round((float) $item->confidence) }}%</span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="section-card p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3"><h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-bolt text-warning me-2"></i> Quick Actions</h6></div>
            <div class="p-4 row g-3">
                {{-- Every button of the sidebar (Dashboard itself excluded) + Live Camera. --}}
                @php
                    $quickActions = [
                        ['farmer.detection',    'fa-upload',            'Upload Detection',     'btn-success'],
                        ['farmer.camera',       'fa-camera',            'Live Camera',          'btn-outline-light border-secondary'],
                        ['farmer.history',      'fa-history',           'History',              'btn-outline-light border-secondary'],
                        ['farmer.announcement', 'fa-bullhorn',          'Announcements',        'btn-outline-light border-secondary'],
                        ['farmer.live_com',     'fa-message',           'Messenger',            'btn-outline-light border-secondary'],
                        ['farmer.field_map',    'fa-map-location-dot',  'Field Map & Weather',  'btn-outline-light border-secondary'],
                        ['farmer.schedule',     'fa-calendar-days',     'Schedule',             'btn-outline-light border-secondary'],
                        ['farmer.reports',      'fa-flag',              'Report Problem',       'btn-outline-light border-secondary'],
                    ];
                @endphp
                @foreach($quickActions as [$qaRoute, $qaIcon, $qaLabel, $qaClass])
                    <div class="col-6 col-md-4 col-lg-3">
                        <a href="{{ route($qaRoute) }}" class="btn {{ $qaClass }} w-100 py-3 fw-bold shadow-sm">
                            <i class="fa-solid {{ $qaIcon }} fa-lg d-block mb-2"></i> {{ $qaLabel }}
                        </a>
                    </div>
                @endforeach
                {{-- AI Assistant is not a page: it opens the floating chat (same as the sidebar button). --}}
                <div class="col-6 col-md-4 col-lg-3">
                    <button type="button" onclick="document.getElementById('sidebar-chat-btn')?.click()" class="btn btn-outline-light border-secondary w-100 py-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-comment-dots fa-lg d-block mb-2"></i> AI Assistant
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const MAPTILER_KEY = '{{ env("MAPTILER_API_KEY") }}';

// 1. Map styles
const mapTiles = {
    "hybrid":  L.tileLayer(`https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "streets": L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "topo":    L.tileLayer(`https://api.maptiler.com/maps/topo-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "dark":    L.tileLayer(`https://api.maptiler.com/maps/dataviz-dark/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true })
};
let currentTile = mapTiles["hybrid"];

const hasMainPin = @json($userLat !== null && $userLng !== null);
const farmLat = @json((float) ($userLat ?? 10.8986));
const farmLng = @json((float) ($userLng ?? 123.4143));

const dashMap = L.map('dashboard-map', { center: [farmLat, farmLng], zoom: 15, layers: [currentTile], attributionControl: false });

function switchDashboardMapStyle(styleKey) {
    if (mapTiles[styleKey]) {
        dashMap.removeLayer(currentTile);
        currentTile = mapTiles[styleKey];
        dashMap.addLayer(currentTile);
    }
}

// 2. Helpers
// Farm/field names are typed by users — always escape before putting them in tooltip HTML.
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
const fmtSize = v => { const n = parseFloat(v); return (isFinite(n) && n > 0) ? `${n} ha` : 'Not set'; };

// Reverse geocoding runs only when a pin is first hovered, one request per second
// (Nominatim's limit), and each result is kept — no burst of requests on page load.
let geoQueue = Promise.resolve();
function reverseGeocode(lat, lng) {
    const job = geoQueue.then(() =>
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&accept-language=en&lat=${lat}&lon=${lng}`)
            .then(r => { if (!r.ok) throw new Error('geocode ' + r.status); return r.json(); })
    );
    geoQueue = job.catch(() => {}).then(() => new Promise(res => setTimeout(res, 1000)));
    return job;
}

function pinIcon(kind, size) {
    return L.divIcon({
        className: `pin-icon pin-${kind}`,
        html: `<i class="fa-solid fa-location-dot" style="font-size:${size}px"></i>`,
        iconSize: [size, size],
        iconAnchor: [size / 2, size],        // tip of the pin = the coordinate
        tooltipAnchor: [0, -(size - 2)]      // tooltip opens just above the pin head
    });
}

function tooltipHtml(name, sizeText, color, address) {
    return `<span class="tt-name" style="color:${color}"><i class="fa-solid fa-tractor me-1"></i>${esc(name)}</span>
            <span class="tt-size">📐 Area: <b>${esc(sizeText)}</b></span>
            <div class="tt-addr"><i class="fa-solid fa-map-pin"></i><span>${esc(address)}</span></div>`;
}

function addFarmPin(lat, lng, o) {
    const marker = L.marker([lat, lng], { icon: pinIcon(o.kind, o.size) }).addTo(dashMap);
    const color = o.kind === 'own' ? '#10b981' : '#38bdf8';
    const sizeText = fmtSize(o.farmSize);
    let address = o.address || 'Locating address…';
    marker.bindTooltip(tooltipHtml(o.name, sizeText, color, address),
        { direction: 'top', className: 'custom-clean-tooltip', opacity: 1 });

    let looked = false;
    marker.on('tooltipopen', () => {
        if (looked) return;
        looked = true;
        reverseGeocode(lat, lng)
            .then(d => { address = (d && d.display_name) ? d.display_name : (o.address || 'Address not registered'); })
            .catch(() => { address = o.address || 'Address unavailable'; })
            .finally(() => marker.setTooltipContent(tooltipHtml(o.name, sizeText, color, address)));
    });
    return marker;
}

// users.additional_farms entries: coords = [lng, lat] (GeoJSON order), options.farmName / farmSize.
function plotsToPins(plots, fallbackName) {
    const out = [];
    (Array.isArray(plots) ? plots : []).forEach((plot, idx) => {
        const coords = plot.coords || (plot.options && plot.options.coords);
        if (!Array.isArray(coords) || coords.length < 2) return;
        const lng = parseFloat(coords[0]), lat = parseFloat(coords[1]);
        if (isNaN(lat) || isNaN(lng)) return;
        const opts = plot.options || {};
        out.push({ lat, lng, name: opts.farmName || plot.placeName || fallbackName(idx), farmSize: opts.farmSize });
    });
    return out;
}

// 3. Plot pins
const ownPoints = [];

if (hasMainPin) {
    addFarmPin(farmLat, farmLng, { kind: 'own', size: 34, name: @json($farmName), farmSize: @json($farmSize) });
    ownPoints.push([farmLat, farmLng]);
}

// This farmer's other registered fields
plotsToPins(@json($ownExtraFarms), i => `My Field ${i + 2}`).forEach(p => {
    addFarmPin(p.lat, p.lng, { kind: 'own', size: 28, name: p.name, farmSize: p.farmSize });
    ownPoints.push([p.lat, p.lng]);
});

// Neighbouring farmers (main pin + every extra field they registered)
@json($otherFarms).forEach(farm => {
    const oLat = parseFloat(farm.latitude), oLng = parseFloat(farm.longitude);
    const baseName = farm.farm_name || 'Neighboring Farm';
    if (!isNaN(oLat) && !isNaN(oLng)) {
        addFarmPin(oLat, oLng, { kind: 'other', size: 26, name: baseName, farmSize: farm.farm_size, address: farm.address });
    }
    plotsToPins(farm.additional_farms, () => `${baseName} (extra field)`).forEach(p => {
        addFarmPin(p.lat, p.lng, { kind: 'other', size: 22, name: p.name, farmSize: p.farmSize, address: farm.address });
    });
});

// 4. Weather for the farm's coordinates
fetch(`{{ route('farmer.field_map.weather') }}?lat=${farmLat}&lon=${farmLng}`)
    .then(res => res.json())
    .then(data => {
        if (data.error) throw new Error(data.error);
        document.getElementById('weather-temp-dash').innerHTML = `${data.temp}°C`;
        document.getElementById('weather-cond-dash').innerText = data.condition;
        document.getElementById('weather-hum-dash').innerHTML = `<i class="fa-solid fa-droplet text-info me-1"></i> ${data.humidity}% Hum`;
        document.getElementById('weather-wind-dash').innerHTML = `<i class="fa-solid fa-wind text-info me-1"></i> ${data.wind} km/h`;
    })
    .catch(() => {
        document.getElementById('weather-temp-dash').innerHTML = '--°C';
        document.getElementById('weather-cond-dash').innerText = 'Weather offline';
    });

// Fit the view to the farmer's own pins once the card has its final size.
setTimeout(() => {
    dashMap.invalidateSize();
    if (ownPoints.length > 1) {
        dashMap.fitBounds(L.latLngBounds(ownPoints), { padding: [50, 50], maxZoom: 16 });
    } else if (ownPoints.length === 1) {
        dashMap.setView(ownPoints[0], 15);
    }
}, 400);
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