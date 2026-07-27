@extends('layouts.technician') 
@section('title', 'Field Map & Weather • RICEGUARD AI')

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.14.2/dist/leaflet-geoman.css" />

<script src="https://cdn.tailwindcss.com"></script>
<script>
    tailwind.config = {
        darkMode: 'class',
        theme: {
            extend: {
                colors: { darkBg: '#0e1116', panelBg: '#161b22', panelBorder: '#30363d', accent: '#0ea5e9', accentHover: '#0284c7' },
                fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'sans-serif'] }
            }
        }
    }
</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<script src="https://unpkg.com/@phosphor-icons/web"></script>

<style>
#map-wrapper { flex: 1 1 auto; position: relative; min-height: 500px; width: 100%; overflow: visible !important; }
#map { height: 100% !important; width: 100% !important; position: absolute; top: 0; left: 0; background-color: #0e1116 !important; }    
.leaflet-container { background-color: #0e1116 !important; }
.leaflet-tile-container img { outline: 1px solid transparent !important; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
.leaflet-bar { border: 1px solid #30363d !important; box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important; border-radius: 6px !important; overflow: hidden; }
.leaflet-bar a, .leaflet-pm-toolbar .leaflet-buttons-control-button { background-color: #161b22 !important; color: #c9d1d9 !important; border-bottom: 1px solid #30363d !important; width: 31px !important; height: 31px !important; line-height: 31px !important; display: flex !important; align-items: center !important; justify-content: center !important; }
.leaflet-bar a:hover, .leaflet-pm-toolbar .leaflet-buttons-control-button:hover { background-color: #30363d !important; color: #ffffff !important; }
.leaflet-pm-toolbar .active { background-color: #0ea5e9 !important; color: #ffffff !important; }
.leaflet-control-zoom { margin-top: 1rem !important; margin-left: 1rem !important; }
.leaflet-pm-toolbar { margin-bottom: 0rem !important; margin-top: 0.5rem !important; margin-left: 1rem !important; display: flex !important; flex-direction: column !important; flex-wrap: wrap !important; max-height: calc(100% - 110px) !important; gap: 1px; }

/* Custom Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #161b22; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #30363d; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #484f58; }

#export-guide-box.hidden { display: none !important; }
.export-bounding-outline { border: 2px dashed rgba(14, 165, 233, 0.6); box-shadow: none; pointer-events: none; }
.drag-handle, .resize-handle { pointer-events: auto; }
.area-tooltip { background: transparent !important; border: none !important; box-shadow: none !important; color: #ffffff !important; font-weight: 700 !important; font-size: 14px !important; text-shadow: 0px 0px 4px rgba(0,0,0,0.8), 1px 1px 2px rgba(0,0,0,0.8) !important; }
.leaflet-tooltip.area-tooltip::before { display: none !important; }
.custom-text-transparent, .leaflet-pm-textarea { background: transparent !important; border: none !important; box-shadow: none !important; font-size: 16px; font-weight: 600; text-shadow: -1px -1px 0 rgba(0,0,0,0.7), 1px -1px 0 rgba(0,0,0,0.7), -1px 1px 0 rgba(0,0,0,0.7), 1px 1px 0 rgba(0,0,0,0.7), 0px 4px 6px rgba(0,0,0,0.9) !important; resize: none; outline: none !important; }
.leaflet-pm-textarea:focus { border: 1px dashed rgba(255,255,255,0.5) !important; }
.custom-pin svg { filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.6)); }

/* Custom Clean Tooltip for Hovering over pins */
.custom-clean-tooltip { background: #161b22 !important; border: 1px solid #30363d !important; color: white !important; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5) !important; border-radius: 8px !important; }
.custom-clean-tooltip::before { border-top-color: #30363d !important; }

/* Custom Farm Pin Style */
.farm-pin-icon i {
    color: #0ea5e9;
    font-size: 32px;
    filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.8));
}
</style>

<div class="container-fluid py-4">
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="fw-bold text-info mb-1"><i class="fas fa-map-location-dot me-2"></i> Area Overview</h2>
            <p class="text-secondary">Precision Map & Real-time Weather Integration</p>
        </div>
    </div>

    <div class="map-container-wrapper flex flex-col antialiased text-sm select-none shadow-lg">
        <header class="h-14 border-b border-panelBorder bg-darkBg flex items-center justify-between px-4 lg:px-6 shrink-0 z-20 relative">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-accent flex items-center justify-center text-white shadow-lg shadow-accent/20"><i class="ph-fill ph-plant text-xl"></i></div>
                <div><h1 class="font-bold text-gray-100 text-base lg:text-lg leading-tight flex items-center gap-2">RICEGUARD AI</h1><p class="text-[10px] lg:text-[11px] text-gray-400 hidden sm:block">Technician Field Monitoring</p></div>
            </div>
            <div class="flex items-center gap-4 text-gray-400 text-xs">
                <div class="hidden sm:flex items-center gap-2"><i class="ph ph-globe"></i> Global Coverage</div>
                <div class="flex items-center gap-2" id="sync-status"><i class="ph ph-lightning"></i> Real-time Sync</div>
            </div>
        </header>

        <div class="flex flex-col lg:flex-row flex-1 overflow-y-auto lg:overflow-hidden">
            
            <main class="flex-1 flex flex-col bg-[#0e1116] min-w-0 min-h-[60vh] lg:min-h-0 relative z-0">
                <div class="flex-1 relative w-full overflow-hidden flex items-center justify-center" id="map-wrapper">
                    <div id="map" class="absolute inset-0 z-10 transition-all duration-300"></div>
                    <div id="export-guide-box" class="absolute pointer-events-none z-30 export-bounding-outline hidden transition-all duration-300"></div>

                    <div class="absolute top-4 left-12 md:left-16 right-4 md:right-auto flex flex-col md:flex-row gap-2 z-[1000] pointer-events-none">
                        <div class="bg-panelBg/95 backdrop-blur-md border border-panelBorder rounded-md flex items-center px-3 py-2 w-full md:w-72 pointer-events-auto shadow-xl">
                            <i class="ph ph-magnifying-glass text-gray-400 mr-3 text-lg"></i>
                            <input type="text" id="map-search-bar" placeholder="Search and press Enter..." class="bg-transparent border-none outline-none text-sm w-full placeholder-gray-500 text-gray-200">
                        </div>
                        
                        <div class="relative pointer-events-auto">
                            <div class="bg-panelBg/95 backdrop-blur-md border border-panelBorder rounded-md flex items-center px-3 py-1 shadow-xl transition w-max">
                                <div class="w-5 h-5 bg-accent/20 rounded mr-2 flex items-center justify-center text-accent">
                                    <i class="ph-fill ph-map-trifold text-xs"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[9px] text-gray-500 font-semibold uppercase tracking-wider leading-tight">Map Style</span>
                                    <select id="map-style-selector" onchange="switchMapStyle(this.value)" class="bg-panelBg border-none outline-none text-xs text-white font-medium cursor-pointer py-0.5 pr-2 focus:ring-0 rounded">
                                        <option value="hybrid" class="bg-panelBg text-white">Satellite Hybrid</option>
                                        <option value="streets" class="bg-panelBg text-white">Urban Streets</option>
                                        <option value="topo" class="bg-panelBg text-white">Topographic Map</option>
                                        <option value="outdoor" class="bg-panelBg text-white">Outdoors Map</option>
                                        <option value="dark" class="bg-panelBg text-white">Dark Mode</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute bottom-4 left-4 lg:top-4 lg:bottom-auto lg:right-4 lg:left-auto bg-panelBg/80 backdrop-blur-md border border-panelBorder rounded-md px-4 py-2 text-xs text-gray-300 font-mono flex items-center gap-4 shadow-xl z-[500] pointer-events-none"><span id="coord-lat">Lat 0.00</span><span id="coord-lng">Lng 0.00</span></div>
                </div>

                <div class="bg-[#0e1116] border-t border-panelBorder grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-panelBorder shrink-0 z-20">
                    <div class="p-5 flex flex-col justify-between">
                        <div>
                            <h3 class="text-xs font-semibold text-white uppercase tracking-wider mb-1">Resize & Dimensions</h3>
                            <p class="text-[11px] text-gray-500 mb-3">Choose standard viewport sizes or enter custom bounds</p>
                            <div class="grid grid-cols-4 gap-2 mb-3">
                                <button onclick="setMapDimension(1280, 720)" class="bg-panelBg hover:bg-panelBorder border border-panelBorder rounded py-1.5 text-center transition"><div class="text-white text-[11px] font-semibold">720p</div></button>
                                <button onclick="setMapDimension(1920, 1080)" class="bg-panelBg hover:bg-panelBorder border border-panelBorder rounded py-1.5 text-center transition"><div class="text-white text-[11px] font-semibold">1080p</div></button>
                                <button onclick="setMapDimension(2560, 1440)" class="bg-panelBg hover:bg-panelBorder border border-panelBorder rounded py-1.5 text-center transition"><div class="text-white text-[11px] font-semibold">2K</div></button>
                                <button onclick="setMapDimension(3840, 2160)" class="bg-panelBg hover:bg-panelBorder border border-panelBorder rounded py-1.5 text-center transition"><div class="text-white text-[11px] font-semibold">4K</div></button>
                            </div>
                        </div>
                        <div class="flex gap-2 items-end">
                            <div class="flex-1"><div class="text-[10px] text-gray-500 mb-1">Width (px)</div><input type="number" id="custom-width" value="1920" class="w-full bg-panelBg border border-panelBorder rounded px-2 py-1 text-xs text-white outline-none focus:border-accent"></div>
                            <div class="flex-1"><div class="text-[10px] text-gray-500 mb-1">Height (px)</div><input type="number" id="custom-height" value="1080" class="w-full bg-panelBg border border-panelBorder rounded px-2 py-1 text-xs text-white outline-none focus:border-accent"></div>
                            <button onclick="applyCustomDimensions()" class="bg-accent hover:bg-accentHover rounded px-3 py-1.5 text-xs font-medium text-white transition shrink-0">Apply</button>
                        </div>
                    </div>

                    <div class="p-5 flex flex-col justify-between transition-opacity duration-300" id="properties-panel" style="opacity: 0.5; pointer-events: none;">
                        <div id="shape-props">
                            <h3 class="text-xs font-semibold text-white uppercase tracking-wider mb-1">Selected Shape Styling</h3>
                            <p class="text-[11px] text-gray-500 mb-3">Dynamically adjust colors and weights of drawn layers</p>
                            <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                                <div class="flex justify-between items-center"><label class="text-xs text-gray-400">Fill Color</label><div class="flex items-center gap-1.5"><input type="color" id="prop-fill-color" value="#0ea5e9" class="w-5 h-5 rounded cursor-pointer bg-transparent border-0 p-0" onchange="updateSelectedShape()"><span class="text-[11px] text-gray-400 font-mono" id="prop-fill-hex">#0ea5e9</span></div></div>
                                <div class="flex justify-between items-center"><label class="text-xs text-gray-400">Border Color</label><div class="flex items-center gap-1.5"><input type="color" id="prop-border-color" value="#0ea5e9" class="w-5 h-5 rounded cursor-pointer bg-transparent border-0 p-0" onchange="updateSelectedShape()"><span class="text-[11px] text-gray-400 font-mono" id="prop-border-hex">#0ea5e9</span></div></div>
                                <div><div class="flex justify-between text-[11px] text-gray-400 mb-1"><label>Opacity</label><span id="prop-fill-op-val" class="font-mono">40%</span></div><input type="range" id="prop-fill-opacity" min="0" max="1" step="0.1" value="0.4" class="w-full h-1 bg-panelBorder rounded-lg appearance-none cursor-pointer accent-accent" oninput="updateSelectedShape()"></div>
                                <div><div class="flex justify-between text-[11px] text-gray-400 mb-1"><label>Thickness</label><span id="prop-weight-val" class="font-mono">2px</span></div><input type="range" id="prop-weight" min="1" max="10" step="1" value="2" class="w-full h-1 bg-panelBorder rounded-lg appearance-none cursor-pointer accent-accent" oninput="updateSelectedShape()"></div>
                            </div>
                        </div>

                        <div id="marker-text-props" style="display: none;">
                            <h3 class="text-xs font-semibold text-white uppercase tracking-wider mb-1">Pin / Text Customization</h3>
                            <p class="text-[11px] text-gray-500 mb-3">Adjust the solid color of your marker or text</p>
                            <div class="flex items-center justify-between bg-darkBg p-3 border border-panelBorder rounded-md">
                                <label class="text-xs text-gray-300 font-medium">Element Color</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" id="prop-marker-color" value="#0ea5e9" class="w-6 h-6 rounded cursor-pointer bg-transparent border-0 p-0" onchange="updateSelectedMarker()">
                                    <span class="text-xs text-gray-400 font-mono" id="prop-marker-hex">#0ea5e9</span>
                                </div>
                            </div>
                        </div>
                        <button onclick="deleteSelectedShape()" class="w-full mt-3 flex items-center justify-center gap-2 py-1.5 rounded bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 text-xs transition-colors"><i class="ph ph-trash"></i> Remove Selected Element</button>
                    </div>

                    <div class="p-5 flex flex-col justify-between">
                        <div>
                            <h3 class="text-xs font-semibold text-white uppercase tracking-wider mb-1">Export Config</h3>
                            <p class="text-[11px] text-gray-500 mb-3">Configure rendering and download snapshots</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div><span class="text-[11px] text-gray-400 block mb-1">Format</span><select id="export-format" class="w-full bg-panelBg border border-panelBorder rounded px-2 py-1 text-xs text-white outline-none focus:border-accent"><option>PNG</option><option>JPEG</option></select></div>
                                <div><span class="text-[11px] text-gray-400 block mb-1">Quality</span><select id="export-quality" class="w-full bg-panelBg border border-panelBorder rounded px-2 py-1 text-xs text-white outline-none focus:border-accent"><option>High (100%)</option><option>Medium (75%)</option><option>Low (50%)</option></select></div>
                            </div>
                        </div>
                        <button id="btn-export-img" onclick="exportMapImage()" class="w-full bg-[#0ea5e9] hover:bg-[#0284c7] text-white py-2 rounded-md font-medium text-xs flex items-center justify-center gap-2 transition-colors"><i class="ph ph-download-simple text-lg"></i> Export Render Snapshot</button>
                    </div>
                </div>
            </main>

            <aside class="w-full lg:w-[320px] border-t lg:border-t-0 lg:border-l border-panelBorder bg-panelBg flex flex-col shrink-0 z-10 overflow-hidden shadow-xl">
                <div class="p-4 border-b border-panelBorder bg-[#111827]">
                    <h2 class="text-[11px] font-semibold text-gray-300 uppercase tracking-wider mb-3 flex justify-between items-center">
                        <span><i class="fas fa-cloud-sun text-accent mr-1"></i> Area Climate</span>
                        <span id="weather-location-name" class="bg-panelBorder px-2 py-0.5 rounded text-[10px] text-gray-400">Sagay City</span>
                    </h2>
                    @if(isset($error))
                        <div class="bg-red-500/10 border border-red-500/20 p-3 rounded text-red-400 text-xs"><i class="fas fa-exclamation-triangle me-1"></i> {{ $error }}</div>
                    @else
                        <div class="flex items-center justify-between mb-3">
                            <div class="text-3xl font-bold text-white" id="weather-temp-val">{{ $temp ?? '--' }}<span class="text-accent text-xl">°C</span></div>
                            <div class="text-right">
                                <div class="text-xs font-medium text-gray-200 mb-1" id="weather-condition-val">{{ $condition ?? '--' }}</div>
                                <span id="weather-risk-badge" class="text-[9px] px-2 py-0.5 rounded font-bold bg-{{ $riskColor ?? 'gray' }}-500/20 text-{{ $riskColor ?? 'gray' }}-400 border border-{{ $riskColor ?? 'gray' }}-500/30">{{ strtoupper($riskLevel ?? 'LOW') }} RISK</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-1 bg-darkBg border border-panelBorder rounded p-2 mb-3">
                            <div class="text-center"><i class="fa-solid fa-droplet text-accent text-xs mb-1"></i><div id="weather-humidity-val" class="text-[10px] text-gray-300 font-semibold">{{ $humidity ?? '--' }}%</div></div>
                            <div class="text-center border-x border-panelBorder"><i class="fa-solid fa-wind text-accent text-xs mb-1"></i><div id="weather-wind-val" class="text-[10px] text-gray-300 font-semibold">{{ $wind ?? '--' }} km/h</div></div>
                            <div class="text-center"><i class="fa-solid fa-cloud-rain text-accent text-xs mb-1"></i><div id="weather-rain-val" class="text-[10px] text-gray-300 font-semibold">{{ $rain ?? '--' }}%</div></div>
                        </div>
                    @endif
                </div>

                <div class="p-4 flex-1 flex flex-col min-h-0 bg-panelBg overflow-hidden map-container-wrapper">
                    <div class="mb-6 flex flex-col max-h-[50%]">
                        <div class="flex justify-between items-center mb-3 border-b border-panelBorder pb-2 shrink-0">
                            <div>
                                <h2 class="text-xs font-semibold text-gray-200 uppercase tracking-wider">Your Map Markers</h2>
                                <div class="text-[10px] text-gray-500 mt-0.5">Total Area: <span id="total-area-sqm" class="font-bold text-[#fca311]">0 m²</span></div>
                            </div>
                        </div>
                        <div class="space-y-3 text-xs text-gray-300 pr-2 overflow-y-auto custom-scrollbar" id="layers-list"></div>
                    </div>

                    <div class="flex flex-col max-h-[50%]">
                        <div class="flex justify-between items-center mb-3 border-b border-panelBorder pb-2 shrink-0">
                            <div>
                                <h2 class="text-xs font-semibold text-accent uppercase tracking-wider"><i class="ph-fill ph-users"></i> Farmer Fields</h2>
                            </div>
                        </div>
                        <div class="space-y-3 text-xs text-gray-300 pr-2 overflow-y-auto custom-scrollbar" id="other-fields-list"></div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@geoman-io/leaflet-geoman-free@2.14.2/dist/leaflet-geoman.min.js"></script>
<script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
// 👇 UPDATED ROUTES FOR TECHNICIAN
const MAPTILER_KEY = '{{ env("MAPTILER_API_KEY") }}';
const SYNC_URL = '{{ route("technician.field_map.sync") }}';
const WEATHER_URL = '{{ route("technician.field_map.weather") }}';
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

if (typeof L !== 'undefined' && L.GridLayer) {
    const originalInitTile = L.GridLayer.prototype._initTile;
    L.GridLayer.include({
        _initTile: function (tile) {
            originalInitTile.call(this, tile);
            const tileSize = this.getTileSize();
            tile.style.width = `${tileSize.x + 1}px`;
            tile.style.height = `${tileSize.y + 1}px`;
        }
    });
}

const baseMaps = {
    "hybrid": L.tileLayer(`https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "streets": L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "topo": L.tileLayer(`https://api.maptiler.com/maps/topo-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "outdoor": L.tileLayer(`https://api.maptiler.com/maps/outdoor-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }),
    "dark": L.tileLayer(`https://api.maptiler.com/maps/dataviz-dark/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true })
};

let currentActiveTileLayer = baseMaps["hybrid"];

const map = L.map('map', {
    center: [{{ $userLat ?? 10.8986 }}, {{ $userLng ?? 123.4143 }}],
    zoom: 14,
    layers: [currentActiveTileLayer],
    attributionControl: false 
});

function switchMapStyle(styleKey) { 
    if (baseMaps[styleKey]) {
        map.removeLayer(currentActiveTileLayer);
        currentActiveTileLayer = baseMaps[styleKey];
        map.addLayer(currentActiveTileLayer);
    } 
}

function fetchAddressForLayer(layer, lat, lng) {
    layer.placeName = "Loading exact location...";
    updateLayersList(); 
    
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(res => res.json())
        .then(data => {
            if (data && data.display_name) {
                layer.placeName = data.display_name;
            } else {
                layer.placeName = "Address not registered";
            }
            updateLayersList();
            saveToDatabase();
        })
        .catch(err => {
            console.error("Geocoding failed:", err);
            layer.placeName = "Failed to load location";
            updateLayersList();
        });
}

function updateWeather(lat, lng) {
    document.getElementById('weather-temp-val').innerHTML = '<i class="ph ph-spinner animate-spin"></i>';
    
    fetch(`${WEATHER_URL}?lat=${lat}&lon=${lng}`)
        .then(res => res.json())
        .then(data => {
            if(data.error) return console.error(data.error);
            
            document.getElementById('weather-temp-val').innerHTML = `${data.temp}<span class="text-accent text-xl">°C</span>`;
            document.getElementById('weather-condition-val').innerText = data.condition;
            document.getElementById('weather-humidity-val').innerText = `${data.humidity}%`;
            document.getElementById('weather-wind-val').innerText = `${data.wind} km/h`;
            document.getElementById('weather-rain-val').innerText = `${data.rain}%`;
            
            const riskBadge = document.getElementById('weather-risk-badge');
            riskBadge.innerText = `${data.riskLevel.toUpperCase()} RISK`;
            riskBadge.className = `text-[9px] px-2 py-0.5 rounded font-bold bg-${data.riskColor}-500/20 text-${data.riskColor}-400 border border-${data.riskColor}-500/30`;
        })
        .catch(err => console.error("Weather update failed:", err));
}

map.on('mousemove', function(e) {
    document.getElementById('coord-lat').innerText = 'Lat ' + e.latlng.lat.toFixed(5);
    document.getElementById('coord-lng').innerText = 'Lng ' + e.latlng.lng.toFixed(5);
});

document.getElementById('map-search-bar').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const query = this.value.trim(); 
        if (!query) return;
        
        const originalPlaceholder = this.placeholder;
        this.placeholder = "Searching...";
        this.value = "";

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => { 
                if(data && data.length > 0) {
                    map.setView([parseFloat(data[0].lat), parseFloat(data[0].lon)], 15); 
                } else {
                    alert("Location not found.");
                }
            })
            .catch(err => console.error("Search failed:", err))
            .finally(() => { this.placeholder = originalPlaceholder; });
    }
});

L.control.zoom({ position: 'topleft' }).addTo(map);

map.pm.addControls({ 
    position: 'topleft', drawMarker: true, drawText: true, drawCircleMarker: false, drawPolyline: true, drawRectangle: true, drawPolygon: true, drawCircle: true, editMode: true, dragMode: false, removalMode: true 
});

map.pm.setGlobalOptions({ 
    textOptions: { className: 'custom-text-transparent' },
    measurements: { measurement: true, displayLabels: true, totalLength: true, segmentLength: true, area: true }
});

function createCustomMarkerIcon(color) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="28" height="40"><path fill="${color}" d="M172.268 501.67C26.97 291.031 0 269.413 0 192 0 85.961 85.961 0 192 0s192 85.961 192 192c0 77.413-26.97 99.031-172.268 309.67-9.535 13.774-29.93 13.773-39.464 0zM192 272c44.183 0 80-35.817 80-80s-35.817-80-80-80-80 35.817-80 80 35.817 80 80 80z"/></svg>`;
    return L.divIcon({ className: 'custom-pin', html: svg, iconSize: [28, 40], iconAnchor: [14, 40], popupAnchor: [0, -40] });
}

let selectedLayer = null;

function updateShapeAreaLabel(layer) {
    if (layer instanceof L.Polygon && !(layer instanceof L.Rectangle)) {
        const areaSqm = turf.area(layer.toGeoJSON());
        let displayArea = areaSqm >= 10000 ? (areaSqm / 10000).toFixed(1) + " ha" : areaSqm.toFixed(0) + " m²";
        layer.unbindTooltip();
        layer.bindTooltip(displayArea, { permanent: true, direction: 'center', className: 'area-tooltip', interactive: false });
    }
}

function updateLayersList() {
    const layers = map.pm.getGeomanLayers();
    const listContainer = document.getElementById('layers-list');
    let totalAreaSqm = 0; let measurementsHTML = '';
    
    if(layers.length === 0) {
        listContainer.innerHTML = '<div class="text-center text-gray-500 text-xs py-6 italic">Draw a shape to see metrics</div>';
        document.getElementById('total-area-sqm').innerText = `0 m²`; return;
    }
    
    layers.forEach(layer => {
        let area = 0, radius = 0, typeName = 'Shape', iconClass = 'ph-hexagon';
        try {
            if (layer.options.customType === 'Text' || layer.pm._shape === 'Text') { typeName = 'Text Label'; iconClass = 'ph-text-t'; }
            else if (layer.options.customType === 'Marker') { typeName = layer.options.isFarmPin ? 'Main Tech Pin' : 'Marker Pin'; iconClass = 'ph-map-pin'; }
            else if (layer instanceof L.Circle) { typeName = 'Circle'; iconClass = 'ph-circle'; radius = layer.getRadius(); area = Math.PI * radius * radius; }
            else if (layer instanceof L.Polygon || layer instanceof L.Rectangle) { typeName = layer instanceof L.Rectangle ? 'Rectangle' : 'Polygon'; iconClass = layer instanceof L.Rectangle ? 'ph-corners-out' : 'ph-hexagon'; area = turf.area(layer.toGeoJSON()); }
        } catch(e){}
        
        totalAreaSqm += area;
        const isSelected = selectedLayer === layer;

        let addressHTML = '';
        if ((typeName === 'Marker Pin' || typeName === 'Main Tech Pin') && layer.placeName) {
            addressHTML = `
                <div class="text-[11px] text-gray-500 italic mt-2 border-t border-panelBorder pt-2">
                    <i class="ph-fill ph-map-pin text-red-400 mr-1"></i> ${layer.placeName}
                </div>
            `;
        }

        measurementsHTML += `
            <div class="bg-darkBg p-3 rounded-lg border ${isSelected ? 'border-accent shadow-[0_0_10px_rgba(14,165,233,0.3)]' : 'border-panelBorder hover:border-gray-500'} cursor-pointer transition group" onclick="selectLayerById(${L.stamp(layer)})">
                <div class="flex items-center gap-2 text-gray-200 font-medium mb-2 text-sm"><i class="ph ${iconClass} ${isSelected ? 'text-accent' : 'text-gray-400 group-hover:text-white'}"></i> ${typeName}</div>
                <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-xs">
                    ${radius > 0 ? `<div class="text-gray-400">Radius:</div><div class="text-right font-mono text-gray-300">${radius.toLocaleString(undefined, {maximumFractionDigits: 2})} m</div>` : ''}
                    ${area > 0 ? `<div class="text-gray-400">Area:</div><div class="text-right font-mono text-gray-300">${area.toLocaleString(undefined, {maximumFractionDigits: 2})} m²</div>` : ''}
                </div>
                ${addressHTML}
            </div>`;
        
        layer.off('click').on('click', () => selectLayer(layer));
    });
    listContainer.innerHTML = measurementsHTML;
    document.getElementById('total-area-sqm').innerText = `${totalAreaSqm.toLocaleString(undefined, {maximumFractionDigits: 2})} m²`;
}

function selectLayer(layer) {
    selectedLayer = layer; 
    updateLayersList();
    const panel = document.getElementById('properties-panel'); 
    panel.style.opacity = '1'; 
    panel.style.pointerEvents = 'auto';

    if (layer.getBounds) {
        map.flyToBounds(layer.getBounds(), { padding: [50, 50], maxZoom: 18, duration: 0.8 });
    } else if (layer.getLatLng) {
        map.flyTo(layer.getLatLng(), 18, { duration: 0.8 });
    }

    const opts = layer.options || {};
    const isMarkerOrText = opts.customType === 'Marker' || opts.customType === 'Text' || (layer.pm && layer.pm._shape === 'Text');

    if(isMarkerOrText) {
        document.getElementById('shape-props').style.display = 'none';
        document.getElementById('marker-text-props').style.display = 'block';
        
        let savedColor = opts.markerColor || (opts.customType === 'Text' ? '#ffffff' : '#0ea5e9');
        document.getElementById('prop-marker-color').value = savedColor;
        document.getElementById('prop-marker-hex').innerText = savedColor.toUpperCase();
    } else {
        document.getElementById('shape-props').style.display = 'block';
        document.getElementById('marker-text-props').style.display = 'none';
        
        if(opts.fillColor) document.getElementById('prop-fill-color').value = opts.fillColor; 
        if(opts.color) document.getElementById('prop-border-color').value = opts.color; 
        if(opts.weight !== undefined) document.getElementById('prop-weight').value = opts.weight; 
        if(opts.fillOpacity !== undefined) document.getElementById('prop-fill-opacity').value = opts.fillOpacity;
        
        document.getElementById('prop-fill-hex').innerText = document.getElementById('prop-fill-color').value.toUpperCase(); 
        document.getElementById('prop-border-hex').innerText = document.getElementById('prop-border-color').value.toUpperCase(); 
    }
}

function selectLayerById(id) { const layer = map.pm.getGeomanLayers().find(l => L.stamp(l) == id); if (layer) selectLayer(layer); }

function updateSelectedShape() {
    if(!selectedLayer) return;
    const fillCol = document.getElementById('prop-fill-color').value, borderCol = document.getElementById('prop-border-color').value, weight = document.getElementById('prop-weight').value, fillOp = document.getElementById('prop-fill-opacity').value;
    document.getElementById('prop-fill-hex').innerText = fillCol.toUpperCase(); document.getElementById('prop-border-hex').innerText = borderCol.toUpperCase(); 
    if (selectedLayer.setStyle) { 
        selectedLayer.setStyle({ fillColor: fillCol, color: borderCol, weight: parseInt(weight), fillOpacity: parseFloat(fillOp) }); 
        updateLayersList(); 
        saveToDatabase(); 
    }
}

function updateSelectedMarker() {
    if(!selectedLayer) return;
    const color = document.getElementById('prop-marker-color').value;
    document.getElementById('prop-marker-hex').innerText = color.toUpperCase();
    
    selectedLayer.options.markerColor = color;
    if(selectedLayer.options.customType === 'Marker') {
        selectedLayer.setIcon(createCustomMarkerIcon(color));
    } else if(selectedLayer.options.customType === 'Text' || selectedLayer.pm._shape === 'Text') {
        if(selectedLayer.getElement()) selectedLayer.getElement().style.color = color;
    }
    saveToDatabase();
}

function deleteSelectedShape() { 
    if (selectedLayer) { 
        map.removeLayer(selectedLayer); 
        selectedLayer = null; 
        document.getElementById('properties-panel').style.opacity = '0.5'; 
        document.getElementById('properties-panel').style.pointerEvents = 'none'; 
        updateLayersList(); 
        saveToDatabase();
    } 
}

function saveToDatabase() {
    const syncStatus = document.getElementById('sync-status');
    if(syncStatus) syncStatus.innerHTML = '<i class="ph ph-spinner animate-spin text-accent"></i> Saving...';

    const layers = map.pm.getGeomanLayers(); 
    const payload = { layers: [] };
    let currentHasFarmPin = false; 
    
    layers.forEach(layer => {
        let type = layer.options.customType || 'Shape';
        if (layer.pm && layer.pm._shape === 'Text') type = 'Text';
        else if (layer instanceof L.Circle) type = 'Circle'; 
        else if (layer instanceof L.Marker && type !== 'Text') type = 'Marker';

        let textContent = null;
        if(type === 'Text') {
             textContent = layer.pm.getText ? layer.pm.getText() : (layer.getElement() ? layer.getElement().innerHTML : '');
        }

        const safeOptions = {
            color: layer.options.color,
            fillColor: layer.options.fillColor,
            weight: layer.options.weight,
            fillOpacity: layer.options.fillOpacity,
            customType: layer.options.customType,
            markerColor: layer.options.markerColor,
            textMarker: layer.options.textMarker,
            isFarmPin: layer.options.isFarmPin 
        };
        
        if (layer.options.isFarmPin) currentHasFarmPin = true;

        payload.layers.push({ 
            id: L.stamp(layer).toString(), 
            type: type, 
            geojson: layer.toGeoJSON(), 
            properties: { 
                options: safeOptions, 
                radius: (layer instanceof L.Circle) ? layer.getRadius() : null,
                markerColor: layer.options.markerColor || null,
                text: textContent,
                placeName: layer.placeName || null // Save address
            } 
        });
    });

    const farmLat = {{ $userLat ?? 'null' }};
    if (farmLat && !currentHasFarmPin) {
        payload.layers.push({
            id: 'deleted-farm-pin',
            type: 'DeletedFarmPin',
            geojson: { type: 'Point', coordinates: [0,0] },
            properties: {}
        });
    }

    fetch(SYNC_URL, { 
        method: 'POST', 
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN }, 
        body: JSON.stringify(payload) 
    })
    .then(res => {
        if(!res.ok) throw new Error('Save fault detected.');
        if(syncStatus) syncStatus.innerHTML = '<i class="ph ph-check-circle text-green-400"></i> Synced';
    })
    .catch(err => {
        console.error('Auto-save error:', err);
        if(syncStatus) syncStatus.innerHTML = '<i class="ph ph-x-circle text-red-400"></i> Sync Failed';
    });
}

function loadFromDatabase() {
    const syncStatus = document.getElementById('sync-status');
    if(syncStatus) syncStatus.innerHTML = '<i class="ph ph-spinner animate-spin text-accent"></i> Loading layers...';

    fetch(SYNC_URL, { headers: { 'X-CSRF-TOKEN': CSRF_TOKEN } })
    .then(async res => {
        if (!res.ok) {
            const errData = await res.json().catch(() => ({}));
            throw new Error(errData.message || `Server returned ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        let deletedFlag = false;
        let hasFarmPin = false;

        if (data && Array.isArray(data)) {
            deletedFlag = data.some(item => item.type === 'DeletedFarmPin');
            
            data.forEach(item => {
                if (item.type === 'DeletedFarmPin') return; 
                
                let props = item.properties;
                let geojson = item.geojson;

                if (typeof props === 'string') { try { props = JSON.parse(props); } catch(e) { props = {}; } }
                if (typeof geojson === 'string') { try { geojson = JSON.parse(geojson); } catch(e) { geojson = {}; } }

                if (!geojson || !geojson.geometry || !geojson.geometry.coordinates) return;
                const coords = geojson.geometry.coordinates;
                let layer;

                if (item.type === 'Circle') { 
                    layer = L.circle([coords[1], coords[0]], { radius: props.radius, ...props.options }); 
                } 
                else if (item.type === 'Marker') { 
                    layer = L.marker([coords[1], coords[0]], { ...props.options }); 
                    layer.options.customType = 'Marker';
                    
                    layer.placeName = props.placeName || null;
                    if (!layer.placeName) fetchAddressForLayer(layer, coords[1], coords[0]);
                    
                    if(props.markerColor) {
                        layer.options.markerColor = props.markerColor;
                        layer.setIcon(createCustomMarkerIcon(props.markerColor));
                    }

                    if (props.options && props.options.isFarmPin) {
                        layer.options.isFarmPin = true;
                        hasFarmPin = true;
                        updateWeather(coords[1], coords[0]); 
                    }
                    
                    layer.on('dragend', function(e) {
                        const pos = e.target.getLatLng();
                        updateWeather(pos.lat, pos.lng);
                        fetchAddressForLayer(e.target, pos.lat, pos.lng);
                        saveToDatabase();
                    });
                } 
                else if (item.type === 'Text') {
                    layer = L.marker([coords[1], coords[0]], {
                        textMarker: true, customType: 'Text', text: props.text, markerColor: props.markerColor || '#ffffff',
                        icon: L.divIcon({ className: 'custom-text-transparent', html: props.text })
                    });
                    layer.on('add', function() { if(this.getElement()) this.getElement().style.color = this.options.markerColor; });
                }
                else { 
                    layer = L.geoJSON(geojson, { style: props.options }).getLayers()[0]; 
                    if (layer) layer.options.customType = 'Shape';
                }

                if (layer) { 
                    layer.addTo(map); 
                    if(item.type === 'Text') L.PM.reInitLayer(layer); 
                    if (layer instanceof L.Polygon) updateShapeAreaLabel(layer);
                    
                    layer.on('pm:edit pm:dragend pm:textchange pm:change pm:remove', function() { 
                        if (this instanceof L.Polygon) updateShapeAreaLabel(this); 
                        updateLayersList(); 
                        saveToDatabase(); 
                    }); 
                }
            });
        }

        const farmLat = {{ $userLat ?? 'null' }};
        const farmLng = {{ $userLng ?? 'null' }};
        
        if (farmLat && farmLng && !hasFarmPin && !deletedFlag) {
            const customFarmIcon = L.divIcon({
                className: 'farm-pin-icon',
                html: '<i class="fa-solid fa-location-dot"></i>',
                iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -32]
            });

            const userFarmMarker = L.marker([farmLat, farmLng], {
                icon: customFarmIcon,
                interactive: true,
                customType: 'Marker', 
                markerColor: '#0ea5e9',
                isFarmPin: true
            }).addTo(map);

            userFarmMarker.bindTooltip(`
                <div style="text-align:center;">
                    <strong style="color: #0ea5e9; font-size: 16px;">{{ $farmName ?? 'Your Location' }}</strong><br>
                    <span style="font-size: 12px; color: #ccc;">Technician Base</span>
                </div>
            `, { direction: 'top', className: 'area-tooltip', permanent: false });

            updateWeather(farmLat, farmLng);
            fetchAddressForLayer(userFarmMarker, farmLat, farmLng);

            userFarmMarker.on('dragend', function(e) {
                const pos = e.target.getLatLng();
                updateWeather(pos.lat, pos.lng);
                fetchAddressForLayer(e.target, pos.lat, pos.lng);
                saveToDatabase();
            });

            userFarmMarker.on('pm:remove', function() {
                saveToDatabase(); 
            });
            
            userFarmMarker.on('click', () => selectLayer(userFarmMarker));
            
            map.setView([farmLat, farmLng], 17);
            saveToDatabase(); 
        } else if (farmLat && farmLng) {
            map.setView([farmLat, farmLng], 17);
        }

        updateLayersList();
        if(syncStatus) syncStatus.innerHTML = '<i class="ph ph-lightning"></i> Real-time Sync';
    })
    .catch(err => {
        console.error("Database Load Error:", err);
        if(syncStatus) syncStatus.innerHTML = '<i class="ph ph-x-circle text-red-400"></i> Load Failed';
    });
}

map.on('pm:create', (e) => {
    if (e.shape === 'Marker') {
        e.layer.options.customType = 'Marker';
        e.layer.options.markerColor = '#0ea5e9';
        e.layer.setIcon(createCustomMarkerIcon('#0ea5e9'));
        
        const latlng = e.layer.getLatLng();
        updateWeather(latlng.lat, latlng.lng);
        fetchAddressForLayer(e.layer, latlng.lat, latlng.lng);
        
        e.layer.on('dragend', function(evt) {
            const pos = evt.target.getLatLng();
            updateWeather(pos.lat, pos.lng);
            fetchAddressForLayer(evt.target, pos.lat, pos.lng);
            saveToDatabase();
        });
    } 
    else if (e.shape === 'Text') {
        e.layer.options.customType = 'Text';
        e.layer.options.markerColor = '#ffffff';
        setTimeout(() => { if(e.layer.getElement()) e.layer.getElement().style.color = '#ffffff'; }, 100);
    } 
    else if(e.layer.setStyle) {
        e.layer.options.customType = 'Shape';
        e.layer.setStyle({ fillColor: document.getElementById('prop-fill-color').value, color: document.getElementById('prop-border-color').value, weight: parseInt(document.getElementById('prop-weight').value), fillOpacity: parseFloat(document.getElementById('prop-fill-opacity').value) });
    }

    if (e.layer instanceof L.Polygon) updateShapeAreaLabel(e.layer);
    
    e.layer.on('pm:edit pm:dragend pm:textchange pm:change pm:remove', function() { 
        if (this instanceof L.Polygon) updateShapeAreaLabel(this); 
        updateLayersList(); 
        saveToDatabase(); 
    });

    selectLayer(e.layer);
    saveToDatabase();
});

map.on('pm:remove', () => {
    selectedLayer = null; 
    document.getElementById('properties-panel').style.opacity = '0.5'; 
    document.getElementById('properties-panel').style.pointerEvents = 'none'; 
    updateLayersList();
    saveToDatabase();
});

loadFromDatabase();

let exportWidth = 1920; let exportHeight = 1080;
let boxLeft = null; let boxTop = null;
let isDraggingBox = false; let isResizingBox = false;
let startX, startY; let startLeft, startTop; let startWidth, startHeight;

function setupGuideBoxInteractions() {
    const guideBox = document.getElementById('export-guide-box');
    guideBox.classList.remove('transition-all', 'duration-300');
    
    if (!guideBox.querySelector('.drag-handle')) {
        guideBox.innerHTML = `
            <div class="drag-handle absolute -top-8 left-0 bg-accent text-white text-[11px] px-2.5 py-1 rounded-t cursor-move pointer-events-auto select-none flex items-center gap-1.5 shadow-xl font-sans font-medium">
                <i class="ph ph-arrows-out-cardinal text-xs"></i> Move Box
            </div>
            <div class="resize-handle absolute -bottom-2 -right-2 w-6 h-6 bg-accent border-2 border-panelBg rounded-full cursor-se-resize pointer-events-auto shadow-xl flex items-center justify-center text-white hover:scale-110 transition-transform">
                <i class="ph ph-corners-out text-xs font-bold"></i>
            </div>
        `;

        const dragHandle = guideBox.querySelector('.drag-handle');
        const resizeHandle = guideBox.querySelector('.resize-handle');

        dragHandle.addEventListener('pointerdown', (e) => {
            e.preventDefault(); e.stopPropagation();
            isDraggingBox = true; startX = e.clientX; startY = e.clientY;
            startLeft = boxLeft; startTop = boxTop;
            dragHandle.setPointerCapture(e.pointerId);
        });

        dragHandle.addEventListener('pointermove', (e) => {
            if (!isDraggingBox) return;
            const dx = e.clientX - startX; const dy = e.clientY - startY;
            boxLeft = startLeft + dx; boxTop = startTop + dy;
            renderGuideBox(false);
        });

        const endDrag = (e) => { if (isDraggingBox) { isDraggingBox = false; try { dragHandle.releasePointerCapture(e.pointerId); } catch(err) {} } };
        dragHandle.addEventListener('pointerup', endDrag);
        dragHandle.addEventListener('pointercancel', endDrag);

        resizeHandle.addEventListener('pointerdown', (e) => {
            e.preventDefault(); e.stopPropagation();
            isResizingBox = true; startX = e.clientX; startY = e.clientY;
            startWidth = exportWidth; startHeight = exportHeight;
            resizeHandle.setPointerCapture(e.pointerId);
        });

        resizeHandle.addEventListener('pointermove', (e) => {
            if (!isResizingBox) return;
            const dx = e.clientX - startX; const dy = e.clientY - startY;
            const scale = parseFloat(guideBox.dataset.scale) || 1;
            exportWidth = Math.max(200, startWidth + (dx / scale));
            exportHeight = Math.max(200, startHeight + (dy / scale));
            renderGuideBox(false);
        });

        const endResize = (e) => { if (isResizingBox) { isResizingBox = false; try { resizeHandle.releasePointerCapture(e.pointerId); } catch(err) {} } };
        resizeHandle.addEventListener('pointerup', endResize);
        resizeHandle.addEventListener('pointercancel', endResize);
    }
}

function renderGuideBox(forceCenter = false) {
    const guideBox = document.getElementById('export-guide-box');
    const wrapper = document.getElementById('map-wrapper');
    if (!wrapper || guideBox.classList.contains('hidden')) return;

    const wrapperRect = wrapper.getBoundingClientRect();
    const padding = 40; 
    const availableWidth = wrapperRect.width - padding;
    const availableHeight = wrapperRect.height - padding;

    const scale = Math.min(availableWidth / exportWidth, availableHeight / exportHeight, 1);
    const visualWidth = exportWidth * scale;
    const visualHeight = exportHeight * scale;

    guideBox.style.width = `${visualWidth}px`;
    guideBox.style.height = `${visualHeight}px`;

    if (forceCenter || boxLeft === null || boxTop === null) {
        boxLeft = (wrapperRect.width - visualWidth) / 2;
        boxTop = (wrapperRect.height - visualHeight) / 2;
    }

    boxLeft = Math.max(-visualWidth + 40, Math.min(boxLeft, wrapperRect.width - 40));
    boxTop = Math.max(-visualHeight + 40, Math.min(boxTop, wrapperRect.height - 40));

    guideBox.style.left = `${boxLeft}px`;
    guideBox.style.top = `${boxTop}px`;

    document.getElementById('custom-width').value = Math.round(exportWidth);
    document.getElementById('custom-height').value = Math.round(exportHeight);

    guideBox.dataset.scale = scale;
    guideBox.dataset.exportWidth = Math.round(exportWidth);
    guideBox.dataset.exportHeight = Math.round(exportHeight);
}

function setMapDimension(width, height) {
    exportWidth = width; exportHeight = height;
    const mapContainer = document.getElementById('map');
    const guideBox = document.getElementById('export-guide-box');

    mapContainer.style.width = '100%'; mapContainer.style.height = '100%';
    mapContainer.style.position = 'absolute'; mapContainer.style.top = '0'; mapContainer.style.left = '0';
    guideBox.classList.remove('hidden');

    setupGuideBoxInteractions();
    renderGuideBox(true); 
    map.invalidateSize();
}

function applyCustomDimensions() {
    const w = parseInt(document.getElementById('custom-width').value) || 1920;
    const h = parseInt(document.getElementById('custom-height').value) || 1080;
    setMapDimension(w, h);
}

function exportMapImage() {
    const format = document.getElementById('export-format').value;
    const qualitySelect = document.getElementById('export-quality').value;
    const exportBtn = document.getElementById('btn-export-img');
    const guideBox = document.getElementById('export-guide-box');
    const wrapper = document.getElementById('map-wrapper');

    exportBtn.innerHTML = '<i class="ph ph-spinner animate-spin text-lg"></i> Processing Snapshot...';
    exportBtn.disabled = true;

    const isGuideActive = !guideBox.classList.contains('hidden');
    if (isGuideActive) guideBox.style.display = 'none'; 

    html2canvas(document.getElementById('map'), {
        useCORS: true,
        allowTaint: false,
        ignoreElements: (element) => element.classList.contains('leaflet-control-zoom') || element.classList.contains('leaflet-pm-toolbar')
    }).then(canvas => {
        if (isGuideActive) guideBox.style.display = 'block';
        let outputCanvas = canvas;

        if (isGuideActive) {
            const targetW = parseInt(guideBox.dataset.exportWidth) || 1920;
            const targetH = parseInt(guideBox.dataset.exportHeight) || 1080;

            const croppedCanvas = document.createElement('canvas');
            croppedCanvas.width = targetW; croppedCanvas.height = targetH;
            const ctx = croppedCanvas.getContext('2d');

            const wrapperRect = wrapper.getBoundingClientRect();
            const boxRect = guideBox.getBoundingClientRect();

            const scaleX = canvas.width / wrapperRect.width;
            const scaleY = canvas.height / wrapperRect.height;

            const cropX = (boxRect.left - wrapperRect.left) * scaleX;
            const cropY = (boxRect.top - wrapperRect.top) * scaleY;
            const cropW = boxRect.width * scaleX;
            const cropH = boxRect.height * scaleY;

            ctx.drawImage(canvas, cropX, cropY, cropW, cropH, 0, 0, targetW, targetH);
            outputCanvas = croppedCanvas;
        }

        const mimeType = format === 'JPEG' ? 'image/jpeg' : 'image/png';
        const quality = qualitySelect.includes('High') ? 1.0 : (qualitySelect.includes('Medium') ? 0.75 : 0.5);
        
        const dataUrl = outputCanvas.toDataURL(mimeType, quality);
        const link = document.createElement('a');
        link.download = `cropsense-ai-export.${format.toLowerCase()}`;
        link.href = dataUrl;
        link.click();

        exportBtn.innerHTML = '<i class="ph ph-download-simple text-lg"></i> Export Render Snapshot';
        exportBtn.disabled = false;
    }).catch(err => {
        if (isGuideActive) guideBox.style.display = 'block';
        alert('Export configuration error.');
        exportBtn.innerHTML = '<i class="ph ph-download-simple text-lg"></i> Export Render Snapshot';
        exportBtn.disabled = false;
        console.error(err);
    });
}

// ===============================================
// RENDER OTHER FARMERS' FIELDS WITH ADDRESSES
// ===============================================
window.otherFarmMarkers = {};

function triggerOtherFarmZoom(lat, lng, markerId) {
    map.flyTo([lat, lng], 17, { duration: 0.8 });
    if(window.otherFarmMarkers[markerId]) {
        setTimeout(() => { window.otherFarmMarkers[markerId].openTooltip(); }, 800);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const otherFarms = @json($otherFarms ?? []);
    const otherFieldsList = document.getElementById('other-fields-list');
    let otherFieldsHTML = '';

    if (otherFarms.length === 0) {
        otherFieldsHTML = '<div class="text-center text-gray-500 text-xs py-4 italic">No other farms found.</div>';
    } else {
        otherFarms.forEach((farm, index) => {
            if (farm.latitude && farm.longitude) {
                const lat = parseFloat(farm.latitude);
                const lng = parseFloat(farm.longitude);
                const fName = farm.farm_name || 'Unknown Farm';
                const fSize = farm.farm_size || 'N/A';
                const fAddress = farm.address || 'Address not registered'; 
                const markerId = `other_farm_${index}`;

                const otherIcon = L.divIcon({
                    className: 'other-farm-pin',
                    html: '<i class="fa-solid fa-location-dot" style="color: #0ea5e9; font-size: 26px; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.8));"></i>',
                    iconSize: [26, 26],
                    iconAnchor: [13, 26],
                    popupAnchor: [0, -26]
                });

                const otherMarker = L.marker([lat, lng], {
                    icon: otherIcon,
                    interactive: true,
                    pmIgnore: true 
                }).addTo(map);

                window.otherFarmMarkers[markerId] = otherMarker;

                otherMarker.bindTooltip(`
                    <div style="text-align:left; max-width: 220px; font-family: sans-serif; padding: 3px;">
                        <strong style="color: #0ea5e9; font-size: 13px; display:block; margin-bottom:2px;"><i class="fa-solid fa-tractor me-1"></i> ${fName}</strong>
                        <span style="font-size: 11px; color: #fff; display:block; margin-bottom:4px;">📐 Area: <b>${fSize} ha</b></span>
                        <div style="border-top: 1px solid #444; padding-top: 4px; font-size: 10px; color: #bbb; line-height: 1.3;">
                            <i class="fa-solid fa-map-pin me-1" style="color: #ef4444;"></i> ${fAddress}
                        </div>
                    </div>
                `, { direction: 'top', className: 'custom-clean-tooltip' });

                otherMarker.on('click', function() {
                    map.flyTo([lat, lng], 17, { duration: 0.8 });
                });

                otherFieldsHTML += `
                    <div class="bg-darkBg p-3 rounded-lg border border-panelBorder hover:border-[#0ea5e9] cursor-pointer transition group" 
                         onclick="triggerOtherFarmZoom(${lat}, ${lng}, '${markerId}')">
                        <div class="flex items-center gap-2 text-gray-200 font-medium mb-1 text-sm">
                            <i class="ph-fill ph-map-pin text-[#0ea5e9]"></i> ${fName}
                        </div>
                        <div class="text-xs text-gray-400 mb-1">
                            Area: <span class="font-mono text-gray-300">${fSize} ha</span>
                        </div>
                        <div class="text-[11px] text-gray-500 line-clamp-2 italic border-t border-panelBorder pt-1 mt-1 group-hover:text-gray-400">
                            ${fAddress}
                        </div>
                    </div>
                `;
            }
        });
    }

    if(otherFieldsList) {
        otherFieldsList.innerHTML = otherFieldsHTML;
    }
});

window.addEventListener('resize', () => {
setTimeout(() => {
        if (typeof map !== 'undefined') {
            map.invalidateSize();
        }
    }, 500);    
    if (!document.getElementById('export-guide-box').classList.contains('hidden')) {
        renderGuideBox(true); 
    }
});
</script>
@endsection