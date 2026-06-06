@extends('layouts.admin')

@section('title', 'Knowledge Modifier History • RiceGuard AI')

@section('content')
<style>
    .version-card { transition: all 0.2s; }
    .version-card:hover { transform: translateY(-2px); }
    .type-badge { font-size: 0.85rem; padding: 4px 10px; }
</style>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-white">Knowledge Update History</h2>
            <p class="text-secondary">Track every change made to the shared knowledge base by Admins and Technicians.</p>
        </div>
    </div>
</div>

@if(empty($data))
    <div class="card bg-dark border-secondary">
        <div class="card-body text-center py-5 text-secondary">
            No knowledge records found in the database.
        </div>
    </div>
@else
    @foreach ($data as $type => $items)
        <div class="card bg-dark border-secondary shadow-sm mb-5">
            <div class="card-header border-secondary">
                <h5 class="mb-0 text-white fw-bold">{{ ucfirst($type) }}s</h5>
            </div>
            <div class="card-body">
                @foreach ($items as $jsonKey => $versions)
                    @php $original = $originalData[$jsonKey] ?? []; @endphp
                    <div class="border border-secondary rounded-3 p-4 mb-4 bg-secondary bg-opacity-10">
                        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-3">
                            <h5 class="fw-bold mb-0 text-white">{{ strtoupper(str_replace('_', ' ', $jsonKey)) }}</h5>
                            <span class="badge {{ $type === 'disease' ? 'bg-primary' : 'bg-danger' }} type-badge">
                                {{ strtoupper($type) }}
                            </span>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-5">
                                <div class="border border-secondary bg-dark p-4 rounded-3 h-100">
                                    <h6 class="text-warning mb-3 fw-bold"><i class="fa-solid fa-history me-2"></i> Original System Default</h6>
                                    <div class="small text-secondary">
                                        <strong class="text-white">Treatments:</strong> {!! nl2br(e($original['treatments'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Causes:</strong> {!! nl2br(e($original['causes'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Nutrient/Enemies:</strong> {!! nl2br(e($original['nutrient_deficiency'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Damage:</strong> {!! nl2br(e($original['grain_damage'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Prevention:</strong> {!! nl2br(e($original['prevention'] ?? '—')) !!}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                @foreach ($versions as $v)
                                <div class="version-card border border-success p-4 rounded-3 mb-3 bg-dark">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h6 class="text-success mb-0 fw-bold">
                                            Current DB Version • {{ \Carbon\Carbon::parse($v['updated_at'])->format('M d, Y h:i A') }}
                                        </h6>
                                        <span class="badge bg-success bg-opacity-25 text-success border border-success">
                                            Updated by: {{ $v['updated_by'] ?? 'System' }}
                                        </span>
                                    </div>
                                    <div class="small text-secondary">
                                        <strong class="text-white">Treatments:</strong> {!! nl2br(e($v['treatments'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Causes:</strong> {!! nl2br(e($v['causes'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Nutrient/Enemies:</strong> {!! nl2br(e($v['nutrient_deficiency'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Damage:</strong> {!! nl2br(e($v['grain_damage'] ?? '—')) !!}<br><br>
                                        <strong class="text-white">Prevention:</strong> {!! nl2br(e($v['prevention'] ?? '—')) !!}
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
@endif
@endsection