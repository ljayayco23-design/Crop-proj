@extends('layouts.farmer') 
@section('title', 'Weather • CROPSENSE AI')

@section('content')
<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold text-success mb-1">🌾 Weather for Rice Farmers</h2>
            <p class="text-secondary">Sagay City • Real-time insights for better decisions</p>
        </div>
    </div>

    @if(isset($error))
        <div class="alert alert-danger text-center p-4 rounded shadow-sm">
            <h5>❌ {{ $error }}</h5>
        </div>
    @else
        <div class="row g-4">
            <div class="col-lg-5">
                <div class="card shadow-sm h-100" style="background: #1e2937; border: 1px solid #334155;">
                    <div class="card-body text-center p-5">
                        <div class="display-1 fw-bold text-info mb-2">{{ $temp }}°C</div>
                        <h4 class="text-light">{{ $condition }}</h4>
                        <div class="d-flex justify-content-center gap-4 mt-4 text-start text-light">
                            <div><i class="fa-solid fa-droplet text-info"></i> {{ $humidity }}% Humidity</div>
                            <div><i class="fa-solid fa-wind text-info"></i> {{ $wind }} km/h Wind</div>
                            <div><i class="fa-solid fa-cloud-rain text-info"></i> {{ $rain }}% Rain</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card shadow-sm h-100 border-{{ $riskColor }}" style="background: #1e2937;">
                    <div class="card-body d-flex align-items-center">
                        <div class="d-flex align-items-center gap-4 w-100 p-3">
                            <div class="flex-shrink-0">
                                <span class="badge bg-{{ $riskColor }} fs-5 px-4 py-3 rounded">{{ strtoupper($riskLevel) }} RISK</span>
                            </div>
                            <div>
                                <h5 class="mb-1 text-light">Today's Farming Risk Level</h5>
                                <p class="text-secondary mb-0">Based on temperature, rain chance, and humidity</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4" style="background: #1e2937; border: 1px solid #334155;">
            <div class="card-header border-bottom border-secondary py-3">
                <h5 class="card-title mb-0 text-light">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i> Farmer Alerts & Recommendations
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    @foreach ($alerts as $alert)
                    <li class="list-group-item bg-transparent border-secondary py-3 text-light">
                        {{ $alert }}
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="text-center text-secondary small mt-4">
            💡 Tip: See symptoms in your field? Go to <strong>Upload</strong> or <strong>Live Camera</strong> for AI diagnosis.
        </div>
    @endif
</div>
@endsection