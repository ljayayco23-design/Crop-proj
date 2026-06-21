@extends('layouts.technician') {{-- Fixed layout inheritance --}}

@section('title', 'CROPSENSE AI • Verified Documents')

@section('content')
<style>
    /* Glassmorphism & Hover Effects for Cards */
    .doc-card {
        background: rgba(30, 41, 59, 0.7);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
    }
    .doc-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5);
        border-color: rgba(14, 165, 233, 0.5);
    }
    .doc-img-container {
        width: 100%;
        height: 220px;
        background: #0f172a;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    .doc-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.5s ease;
    }
    .doc-card:hover .doc-img {
        transform: scale(1.08);
    }
    .doc-info p {
        margin-bottom: 0.6rem;
        color: #cbd5e1;
        font-size: 0.9rem;
    }
    .badge-id {
        background: rgba(14, 165, 233, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(14, 165, 233, 0.3);
        font-size: 0.75rem;
        padding: 0.4em 0.8em;
        letter-spacing: 0.5px;
    }
    .icon-width {
        width: 20px;
        text-align: center;
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Header Section -->
    <div class="d-flex justify-content-between align-items-center mb-5">
        <div>
            <h4 class="mb-1 text-white fw-bold"><i class="fas fa-file-contract text-info me-2"></i> Verified Farmer Documents</h4>
            <p class="text-muted mb-0">Browse and manage approved verification files and farm details</p>
        </div>
    </div>

    <!-- Document Grid -->
    <div class="row g-4">
        @forelse($users->where('status', 'approved') as $user)
            <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-6">
                <div class="doc-card h-100 d-flex flex-column shadow-sm">
                    
                    <!-- Image Area -->
                    <div class="doc-img-container position-relative">
                        @if($user->document_photo)
                            <img src="{{ str_starts_with($user->document_photo, 'data:image') ? $user->document_photo : 'data:image/jpeg;base64,' . $user->document_photo }}" class="doc-img" alt="Document Photo">
                            <span class="position-absolute top-0 end-0 m-3 badge rounded-pill bg-success shadow"><i class="fas fa-check-circle me-1"></i> Verified</span>
                        @else
                            <div class="text-center text-muted">
                                <i class="fas fa-file-image fs-1 mb-2 opacity-25"></i>
                                <div class="small fst-italic">No Document Found</div>
                            </div>
                        @endif
                    </div>
                    
                    <!-- Information Area -->
                    <div class="p-4 doc-info flex-grow-1 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-4">
                            <div>
                                <h5 class="text-info mb-1 fw-bold">{{ $user->full_name }}</h5>
                                <span class="badge rounded-pill badge-id fw-semibold">{{ strtoupper($user->id_type ?? 'UNKNOWN ID') }}</span>
                            </div>
                        </div>
                        
                        <p><i class="fas fa-envelope text-secondary me-2 icon-width"></i> {{ $user->email }}</p>
                        <p><i class="fas fa-phone text-secondary me-2 icon-width"></i> {{ $user->phone ?? 'N/A' }}</p>
                        <p class="mb-0"><i class="fas fa-map-marker-alt text-secondary me-2 icon-width"></i> <span class="text-truncate d-inline-block align-bottom" style="max-width: 85%;">{{ $user->address ?? 'N/A' }}</span></p>
                        
                        <div class="mt-auto">
                            <hr class="border-secondary opacity-25 my-3">
                            <div class="d-flex justify-content-between align-items-center small bg-dark bg-opacity-50 p-2 rounded">
                                <span class="text-light"><i class="fas fa-tractor text-secondary me-2"></i> {{ $user->farm_name ?? 'N/A' }}</span>
                                <span class="text-success fw-bold bg-success bg-opacity-10 px-2 py-1 rounded">{{ $user->farm_size ? $user->farm_size . ' ha' : 'N/A' }}</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        @empty
            <!-- Empty State -->
            <div class="col-12">
                <div class="text-center py-5 doc-card w-100">
                    <i class="fas fa-folder-open fs-1 text-muted mb-3 opacity-50"></i>
                    <h5 class="text-white fw-bold">No Documents Found</h5>
                    <p class="text-muted">There are currently no approved farmer profiles to display.</p>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection