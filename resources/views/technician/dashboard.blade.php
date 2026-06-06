@extends('layouts.technician')
@section('title', 'Technician Dashboard • CROPSENSE AI')

@section('content')
<div class="page-header mb-4">
    <h4 class="fw-bold text-white mb-1">Technician Dashboard</h4>
    <p class="text-secondary mb-0">Welcome back, {{ Auth::user()->full_name }}!</p>
</div>

<div class="row g-4 mb-5">
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(16,185,129,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-users fa-3x text-success mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Farmers Supported</h6>
                <h2 class="fw-bold text-white mb-0">{{ number_format($totalFarmers ?? 0) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(59,130,246,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-database fa-3x text-primary mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Knowledge Entries</h6>
                <h2 class="fw-bold text-white mb-0">{{ DB::table('treatment_records')->count() ?? 0 }}</h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(245,158,11,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-camera-retro fa-3x text-warning mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Total Detections</h6>
                <h2 class="fw-bold text-white mb-0">{{ number_format($totalDetections ?? 0) }}</h2>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="prodigy-card p-4 h-100 text-center position-relative overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 h-100" style="background: linear-gradient(135deg, rgba(14,165,233,0.1), transparent); z-index:0;"></div>
            <div class="position-relative z-1">
                <i class="fas fa-check-circle fa-3x text-info mb-3"></i>
                <h6 class="text-secondary fw-bold text-uppercase mb-1">Active Status</h6>
                <h2 class="fw-bold text-info mb-0">Online</h2>
            </div>
        </div>
    </div>
</div>

<h5 class="fw-bold text-white mb-3"><i class="fas fa-bolt text-warning me-2"></i>Quick Navigation</h5>
<div class="row g-3">
    <div class="col-md-4">
        <a href="{{ route('technician.records') }}" class="btn btn-info w-100 py-4 text-dark fw-bold shadow-sm rounded-3">
            <i class="fas fa-folder-open fa-2x d-block mb-2"></i> Farmers Records
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('technician.live_com') }}" class="btn btn-primary w-100 py-4 fw-bold shadow-sm rounded-3">
            <i class="fas fa-comments fa-2x d-block mb-2"></i> Live Chat
        </a>
    </div>
    <div class="col-md-4">
        <a href="{{ route('technician.announcement') }}" class="btn btn-warning w-100 py-4 text-dark fw-bold shadow-sm rounded-3">
            <i class="fas fa-bullhorn fa-2x d-block mb-2"></i> Announcements
        </a>
    </div>
</div>
@endsection