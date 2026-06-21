@extends('layouts.admin')

@section('title', 'Knowledge Modifier History • RiceGuard AI')

@section('content')
<style>
    .scroll-container { 
        max-height: 80vh; 
        overflow-y: auto; 
        padding-right: 15px; 
    }
    .scroll-container::-webkit-scrollbar { width: 6px; }
    .scroll-container::-webkit-scrollbar-track { background: #1e2937; border-radius: 4px; }
    .scroll-container::-webkit-scrollbar-thumb { background: #475569; border-radius: 4px; }
    .scroll-container::-webkit-scrollbar-thumb:hover { background: #64748b; }
    
    .timeline-item { border-left: 2px solid #3b82f6; padding-left: 1.5rem; position: relative; margin-bottom: 1.5rem; }
    .timeline-item::before {
        content: ''; position: absolute; left: -6px; top: 0; width: 10px; height: 10px;
        background: #3b82f6; border-radius: 50%;
    }
    .timeline-item.groq-item { border-left-color: #0dcaf0; }
    .timeline-item.groq-item::before { background: #0dcaf0; }
    
    .accordion-button::after { filter: invert(1); }
    .accordion-button:not(.collapsed) { background-color: rgba(255,255,255,0.05); color: white; box-shadow: none; }
    .accordion-button { background-color: transparent; color: #cbd5e1; padding: 10px 15px; font-weight: 600; box-shadow: none; }
</style>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-white">Knowledge Update History</h2>
            <p class="text-secondary">Track every modification made to the shared knowledge base. View full records and identifying authors.</p>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- COLUMN 1: Fallback System Data -->
    <div class="col-lg-6">
        <div class="card bg-dark border-secondary h-100 shadow-sm">
            <div class="card-header border-secondary sticky-top bg-dark z-1 py-3">
                <h5 class="mb-0 text-white fw-bold"><i class="fas fa-database me-2 text-primary"></i> Primary Knowledge Base</h5>
            </div>
            <div class="card-body scroll-container">
                @if(empty($data))
                    <div class="text-center text-secondary py-5">No primary system records found.</div>
                @else
                    @foreach ($data as $type => $items)
                        @foreach ($items as $jsonKey => $versions)
                            <div class="mb-5">
                                <h5 class="fw-bold text-white border-bottom border-secondary pb-2 mb-4 d-flex align-items-center justify-content-between">
                                    {{ strtoupper(str_replace('_', ' ', $jsonKey)) }}
                                    <span class="badge {{ $type === 'disease' ? 'bg-primary' : 'bg-danger' }} fs-6">
                                        {{ strtoupper($type) }}
                                    </span>
                                </h5>
                                
                                <div class="accordion" id="accordion-primary-{{ $jsonKey }}">
                                    @foreach ($versions as $index => $v)
                                        <div class="timeline-item">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <span class="fw-bold text-white d-block">{{ \Carbon\Carbon::parse($v['updated_at'])->format('M d, Y h:i A') }}</span>
                                                    <span class="badge bg-primary bg-opacity-25 text-primary mt-1"><i class="fas fa-user-edit me-1"></i> {{ $v['updated_by'] ?? 'System' }}</span>
                                                </div>
                                            </div>
                                            
                                            <div class="accordion-item border-secondary bg-secondary bg-opacity-10 rounded">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-p-{{ $v['id'] }}">
                                                        View Full Record Data
                                                    </button>
                                                </h2>
                                                <div id="collapse-p-{{ $v['id'] }}" class="accordion-collapse collapse" data-bs-parent="#accordion-primary-{{ $jsonKey }}">
                                                    <div class="accordion-body text-secondary small">
                                                        <strong class="text-white">Description:</strong> <p>{{ $v['description'] ?? 'N/A' }}</p>
                                                        <strong class="text-success">Treatments:</strong> <p>{{ $v['treatments'] ?? 'N/A' }}</p>
                                                        <strong class="text-warning">Causes:</strong> <p>{{ $v['causes'] ?? 'N/A' }}</p>
                                                        @if($type === 'disease')
                                                            <strong class="text-info">Nutrient Deficiency:</strong> <p>{{ $v['nutrient_deficiency'] ?? 'N/A' }}</p>
                                                        @else
                                                            <strong class="text-danger">Damage Symptoms:</strong> <p>{{ $v['grain_damage'] ?? 'N/A' }}</p>

                                                            <strong class="text-info">Natural Enemies:</strong> <p>{{ $v['natural_enemies'] ?? 'N/A' }}</p>
                                                        @endif
                                                        <strong class="text-light">Prevention:</strong> <p>{{ $v['prevention'] ?? 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                @endif
            </div>
        </div>
    </div>

    <!-- COLUMN 2: Groq AI Discovered Data -->
    <div class="col-lg-6">
        <div class="card bg-dark border-info h-100 shadow-sm">
            <div class="card-header border-info sticky-top bg-dark z-1 py-3">
                <h5 class="mb-0 text-info fw-bold"><i class="fas fa-robot me-2"></i> Groq AI Discovered Data</h5>
            </div>
            <div class="card-body scroll-container">
                @if(empty($groqGrouped))
                    <div class="text-center text-secondary py-5">No Groq data has been saved or modified yet.</div>
                @else
                    @foreach ($groqGrouped as $type => $items)
                        @foreach ($items as $jsonKey => $versions)
                            <div class="mb-5">
                                <h5 class="fw-bold text-info border-bottom border-info pb-2 mb-4 d-flex align-items-center justify-content-between">
                                    {{ strtoupper(str_replace('_', ' ', $jsonKey)) }}
                                    <span class="badge {{ $type === 'disease' ? 'bg-primary' : 'bg-danger' }} fs-6">
                                        {{ strtoupper($type) }}
                                    </span>
                                </h5>
                                
                                <div class="accordion" id="accordion-groq-{{ $jsonKey }}">
                                    @foreach ($versions as $index => $v)
                                        <div class="timeline-item groq-item">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <span class="fw-bold text-white d-block">{{ \Carbon\Carbon::parse($v['updated_at'])->format('M d, Y h:i A') }}</span>
                                                    <span class="badge bg-info bg-opacity-25 text-info mt-1"><i class="fas fa-microchip me-1"></i> {{ $v['updated_by'] ?? 'Groq Auto' }}</span>
                                                </div>
                                            </div>
                                            
                                            <div class="accordion-item border-info bg-info bg-opacity-10 rounded">
                                                <h2 class="accordion-header">
                                                    <button class="accordion-button collapsed text-info" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-g-{{ $v['id'] }}">
                                                        View Full Record Data
                                                    </button>
                                                </h2>
                                                <div id="collapse-g-{{ $v['id'] }}" class="accordion-collapse collapse" data-bs-parent="#accordion-groq-{{ $jsonKey }}">
                                                    <div class="accordion-body text-secondary small">
                                                        <strong class="text-white">Description:</strong> <p>{{ $v['description'] ?? 'N/A' }}</p>
                                                        <strong class="text-success">Treatments:</strong> <p>{{ $v['treatments'] ?? 'N/A' }}</p>
                                                        <strong class="text-warning">Causes:</strong> <p>{{ $v['causes'] ?? 'N/A' }}</p>
                                                        @if($type === 'disease')
                                                            <strong class="text-info">Nutrient Deficiency:</strong> <p>{{ $v['nutrient_deficiency'] ?? 'N/A' }}</p>
                                                        @else
                                                            <strong class="text-danger">Damage Symptoms:</strong> <p>{{ $v['grain_damage'] ?? 'N/A' }}</p>
                                                            <strong class="text-info">Natural Enemies:</strong> <p>{{ $v['natural_enemies'] ?? 'N/A' }}</p>
                                                        @endif
                                                        <strong class="text-light">Prevention:</strong> <p>{{ $v['prevention'] ?? 'N/A' }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endforeach
                @endif
            </div>
        </div>
    </div>
</div>
@endsection