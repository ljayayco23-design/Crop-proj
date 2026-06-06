@extends('layouts.farmer')
@section('title', 'Farmer Dashboard • CROPSENSE AI')

@php
    // Embedded Laravel logic to ensure accurate UI metric display safely
    $user_id = Auth::id();
    $total_detections = DB::table('user_detections')->where('user_id', $user_id)->count();
    $affected = DB::table('user_detections')->where('user_id', $user_id)->where('class_key', 'not like', '%healthy%')->count();
    $healthy = DB::table('user_detections')->where('user_id', $user_id)->where('class_key', 'like', '%healthy%')->count();
    $recent = DB::table('user_detections')->where('user_id', $user_id)->orderBy('created_at', 'desc')->limit(5)->get();
@endphp

@section('content')
<div class="page-header mb-4">
    <h4 class="fw-bold text-white mb-1">🌾 CROPSENSE AI Dashboard</h4>
    <p class="text-secondary mb-0">Welcome back, {{ Auth::user()->full_name }} • Real-time farm overview</p>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-camera-retro fa-2x text-primary"></i>
                <span class="badge bg-primary bg-opacity-25 text-primary border border-primary px-2 py-1">Season</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Total Detections</h6>
            <h2 class="fw-bold text-white mb-0">{{ number_format($total_detections) }}</h2>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-bug fa-2x text-danger"></i>
                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger px-2 py-1">Attention</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Affected Plants</h6>
            <h2 class="fw-bold text-danger mb-0">{{ number_format($affected) }}</h2>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-seedling fa-2x text-success"></i>
                <span class="badge bg-success bg-opacity-25 text-success border border-success px-2 py-1">Good</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">Healthy Plants</h6>
            <h2 class="fw-bold text-success mb-0">{{ number_format($healthy) }}</h2>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6">
        <div class="section-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <i class="fa-solid fa-history fa-2x text-warning"></i>
                <span class="badge bg-warning bg-opacity-25 text-warning border border-warning px-2 py-1">Active</span>
            </div>
            <h6 class="text-secondary fw-bold text-uppercase mb-1">History Logs</h6>
            <h2 class="fw-bold text-white mb-0">{{ number_format($total_detections) }}</h2>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="section-card h-100 p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3"><h5 class="mb-0 fw-bold text-white"><i class="fa-solid fa-cloud-sun text-info me-2"></i> Today's Weather</h5></div>
            <div class="p-5 text-center">
                <div class="display-1 fw-bold text-info mb-2">32°C</div>
                <h4 class="text-secondary">Partly Cloudy</h4>
                <div class="d-flex justify-content-center gap-4 mt-4 text-start">
                    <div class="text-white"><i class="fa-solid fa-droplet text-info me-1"></i> 78% Hum</div>
                    <div class="text-white"><i class="fa-solid fa-wind text-info me-1"></i> 14 km/h</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="section-card h-100 p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-white"><i class="fa-solid fa-clock-rotate-left text-success me-2"></i> Recent Detections</h5>
                <a href="{{ route('farmer.history') }}" class="btn btn-sm btn-outline-success">View All</a>
            </div>
            <div class="p-0">
                @if($recent->isEmpty())
                    <div class="text-center py-5">
                        <i class="fa-solid fa-seedling fa-4x text-secondary mb-3 opacity-50"></i>
                        <p class="text-secondary mb-2">No detections recorded yet.</p>
                        <a href="{{ route('farmer.detection') }}" class="btn btn-success mt-2">Upload First Image</a>
                    </div>
                @else
                    <div class="list-group list-group-flush bg-transparent">
                        @foreach($recent as $item)
                        <div class="list-group-item bg-transparent border-secondary py-3 px-4 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fa-solid fa-leaf text-success fa-lg"></i>
                                <div>
                                    <strong class="text-white text-capitalize">{{ str_replace('_', ' ', $item->class_key) }}</strong>
                                    <small class="d-block text-secondary">{{ \Carbon\Carbon::parse($item->created_at)->format('M d, Y • h:i A') }}</small>
                                </div>
                            </div>
                            <span class="badge bg-success px-3 py-2">{{ $item->confidence }}% Confidence</span>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-12">
        <div class="section-card p-0 overflow-hidden">
            <div class="bg-dark border-bottom border-secondary p-3"><h5 class="mb-0 fw-bold text-white"><i class="fa-solid fa-bolt text-warning me-2"></i> Quick Actions</h5></div>
            <div class="p-4 row g-3">
                <div class="col-md-4"><a href="{{ route('farmer.detection') }}" class="btn btn-success w-100 py-4 fw-bold shadow-sm"><i class="fa-solid fa-upload fa-2x d-block mb-2"></i> Upload Image</a></div>
                <div class="col-md-4"><a href="{{ route('farmer.camera') }}" class="btn btn-outline-light border-secondary w-100 py-4 fw-bold shadow-sm"><i class="fa-solid fa-camera fa-2x d-block mb-2"></i> Live Camera</a></div>
                <div class="col-md-4"><a href="{{ route('farmer.history') }}" class="btn btn-outline-light border-secondary w-100 py-4 fw-bold shadow-sm"><i class="fa-solid fa-history fa-2x d-block mb-2"></i> View History</a></div>
            </div>
        </div>
    </div>
</div>
@endsection