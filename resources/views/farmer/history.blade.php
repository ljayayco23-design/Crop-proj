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

@if(empty($detectionData))
    <div class="alert bg-dark border-secondary text-center py-5 shadow-sm text-muted">
        <i class="fas fa-folder-open fa-3x mb-3"></i>
        <h5>No History Found</h5>
        <p>You haven't scanned any rice plants yet.</p>
        <a href="{{ route('farmer.detection') }}" class="btn btn-success mt-2">
            <i class="fas fa-camera me-1"></i> Start Scanning
        </a>
    </div>
@else
    <div class="row g-4 mb-5">
        @foreach($detectionData as $det)
            @php
                $kb = $knowledgeBase[$det['class_key']] ?? [];
                $isPest = $det['is_pest'];
            @endphp
            <div class="col-lg-6">
                <div class="card bg-secondary bg-opacity-10 border-secondary h-100 position-relative">
                    
                    <div class="position-absolute top-0 end-0 me-3 mt-3 z-3">
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDetection('{{ $det['class_key'] }}')" title="Delete this record">
                            <i class="fas fa-trash me-1"></i> Delete
                        </button>
                    </div>

                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="fs-2">{{ $isPest ? '🐛' : '🌾' }}</div>
                            <div>
                                <h5 class="mb-1 fw-bold text-white">{{ $det['class_name'] }}</h5>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="badge {{ $isPest ? 'bg-warning text-dark' : 'bg-success' }}">{{ $isPest ? 'PEST' : 'DISEASE' }}</span>
                                    <span class="badge bg-secondary bg-opacity-50 border border-secondary text-light">Confidence: {{ $det['confidence'] }}%</span>
                                </div>
                            </div>
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
    </div>
@endif

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
        
        // Clear image source when modal closes
        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalImageBig').src = '';
        });
    });

    // Show Image Modal
    function showImageModal(src) {
        document.getElementById('modalImageBig').src = src;
        imgModal.show();
    }

    // Delete a single image
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

    // Delete an entire detection group
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
</script>
@endsection