@extends('layouts.farmer')

@section('title', 'Detection History • CROPSENSE AI')

@section('content')
<style>
    .knowledge-section { background: #1e2937; border-radius: 8px; padding: 16px; margin-bottom: 12px; border: 1px solid #334155; }
    .image-gallery img { transition: all 0.2s; cursor: pointer; border: 2px solid transparent; }
    .image-gallery img:hover { transform: scale(1.05); border-color: #3b82f6; z-index: 10; position: relative;}
    .img-container { position: relative; display: inline-block; }
    .btn-delete-img { position: absolute; top: 4px; right: 4px; padding: 2px 6px; font-size: 10px; z-index: 5;}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-success"><i class="fas fa-history me-2"></i> Your Detection History</h4>
        <p class="text-secondary mb-0">Review your past scans and recommended treatments</p>
    </div>
</div>

<div id="no-history-alert" class="alert bg-dark border-secondary text-center py-5 shadow-sm text-muted" style="{{ empty($detectionData) ? '' : 'display: none;' }}">
    <i class="fas fa-folder-open fa-3x mb-3"></i>
    <h5>No History Found</h5>
    <p>You haven't scanned any rice plants yet.</p>
    <a href="{{ route('farmer.detection') }}" class="btn btn-success mt-2">
        <i class="fas fa-camera me-1"></i> Start Scanning
    </a>
</div>

@php
    // --- AGRONOMIC DATA MAP FOR SEVERITY ---
    $severityMap = [
        'healthy_rice_plant' => 0, 'bacterial_leaf_blight' => 60, 'leaf_blast' => 80, 
        'rice_false_smut' => 30, 'sheath_blight' => 40, 'tungro_virus' => 85, 
        'brown_planthopper' => 90, 'leaf_folders' => 20, 'leafhopper' => 30, 
        'rice_bug' => 80, 'rice_gall_midge' => 40, 'rice_leaf_roller' => 20, 
        'rice_stem_borer' => 30, 'snail' => 75
    ];
@endphp

<div class="row g-4 mb-5" id="history-container">
    @if(!empty($detectionData))
        @foreach($detectionData as $det)
            @php
                $kb = $knowledgeBase[$det['class_key']] ?? [];
                $isPest = $det['is_pest'];
                
                // Fetch severity and assign color
                $severityVal = $severityMap[$det['class_key']] ?? 'N/A';
                $sevColor = 'text-light';
                if($severityVal === 0) $sevColor = 'text-success';
                elseif($severityVal <= 30) $sevColor = 'text-info';
                elseif($severityVal <= 50) $sevColor = 'text-warning';
                elseif($severityVal > 50) $sevColor = 'text-danger';
            @endphp
            <div class="col-lg-6">
                <div class="card bg-secondary bg-opacity-10 border-secondary h-100 position-relative">
                    
                    <div class="position-absolute top-0 end-0 me-3 mt-3 z-3 d-flex gap-2 align-items-center">
                        <span class="badge bg-success text-white px-3 py-2 fw-bold shadow-sm">
                            <i class="fas fa-check-circle me-1"></i> Synced
                        </span>
                        <button class="btn btn-sm btn-outline-danger shadow-sm bg-dark" onclick="deleteDetection('{{ $det['class_key'] }}')" title="Delete this record">
                            <i class="fas fa-trash me-1"></i>
                        </button>
                    </div>

                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="fs-2">{{ $isPest ? '🐛' : '🌾' }}</div>
                            <div>
                                <h5 class="mb-1 fw-bold text-white">{{ $det['class_name'] }}</h5>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <span class="badge {{ $isPest ? 'bg-warning text-dark' : 'bg-success' }}">{{ $isPest ? 'PEST' : 'DISEASE' }}</span>
                                    <span class="badge bg-secondary bg-opacity-50 border border-secondary text-light">Confidence: {{ $det['confidence'] }}%</span>
                                    <span class="badge bg-secondary bg-opacity-50 border border-secondary {{ $sevColor }}">Severity: {{ $severityVal }}{{ $severityVal !== 'N/A' ? '%' : '' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="knowledge-section">
                            <strong class="text-white d-block mb-2">Description / About</strong>
                            <div class="small text-light">{!! nl2br(e($kb['description'] ?? 'No description available.')) !!}</div>
                        </div>

                        <div class="knowledge-section">
                            <strong class="text-success d-block mb-2">Recommended Treatments</strong>
                            <div class="small text-light">{!! nl2br(e($kb['treatments'] ?? 'No data available yet.')) !!}</div>
                        </div>

                        <div class="knowledge-section">
                            <strong class="d-block mb-2 text-warning">Common Causes / Biology</strong>
                            <div class="small text-light">{!! nl2br(e($kb['causes'] ?? '—')) !!}</div>
                        </div>

                        @if(!$isPest)
                        <div class="knowledge-section">
                            <strong class="text-info d-block mb-2">Nutrient Deficiency</strong>
                            <div class="small text-light">{!! nl2br(e($kb['nutrient_deficiency'] ?? '—')) !!}</div>
                        </div>
                        @endif

                        <div class="knowledge-section">
                            <strong class="text-danger d-block mb-2">{{ $isPest ? 'Damage Symptoms' : 'Grain / Paddy Damage' }}</strong>
                            <div class="small text-light">{!! nl2br(e($kb['grain_damage'] ?? '—')) !!}</div>
                        </div>

                        @if($isPest)
                        <div class="knowledge-section">
                            <strong class="text-info d-block mb-2">Natural Enemies</strong>
                            <div class="small text-light">{!! nl2br(e($kb['natural_enemies'] ?? '—')) !!}</div>
                        </div>
                        @endif

                        <div class="knowledge-section mb-0">
                            <strong class="text-success d-block mb-2">Prevention Tips</strong>
                            <div class="small text-light">{!! nl2br(e($kb['prevention'] ?? '—')) !!}</div>
                        </div>

                        @if(!empty($det['images']))
                        <div class="mt-4 pt-3 border-top border-secondary">
                            <p class="text-secondary small mb-3">Uploaded Photos ({{ count($det['images']) }})</p>
                            <div class="image-gallery d-flex flex-wrap gap-2">
                                @foreach($det['images'] as $img)
                                    <div class="img-container">
                                        <img src="{{ $img }}" class="rounded-3 shadow-sm" style="width: 80px; height: 80px; object-fit: cover;" onclick="showImageModal('{{ addslashes($img) }}')">
                                        <button class="btn btn-danger btn-delete-img rounded-circle shadow" onclick="deleteImage('{{ $img }}')" title="Delete this image">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>

<div id="imageModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-0">
                <img id="modalImageBig" src="" class="img-fluid rounded shadow-lg" style="max-height: 85vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    const csrfToken = "{{ csrf_token() }}";
    const actionUrl = "{{ route('farmer.history.action') }}";
    let imgModal = null;

    document.addEventListener("DOMContentLoaded", () => {
        imgModal = new bootstrap.Modal(document.getElementById('imageModal'));
        
        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalImageBig').src = '';
        });
    });

    function showImageModal(src) {
        document.getElementById('modalImageBig').src = src;
        imgModal.show();
    }

    function deleteImage(imagePath) {
        if(!confirm("Are you sure you want to delete this specific image?")) return;
        
        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ action: 'delete_image', image_path: imagePath })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Failed to delete image.');
            }
        })
        .catch(err => console.error(err));
    }

    function deleteDetection(classKey) {
        if(!confirm("Are you sure you want to delete this entire detection record and all its images?")) return;

        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ action: 'delete_detection', class_key: classKey })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Failed to delete detection record.');
            }
        })
        .catch(err => console.error(err));
    }

// --- FIXED: Offline IndexedDB Script ---
let dbInstance = null;

document.addEventListener("DOMContentLoaded", async () => {
    const dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open('CropSenseDB', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    try {
        dbInstance = await dbPromise;
        
        if (!dbInstance.objectStoreNames.contains('offline_history')) return;

        const tx = dbInstance.transaction('offline_history', 'readonly');
        const store = tx.objectStore('offline_history');
        const request = store.getAll();

        request.onsuccess = () => {
            // FIXED: Correct variable naming to prevent the crash
            const allOfflineRecords = request.result; 

            const currentUserId = {{ Auth::id() }};
            const offlineRecords = allOfflineRecords.filter(record => record.user_id === currentUserId);

            if (offlineRecords && offlineRecords.length > 0) {
                const noHistoryAlert = document.getElementById('no-history-alert');
                if(noHistoryAlert) noHistoryAlert.style.display = 'none';

                const container = document.getElementById('history-container');
                
                offlineRecords.forEach(record => {
                    const isPest = record.groq_data?.is_pest || false;
                    const kb = record.groq_data || {};
                    const formattedName = record.class_key.replace(/_/g, ' ').toUpperCase();

                    // --- JAVASCRIPT SEVERITY LOGIC FOR OFFLINE RECORDS ---
                    const severityMapJS = {
                        'healthy_rice_plant': 0, 'bacterial_leaf_blight': 60, 'leaf_blast': 80, 'rice_false_smut': 30,
                        'sheath_blight': 40, 'tungro_virus': 85, 'brown_planthopper': 90, 'leaf_folders': 20,
                        'leafhopper': 30, 'rice_bug': 80, 'rice_gall_midge': 40, 'rice_leaf_roller': 20,
                        'rice_stem_borer': 30, 'snail': 75
                    };
                    
                    let severityVal = record.groq_data?.severity_percent ?? severityMapJS[record.class_key] ?? 'N/A';
                    
                    let sevColor = 'text-light';
                    if(severityVal === 0) sevColor = 'text-success';
                    else if(severityVal <= 30) sevColor = 'text-info';
                    else if(severityVal <= 50) sevColor = 'text-warning';
                    else if(severityVal > 50) sevColor = 'text-danger';

                    const severityText = severityVal !== 'N/A' ? `${severityVal}%` : severityVal;
                    // -----------------------------------------------------

                    // Helper to format text safely
                    const formatText = (text) => text ? text.replace(/\n/g, '<br>') : '—';

                    let extraSections = '';
                    if (isPest) {
                        extraSections = `
                            <div class="knowledge-section">
                                <strong class="text-danger d-block mb-2">Damage Symptoms</strong>
                                <div class="small text-light">${formatText(kb.grain_damage)}</div>
                            </div>
                            <div class="knowledge-section">
                                <strong class="text-info d-block mb-2">Natural Enemies</strong>
                                <div class="small text-light">${formatText(kb.natural_enemies)}</div>
                            </div>
                        `;
                    } else {
                        extraSections = `
                            <div class="knowledge-section">
                                <strong class="text-info d-block mb-2">Nutrient Deficiency</strong>
                                <div class="small text-light">${formatText(kb.nutrient_deficiency)}</div>
                            </div>
                            <div class="knowledge-section">
                                <strong class="text-danger d-block mb-2">Grain / Paddy Damage</strong>
                                <div class="small text-light">${formatText(kb.grain_damage)}</div>
                            </div>
                        `;
                    }

                    // Card HTML rendering the "Not sync yet" warning
                    const cardHtml = `
                        <div class="col-lg-6 offline-card" id="offline-record-${record.id}">
                            <div class="card bg-secondary bg-opacity-10 border-warning h-100 position-relative shadow" style="border-width: 2px;">
                            
                            <!-- TOP-RIGHT BADGE & DELETE BUTTON -->
                            <div class="position-absolute top-0 end-0 me-3 mt-3 z-3 d-flex gap-2 align-items-center">
                                <span class="badge bg-warning text-dark px-3 py-2 fw-bold shadow-sm sync-badge">
                                    <i class="fas fa-cloud-upload-alt me-1"></i> Not sync yet
                                </span>
                                <button class="btn btn-sm btn-outline-danger shadow-sm bg-dark" onclick="deleteOfflineRecord(${record.id}, this)" title="Delete offline record">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <div class="card-body p-4 opacity-75 mt-3">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="fs-2">${isPest ? '🐛' : '🌾'}</div>
                                    <div>
                                        <h5 class="mb-1 fw-bold text-white">${formattedName}</h5>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <span class="badge ${isPest ? 'bg-warning text-dark' : 'bg-success'}">${isPest ? 'PEST' : 'DISEASE'}</span>
                                            <span class="badge bg-secondary bg-opacity-50 border border-secondary text-light">Confidence: ${record.confidence}%</span>
                                            <span class="badge bg-secondary bg-opacity-50 border border-secondary ${sevColor}">Severity: ${severityText}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="knowledge-section">
                                    <strong class="text-white d-block mb-2">Description / About</strong>
                                    <div class="small text-light">${formatText(kb.description)}</div>
                                </div>

                                <div class="knowledge-section">
                                    <strong class="text-success d-block mb-2">Recommended Treatments</strong>
                                    <div class="small text-light">${formatText(kb.treatments)}</div>
                                </div>

                                <div class="knowledge-section">
                                    <strong class="d-block mb-2 text-warning">Common Causes / Biology</strong>
                                    <div class="small text-light">${formatText(kb.causes)}</div>
                                </div>

                                ${extraSections}

                                <div class="knowledge-section mb-0">
                                    <strong class="text-success d-block mb-2">Prevention Tips</strong>
                                    <div class="small text-light">${formatText(kb.prevention)}</div>
                                </div>

                                <div class="mt-4 pt-3 border-top border-secondary">
                                    <p class="text-secondary small mb-3">Offline Image (1)</p>
                                    <div class="image-gallery d-flex flex-wrap gap-2">
                                        <div class="img-container">
                                            <img src="${record.image_base64}" class="rounded-3 shadow-sm" style="width: 80px; height: 80px; object-fit: cover;" onclick="showImageModal(this.src)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    `;
                    
                    container.insertAdjacentHTML('afterbegin', cardHtml);
                });
            }
        };
    } catch(e) {
        console.log("No offline database found or error opening DB.");
    }
});

// --- NEW: Auto-Sync Listener for History Page ---
window.addEventListener('online', async () => {
    if (!dbInstance) return;
    
    console.log("Connection restored. Syncing records from history page...");
    
    const tx = dbInstance.transaction('offline_history', 'readonly');
    const store = tx.objectStore('offline_history');
    const request = store.getAll();

    request.onsuccess = async () => {
        const allRecords = request.result;
        const currentUserId = {{ Auth::id() }};
        const userRecords = allRecords.filter(record => record.user_id === currentUserId);

        if (userRecords.length > 0) {
            // 1. Visually change badges to "Syncing..."
            userRecords.forEach(record => {
                const badgeContainer = document.querySelector(`#offline-record-${record.id} .sync-badge`);
                if(badgeContainer) {
                    badgeContainer.className = 'badge bg-info text-dark px-3 py-2 fw-bold shadow-sm sync-badge';
                    badgeContainer.innerHTML = '<i class="fas fa-sync fa-spin me-1"></i> Syncing...';
                }
            });

            // 2. Process uploads
            for (const record of userRecords) {
                const { id, user_id, ...serverPayload } = record; 
                
                try {
                    const response = await fetch("{{ route('farmer.history.save') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({...serverPayload, user_id: currentUserId})
                    });

                    if (response.ok) {
                        // Remove record from IndexedDB
                        const delTx = dbInstance.transaction('offline_history', 'readwrite');
                        delTx.objectStore('offline_history').delete(id);

                        // 3. Swap badge color and text to "Synced"
                        const badgeContainer = document.querySelector(`#offline-record-${record.id} .sync-badge`);
                        if (badgeContainer) {
                            badgeContainer.className = 'badge bg-success text-white px-3 py-2 fw-bold shadow-sm sync-badge';
                            badgeContainer.innerHTML = '<i class="fas fa-check-circle me-1"></i> Synced';
                        }
                    }
                } catch(e) {
                    console.error("Failed to sync record", e);
                }
            }
            
            // 4. Brief delay to let the user see the green "Synced" state before reload
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    };
});

    // Delete a specific offline record from IndexedDB
    function deleteOfflineRecord(id, buttonElement) {
        if(!confirm("Are you sure you want to delete this unsynced offline record?")) return;

        if (!dbInstance) {
            alert("Database not ready.");
            return;
        }

        const tx = dbInstance.transaction('offline_history', 'readwrite');
        const store = tx.objectStore('offline_history');
        const request = store.delete(id);

        request.onsuccess = () => {
            // Remove the card from the screen visually
            const cardWrapper = buttonElement.closest('.offline-card');
            if (cardWrapper) {
                cardWrapper.remove();
            }

            // Check if there are any cards left, if not, show the "No History" alert
            const remainingCards = document.querySelectorAll('.col-lg-6');
            if (remainingCards.length === 0) {
                const noHistoryAlert = document.getElementById('no-history-alert');
                if (noHistoryAlert) noHistoryAlert.style.display = 'block';
            }
        };

        request.onerror = () => {
            alert("Failed to delete offline record.");
        };
    }
</script>
@endsection