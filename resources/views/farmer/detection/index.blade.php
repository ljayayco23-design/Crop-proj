@extends('layouts.farmer')

@section('title', 'RICEGUARD AI • Rice Disease & Pest Detector')

@section('content')
<style>
    .hidden { display: none !important; }

    /* Mobile Device Adjustments */
    @media (max-width: 576px) {
        /* Shrink drop zone so it fits without scrolling */
        #drop-zone {
            padding: 1.5rem !important;
            min-height: 220px !important;
        }
        #drop-zone .btn {
            width: 100%; /* Full width button on mobile */
            padding-left: 10px !important;
            padding-right: 10px !important;
        }
        
        /* Scale text */
        .page-header-title h4 { font-size: 1.25rem; }
    }
</style>

<div class="nxl-content">
    <div class="page-header mb-4">
        <div class="page-header-title">
            <h4 class="m-b-10 fw-bold">Rice Disease & Pest Detector</h4>
        </div>
    </div>

    <div class="main-content">
        <div class="container-fluid">
            <div class="row g-4">

                <div class="col-lg-5">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Upload Rice Plant Image</h5>
                        <select id="language-selector" class="form-select form-select-sm w-auto bg-dark text-white border-secondary fw-bold">
                            <option value="tagalog" selected>Tagalog</option>
                            <option value="english">English</option>
                            <option value="cebuano">Cebuano</option>
                            <option value="hiligaynon">Hiligaynon</option>
                        </select>
                    </div>
                        <div class="card-body">

                            <div id="drop-zone" class="border border-dashed border-success rounded-3 p-5 text-center cursor-pointer mb-4" 
                                 style="min-height: 280px; background: rgba(16,185,129,0.05); cursor: pointer;">
                                <input type="file" id="file-input" accept="image/*" style="display:none;">
                                <i class="fa-solid fa-cloud-arrow-up fa-4x text-success mb-3"></i>
                                <h5 class="mb-2">Drop image here or</h5>
                                <button type="button" onclick="browsePhoto()" class="btn btn-success px-5 py-2">                                    
                                    <i class="fa-solid fa-folder-open me-2"></i> BROWSE PHOTO
                                </button>
                                <p class="text-muted mt-4 small">JPG or PNG • Clear rice leaf/plant photo</p>
                            </div>

                            <div id="preview-container" class="hidden mb-4">
                                <div class="d-flex justify-content-between mb-3">
                                    <span class="fw-medium">Selected Image</span>
                                    <button type="button" onclick="clearPreview()" class="btn btn-sm btn-outline-danger">
                                            <i class="fa-solid fa-trash"></i> Clear
                                    </button>
                                </div>
                                <div class="border border-secondary rounded-3 overflow-hidden text-center bg-black">
                                    <img id="preview-image" class="img-fluid" alt="Preview" style="max-height: 380px; object-fit: contain;">
                                </div>
                            </div>

                            <button type="button" onclick="classifyCurrentImage()" id="classify-btn" class="btn btn-success btn-lg w-100 py-3 fw-bold shadow" disabled> 
                                <i class="fa-solid fa-magnifying-glass me-2"></i> CLASSIFY IMAGE
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card bg-dark border-secondary h-100">
                        <div class="card-header border-secondary d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Classification Results</h5>
                            <div id="status" class="badge bg-warning text-dark">Model loading...</div>
                        </div>
                        <div class="card-body">

                            <div id="no-result" class="text-center py-5">
                                <i class="fa-solid fa-seedling fa-5x text-secondary mb-4 opacity-50"></i>
                                <h5>No image classified yet</h5>
                                <p class="text-secondary">Upload a clear photo or use the camera to start detection</p>
                            </div>

                            <div id="results-panel" class="hidden">
                                <div class="d-flex justify-content-end mb-4">
                                    <button type="button" onclick="saveCurrentDetection()" class="btn btn-outline-success fw-bold">
                                            <i class="fa-solid fa-floppy-disk me-2"></i> SAVE TO HISTORY
                                    </button>
                                </div>

                                <div class="mb-4">
                                    <div class="d-flex align-items-baseline gap-3">
                                        <div id="top-label" class="h3 mb-0 text-success fw-bold"></div>
                                        <div id="top-confidence" class="display-6 fw-bold text-white"></div>
                                    </div>
                                </div>

                                <div id="predictions-list" class="mb-4 p-3 border border-secondary rounded bg-dark"></div>

                                <div class="card mb-4 bg-secondary bg-opacity-10 border-0">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col">
                                                <div id="severity-label" class="h4 mb-1 fw-bold"></div>
                                                <p id="severity-message" class="mb-0 text-light"></p>
                                            </div>
                                            <div class="col-auto text-end">
                                                <small class="text-light">Damage Level</small>
                                                <div id="severity-percent" class="h3 fw-bold text-white mb-0"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12"><div id="description" class="p-3 border border-secondary rounded"></div></div>
                                    <div class="col-12"><div id="treatment" class="p-3 border border-secondary rounded"></div></div>
                                    <div class="col-12"><div id="causes" class="p-3 border border-secondary rounded"></div></div>
                                    <div id="nutrient-section" class="col-12"><div id="nutrient" class="p-3 border border-secondary rounded"></div></div>
                                    <div id="damage-section" class="col-12 hidden"><div id="damage" class="p-3 border border-secondary rounded"></div></div>
                                    <div id="grain-section" class="col-12"><div id="grain" class="p-3 border border-secondary rounded"></div></div>
                                    <div id="natural-enemies-section" class="col-12 hidden"><div id="natural-enemies" class="p-3 border border-secondary rounded"></div></div>
                                    <div class="col-12"><div id="prevention" class="p-3 border border-secondary rounded"></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@3.21.0/dist/tf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@teachablemachine/image@0.8.4/dist/teachablemachine-image.min.js"></script>

<script>
// ==================== DATA ====================
const diseaseNames = @json($diseaseNames ?? []);
const pestNames = @json($pestNames ?? []);
const knowledgeBase = @json($knowledgeBase ?? []);

const modelURL = "{{ asset('model/model.json') }}";
const metadataURL = "{{ asset('model/metadata.json') }}";

let model = null;
let currentImage = null;
let currentObjectURL = null;
let lastClassKey = null;
let lastConfidence = 65;
let isModelReady = false;
window.compressedBase64 = null; // Global reference for chatbot image analysis context
let currentGroqData = null; 

let lastPredictions = null;
let isShowingFallback = false;

const uiTranslations = {
    english: { description: "Description / About", treatment: "Treatment", causes: "Causes", prevention: "Prevention", nutrient: "Nutrient / Deficiency", grain: "Grain Impact", damage: "Damage Symptoms", naturalEnemies: "Natural Enemies" },
    tagalog: { description: "Paglalarawan / Tungkol dito", treatment: "Paggamot", causes: "Mga Sanhi", prevention: "Pag-iwas", nutrient: "Kakulangan sa Nutrisyon", grain: "Epekto sa Butil", damage: "Sintomas ng Pinsala", naturalEnemies: "Mga Likas na Kaaway" },
    cebuano: { description: "Paghulagway / Mahitungod", treatment: "Pagtambal", causes: "Mga Hinungdan", prevention: "Pagpugong", nutrient: "Kulang sa Nutrisyon", grain: "Epekto sa Uhay", damage: "Sintomas sa Kadaot", naturalEnemies: "Mga Natural nga Kaaway" },
    hiligaynon: { description: "Paglaragway / Tuhoy Diri", treatment: "Pagbulong", causes: "Mga Rason", prevention: "Pagpangamlig", nutrient: "Kulang sa Nutrisyon", grain: "Epekto sa Uhay", damage: "Sintomas sang Halit", naturalEnemies: "Mga Natural nga Kontra" }
};

async function compressImageFile(file) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const objectUrl = URL.createObjectURL(file);
        
        img.onload = () => {
            URL.revokeObjectURL(objectUrl);
            const canvas = document.createElement('canvas');
            const MAX_WIDTH = 512; 
            const scale = Math.min(MAX_WIDTH / img.width, 1); 
            
            canvas.width = img.width * scale;
            canvas.height = img.height * scale;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            resolve(canvas.toDataURL('image/jpeg', 0.6)); 
        };
        img.onerror = (err) => reject(err);
        img.src = objectUrl;
    });
}

async function loadModel() {
    const statusEl = document.getElementById('status');
    statusEl.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Loading TF model...`;
    try {
        model = await tmImage.load(modelURL, metadataURL);
        statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> Model ready`;
        statusEl.className = "badge bg-success text-white";
        isModelReady = true;
    } catch (e) {
        statusEl.innerHTML = `<i class="fa-solid fa-bolt"></i> Groq Only Mode`;
        statusEl.className = "badge bg-primary text-white";
    }
}

window.browsePhoto = function() { document.getElementById('file-input').click(); };

function setupUpload() {
    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');

    fileInput.addEventListener('change', e => {
        if (e.target.files[0]) handleFile(e.target.files[0]);
    });

    dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.style.borderColor = '#10b981'; });
    dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor = ''; });
    dropZone.addEventListener('drop', e => {
        e.preventDefault();
        dropZone.style.borderColor = '';
        if (e.dataTransfer.files[0]) handleFile(e.dataTransfer.files[0]);
    });
}

async function handleFile(file) {
    if (!file.type.startsWith('image/')) return alert('Please select a valid image file');

    try {
        window.compressedBase64 = await compressImageFile(file); 
        
        document.getElementById('preview-image').src = window.compressedBase64;
        document.getElementById('preview-container').classList.remove('hidden');

        currentImage = new Image();
        currentImage.src = window.compressedBase64;
        currentImage.onload = () => document.getElementById('classify-btn').disabled = false;
    } catch (err) {
        alert("Failed to process the image.");
    }
}

function clearPreview() {
    document.getElementById('preview-container').classList.add('hidden');
    document.getElementById('classify-btn').disabled = true;
    window.compressedBase64 = null;
    currentImage = null;
    currentGroqData = null;
    document.getElementById('results-panel').classList.add('hidden');
    document.getElementById('no-result').classList.remove('hidden');
}

window.classifyCurrentImage = async function() {
    if (!currentImage || !window.compressedBase64) return alert("No image selected.");

    const btn = document.getElementById('classify-btn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ANALYZING WITH AI...`;

    const statusEl = document.getElementById('status');
    statusEl.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Groq analyzing...`;
    statusEl.className = "badge bg-info text-dark";

    try {
        const response = await fetch("{{ route('farmer.history.groq') }}", {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
           body: JSON.stringify({ 
                image_base64: window.compressedBase64,
                language: document.getElementById('language-selector').value
            })
        });

        if (response.status === 401 || response.status === 419) {
            alert("Your login session has expired. The page will now refresh so you can log back in.");
            window.location.reload();
            return;
        }

       const rawText = await response.text(); 
        let result;
        try {
            result = JSON.parse(rawText);
        } catch (parseError) {
            throw new Error("Server crashed or returned non-JSON data.");
        }

        if (result.success && result.data && result.data.class_key) {
            statusEl.innerHTML = `<i class="fa-solid fa-bolt"></i> Groq Ready`;
            statusEl.className = "badge bg-primary text-white";
            displayGroqResults(result.data);
            btn.disabled = false;
            btn.innerHTML = original;
            return; 
        } else {
            throw new Error(result.message || "Unknown API Error");
        }
    } catch (err) {
        statusEl.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> Groq failed. Using Fallback...`;
        statusEl.className = "badge bg-warning text-dark";

        if (!isModelReady) {
            alert("Both AI and Fallback Model failed.");
            statusEl.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Scan Failed`;
            statusEl.className = "badge bg-danger text-white";
        } else {
            const predictions = await model.predict(currentImage);
            displayResults(predictions);
            setTimeout(() => {
                statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> Model ready`;
                statusEl.className = "badge bg-success text-white";
            }, 4000);
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
};

function displayGroqResults(data) {
    currentGroqData = data;
    isShowingFallback = false;
    const lang = document.getElementById('language-selector').value;
    const t = uiTranslations[lang];
    
    document.getElementById('no-result').classList.add('hidden');
    document.getElementById('results-panel').classList.remove('hidden');

    lastClassKey = data.class_key;
    lastConfidence = data.confidence;

    const safeSet = (id, html) => { const el = document.getElementById(id); if(el) el.innerHTML = html; };

    document.getElementById('top-label').textContent = data.class_name;
    document.getElementById('top-confidence').innerHTML = `${data.confidence}%`;

    safeSet('predictions-list', `<div class="d-flex justify-content-between mb-2"><span class="text-primary fw-bold"><i class="fa-solid fa-bolt me-2"></i>Groq AI Primary Diagnosis</span><span class="text-primary">${data.confidence}%</span></div>`);

    let color = "text-info";
    if (data.severity_label === 'HEALTHY') color = "text-success";
    else if (data.severity_label === 'SEVERE') color = "text-danger";
    else if (data.severity_label === 'MODERATE') color = "text-warning";

    const severityLabelEl = document.getElementById('severity-label');
    if(severityLabelEl) {
        severityLabelEl.textContent = data.severity_label;
        severityLabelEl.className = `h4 mb-1 ${color}`;
    }
    safeSet('severity-percent', data.severity_percent + "%");
    safeSet('severity-message', data.severity_message);

    safeSet('description', `<strong class="text-white"><i class="fa-solid fa-circle-info me-2"></i>${t.description}:</strong><p class="mt-2 mb-0">${data.description || '—'}</p>`);
    safeSet('treatment', `<strong class="text-success"><i class="fa-solid fa-spray-can-sparkles me-2"></i>${t.treatment}:</strong><p class="mt-2 mb-0">${data.treatments || '—'}</p>`);
    safeSet('causes', `<strong class="text-warning"><i class="fa-solid fa-question-circle me-2"></i>${t.causes}:</strong><p class="mt-2 mb-0">${data.causes || '—'}</p>`);
    safeSet('prevention', `<strong class="text-info"><i class="fa-solid fa-shield-heart me-2"></i>${t.prevention}:</strong><p class="mt-2 mb-0">${data.prevention || '—'}</p>`);

    if (data.is_pest) {
        document.getElementById('nutrient-section')?.classList.add('hidden');
        document.getElementById('grain-section')?.classList.add('hidden');
        document.getElementById('damage-section')?.classList.remove('hidden');
        document.getElementById('natural-enemies-section')?.classList.remove('hidden');
        safeSet('damage', `<strong class="text-danger"><i class="fa-solid fa-wheat-awn me-2"></i>${t.damage}:</strong><p class="mt-2 mb-0">${data.grain_damage || '—'}</p>`);
        safeSet('natural-enemies', `<strong class="text-success"><i class="fa-solid fa-bug-slash me-2"></i>${t.naturalEnemies}:</strong><p class="mt-2 mb-0">${data.natural_enemies || '—'}</p>`);
    } else {
        document.getElementById('damage-section')?.classList.add('hidden');
        document.getElementById('natural-enemies-section')?.classList.add('hidden');
        document.getElementById('nutrient-section')?.classList.remove('hidden');
        document.getElementById('grain-section')?.classList.remove('hidden');
        safeSet('nutrient', `<strong class="text-warning"><i class="fa-solid fa-leaf me-2"></i>${t.nutrient}:</strong><p class="mt-2 mb-0">${data.nutrient_deficiency || '—'}</p>`);
        safeSet('grain', `<strong class="text-danger"><i class="fa-solid fa-seedling me-2"></i>${t.grain}:</strong><p class="mt-2 mb-0">${data.grain_damage || '—'}</p>`);
    }
}

function displayResults(predictions) {
    lastPredictions = predictions;
    isShowingFallback = true;
    currentGroqData = null;
    const lang = document.getElementById('language-selector').value;
    const t = uiTranslations[lang];

    document.getElementById('no-result').classList.add('hidden');
    document.getElementById('results-panel').classList.remove('hidden');
    const safeSet = (id, html) => { const el = document.getElementById(id); if(el) el.innerHTML = html; };

    let filtered = predictions.filter(p => p.probability >= 0.01);
    filtered.sort((a, b) => b.probability - a.probability);
    const top = filtered[0];

    const className = top.className.trim().toLowerCase().replace(/\s+/g, '_');
    lastClassKey = className;
    lastConfidence = Math.round(top.probability * 100);

    // --- AGRONOMIC DATA SEVERITY ESTIMATES ---
    // These reflect real-world potential yield loss/damage for each pest or disease
    const severityEstimates = {
        'healthy_rice_plant': { label: 'HEALTHY', percent: 0, message: 'The plant appears to be in good condition.' },
        'bacterial_leaf_blight': { label: 'SEVERE', percent: 60, message: 'Can cause up to 60% yield loss if left untreated during the tillering stage.' },
        'leaf_blast': { label: 'SEVERE', percent: 80, message: 'Highly destructive; neck blast infections can cause up to 80% yield loss.' },
        'rice_false_smut': { label: 'MODERATE', percent: 30, message: 'Generally causes 10-30% yield loss depending on weather and severity.' },
        'sheath_blight': { label: 'MODERATE', percent: 40, message: 'Often causes 20-50% yield loss, especially in dense, high-fertilizer canopies.' },
        'tungro_virus': { label: 'SEVERE', percent: 85, message: 'Can wipe out crops entirely if infection happens early in the vegetative stage.' },
        'brown_planthopper': { label: 'SEVERE', percent: 90, message: 'Causes severe hopperburn, leading to massive or complete yield loss.' },
        'leaf_folders': { label: 'LOW', percent: 20, message: 'Damage looks severe but usually only results in minor yield loss (up to 20%).' },
        'leafhopper': { label: 'MODERATE', percent: 30, message: 'Direct damage is moderate, but they are dangerous vectors for viral diseases.' },
        'rice_bug': { label: 'SEVERE', percent: 80, message: 'Sucks sap from developing grains, capable of causing up to 80% empty grains.' },
        'rice_gall_midge': { label: 'MODERATE', percent: 40, message: 'Damages tillers (onion shoots), causing moderate yield reduction.' },
        'rice_leaf_roller': { label: 'LOW', percent: 20, message: 'Similar to leaf folders; rarely causes total crop failure.' },
        'rice_stem_borer': { label: 'MODERATE', percent: 30, message: 'Causes deadhearts and whiteheads; typically results in 10-30% yield loss.' },
        'snail': { label: 'SEVERE', percent: 75, message: 'Golden apple snails can completely destroy young seedlings and seedbeds quickly.' }
    };

    const estimate = severityEstimates[className] || { label: 'UNKNOWN', percent: 0, message: 'Severity estimate data unavailable.' };

    let color = "text-secondary";
    if (estimate.label === 'HEALTHY') color = "text-success";
    else if (estimate.label === 'LOW') color = "text-info";
    else if (estimate.label === 'MODERATE') color = "text-warning";
    else if (estimate.label === 'SEVERE') color = "text-danger";

    const severityLabelEl = document.getElementById('severity-label');
    if (severityLabelEl) {
        severityLabelEl.textContent = estimate.label;
        severityLabelEl.className = `h4 mb-1 fw-bold ${color}`;
    }
    safeSet('severity-percent', estimate.percent + "%");
    safeSet('severity-message', estimate.message);
    // -----------------------------------------

    const isPest = Object.keys(pestNames).includes(className);
    const nameMap = isPest ? pestNames : diseaseNames;

    // Display model confidence for NAME identification
    document.getElementById('top-label').textContent = nameMap[className] || top.className;
    document.getElementById('top-confidence').innerHTML = `${lastConfidence}%`;

    let html = '';
    filtered.forEach(pred => {
        let pName = pred.className.trim().toLowerCase().replace(/\s+/g, '_');
        html += `<div class="d-flex justify-content-between mb-2"><span>${nameMap[pName] || pred.className}</span><span class="text-secondary">${(pred.probability * 100).toFixed(1)}%</span></div>`;
    });
    safeSet('predictions-list', html);

    const kb = knowledgeBase[className] || {};
    
    safeSet('description', `<strong class="text-white"><i class="fa-solid fa-circle-info me-2"></i>${t.description}:</strong><p class="mt-2 mb-0">${kb.description || '—'}</p>`);
    safeSet('treatment', `<strong class="text-success"><i class="fa-solid fa-spray-can-sparkles me-2"></i>${t.treatment}:</strong><p class="mt-2 mb-0">${kb.treatments || '—'}</p>`);
    safeSet('causes', `<strong class="text-warning"><i class="fa-solid fa-question-circle me-2"></i>${t.causes}:</strong><p class="mt-2 mb-0">${kb.causes || '—'}</p>`);
    safeSet('prevention', `<strong class="text-info"><i class="fa-solid fa-shield-heart me-2"></i>${t.prevention}:</strong><p class="mt-2 mb-0">${kb.prevention || '—'}</p>`);

    if (isPest) {
        document.getElementById('nutrient-section')?.classList.add('hidden');
        document.getElementById('grain-section')?.classList.add('hidden');
        document.getElementById('damage-section')?.classList.remove('hidden');
        document.getElementById('natural-enemies-section')?.classList.remove('hidden');
        safeSet('damage', `<strong class="text-danger"><i class="fa-solid fa-wheat-awn me-2"></i>${t.damage}:</strong><p class="mt-2 mb-0">${kb.grain_damage || '—'}</p>`);
        safeSet('natural-enemies', `<strong class="text-success"><i class="fa-solid fa-bug-slash me-2"></i>${t.naturalEnemies}:</strong><p class="mt-2 mb-0">${kb.natural_enemies || '—'}</p>`);
    } else {
        document.getElementById('damage-section')?.classList.add('hidden');
        document.getElementById('natural-enemies-section')?.classList.add('hidden');
        document.getElementById('nutrient-section')?.classList.remove('hidden');
        document.getElementById('grain-section')?.classList.remove('hidden');
        safeSet('nutrient', `<strong class="text-warning"><i class="fa-solid fa-leaf me-2"></i>${t.nutrient}:</strong><p class="mt-2 mb-0">${kb.nutrient_deficiency || '—'}</p>`);
        safeSet('grain', `<strong class="text-danger"><i class="fa-solid fa-seedling me-2"></i>${t.grain}:</strong><p class="mt-2 mb-0">${kb.grain_damage || '—'}</p>`);
    }

    currentGroqData = {
        is_pest: isPest,
        description: kb.description || '',
        treatments: kb.treatments || '',
        causes: kb.causes || '',
        nutrient_deficiency: kb.nutrient_deficiency || '',
        grain_damage: kb.grain_damage || '',
        prevention: kb.prevention || '',
        natural_enemies: kb.natural_enemies || ''
    };
}

document.getElementById('language-selector').addEventListener('change', function() {
    if (!document.getElementById('results-panel').classList.contains('hidden')) {
        if (!isShowingFallback && currentGroqData) {
            displayGroqResults(currentGroqData);
        } else if (isShowingFallback && lastPredictions) {
            displayResults(lastPredictions);
        }
    }
});

window.saveCurrentDetection = async function() {
    if (!lastClassKey || !window.compressedBase64) {
        alert("No detection to save.");
        return;
    }
    
    const payload = {
        user_id: {{ Auth::id() }},
        class_key: lastClassKey,
        confidence: lastConfidence,
        image_base64: window.compressedBase64,
        groq_data: currentGroqData 
    };

    const success = await sendToServer(payload);
    if (success) {
        alert("✅ Detection saved successfully to your history!");
    } else {
        try {
            await saveLocally(payload);
            alert("📱 Network unavailable. Detection saved locally to your device!");
        } catch (e) {
            alert("An error occurred saving offline.");
        }
    }
};

window.sendToServer = async function(payload) {
    try {
        const response = await fetch("{{ route('farmer.history.save') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        });
        return response.ok; 
    } catch (error) {
        return false; 
    }
};

const dbPromise = new Promise((resolve, reject) => {
    const request = indexedDB.open('CropSenseDB', 1);
    request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('offline_history')) {
            db.createObjectStore('offline_history', { keyPath: 'id', autoIncrement: true });
        }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

async function saveLocally(payload) {
    const db = await dbPromise;
    const tx = db.transaction('offline_history', 'readwrite');
    tx.objectStore('offline_history').add(payload);
    return tx.complete;
}

window.onload = async () => {
    await loadModel();
    setupUpload();

    const savedCameraImage = sessionStorage.getItem('capturedImage');
    if (savedCameraImage) {
        sessionStorage.removeItem('capturedImage');
        fetch(savedCameraImage)
            .then(res => res.blob())
            .then(blob => {
                const file = new File([blob], "camera_capture.jpg", { type: "image/jpeg" });
                handleFile(file);
                setTimeout(() => {
                    if(document.getElementById('classify-btn').disabled === false) {
                        classifyCurrentImage();
                    }
                }, 500);
            });
    }
};
</script>
@endsection