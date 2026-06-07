@extends('layouts.farmer')

@section('title', 'CROPSENSE AI • Rice Disease & Pest Detector')

@section('content')
<style>
    .hidden { display: none !important; }

    /* Responsive Chat Toggle */
    .chat-toggle-btn {
        width: 60px; 
        height: 60px; 
        font-size: 28px; 
        z-index: 1045;
        bottom: 30px; /* Anchors to the bottom of the screen */
        right: 30px;  /* Anchors to the right of the screen */
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Responsive Chat Window */
    .chat-window {
        width: 380px; 
        height: 520px; 
        border: 1px solid #334155; 
        z-index: 1050;
        bottom: 100px; /* Floats right above the chat button */
        right: 30px;
    }

    /* Mobile Device Adjustments */
    @media (max-width: 576px) {
        /* Make chat full-screen on phones */
        .chat-window {
            width: 100% !important;
            height: 100dvh !important; /* Uses dynamic viewport height */
            margin: 0 !important;
            bottom: 0 !important;
            right: 0 !important;
            border-radius: 0 !important;
            z-index: 9999;
        }
        .chat-header { border-radius: 0 !important; }
        
        /* Adjust chat button position on small screens */
        .chat-toggle-btn {
            bottom: 20px !important;
            right: 20px !important;
        }

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
                        <div class="card-header border-secondary">
                            <h5 class="card-title mb-0">Upload Rice Plant Image</h5>
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
                                    <div class="col-12"><div id="treatment" class="p-3 border border-secondary rounded"></div></div>
                                    <div class="col-12"><div id="causes" class="p-3 border border-secondary rounded"></div></div>
                                    <div id="nutrient-section" class="col-12"><div id="nutrient" class="p-3 border border-secondary rounded"></div></div>
                                    <div id="damage-section" class="col-12 hidden"><div id="damage" class="p-3 border border-secondary rounded"></div></div>
                                    <div id="grain-section" class="col-12"><div id="grain" class="p-3 border border-secondary rounded"></div></div>
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

<button id="chat-toggle" class="btn btn-success rounded-circle position-fixed chat-toggle-btn shadow-lg">
    <i class="fa-solid fa-comment-dots"></i>
</button>

<div id="chat-window" class="position-fixed bg-dark rounded-4 shadow-lg chat-window" style="display:none;flex-direction:column;">
    <div class="chat-header d-flex justify-content-between align-items-center p-3 bg-success text-white rounded-top-4">
        <h5 class="mb-0 fw-bold">CROPSENSE AI Assistant 🌾</h5>
        <button id="chat-close" class="btn-close btn-close-white"></button>
    </div>
    <div id="chat-messages" class="flex-grow-1 p-3 overflow-auto" style="background:#1e2937;"></div>
    <div class="p-3 border-top border-secondary">
        <div class="input-group">
            <input id="chat-input" type="text" autocomplete="off" class="form-control bg-dark text-white border-secondary" placeholder="Ask about rice farming...">
            <button id="chat-send" class="btn btn-success"><i class="fa-solid fa-paper-plane"></i></button>
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
let currentBase64 = null; 
let lastClassKey = null;
let lastConfidence = 65;
let isModelReady = false;
let compressedBase64 = null;

async function compressImage(base64Image) {
    return new Promise((resolve) => {
        const img = new Image();
        img.src = base64Image;
        img.onload = () => {
            const canvas = document.createElement('canvas');
            const MAX_WIDTH = 800; 
            const scale = MAX_WIDTH / img.width;
            
            canvas.width = MAX_WIDTH;
            canvas.height = img.height * scale;

            const ctx = canvas.getContext('2d');
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

            resolve(canvas.toDataURL('image/jpeg', 0.7));
        };
    });
}

// ==================== MODEL LOADING ====================
async function loadModel() {
    const statusEl = document.getElementById('status');
    statusEl.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Loading model...`;
    try {
        model = await tmImage.load(modelURL, metadataURL);
        statusEl.innerHTML = `<i class="fa-solid fa-circle-check"></i> Model ready`;
        statusEl.className = "badge bg-success text-white";
        isModelReady = true;
    } catch (e) {
        console.error(e);
        statusEl.innerHTML = `<i class="fa-solid fa-circle-xmark"></i> Model failed`;
        statusEl.className = "badge bg-danger text-white";
    }
}

// ==================== UPLOAD & CLASSIFICATION ====================
function browsePhoto() { document.getElementById('file-input').click(); }

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

    const reader = new FileReader();
    reader.onload = async (e) => {
        currentBase64 = e.target.result; 
        compressedBase64 = await compressImage(currentBase64); 
        
        document.getElementById('preview-image').src = currentBase64;
        document.getElementById('preview-container').classList.remove('hidden');

        currentImage = new Image();
        currentImage.src = currentBase64;
        currentImage.onload = () => document.getElementById('classify-btn').disabled = false;
    };
    reader.readAsDataURL(file);
}

function clearPreview() {
    document.getElementById('preview-container').classList.add('hidden');
    document.getElementById('classify-btn').disabled = true;
    currentBase64 = null;
    currentImage = null;
    document.getElementById('results-panel').classList.add('hidden');
    document.getElementById('no-result').classList.remove('hidden');
}

async function classifyCurrentImage() {
    if (!isModelReady || !currentImage) return alert("Model not ready or no image selected.");

    const btn = document.getElementById('classify-btn');
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> CLASSIFYING...`;

    try {
        const predictions = await model.predict(currentImage);
        displayResults(predictions);
    } catch (err) {
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.innerHTML = original;
    }
}

// ==================== DISPLAY RESULTS ====================
function displayResults(predictions) {
    document.getElementById('no-result').classList.add('hidden');
    document.getElementById('results-panel').classList.remove('hidden');

    let filtered = predictions.filter(p => p.probability >= 0.01);
    filtered.sort((a, b) => b.probability - a.probability);
    const top = filtered[0];

    let rawClassName = top.className.trim().toLowerCase().replace(/\s+/g, '_');
    if (rawClassName === 'left_blast') rawClassName = 'leaf_blast'; 

    if (rawClassName === 'random') {
        document.getElementById('top-label').textContent = "Unrecognized Image";
        document.getElementById('top-confidence').innerHTML = `${Math.round(top.probability * 100)}%`;
        document.getElementById('severity-label').textContent = "UNKNOWN";
        document.getElementById('severity-message').textContent = "Please upload a clear photo of a rice plant.";
        document.getElementById('predictions-list').innerHTML = '';
        document.getElementById('treatment').innerHTML = '<p class="text-secondary mb-0">No data available.</p>';
        document.getElementById('causes').innerHTML = '';
        document.getElementById('prevention').innerHTML = '';
        document.getElementById('damage-section')?.classList.add('hidden');
        document.getElementById('nutrient-section')?.classList.add('hidden');
        document.getElementById('grain-section')?.classList.add('hidden');
        lastClassKey = null; 
        return; 
    }

    const className = rawClassName;
    lastClassKey = className;
    lastConfidence = Math.round(top.probability * 100);

    const isPest = Object.keys(pestNames).includes(className);
    const nameMap = isPest ? pestNames : diseaseNames;

    document.getElementById('top-label').textContent = nameMap[className] || top.className;
    document.getElementById('top-confidence').innerHTML = `${lastConfidence}%`;

    let html = '';
    filtered.forEach(pred => {
        let pName = pred.className.trim().toLowerCase().replace(/\s+/g, '_');
        if (pName === 'left_blast') pName = 'leaf_blast';
        const perc = (pred.probability * 100).toFixed(1);
        html += `<div class="d-flex justify-content-between mb-2"><span>${nameMap[pName] || pred.className}</span><span class="text-secondary">${perc}%</span></div>`;
    });
    document.getElementById('predictions-list').innerHTML = html;

    let severityLabel = "LOW", severityMessage = "Monitor plant.", severityPercent = 30, color = "text-info";
    if (className.includes("healthy")) {
        severityLabel = "HEALTHY"; severityMessage = "No action needed."; severityPercent = 0; color = "text-success";
    } else if (top.probability >= 0.80) {
        severityLabel = "SEVERE"; severityMessage = "Immediate action required!"; severityPercent = 95; color = "text-danger";
    } else if (top.probability >= 0.50) {
        severityLabel = "MODERATE"; severityMessage = "Apply treatment soon."; severityPercent = 65; color = "text-warning";
    }

    const severityLabelEl = document.getElementById('severity-label');
    severityLabelEl.textContent = severityLabel;
    severityLabelEl.className = `h4 mb-1 ${color}`;
    document.getElementById('severity-percent').textContent = severityPercent + "%";
    document.getElementById('severity-message').textContent = severityMessage;

    const kb = knowledgeBase[className] || {};

    document.getElementById('treatment').innerHTML = `<strong class="text-success"><i class="fa-solid fa-spray-can-sparkles me-2"></i>Treatment:</strong><p class="mt-2 mb-0">${kb.treatments || 'No specific treatment data found.'}</p>`;
    document.getElementById('causes').innerHTML = `<strong class="text-warning"><i class="fa-solid fa-question-circle me-2"></i>Causes:</strong><p class="mt-2 mb-0">${kb.causes || '—'}</p>`;
    document.getElementById('prevention').innerHTML = `<strong class="text-info"><i class="fa-solid fa-shield-heart me-2"></i>Prevention:</strong><p class="mt-2 mb-0">${kb.prevention || '—'}</p>`;

    if (isPest) {
        document.getElementById('nutrient-section').classList.add('hidden');
        document.getElementById('grain-section').classList.add('hidden');
        document.getElementById('damage-section').classList.remove('hidden');
        document.getElementById('damage').innerHTML = `<strong class="text-danger"><i class="fa-solid fa-wheat-awn me-2"></i>Damage:</strong><p class="mt-2 mb-0">${kb.grain_damage || '—'}</p>`;
    } else {
        document.getElementById('damage-section').classList.add('hidden');
        document.getElementById('nutrient-section').classList.remove('hidden');
        document.getElementById('grain-section').classList.remove('hidden');
        document.getElementById('nutrient').innerHTML = `<strong class="text-warning"><i class="fa-solid fa-leaf me-2"></i>Nutrient/Deficiency:</strong><p class="mt-2 mb-0">${kb.nutrient_deficiency || 'Not applicable'}</p>`;
        document.getElementById('grain').innerHTML = `<strong class="text-danger"><i class="fa-solid fa-seedling me-2"></i>Grain Impact:</strong><p class="mt-2 mb-0">${kb.grain_damage || 'Not applicable'}</p>`;
    }
}

// ==================== SAVE HISTORY VIA CONTROLLER ====================
async function saveCurrentDetection() {
    if (!lastClassKey || !compressedBase64) return alert("No detection to save.");
    
    try {
        const response = await fetch("{{ route('farmer.history.save') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                class_key: lastClassKey,
                confidence: lastConfidence,
                image_base64: compressedBase64 
            })
        });

        if (!response.ok) {
            const errorText = await response.text();
            console.error("Server Error Details:", errorText);
            return alert("❌ Server error occurred. Please check the console.");
        }

        const data = await response.json();
        if(data.success) {
            alert("✅ Detection saved to history successfully!");
        } else {
            alert("❌ Failed to save detection.");
        }
    } catch(e) {
        console.error("Network or parsing error:", e);
        alert("Server connection failed. Check console.");
    }
}

// ==================== CHATBOT ====================
function initChat() {
    const toggleBtn = document.getElementById('chat-toggle');
    const chatWindow = document.getElementById('chat-window');
    const closeBtn = document.getElementById('chat-close');
    const sendBtn = document.getElementById('chat-send');
    const input = document.getElementById('chat-input');

    toggleBtn.addEventListener('click', () => chatWindow.style.display = 'flex');
    closeBtn.addEventListener('click', () => chatWindow.style.display = 'none');
    sendBtn.addEventListener('click', sendChatQuery);
    input.addEventListener('keypress', e => { if (e.key === 'Enter') sendChatQuery(); });

    addChatMessage("Hello! 🌾 Ask me anything about rice farming.", false);
}

async function sendChatQuery() {
    const input = document.getElementById('chat-input');
    const query = input.value.trim();
    if (!query) return;

    addChatMessage(query, true);
    input.value = '';

    const typing = document.createElement('div');
    typing.id = 'typing-indicator';
    typing.className = 'd-flex justify-content-start mt-2';
    typing.innerHTML = `<div class="bg-secondary bg-opacity-25 text-white px-3 py-2 rounded"><i class="fa-solid fa-spinner fa-spin"></i> Thinking...</div>`;
    document.getElementById('chat-messages').appendChild(typing);

    try {
        const response = await fetch("{{ route('farmer.detection') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": "{{ csrf_token() }}" 
            },
            body: JSON.stringify({
                action: "chat_query",
                query: query,
                language: "en"
            })
        });

        const data = await response.json();
        document.getElementById('typing-indicator').remove();
        
        if (data && data.response) {
            addChatMessage(data.response, false);
        } else {
            addChatMessage("I'm sorry, I couldn't get a response right now.", false);
        }

    } catch (error) {
        document.getElementById('typing-indicator').remove();
        addChatMessage("I'm having trouble connecting right now.", false);
    }
}

function addChatMessage(text, isUser = false) {
    const container = document.getElementById('chat-messages');
    const msg = document.createElement('div');
    msg.className = `d-flex mt-2 ${isUser ? 'justify-content-end' : 'justify-content-start'}`;
    msg.innerHTML = `<div class="${isUser ? 'bg-success' : 'bg-secondary bg-opacity-25'} text-white px-3 py-2 rounded">${text}</div>`;
    container.appendChild(msg);
    container.scrollTop = container.scrollHeight;
}

// ==================== INITIALIZATION & CAMERA BRIDGE ====================
window.onload = async () => {
    await loadModel();
    setupUpload();
    initChat();

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