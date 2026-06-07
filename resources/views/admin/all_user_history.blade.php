@extends('layouts.admin')

@section('title', 'CROPSENSE AI • All Users History')

@section('content')
<style>
    .detection-card { transition: all 0.3s ease; background-color: #1e293b; border: 1px solid #334155; }
    .detection-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.15); border-color: #10b981; }
    
    .image-gallery img { 
        width: 85px; height: 85px; object-fit: cover; 
        border-radius: 12px; border: 2px solid #334155; 
        cursor: pointer; transition: all 0.2s;
    }
    .image-gallery img:hover { transform: scale(1.08); border-color: #10b981; }
    
    .scrollable-section { max-height: 720px; overflow-y: auto; padding-right: 12px; }
    .scrollable-section::-webkit-scrollbar { width: 6px; }
    .scrollable-section::-webkit-scrollbar-thumb { background: #3b82f6; border-radius: 20px; }
    
    .kb-box { background: rgba(15, 23, 42, 0.6); border: 1px solid #334155; padding: 15px; border-radius: 8px; margin-top: 8px; }
</style>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-white mb-1">All Farmers History</h4>
            <p class="text-secondary mb-0">Complete detection history for all registered farmers.</p>
        </div>
    </div>

    @if(empty($allUsersData))
        <div class="card bg-dark border-secondary">
            <div class="card-body text-center py-5 text-muted">
                <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                <h5>No farmer data found yet.</h5>
            </div>
        </div>
    @else
        @foreach ($allUsersData as $userData)
            <div class="card bg-dark border-secondary shadow-sm mb-5">
                <div class="card-header border-secondary d-flex align-items-center gap-3 py-3">
                    <div class="bg-success text-white rounded-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-size: 24px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-white">{{ $userData['user_name'] }}</h5>
                        <small class="text-success">{{ $userData['email'] }}</small>
                    </div>
                </div>
                
                <div class="card-body p-4">
                    <h5 class="mb-4 fw-bold text-light"><i class="fa-solid fa-magnifying-glass text-info me-2"></i> Detection History</h5>
                    
                    <div class="row g-4">
                        <div class="col-lg-6">
                            <div class="card bg-transparent border-0 h-100">
                                <h6 class="mb-3 text-white"><span class="fs-4 me-2">🌾</span> Detected Diseases</h6>
                                <div class="scrollable-section">
                                    @php $diseases = array_filter($userData['detectionData'], fn($d) => $d['is_pest'] == 0); @endphp
                                    
                                    @forelse($diseases as $det)
                                        @php $kb = $knowledgeBase[$det['class_key']] ?? []; @endphp
                                        <div class="detection-card p-4 rounded-4 mb-4">
                                            <div class="d-flex align-items-center gap-3 mb-4">
                                                <div class="bg-success bg-opacity-25 text-success rounded-3 d-flex align-items-center justify-content-center" style="width: 55px; height: 55px; font-size: 28px;">🌾</div>
                                                <div>
                                                    <h5 class="fw-bold text-white mb-1">{{ $det['class_name'] }}</h5>
                                                    <span class="badge bg-success">DISEASE</span>
                                                </div>
                                            </div>

                                            <div class="row g-3 text-sm">
                                                <div class="col-md-6">
                                                    <strong class="text-success">Recommended Treatments</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['treatments'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong class="text-info">Common Causes</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['causes'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong class="text-warning">Nutrient Deficiency</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['nutrient_deficiency'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong class="text-danger">Grain Damage</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['grain_damage'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-12">
                                                    <strong class="text-success">Prevention Tips</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['prevention'] ?? '—')) !!}</div>
                                                </div>
                                            </div>

                                            @if(!empty($det['images']))
                                            <div class="mt-4 pt-3 border-top border-secondary">
                                                <p class="text-muted small mb-2">Uploaded Photos</p>
                                                <div class="image-gallery d-flex flex-wrap gap-2">
                                                    @foreach($det['images'] as $img)
                                                    <img src="{{ (str_starts_with($img, 'http') || str_starts_with($img, '/') || str_starts_with($img, 'data:image/')) ? $img : 'data:image/jpeg;base64,' . $img }}" 
                                                        alt="Photo" 
                                                        onclick="showImageModal('{{ (str_starts_with($img, 'http') || str_starts_with($img, '/') || str_starts_with($img, 'data:image/')) ? $img : 'data:image/jpeg;base64,' . $img }}')">
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="text-center py-5 text-muted border border-secondary rounded-4" style="background: #1e293b;">
                                            <p class="mb-0">No diseases detected yet.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card bg-transparent border-0 h-100">
                                <h6 class="mb-3 text-white"><span class="fs-4 me-2">🐛</span> Detected Pests</h6>
                                <div class="scrollable-section">
                                    @php $pests = array_filter($userData['detectionData'], fn($d) => $d['is_pest'] == 1); @endphp
                                    
                                    @forelse($pests as $det)
                                        @php $kb = $knowledgeBase[$det['class_key']] ?? []; @endphp
                                        <div class="detection-card p-4 rounded-4 mb-4 border-warning border-opacity-50">
                                            <div class="d-flex align-items-center gap-3 mb-4">
                                                <div class="bg-warning bg-opacity-25 text-warning rounded-3 d-flex align-items-center justify-content-center" style="width: 55px; height: 55px; font-size: 28px;">🐛</div>
                                                <div>
                                                    <h5 class="fw-bold text-white mb-1">{{ $det['class_name'] }}</h5>
                                                    <span class="badge bg-warning text-dark">PEST</span>
                                                </div>
                                            </div>

                                            <div class="row g-3 text-sm">
                                                <div class="col-md-6">
                                                    <strong class="text-success">Recommended Treatments</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['treatments'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong class="text-info">Common Causes</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['causes'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong class="text-warning">Damage Symptoms</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['grain_damage'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <strong class="text-danger">Natural Enemies</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['nutrient_deficiency'] ?? '—')) !!}</div>
                                                </div>
                                                <div class="col-12">
                                                    <strong class="text-success">Prevention Tips</strong>
                                                    <div class="kb-box text-light">{!! nl2br(e($kb['prevention'] ?? '—')) !!}</div>
                                                </div>
                                            </div>

                                            @if(!empty($det['images']))
                                            <div class="mt-4 pt-3 border-top border-secondary">
                                                <p class="text-muted small mb-2">Uploaded Photos</p>
                                                <div class="image-gallery d-flex flex-wrap gap-2">
                                                    @foreach($det['images'] as $img)
                                                    <img src="{{ (str_starts_with($img, 'http') || str_starts_with($img, '/') || str_starts_with($img, 'data:image/')) ? $img : 'data:image/jpeg;base64,' . $img }}" 
                                                        alt="Photo" 
                                                        onclick="showImageModal('{{ (str_starts_with($img, 'http') || str_starts_with($img, '/') || str_starts_with($img, 'data:image/')) ? $img : 'data:image/jpeg;base64,' . $img }}')">                                                         
                                                    @endforeach
                                                </div>
                                            </div>
                                            @endif
                                        </div>
                                    @empty
                                        <div class="text-center py-5 text-muted border border-secondary rounded-4" style="background: #1e293b;">
                                            <p class="mb-0">No pests detected yet.</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        @endforeach
    @endif
</div>

<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
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
</script>
@endsection