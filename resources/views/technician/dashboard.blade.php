@extends('layouts.technician')
@section('title', 'Technician Dashboard • CROPSENSE AI')

@php
    // Fetch dashboard statistics dynamically
    $totalFarmers = DB::table('users')->where('role', 'farmer')->count();
    $totalDetections = DB::table('user_detections')->count();
    $knowledgeEntries = DB::table('treatment_records')->count();
    
    // Fetch all approved farmers with coordinates for the map
    $allFarms = DB::table('users')
        ->where('role', 'farmer')
        ->where('status', 'approved')
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->select('farm_name', 'farm_size', 'latitude', 'longitude', 'address') 
        ->get();

    // Default coordinates for Technician (Center of Sagay City as fallback)
    $techLat = 10.8986;
    $techLng = 123.4143;
@endphp

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .dashboard-map-wrapper { position: relative; height: 100%; min-height: 350px; width: 100%; border-radius: 8px; overflow: hidden; background-color: #0e1116; }
    #dashboard-map { height: 100% !important; width: 100% !important; position: absolute; top:0; left:0;}
    .custom-clean-tooltip { background: #161b22 !important; border: 1px solid #334155 !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important; border-radius: 8px !important; opacity: 1 !important; }
    .custom-clean-tooltip::before { border-top-color: #334155 !important; }
    .farmer-pin-icon i { color: #0ea5e9; font-size: 28px; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.8)); }
    .prodigy-card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
</style>

<div class="page-header mb-4">
    <h4 class="fw-bold text-white mb-1">Technician Dashboard</h4>
    <p class="text-secondary mb-0">Welcome back, {{ Auth::user()->full_name }}!</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(16,185,129,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-users fa-3x text-success mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Farmers Supported</h6>
                <h2 class="fw-bold text-white mb-0">{{ number_format($totalFarmers) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-database fa-3x text-primary mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Knowledge Entries</h6>
                <h2 class="fw-bold text-white mb-0">{{ number_format($knowledgeEntries) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(245,158,11,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-camera-retro fa-3x text-warning mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Total Detections</h6>
                <h2 class="fw-bold text-white mb-0">{{ number_format($totalDetections) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(14,165,233,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-check-circle fa-3x text-info mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Active Status</h6>
                <h2 class="fw-bold text-info mb-0">Online</h2>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4 align-items-stretch">
    <div class="col-xl-4 col-lg-5">
        <div class="prodigy-card h-100 p-0 overflow-hidden d-flex flex-column">
            <div class="bg-dark border-bottom border-secondary p-3">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-cloud-sun text-info me-2"></i> District Weather Overview</h6>
            </div>
            <div class="p-4 text-center flex-grow-1 d-flex flex-column justify-content-center">
                <div class="display-3 fw-bold text-info mb-2" id="weather-temp-dash">
                    <i class="fas fa-spinner fa-spin fs-4 text-secondary"></i>
                </div>
                <h5 class="text-secondary mb-4" id="weather-cond-dash">Loading...</h5>
                <div class="d-flex justify-content-between px-3 bg-dark bg-opacity-50 p-3 rounded-3 border border-secondary">
                    <div class="text-white small" id="weather-hum-dash"><i class="fa-solid fa-droplet text-info me-1"></i> --% Hum</div>
                    <div class="text-white small" id="weather-wind-dash"><i class="fa-solid fa-wind text-info me-1"></i> -- km/h</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-8 col-lg-7">
        <div class="prodigy-card h-100 p-0 overflow-hidden d-flex flex-column">
            <div class="bg-dark border-bottom border-secondary p-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-map-location-dot text-info me-2"></i> Global Farmers Map</h6>
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
</div>

<h5 class="fw-bold text-white mb-3"><i class="fas fa-bolt text-warning me-2"></i>Quick Navigation</h5>
<div class="row g-3">
    <div class="col-md-4">
        <a href="{{ route('technician.records') }}" class="btn btn-info w-100 py-3 text-dark fw-bold shadow-sm rounded-3">
            <i class="fas fa-folder-open fa-lg d-block mb-2"></i> Farmers Records
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('technician.live_com') }}" class="btn btn-primary w-100 py-3 fw-bold shadow-sm rounded-3">
            <i class="fas fa-comments fa-lg d-block mb-2"></i> Live Chat
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('technician.announcement') }}" class="btn btn-warning w-100 py-3 text-dark fw-bold shadow-sm rounded-3">
            <i class="fas fa-bullhorn fa-lg d-block mb-2"></i> Announcements
        </a>
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
const techLat = {{ $techLat }};
const techLng = {{ $techLng }};

const dashMap = L.map('dashboard-map', {
    center: [techLat, techLng],
    zoom: 13,
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

// 2. Tooltip Geocoding logic
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

// 3. Load all farmer pins dynamically
const allFarmsData = @json($allFarms ?? []);
if (allFarmsData.length > 0) {
    const markers = [];
    allFarmsData.forEach(farm => {
        if (farm.latitude && farm.longitude) {
            const fLat = parseFloat(farm.latitude);
            const fLng = parseFloat(farm.longitude);
            
            const farmerIcon = L.divIcon({
                className: 'farmer-pin-icon',
                html: '<i class="fa-solid fa-location-dot"></i>',
                iconSize: [28, 28], iconAnchor: [14, 28]
            });
            
            const marker = L.marker([fLat, fLng], { icon: farmerIcon }).addTo(dashMap);
            attachAddressTooltip(marker, fLat, fLng, farm.farm_name || "Registered Farmer", farm.farm_size || "N/A", "#0ea5e9");
            markers.push(marker);
        }
    });
    
    // Automatically adjust the camera to fit all farmer pins if any exist
    if (markers.length > 0) {
        const group = new L.featureGroup(markers);
        dashMap.fitBounds(group.getBounds(), { padding: [30, 30], maxZoom: 15 });
    }
}

// 4. Fetch Dynamic District Weather
fetch(`{{ route('technician.field_map.weather') }}?lat=${techLat}&lon=${techLng}`)
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
@endsection