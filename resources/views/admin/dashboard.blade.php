@extends('layouts.admin')
@section('title', 'Admin Analytics Dashboard • RICEGUARD AI')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    .custom-clean-tooltip { background: #161b22 !important; border: 1px solid #334155 !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important; border-radius: 8px !important; }
    .custom-clean-tooltip::before { border-top-color: #334155 !important; }
    .scrollable-list { max-height: 400px; overflow-y: auto; overflow-x: hidden; }
    .scrollable-list::-webkit-scrollbar { width: 6px; }
    .scrollable-list::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
    .list-hover-item:hover { border-color: #3b82f6 !important; background: rgba(59, 130, 246, 0.1); }
</style>

<div class="mb-4">
    <h4 class="fw-bold text-white mb-1">RICEGUARD AI • Admin Analytics Dashboard</h4>
    <p class="text-secondary mb-0">Real-time overview • {{ \Carbon\Carbon::now()->format('F j, Y') }}</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl col-md-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Total Farmers</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($registeredFarmers ?? 0) }}</h2>
                </div>
                <div class="bg-primary bg-opacity-25 text-primary rounded-3 d-flex align-items-center justify-content-center" style="width:45px;height:45px;font-size:18px;"><i class="fas fa-users"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(16,185,129,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Active Technicians</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($activeTechnicians ?? 0) }}</h2>
                </div>
                <div class="bg-success bg-opacity-25 text-success rounded-3 d-flex align-items-center justify-content-center" style="width:45px;height:45px;font-size:18px;"><i class="fas fa-user-check"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(139,92,246,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Total Paddy Area</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($totalPaddyArea ?? 0, 1) }} <span class="fs-6 text-secondary">ha</span></h2>
                </div>
                <div class="bg-info bg-opacity-25 text-info rounded-3 d-flex align-items-center justify-content-center" style="width:45px;height:45px;font-size:18px;"><i class="fas fa-seedling"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(245,158,11,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Total Detections</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($totalDetections ?? 0) }}</h2>
                </div>
                <div class="bg-warning bg-opacity-25 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width:45px;height:45px;font-size:18px;"><i class="fas fa-camera-retro"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-12">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(239,68,68,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Pending Approvals</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($pendingApprovals ?? 0) }}</h2>
                </div>
                <div class="bg-danger bg-opacity-25 text-danger rounded-3 d-flex align-items-center justify-content-center" style="width:45px;height:45px;font-size:18px;"><i class="fas fa-clock"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="prodigy-card h-100 p-0 overflow-hidden d-flex flex-column" style="min-height: 450px;">
            <div class="bg-dark border-bottom border-secondary p-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-map-location-dot text-primary me-2"></i> Global Operations Map</h6>
                <select id="dashboard-map-style" onchange="switchMapStyle(this.value)" class="form-select form-select-sm bg-dark text-white border-secondary" style="width: 140px; font-size: 0.75rem;">
                    <option value="hybrid">Satellite Hybrid</option>
                    <option value="streets">Urban Streets</option>
                    <option value="topo">Topographic Map</option>
                    <option value="dark">Dark Mode</option>
                </select>
            </div>
            <div class="flex-grow-1 position-relative">
                <div id="dashboard-map" class="position-absolute w-100 h-100"></div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="prodigy-card h-100 p-0 overflow-hidden d-flex flex-column">
            <div class="bg-dark border-bottom border-secondary p-3">
                <h6 class="mb-0 fw-bold text-white"><i class="fas fa-users-viewfinder text-success me-2"></i> Active Field Locations</h6>
            </div>
            <div class="p-3 scrollable-list flex-grow-1" id="user-locations-list">
                </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="prodigy-card p-4 h-100">
            <h5 class="fw-bold text-white mb-3"><i class="fas fa-tractor text-success me-2"></i> All Farmers</h5>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead class="sticky-top bg-dark">
                        <tr>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Hectares</th>
                            <th>Current Weather</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allFarmers ?? [] as $farmer)
                        <tr>
                            <td>{{ $farmer->full_name }}</td>
                            <td class="small text-secondary">{{ $farmer->address ?? 'N/A' }}</td>
                            <td><span class="badge bg-secondary">{{ $farmer->farm_size ?? 0 }} ha</span></td>
                            <td class="weather-cell" data-lat="{{ $farmer->device_latitude ?? $farmer->latitude }}" data-lng="{{ $farmer->device_longitude ?? $farmer->longitude }}">
                                <i class="fas fa-spinner fa-spin text-muted"></i>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-xl-5">
        <div class="prodigy-card p-4 h-100">
            <h5 class="fw-bold text-white mb-3"><i class="fas fa-user-cog text-info me-2"></i> Technicians</h5>
            <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead class="sticky-top bg-dark">
                        <tr>
                            <th>Name</th>
                            <th>Address</th>
                            <th>Current Weather</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allTechnicians ?? [] as $tech)
                        <tr>
                            <td>{{ $tech->full_name }}</td>
                            <td class="small text-secondary">{{ $tech->address ?? 'N/A' }}</td>
                            <td class="weather-cell" data-lat="{{ $tech->device_latitude }}" data-lng="{{ $tech->device_longitude }}">
                                <i class="fas fa-spinner fa-spin text-muted"></i>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    // --- MAP INITIALIZATION ---
    const MAPTILER_KEY = '{{ env("MAPTILER_API_KEY") }}';
    
    const mapTiles = {
        "hybrid": L.tileLayer(`https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
        "streets": L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
        "topo": L.tileLayer(`https://api.maptiler.com/maps/topo-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
        "dark": L.tileLayer(`https://api.maptiler.com/maps/dataviz-dark/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true })
    };

    let currentTile = mapTiles["hybrid"];
    let dashMap = L.map('dashboard-map', { zoomControl: true }).setView([10.8986, 123.4143], 11); 
    dashMap.addLayer(currentTile);

    function switchMapStyle(styleKey) {
        if (mapTiles[styleKey]) {
            dashMap.removeLayer(currentTile);
            currentTile = mapTiles[styleKey];
            dashMap.addLayer(currentTile);
        }
    }

    // --- POPULATE MAP & SIDE LIST ---
    window.userMarkers = {};
    const allFarmers = @json($allFarmers ?? []);
    const allTechnicians = @json($allTechnicians ?? []);
    const listContainer = document.getElementById('user-locations-list');
    let listHTML = '';

    function addUserToMap(user, role) {
        // Use device coordinates first, fallback to registered coordinates
        const lat = parseFloat(user.device_latitude || user.latitude);
        const lng = parseFloat(user.device_longitude || user.longitude);
        
        if (!lat || !lng || isNaN(lat)) return;

        const markerId = `user_${role}_${user.id}`;
        const color = role === 'farmer' ? '#10b981' : '#0ea5e9'; // Green for Farmer, Blue for Tech
        const iconClass = role === 'farmer' ? 'fa-tractor' : 'fa-user-cog';
        
        // 1. Create Marker
        const customIcon = L.divIcon({
            className: 'custom-pin',
            html: `<i class="fa-solid fa-location-dot" style="color: ${color}; font-size: 28px; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.8));"></i>`,
            iconSize: [28, 28], iconAnchor: [14, 28]
        });

        const marker = L.marker([lat, lng], { icon: customIcon }).addTo(dashMap);
        window.userMarkers[markerId] = marker;

        // 2. Bind Tooltip
        const sizeText = role === 'farmer' ? `<span style="font-size: 11px; color: #fff; display:block; margin-bottom:4px;">📐 Area: <b>${user.farm_size || 0} ha</b></span>` : '';
        marker.bindTooltip(`
            <div style="text-align:left; max-width: 220px; font-family: system-ui, sans-serif; padding: 4px;">
                <strong style="color: ${color}; font-size: 13px; display:block; margin-bottom:2px;">
                    <i class="fa-solid ${iconClass} me-1"></i> ${user.full_name}
                </strong>
                ${sizeText}
                <div style="border-top: 1px solid #444; padding-top: 4px; font-size: 10px; color: #bbb; line-height: 1.3;">
                    ${user.address || 'Address not registered'}
                </div>
            </div>
        `, { direction: 'top', className: 'custom-clean-tooltip' });

        // 3. Add to HTML List
        listHTML += `
            <div class="bg-dark p-3 rounded-3 border border-secondary list-hover-item mb-2 cursor-pointer transition" 
                 style="cursor: pointer;" onclick="triggerZoom(${lat}, ${lng}, '${markerId}')">
                <div class="d-flex align-items-center gap-2 text-light fw-bold mb-1 small">
                    <i class="fa-solid ${iconClass}" style="color: ${color}"></i> ${user.full_name}
                </div>
                ${role === 'farmer' ? `<div class="text-secondary small mb-1">Area: <span class="text-light">${user.farm_size || 0} ha</span></div>` : ''}
                <div class="text-muted" style="font-size: 11px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">
                    ${user.address || 'No address provided'}
                </div>
            </div>
        `;
    }

    // Process both arrays
    allFarmers.forEach(f => addUserToMap(f, 'farmer'));
    allTechnicians.forEach(t => addUserToMap(t, 'technician'));
    
    if (listHTML === '') {
        listContainer.innerHTML = '<div class="text-center text-muted py-5 small fst-italic">No active locations found.</div>';
    } else {
        listContainer.innerHTML = listHTML;
    }

    // Zoom Function Triggered by clicking the list
    window.triggerZoom = function(lat, lng, markerId) {
        dashMap.flyTo([lat, lng], 17, { duration: 0.8 });
        if(window.userMarkers[markerId]) {
            setTimeout(() => { window.userMarkers[markerId].openTooltip(); }, 800);
        }
    };

    setTimeout(() => { dashMap.invalidateSize(); }, 500);

    // --- FETCH WEATHER FOR TABLES ---
    document.querySelectorAll('.weather-cell').forEach(cell => {
        const lat = cell.getAttribute('data-lat');
        const lng = cell.getAttribute('data-lng');

        if(lat && lng && lat !== "" && lng !== "") {
            fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current_weather=true`)
            .then(res => res.json())
            .then(data => {
                if(data.current_weather) {
                    cell.innerHTML = `<span class="text-info fw-bold">${data.current_weather.temperature}°C</span> 
                                      <small class="text-muted ms-1">(${data.current_weather.windspeed} km/h)</small>`;
                } else {
                    cell.innerHTML = '<span class="text-muted small">N/A</span>';
                }
            }).catch(() => {
                cell.innerHTML = '<span class="text-danger small">Error</span>';
            });
        } else {
            cell.innerHTML = '<span class="text-muted small">No Location Data</span>';
        }
    });
</script>
@endsection