@extends('layouts.admin')

@section('title', 'Admin • Documents')

@section('content')
<div class="container-fluid">
    <h4 class="mb-4 text-white">User Verification Documents</h4>

    <div class="row g-4">
        <!-- APPROVED COLUMN -->
        <div class="col-md-6">
            <h5 class="text-success border-bottom border-success pb-2"><i class="fas fa-check-circle me-2"></i> Approved</h5>
            <div class="pe-2" style="max-height: 75vh; overflow-y: auto; overflow-x: hidden;">
                @foreach($users->where('status', 'approved') as $user)
                    <div class="card bg-dark text-light border-secondary mb-3 shadow-sm">
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12 border-bottom border-secondary pb-2 mb-2">
                                    <h6 class="text-success mb-0">{{ $user->full_name }} <small class="text-muted">({{ ucfirst($user->role) }})</small></h6>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 small"><strong>Email:</strong> {{ $user->email }}</p>
                                    <p class="mb-1 small"><strong>Phone:</strong> {{ $user->phone ?? 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>DOB:</strong> {{ $user->dob ?? 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>Address:</strong> {{ $user->address ?? 'N/A' }}</p>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 small"><strong>Farm Name:</strong> {{ $user->farm_name ?? 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>Size:</strong> {{ $user->farm_size ? $user->farm_size . ' ha' : 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>Water Source:</strong> <span class="text-capitalize">{{ $user->water_source ?? 'N/A' }}</span></p>
                                </div>
                                <div class="col-12 text-center mt-3">
                                    <p class="text-muted small mb-1">ID Document ({{ strtoupper($user->id_type ?? 'N/A') }})</p>
                                    @if($user->document_photo)
                                        <img src="{{ str_starts_with($user->document_photo, 'data:image') ? $user->document_photo : 'data:image/jpeg;base64,' . $user->document_photo }}" class="img-fluid rounded border border-secondary" style="max-height: 200px; object-fit: contain;">
                                    @else
                                        <span class="text-muted fst-italic small">No Document</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- DECLINED COLUMN -->
        <div class="col-md-6">
            <h5 class="text-danger border-bottom border-danger pb-2"><i class="fas fa-times-circle me-2"></i> Declined</h5>
            <div class="pe-2" style="max-height: 75vh; overflow-y: auto; overflow-x: hidden;">
                @foreach($users->where('status', 'declined') as $user)
                    <div class="card bg-dark text-light border-secondary mb-3 shadow-sm">
                        <div class="card-body p-4">
                            <div class="row g-3">
                                <div class="col-12 border-bottom border-secondary pb-2 mb-2">
                                    <h6 class="text-danger mb-0">{{ $user->full_name }} <small class="text-muted">({{ ucfirst($user->role) }})</small></h6>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 small"><strong>Email:</strong> {{ $user->email }}</p>
                                    <p class="mb-1 small"><strong>Phone:</strong> {{ $user->phone ?? 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>DOB:</strong> {{ $user->dob ?? 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>Address:</strong> {{ $user->address ?? 'N/A' }}</p>
                                </div>
                                <div class="col-sm-6">
                                    <p class="mb-1 small"><strong>Farm Name:</strong> {{ $user->farm_name ?? 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>Size:</strong> {{ $user->farm_size ? $user->farm_size . ' ha' : 'N/A' }}</p>
                                    <p class="mb-1 small"><strong>Water Source:</strong> <span class="text-capitalize">{{ $user->water_source ?? 'N/A' }}</span></p>
                                </div>
                                <div class="col-12 text-center mt-3">
                                    <p class="text-muted small mb-1">ID Document ({{ strtoupper($user->id_type ?? 'N/A') }})</p>
                                    @if($user->document_photo)
                                        <img src="{{ str_starts_with($user->document_photo, 'data:image') ? $user->document_photo : 'data:image/jpeg;base64,' . $user->document_photo }}" class="img-fluid rounded border border-secondary" style="max-height: 200px; object-fit: contain;">
                                    @else
                                        <span class="text-muted fst-italic small">No Document</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection