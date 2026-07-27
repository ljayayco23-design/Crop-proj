@extends('layouts.technician')

@section('title', 'Farmers Records • RICEGUARD AI')

@section('content')
<style>
    .knowledge-section { background: #1e2937; border-radius: 8px; padding: 16px; margin-bottom: 12px; border: 1px solid #334155; }
    .image-gallery img { transition: all 0.2s; cursor: pointer; border: 2px solid transparent; }
    .image-gallery img:hover { transform: scale(1.05); border-color: #3b82f6; z-index: 10; position: relative;}
    .img-container { position: relative; display: inline-block; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">Farmers Records & Knowledge Base</h4>
        <p class="text-secondary mb-0">View farmer detections and manage global shared knowledge</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success bg-success text-white border-0 shadow-sm alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
@endif

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

@foreach($allUsersData as $userData)
    <div class="card bg-dark border-secondary shadow-sm mb-5">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-3 mb-4 border-bottom border-secondary pb-3">
                <div class="rounded-circle d-flex align-items-center justify-content-center bg-info text-white" style="width:48px;height:48px;font-size:24px;">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <h5 class="mb-0 fw-bold text-white">{{ $userData['user_name'] }}</h5>
                    <small class="text-info">{{ $userData['email'] }}</small>
                </div>
            </div>

            <div class="row g-4">
                @forelse($userData['detectionData'] as $det)
                    @php
                        $kb = $knowledgeBase[$det['class_key']] ?? [];
                        $isPest = $det['is_pest'];
                        $images = isset($det['images']) ? (is_array($det['images']) ? $det['images'] : json_decode($det['images'], true)) : [];
                        
                        // Detect if the loaded item belongs to the Groq subset
                        $isGroq = (isset($kb['is_groq']) && $kb['is_groq']) || 
                                  str_contains(strtolower($kb['updated_by'] ?? ''), 'groq') || 
                                  (isset($det['source']) && strtolower($det['source']) === 'groq');

                        // Severity calculation matching farmer side implementation[cite: 7]
                        $severityVal = $severityMap[$det['class_key']] ?? 'N/A';
                        $sevColor = 'text-light';
                        if($severityVal === 0) $sevColor = 'text-success';
                        elseif($severityVal !== 'N/A' && $severityVal <= 30) $sevColor = 'text-info';
                        elseif($severityVal !== 'N/A' && $severityVal <= 50) $sevColor = 'text-warning';
                        elseif($severityVal !== 'N/A' && $severityVal > 50) $sevColor = 'text-danger';
                        
                        $severityDisplay = $severityVal !== 'N/A' ? $severityVal . '%' : 'N/A';
                    @endphp
                    <div class="col-lg-6">
                        <div class="card bg-secondary bg-opacity-10 border-secondary h-100 position-relative">
                            
                            <div class="dropdown position-absolute top-0 end-0 me-3 mt-3 z-3">
                                <button class="btn btn-link text-secondary p-1" data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v fa-lg"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary shadow">
                                    <li>
                                        <a class="dropdown-item text-white" href="#" onclick="openEditModal('{{ $det['class_key'] }}', '{{ addslashes($det['class_name']) }}', {{ $isPest ? 'true' : 'false' }}, {{ $isGroq ? 'true' : 'false' }})">
                                            <i class="fas fa-pen me-2 text-info"></i> Edit Knowledge
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-white" href="#" onclick="printCard(this)">
                                            <i class="fas fa-print me-2 text-secondary"></i> Print Card
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="fs-2">{{ $isPest ? '🐛' : '🌾' }}</div>
                                    <div>
                                        <h5 class="mb-1 fw-bold text-white">{{ $det['class_name'] }}</h5>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <span class="badge {{ $isPest ? 'bg-warning text-dark' : 'bg-success' }}">{{ $isPest ? 'PEST' : 'DISEASE' }}</span>
                                            <span class="badge bg-secondary bg-opacity-50 border border-secondary text-light">Confidence: {{ $det['confidence'] ?? 'N/A' }}%</span>
                                            <span class="badge bg-secondary bg-opacity-50 border border-secondary {{ $sevColor }}">Severity: {{ $severityDisplay }}</span>
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

                                @if(!empty($images))
                                <div class="mt-4 pt-3 border-top border-secondary">
                                    <p class="text-secondary small mb-3">Uploaded Photos ({{ count($images) }})</p>
                                    <div class="image-gallery d-flex flex-wrap gap-2">
                                        @foreach($images as $img)
                                            @php
                                                $formattedSrc = strpos($img, 'data:image') === 0 ? $img : 'data:image/jpeg;base64,' . $img;
                                            @endphp
                                            <div class="img-container">
                                                <img src="{{ $formattedSrc }}" class="rounded-3 shadow-sm" 
                                                    style="width: 80px; height: 80px; object-fit: cover;" 
                                                    onclick="showImageModal('{{ addslashes($formattedSrc) }}')">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-4">
                        <p class="text-secondary mb-0">This farmer has no detection records yet.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
@endforeach

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

<div id="editModal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark border-secondary text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title fw-bold" id="modalTitle">Edit Knowledge Base</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="{{ route('technician.knowledge.update') }}" id="editForm">
                    @csrf
                    <input type="hidden" name="disease_key" id="modal_key">
                    <input type="hidden" name="is_groq" id="modal_is_groq" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-light">Description / About</label>
                        <textarea name="description" id="edit_description" rows="3" class="form-control bg-dark text-light border-secondary"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-success">Recommended Treatments</label>
                        <textarea name="treatments" id="edit_treatments" rows="4" class="form-control bg-dark text-light border-secondary"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-warning">Common Causes</label>
                        <textarea name="causes" id="edit_causes" rows="3" class="form-control bg-dark text-light border-secondary"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-info" id="label_nutrient">Nutrient Deficiency</label>
                            <textarea name="nutrient_deficiency" id="edit_nutrient" rows="3" class="form-control bg-dark text-light border-secondary"></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold text-info" id="label_enemies">Natural Enemies</label>
                            <textarea name="natural_enemies" id="edit_enemies" rows="3" class="form-control bg-dark text-light border-secondary"></textarea>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Grain Damage / Symptoms</label>
                        <textarea name="grain_damage" id="edit_grain" rows="3" class="form-control bg-dark text-light border-secondary"></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold text-success">Prevention Tips</label>
                        <textarea name="prevention" id="edit_prevention" rows="3" class="form-control bg-dark text-light border-secondary"></textarea>
                    </div>

                    <div class="d-flex gap-3 pt-3 border-top border-secondary">
                        <button type="button" data-bs-dismiss="modal" class="btn btn-secondary flex-fill fw-bold">Cancel</button>
                        <button type="submit" class="btn btn-info flex-fill fw-bold text-dark">Save Changes Globally</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const kbData = @json($knowledgeBase);
    let imgModal = null;
    let editModal = null;

    document.addEventListener("DOMContentLoaded", () => {
        imgModal = new bootstrap.Modal(document.getElementById('imageModal'));
        editModal = new bootstrap.Modal(document.getElementById('editModal'));

        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalImageBig').src = '';
        });
    });

    function showImageModal(src) {
        document.getElementById('modalImageBig').src = src;
        imgModal.show();
    }

    // UPDATED: Now receives and sets the isGroq state flags cleanly
    function openEditModal(key, name, isPest, isGroq = false) {
        document.getElementById('modal_key').value = key;
        document.getElementById('modal_is_groq').value = isGroq ? 1 : 0;
        document.getElementById('modalTitle').textContent = 'Edit Knowledge: ' + name + (isGroq ? ' (Groq AI Data)' : '');
        
        const data = kbData[key] || {};
        document.getElementById('edit_description').value = data.description || '';
        document.getElementById('edit_treatments').value = data.treatments || '';
        document.getElementById('edit_causes').value = data.causes || '';
        document.getElementById('edit_nutrient').value = data.nutrient_deficiency || '';
        document.getElementById('edit_enemies').value = data.natural_enemies || '';
        document.getElementById('edit_grain').value = data.grain_damage || '';
        document.getElementById('edit_prevention').value = data.prevention || '';
        
        if (isPest) {
            document.getElementById('edit_nutrient').parentElement.style.display = 'none';
            document.getElementById('edit_enemies').parentElement.style.display = 'block';
        } else {
            document.getElementById('edit_nutrient').parentElement.style.display = 'block';
            document.getElementById('edit_enemies').parentElement.style.display = 'none';
        }
        editModal.show();
    }

    function printCard(btn) {
        const card = btn.closest('.card');
        if (!card) return;
        const originalContent = document.body.innerHTML;
        document.body.innerHTML = `<div style="padding:20px; font-family:sans-serif;">${card.outerHTML}</div>`;
        window.print();
        document.body.innerHTML = originalContent;
        location.reload(); 
    }

    document.getElementById('editForm').addEventListener('submit', function() {
        if (!confirm('Save these changes to the shared knowledge base? This will immediately affect all farmers.')) {
            return false;
        }
    });
</script>
@endsection