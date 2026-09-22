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
    <div class="col-xl col-md-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(236,72,153,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Reports</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($escalatedReportsCount ?? 0) }}</h2>
                </div>
                <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:45px;height:45px;font-size:18px;background: rgba(236,72,153,0.25); color:#ec4899;"><i class="fas fa-flag"></i></div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="prodigy-card p-4 h-100 position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(20,184,166,0.1), transparent); z-index:0;"></div>
            <div class="d-flex justify-content-between align-items-start position-relative z-1">
                <div>
                    <p class="text-secondary mb-1 small text-uppercase fw-bold">Assignments</p>
                    <h2 class="fw-bold text-white mb-0">{{ number_format($activeAssignmentsCount ?? 0) }}</h2>
                </div>
                <div class="rounded-3 d-flex align-items-center justify-content-center" style="width:45px;height:45px;font-size:18px;background: rgba(20,184,166,0.25); color:#14b8a6;"><i class="fas fa-diagram-project"></i></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="prodigy-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-chart-line text-primary me-2"></i> Detection Trends</h6>
                <span class="badge bg-primary bg-opacity-25 text-primary small">Last 6 Months</span>
            </div>
            <p class="text-secondary small mb-3">Total rice disease &amp; pest detections logged by farmers each month.</p>
            <div id="detectionTrendChart"></div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="prodigy-card h-100 p-4">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <h6 class="mb-0 fw-bold text-white"><i class="fa-solid fa-chart-pie text-warning me-2"></i> Top Diseases &amp; Pests</h6>
                <span class="badge bg-warning bg-opacity-25 text-warning small">All Time</span>
            </div>
            <p class="text-secondary small mb-3">Most frequently detected issues across all farms.</p>
            <div id="diseaseDistributionChart"></div>
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
                <h6 class="mb-0 fw-bold text-white"><i class="fas fa-users-viewfinder text-success me-2"></i> Active Field Locations & Assigned Area</h6>
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
    // --- ANALYTICS: DETECTION TREND (LINE/AREA CHART) ---
    const trendLabels = @json($trendLabels ?? []);
    const trendData = @json($trendData ?? []);

    new ApexCharts(document.querySelector("#detectionTrendChart"), {
        chart: { type: 'area', height: 300, background: 'transparent', foreColor: '#94a3b8', toolbar: { show: false } },
        series: [{ name: 'Detections', data: trendData }],
        xaxis: {
            categories: trendLabels,
            axisBorder: { color: '#334155' },
            axisTicks: { color: '#334155' }
        },
        yaxis: { min: 0, forceNiceScale: true, labels: { formatter: (v) => Math.round(v) } },
        grid: { borderColor: '#334155', strokeDashArray: 4 },
        colors: ['#3b82f6'],
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05, stops: [0, 90, 100] }
        },
        markers: { size: 4, colors: ['#3b82f6'], strokeColors: '#0f172a', strokeWidth: 2, hover: { size: 6 } },
        dataLabels: { enabled: false },
        tooltip: { theme: 'dark', y: { formatter: (v) => v + ' detection' + (v === 1 ? '' : 's') } }
    }).render();

    // --- ANALYTICS: TOP DISEASE/PEST DISTRIBUTION (DONUT CHART) ---
    const pieLabels = @json($pieLabels ?? []);
    const pieData = @json($pieData ?? []);
    const hasPieData = pieData.length > 0 && pieData.some(v => v > 0);

    new ApexCharts(document.querySelector("#diseaseDistributionChart"), {
        chart: { type: 'donut', height: 300, background: 'transparent', foreColor: '#94a3b8' },
        series: hasPieData ? pieData : [1],
        labels: hasPieData ? pieLabels : ['No detections yet'],
        colors: hasPieData ? ['#ef4444', '#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#0ea5e9'] : ['#334155'],
        legend: { position: 'bottom', fontSize: '12px', labels: { colors: '#cbd5e1' } },
        stroke: { colors: ['#0f172a'], width: 2 },
        dataLabels: { enabled: hasPieData, style: { fontSize: '11px' } },
        tooltip: { theme: 'dark', enabled: hasPieData },
        plotOptions: {
            pie: {
                donut: {
                    size: '68%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total',
                            color: '#94a3b8',
                            formatter: (w) => hasPieData ? w.globals.seriesTotals.reduce((a, b) => a + b, 0) : '0'
                        }
                    }
                }
            }
        }
    }).render();

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
    // Farmer markers grouped by barangay_id, so a technician's "View
    // Assigned Area" button can pull up exactly the farmer pins that fall
    // inside their assigned barangay(s) without a second request.
    window.farmersByBarangayId = {};
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

        // Index farmer pins by barangay so a technician's assigned-area
        // button (below) can find every farmer marker that belongs to
        // their assigned barangay(s).
        if (role === 'farmer') {
            const bId = String(user.barangay_id || '');
            if (bId) {
                window.farmersByBarangayId[bId] = window.farmersByBarangayId[bId] || [];
                window.farmersByBarangayId[bId].push({ lat, lng, markerId });
            }
        }

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
        // Technicians additionally show their assigned area (barangay(s)
        // handed to them via Assignment Management) with a button that
        // zooms/fits the map to their own pin PLUS every farmer pin that
        // falls inside that assigned area — so it renders together with
        // the farmer farm locations already on the map, never replacing
        // the technician's own point.
        const assignedAreaBlock = role === 'technician' ? `
            <div class="text-secondary small mb-1">
                Assigned Area: <span class="text-light">${user.assigned_area_label || 'Not yet assigned'}</span>
            </div>
            <button type="button"
                    class="btn btn-sm btn-outline-info py-0 px-2 mb-1"
                    style="font-size: 10px;"
                    onclick="event.stopPropagation(); showAssignedArea('${markerId}', ${JSON.stringify(user.assigned_barangay_ids || [])})">
                <i class="fa-solid fa-map-location-dot me-1"></i> View Assigned Area
            </button>
        ` : '';

        listHTML += `
            <div class="bg-dark p-3 rounded-3 border border-secondary list-hover-item mb-2 cursor-pointer transition" 
                 style="cursor: pointer;" onclick="triggerZoom(${lat}, ${lng}, '${markerId}')">
                <div class="d-flex align-items-center gap-2 text-light fw-bold mb-1 small">
                    <i class="fa-solid ${iconClass}" style="color: ${color}"></i> ${user.full_name}
                </div>
                ${role === 'farmer' ? `<div class="text-secondary small mb-1">Area: <span class="text-light">${user.farm_size || 0} ha</span></div>` : ''}
                ${assignedAreaBlock}
                <div class="text-muted" style="font-size: 11px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden;">
                    ${user.address || 'No address provided'}
                </div>
            </div>
        `;
    }

    // Additional farm plots: a farmer can register more than one field via
    // the Field Map page (users.additional_farms), each with its own pin
    // and hectare size — separate from their single main lat/lng. Those
    // never made it onto this map before, so a farmer with 3 fields only
    // ever showed as 1 marker. Plot every saved plot here too, using the
    // same GeoJSON [lng, lat] coordinate order FieldMapController@syncLayers
    // stores them in.
    function addAdditionalFarmPlotsToMap(farmer) {
        const plots = Array.isArray(farmer.additional_farms) ? farmer.additional_farms : [];

        plots.forEach((plot, idx) => {
            const coords = plot.coords || (plot.options && plot.options.coords);
            if (!Array.isArray(coords) || coords.length < 2) return;

            const lng = parseFloat(coords[0]);
            const lat = parseFloat(coords[1]);
            if (isNaN(lat) || isNaN(lng)) return;

            const opts = plot.options || {};
            const plotName = opts.farmName || plot.placeName || `${farmer.full_name}'s Field ${idx + 2}`;
            const plotSize = opts.farmSize || 0;
            const markerId = `farm_plot_${farmer.id}_${plot.id || idx}`;
            const color = '#10b981';

            const customIcon = L.divIcon({
                className: 'custom-pin',
                html: `<i class="fa-solid fa-map-pin" style="color: ${color}; font-size: 24px; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.8));"></i>`,
                iconSize: [24, 24], iconAnchor: [12, 24]
            });

            const marker = L.marker([lat, lng], { icon: customIcon }).addTo(dashMap);
            window.userMarkers[markerId] = marker;

            marker.bindTooltip(`
                <div style="text-align:left; max-width: 220px; font-family: system-ui, sans-serif; padding: 4px;">
                    <strong style="color: ${color}; font-size: 13px; display:block; margin-bottom:2px;">
                        <i class="fa-solid fa-map-pin me-1"></i> ${plotName}
                    </strong>
                    <span style="font-size: 11px; color: #fff; display:block; margin-bottom:4px;">📐 Area: <b>${plotSize} ha</b></span>
                    <div style="border-top: 1px solid #444; padding-top: 4px; font-size: 10px; color: #bbb; line-height: 1.3;">
                        Additional field of ${farmer.full_name}
                    </div>
                </div>
            `, { direction: 'top', className: 'custom-clean-tooltip' });

            listHTML += `
                <div class="bg-dark p-3 rounded-3 border border-secondary list-hover-item mb-2 cursor-pointer transition"
                     style="cursor: pointer;" onclick="triggerZoom(${lat}, ${lng}, '${markerId}')">
                    <div class="d-flex align-items-center gap-2 text-light fw-bold mb-1 small">
                        <i class="fa-solid fa-map-pin" style="color: ${color}"></i> ${plotName}
                    </div>
                    <div class="text-secondary small mb-1">Area: <span class="text-light">${plotSize} ha</span></div>
                    <div class="text-muted" style="font-size: 11px;">Additional field of ${farmer.full_name}</div>
                </div>
            `;
        });
    }

    // Process both arrays
    allFarmers.forEach(f => { addUserToMap(f, 'farmer'); addAdditionalFarmPlotsToMap(f); });
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

    // Triggered by a technician's "View Assigned Area" button. Fits the
    // map to the technician's OWN pin plus every farmer pin whose
    // barangay_id is inside their assigned area — the technician's own
    // location always stays on the map, shown together with the farmer
    // farm locations that belong to their assignment.
    window.showAssignedArea = function(techMarkerId, assignedBarangayIds) {
        const techMarker = window.userMarkers[techMarkerId];
        const points = [];
        const farmerMarkerIds = [];

        if (techMarker) {
            points.push(techMarker.getLatLng());
        }

        (assignedBarangayIds || []).map(String).forEach(bId => {
            (window.farmersByBarangayId[bId] || []).forEach(f => {
                points.push(L.latLng(f.lat, f.lng));
                farmerMarkerIds.push(f.markerId);
            });
        });

        if (points.length === 0) return;

        if (points.length === 1) {
            dashMap.flyTo(points[0], 16, { duration: 0.8 });
        } else {
            dashMap.flyToBounds(L.latLngBounds(points), { padding: [60, 60], duration: 0.8 });
        }

        // Pop open the technician's own tooltip + every assigned farmer's
        // tooltip once the fly animation settles, so it's obvious which
        // pins belong to this technician's assigned area.
        setTimeout(() => {
            if (techMarker) techMarker.openTooltip();
            farmerMarkerIds.forEach(id => {
                if (window.userMarkers[id]) window.userMarkers[id].openTooltip();
            });
        }, 800);
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