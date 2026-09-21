@extends('layouts.farmer')

@section('title', 'Detection History • CROPSENSE AI')

@section('content')
<style>
    .knowledge-section { border-bottom: 1px solid rgba(255,255,255,0.08); padding: 10px 2px 12px; margin-bottom: 12px; }
    .knowledge-section:last-child { border-bottom: none; }
    .image-gallery img { transition: all 0.2s; cursor: pointer; border: 2px solid transparent; }
    .image-gallery img:hover { transform: scale(1.05); border-color: #3b82f6; z-index: 10; position: relative;}
    .img-container { position: relative; display: inline-block; }
    .btn-delete-img { position: absolute; top: 4px; right: 4px; padding: 2px 6px; font-size: 10px; z-index: 5;}

    /* Collapsed detection row + floating detail panel (centered modal style) */
    .detection-row { position: relative; }
    .detection-row-header { padding: 10px 4px; border-bottom: 1px solid #334155; flex-wrap: wrap; row-gap: 6px; }
    .detection-row-header:last-child { border-bottom: none; }
    .detection-name-toggle { color: #fff; text-decoration: underline; cursor: pointer; font-weight: 500; word-break: break-word; }
    .detection-name-toggle:hover { color: #3b82f6; }
    .detection-date { white-space: nowrap; }

    /* Each occurrence of a category (e.g. "Leaf Blast") is rendered as its
       own separate, full container below — own photo, own badges, own full
       description/treatment/etc. Stacked in normal flow; the outer
       .detection-detail-panel (max-height + overflow-y already set above)
       handles scrolling when there are many scans, so this list itself
       doesn't need its own nested scrollbar. */
    .detection-instances-list { }
    .instance-full-card { transition: border-color 0.15s ease; }
    .instance-full-card:hover { border-color: #3b82f6 !important; }
    .instance-full-card .knowledge-section { margin-bottom: 10px; }
    .instance-full-card .knowledge-section:last-child { margin-bottom: 0; }

    .detection-detail-backdrop {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        z-index: 1040;
    }
    .detection-detail-backdrop.show { display: block; }

    .detection-detail-panel {
        display: none;
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 1050;
        width: min(900px, 94vw);
        max-height: 90vh;
        overflow-y: auto;
        overflow-x: hidden;
        background: #0f172a;
        border: 1px solid #334155;
        border-radius: 10px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.6);
        padding: 28px;
    }

    /* Large screens: give the panel more breathing room */
    @media (min-width: 992px) {
        .detection-detail-panel { width: min(1000px, 85vw); padding: 32px; }
    }

    /* Small / mobile screens: use nearly the full viewport, tighter padding */
    @media (max-width: 576px) {
        .detection-detail-panel {
            width: 96vw;
            max-height: 92vh;
            padding: 16px;
            border-radius: 8px;
        }
        .detection-detail-panel .fs-2 { font-size: 1.5rem !important; }
        .detection-detail-panel h5 { font-size: 1.05rem; }
        .knowledge-section { padding: 12px; }
        .image-gallery img { width: 64px !important; height: 64px !important; }
        .detection-row-header { gap: 6px !important; }
        .detection-row-header > div { flex-wrap: wrap; row-gap: 6px; justify-content: flex-end; }
    }
    .detection-detail-panel.show { display: block; }
    .detection-detail-close { position: absolute; top: 10px; right: 10px; }
    .detection-detail-print { position: absolute; top: 10px; right: 52px; }

    /* ---------- Report-style detection header ----------
       Picture + type badge/name + confidence gauge, and the Severity /
       Damage Level stat chips — same visual language as the "Report the
       Problem" modal on the detection page, reused here for consistency. */
    .rg-type-badge {
        display: inline-flex; align-items: center; gap: 6px;
        background: rgba(16,185,129,0.14);
        color: #10b981;
        font-weight: 700;
        font-size: 0.8rem;
        padding: 7px 14px;
        border-radius: 999px;
        flex: 0 0 auto;
        align-self: flex-start;
    }
    .rg-type-badge.is-disease { background: rgba(239,68,68,0.14); color: #f87171; }

    .rg-report-thumb {
        width: 92px; height: 92px; object-fit: cover;
        border-radius: 12px; background: #000; flex: 0 0 auto;
        box-shadow: 0 4px 14px rgba(0,0,0,.4);
    }

    .rg-report-top { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .rg-report-top-info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
    .rg-report-name { font-size: 1.15rem; font-weight: 800; color: #fff; line-height: 1.2; }

    .rg-confidence-gauge { position: relative; width: 128px; height: 74px; flex: 0 0 auto; }
    .rg-confidence-gauge svg { width: 100%; height: 100%; display: block; overflow: visible; }
    .rg-confidence-gauge .gauge-track { fill: none; stroke: rgba(255,255,255,0.08); stroke-width: 16; stroke-linecap: round; }
    .rg-confidence-gauge .gauge-fill { fill: none; stroke-width: 16; stroke-linecap: round; }
    .rg-report-gauge-center {
        position: absolute; left: 0; right: 0; bottom: 2px;
        display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
    }
    .rg-report-gauge-center span { font-size: 1.1rem; font-weight: 800; line-height: 1.1; color: inherit; }
    .rg-report-gauge-center small { font-size: .65rem; font-weight: 600; color: #94a3b8; letter-spacing: .02em; }

    .rg-report-stat {
        background: rgba(255,255,255,0.04);
        border-radius: 10px;
        padding: 10px 12px;
        height: 100%;
    }
    .rg-report-stat-label { font-size: .72rem; font-weight: 600; color: #94a3b8; margin-bottom: 3px; }
    .rg-report-stat-value { font-size: 1rem; font-weight: 800; color: #fff; }
    .rg-report-stat-value.text-success { color: #22c55e !important; }
    .rg-report-stat-value.text-info { color: #38bdf8 !important; }
    .rg-report-stat-value.text-warning { color: #f59e0b !important; }
    .rg-report-stat-value.text-danger { color: #ef4444 !important; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1 text-success"><i class="fas fa-history me-2"></i> Your Detection History</h4>
        <p class="text-secondary mb-0">Review your past scans and recommended treatments</p>
    </div>
</div>

@php
    // --- AGRONOMIC DATA MAP FOR SEVERITY (must be defined before it's used below) ---
    $severityMap = [
        'healthy_rice_plant' => 0, 'bacterial_leaf_blight' => 60, 'leaf_blast' => 80, 
        'rice_false_smut' => 30, 'sheath_blight' => 40, 'tungro_virus' => 85, 
        'brown_planthopper' => 90, 'leaf_folders' => 20, 'leafhopper' => 30, 
        'rice_bug' => 80, 'rice_gall_midge' => 40, 'rice_leaf_roller' => 20, 
        'rice_stem_borer' => 30, 'snail' => 75
    ];

    // Model-classified scans never get a severity_label from the backend
    // (only a Groq-classified scan's own snapshot carries one) — this is
    // the same fallback label per class that the detection page's JS
    // estimate table (severityEstimates) uses for its percents above, so
    // a MODEL scan's Severity chip isn't left blank while Damage Level
    // (which already falls back to $severityMap) shows a percent.
    $severityLabelMap = [
        'healthy_rice_plant' => 'HEALTHY', 'bacterial_leaf_blight' => 'SEVERE', 'leaf_blast' => 'SEVERE',
        'rice_false_smut' => 'MODERATE', 'sheath_blight' => 'MODERATE', 'tungro_virus' => 'SEVERE',
        'brown_planthopper' => 'SEVERE', 'leaf_folders' => 'LOW', 'leafhopper' => 'MODERATE',
        'rice_bug' => 'SEVERE', 'rice_gall_midge' => 'MODERATE', 'rice_leaf_roller' => 'LOW',
        'rice_stem_borer' => 'MODERATE', 'snail' => 'SEVERE'
    ];

    $hasAnyDetections = collect($fieldSections ?? [])->contains(function ($sec) {
        return !empty($sec['diseases']) || !empty($sec['pests']);
    });
@endphp

<div id="no-history-alert" class="alert bg-dark border-secondary text-center py-5 shadow-sm text-muted" style="{{ $hasAnyDetections ? 'display: none;' : '' }}">    <i class="fas fa-folder-open fa-3x mb-3"></i>
    <h5>No History Found</h5>
    <p>You haven't scanned any rice plants yet.</p>
    <a href="{{ route('farmer.detection') }}" class="btn btn-success mt-2">
        <i class="fas fa-camera me-1"></i> Start Scanning
    </a>
</div>

<div id="history-container">
    @foreach($fieldSections ?? [] as $section)
        <div class="field-history-container mb-5 p-4 rounded-3 border border-secondary bg-secondary bg-opacity-10">
            <h5 class="fw-bold text-white mb-4 d-flex align-items-center gap-2">
                <i class="fas fa-map-marker-alt text-success"></i> {{ $section['label'] }}
            </h5>

            @if(empty($section['diseases']) && empty($section['pests']))
                <p class="text-secondary mb-0 small">No detections recorded for this field yet.</p>
            @else
                <div class="row g-4">
                    <div class="col-lg-6">
                        <h6 class="text-success text-uppercase small fw-bold mb-3"><i class="fas fa-disease me-1"></i> Diseases</h6>
                        @forelse($section['diseases'] as $det)
                            @php
                                $isPest = $det['is_pest'];
                                $detId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $det['field_key'] . '-' . $det['class_key']);
                                $instanceCount = count($det['instances']);
                            @endphp
                            <div class="detection-row">
                                <div class="detection-row-header d-flex justify-content-between align-items-center gap-2">
                                    <span class="detection-name-toggle" onclick="toggleDetectionDetail('{{ $detId }}')">{{ $det['class_name'] }}</span>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        <span class="badge bg-secondary bg-opacity-50 border border-secondary text-light small">
                                            {{ $instanceCount }} scan{{ $instanceCount === 1 ? '' : 's' }}
                                        </span>
                                        <span class="badge bg-success text-white px-2 py-1 fw-bold shadow-sm">
                                            <i class="fas fa-check-circle me-1"></i> Synced
                                        </span>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDetection('{{ $det['field_key'] }}', '{{ $det['class_key'] }}')" title="Delete this entire record">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="detection-detail-panel" id="detail-{{ $detId }}">
                                    <button type="button" class="btn btn-sm btn-outline-light detection-detail-print" onclick="printDetectionPanel('{{ $detId }}', '{{ $det['class_name'] }}')" title="Print all">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary detection-detail-close" onclick="toggleDetectionDetail('{{ $detId }}')" title="Close">
                                        <i class="fas fa-times"></i>
                                    </button>

                                    <div class="d-flex align-items-center gap-3 mb-4 pe-5">
                                        <div class="fs-2">{{ $isPest ? '🐛' : '🌾' }}</div>
                                        <div>
                                            <h5 class="mb-1 fw-bold text-white">{{ $det['class_name'] }}</h5>
                                            <div class="small text-secondary">
                                                {{ $instanceCount }} separate scan{{ $instanceCount === 1 ? '' : 's' }} of this {{ $isPest ? 'pest' : 'disease' }} — each shown below with its own info and photo.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="detection-instances-list d-flex flex-column gap-3">
                                        @foreach($det['instances'] as $inst)
                                            @php
                                                $instIsGroq = ($inst['source'] ?? 'model') === 'groq';
                                                $ikb = $inst['kb'] ?? [];
                                                $iSevLabel = $inst['severity_label'] ?? ($severityLabelMap[$det['class_key']] ?? null);
                                                $iSevPercent = $inst['severity_percent'] ?? ($severityMap[$det['class_key']] ?? null);
                                                $iSevMessage = $inst['severity_message'] ?? null;
                                                $iSevColor = 'text-light';
                                                if (is_numeric($iSevPercent)) {
                                                    if ((int) $iSevPercent === 0) $iSevColor = 'text-success';
                                                    elseif ($iSevPercent <= 30) $iSevColor = 'text-info';
                                                    elseif ($iSevPercent <= 50) $iSevColor = 'text-warning';
                                                    else $iSevColor = 'text-danger';
                                                }
                                                // Confidence gauge geometry — same half-circle arc math used
                                                // by the "Report the Problem" modal's confidence gauge.
                                                $iConfidence = max(0, min(100, (int) ($inst['confidence'] ?? 0)));
                                                $iArcLen = M_PI * 86;
                                                $iGaugeOffset = $iArcLen * (1 - $iConfidence / 100);
                                                $iGaugeColor = '#10b981';
                                                if ($iConfidence < 50) $iGaugeColor = '#ef4444';
                                                elseif ($iConfidence < 80) $iGaugeColor = '#f59e0b';
                                            @endphp
                                            <div class="instance-full-card rounded-3 border border-secondary bg-dark bg-opacity-25 p-3">
                                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        @if(!empty($inst['id']))
                                                        <span class="badge bg-dark border border-secondary text-light small instance-id-badge">
                                                            <i class="fas fa-hashtag me-1"></i>ID: {{ $inst['id'] }}
                                                        </span>
                                                        @endif
                                                        <span class="badge {{ $instIsGroq ? 'bg-info text-dark' : 'bg-secondary' }}">
                                                            <i class="fas {{ $instIsGroq ? 'fa-robot' : 'fa-microchip' }} me-1"></i>
                                                            {{ $instIsGroq ? 'Groq AI' : 'Model' }}
                                                        </span>
                                                        <span class="text-secondary small detection-date">{{ $inst['date'] ?? '—' }}</span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                        <button class="btn btn-sm btn-outline-light" onclick="printInstanceCard(this)" title="Print this scan">
                                                            <i class="fas fa-print"></i>
                                                        </button>
                                                        @if(!empty($inst['id']))
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteInstance({{ $inst['id'] }})" title="Delete this detection">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Picture + type badge/name + confidence gauge, styled the same
                                                     way as the "Report the Problem" modal's detection header. -->
                                                <div class="rg-report-top mb-3">
                                                    @if(!empty($inst['image']))
                                                        <img src="{{ $inst['image'] }}" class="rg-report-thumb" alt="Detected image" onclick="showImageModal('{{ addslashes($inst['image']) }}')" style="cursor: pointer;">
                                                    @else
                                                        <div class="rg-report-thumb d-flex align-items-center justify-content-center text-secondary"><i class="fas fa-image"></i></div>
                                                    @endif
                                                    <div class="rg-report-top-info">
                                                        <div class="rg-type-badge {{ $isPest ? '' : 'is-disease' }}">
                                                            <i class="fas {{ $isPest ? 'fa-bug' : 'fa-disease' }}"></i>
                                                            <span>{{ $isPest ? 'Pest' : 'Disease' }}</span>
                                                        </div>
                                                        <div class="rg-report-name">{{ $det['class_name'] }}</div>
                                                    </div>
                                                    <div class="rg-confidence-gauge rg-report-gauge">
                                                        <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
                                                            <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100" style="fill:none;"></path>
                                                            <path class="gauge-fill" d="M14,100 A86,86 0 0 1 186,100" style="fill:none; stroke-dasharray: {{ $iArcLen }}; stroke-dashoffset: {{ $iGaugeOffset }}; stroke: {{ $iGaugeColor }};"></path>
                                                        </svg>
                                                        <div class="rg-report-gauge-center" style="color: {{ $iGaugeColor }};">
                                                            <span>{{ $iConfidence }}%</span>
                                                            <small>Confidence</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Severity / Damage Level stat chips, colored by level, same
                                                     look as the report modal's Severity / Damage Level chips. -->
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <div class="rg-report-stat">
                                                            <div class="rg-report-stat-label"><i class="fa-solid fa-gauge-high me-1"></i>Severity</div>
                                                            <div class="rg-report-stat-value {{ $iSevColor }}">{{ $iSevLabel ?: '—' }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="rg-report-stat">
                                                            <div class="rg-report-stat-label"><i class="fa-solid fa-wheat-awn me-1"></i>Damage Level</div>
                                                            <div class="rg-report-stat-value {{ $iSevColor }}">{{ is_numeric($iSevPercent) ? $iSevPercent.'%' : '—' }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if($iSevMessage)
                                                    <div class="small text-light mb-3 fst-italic">{{ $iSevMessage }}</div>
                                                @endif

                                                <div class="knowledge-section">
                                                    <strong class="text-white d-block mb-2">Description / About</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['description'] ?? 'No description available.')) !!}</div>
                                                </div>

                                                <div class="knowledge-section">
                                                    <strong class="text-success d-block mb-2">Recommended Treatments</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['treatments'] ?? 'No data available yet.')) !!}</div>
                                                </div>

                                                <div class="knowledge-section">
                                                    <strong class="d-block mb-2 text-warning">Common Causes / Biology</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['causes'] ?? '—')) !!}</div>
                                                </div>

                                                @if(!$isPest)
                                                    <div class="knowledge-section">
                                                        <strong class="text-info d-block mb-2">Nutrient Deficiency</strong>
                                                        <div class="small text-light">{!! nl2br(e($ikb['nutrient_deficiency'] ?? '—')) !!}</div>
                                                    </div>
                                                @endif

                                                <div class="knowledge-section">
                                                    <strong class="text-danger d-block mb-2">{{ $isPest ? 'Damage Symptoms' : 'Grain / Paddy Damage' }}</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['grain_damage'] ?? '—')) !!}</div>
                                                </div>

                                                @if($isPest)
                                                    <div class="knowledge-section">
                                                        <strong class="text-info d-block mb-2">Natural Enemies</strong>
                                                        <div class="small text-light">{!! nl2br(e($ikb['natural_enemies'] ?? '—')) !!}</div>
                                                    </div>
                                                @endif

                                                <div class="knowledge-section mb-0">
                                                    <strong class="text-success d-block mb-2">Prevention Tips</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['prevention'] ?? '—')) !!}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-secondary small">No disease detections yet.</p>
                        @endforelse
                    </div>
                    <div class="col-lg-6">
                        <h6 class="text-warning text-uppercase small fw-bold mb-3"><i class="fas fa-bug me-1"></i> Pests</h6>
                        @forelse($section['pests'] as $det)
                            @php
                                $isPest = $det['is_pest'];
                                $detId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $det['field_key'] . '-' . $det['class_key']);
                                $instanceCount = count($det['instances']);
                            @endphp
                            <div class="detection-row">
                                <div class="detection-row-header d-flex justify-content-between align-items-center gap-2">
                                    <span class="detection-name-toggle" onclick="toggleDetectionDetail('{{ $detId }}')">{{ $det['class_name'] }}</span>
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                        <span class="badge bg-secondary bg-opacity-50 border border-secondary text-light small">
                                            {{ $instanceCount }} scan{{ $instanceCount === 1 ? '' : 's' }}
                                        </span>
                                        <span class="badge bg-success text-white px-2 py-1 fw-bold shadow-sm">
                                            <i class="fas fa-check-circle me-1"></i> Synced
                                        </span>
                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteDetection('{{ $det['field_key'] }}', '{{ $det['class_key'] }}')" title="Delete this entire record">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="detection-detail-panel" id="detail-{{ $detId }}">
                                    <button type="button" class="btn btn-sm btn-outline-light detection-detail-print" onclick="printDetectionPanel('{{ $detId }}', '{{ $det['class_name'] }}')" title="Print all">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary detection-detail-close" onclick="toggleDetectionDetail('{{ $detId }}')" title="Close">
                                        <i class="fas fa-times"></i>
                                    </button>

                                    <div class="d-flex align-items-center gap-3 mb-4 pe-5">
                                        <div class="fs-2">{{ $isPest ? '🐛' : '🌾' }}</div>
                                        <div>
                                            <h5 class="mb-1 fw-bold text-white">{{ $det['class_name'] }}</h5>
                                            <div class="small text-secondary">
                                                {{ $instanceCount }} separate scan{{ $instanceCount === 1 ? '' : 's' }} of this {{ $isPest ? 'pest' : 'disease' }} — each shown below with its own info and photo.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="detection-instances-list d-flex flex-column gap-3">
                                        @foreach($det['instances'] as $inst)
                                            @php
                                                $instIsGroq = ($inst['source'] ?? 'model') === 'groq';
                                                $ikb = $inst['kb'] ?? [];
                                                $iSevLabel = $inst['severity_label'] ?? ($severityLabelMap[$det['class_key']] ?? null);
                                                $iSevPercent = $inst['severity_percent'] ?? ($severityMap[$det['class_key']] ?? null);
                                                $iSevMessage = $inst['severity_message'] ?? null;
                                                $iSevColor = 'text-light';
                                                if (is_numeric($iSevPercent)) {
                                                    if ((int) $iSevPercent === 0) $iSevColor = 'text-success';
                                                    elseif ($iSevPercent <= 30) $iSevColor = 'text-info';
                                                    elseif ($iSevPercent <= 50) $iSevColor = 'text-warning';
                                                    else $iSevColor = 'text-danger';
                                                }
                                                // Confidence gauge geometry — same half-circle arc math used
                                                // by the "Report the Problem" modal's confidence gauge.
                                                $iConfidence = max(0, min(100, (int) ($inst['confidence'] ?? 0)));
                                                $iArcLen = M_PI * 86;
                                                $iGaugeOffset = $iArcLen * (1 - $iConfidence / 100);
                                                $iGaugeColor = '#10b981';
                                                if ($iConfidence < 50) $iGaugeColor = '#ef4444';
                                                elseif ($iConfidence < 80) $iGaugeColor = '#f59e0b';
                                            @endphp
                                            <div class="instance-full-card rounded-3 border border-secondary bg-dark bg-opacity-25 p-3">
                                                <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
                                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                                        @if(!empty($inst['id']))
                                                        <span class="badge bg-dark border border-secondary text-light small instance-id-badge">
                                                            <i class="fas fa-hashtag me-1"></i>ID: {{ $inst['id'] }}
                                                        </span>
                                                        @endif
                                                        <span class="badge {{ $instIsGroq ? 'bg-info text-dark' : 'bg-secondary' }}">
                                                            <i class="fas {{ $instIsGroq ? 'fa-robot' : 'fa-microchip' }} me-1"></i>
                                                            {{ $instIsGroq ? 'Groq AI' : 'Model' }}
                                                        </span>
                                                        <span class="text-secondary small detection-date">{{ $inst['date'] ?? '—' }}</span>
                                                    </div>
                                                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                        <button class="btn btn-sm btn-outline-light" onclick="printInstanceCard(this)" title="Print this scan">
                                                            <i class="fas fa-print"></i>
                                                        </button>
                                                        @if(!empty($inst['id']))
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteInstance({{ $inst['id'] }})" title="Delete this detection">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Picture + type badge/name + confidence gauge, styled the same
                                                     way as the "Report the Problem" modal's detection header. -->
                                                <div class="rg-report-top mb-3">
                                                    @if(!empty($inst['image']))
                                                        <img src="{{ $inst['image'] }}" class="rg-report-thumb" alt="Detected image" onclick="showImageModal('{{ addslashes($inst['image']) }}')" style="cursor: pointer;">
                                                    @else
                                                        <div class="rg-report-thumb d-flex align-items-center justify-content-center text-secondary"><i class="fas fa-image"></i></div>
                                                    @endif
                                                    <div class="rg-report-top-info">
                                                        <div class="rg-type-badge {{ $isPest ? '' : 'is-disease' }}">
                                                            <i class="fas {{ $isPest ? 'fa-bug' : 'fa-disease' }}"></i>
                                                            <span>{{ $isPest ? 'Pest' : 'Disease' }}</span>
                                                        </div>
                                                        <div class="rg-report-name">{{ $det['class_name'] }}</div>
                                                    </div>
                                                    <div class="rg-confidence-gauge rg-report-gauge">
                                                        <svg viewBox="0 0 200 110" preserveAspectRatio="xMidYMid meet">
                                                            <path class="gauge-track" d="M14,100 A86,86 0 0 1 186,100" style="fill:none;"></path>
                                                            <path class="gauge-fill" d="M14,100 A86,86 0 0 1 186,100" style="fill:none; stroke-dasharray: {{ $iArcLen }}; stroke-dashoffset: {{ $iGaugeOffset }}; stroke: {{ $iGaugeColor }};"></path>
                                                        </svg>
                                                        <div class="rg-report-gauge-center" style="color: {{ $iGaugeColor }};">
                                                            <span>{{ $iConfidence }}%</span>
                                                            <small>Confidence</small>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Severity / Damage Level stat chips, colored by level, same
                                                     look as the report modal's Severity / Damage Level chips. -->
                                                <div class="row g-2 mb-3">
                                                    <div class="col-6">
                                                        <div class="rg-report-stat">
                                                            <div class="rg-report-stat-label"><i class="fa-solid fa-gauge-high me-1"></i>Severity</div>
                                                            <div class="rg-report-stat-value {{ $iSevColor }}">{{ $iSevLabel ?: '—' }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="rg-report-stat">
                                                            <div class="rg-report-stat-label"><i class="fa-solid fa-wheat-awn me-1"></i>Damage Level</div>
                                                            <div class="rg-report-stat-value {{ $iSevColor }}">{{ is_numeric($iSevPercent) ? $iSevPercent.'%' : '—' }}</div>
                                                        </div>
                                                    </div>
                                                </div>

                                                @if($iSevMessage)
                                                    <div class="small text-light mb-3 fst-italic">{{ $iSevMessage }}</div>
                                                @endif

                                                <div class="knowledge-section">
                                                    <strong class="text-white d-block mb-2">Description / About</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['description'] ?? 'No description available.')) !!}</div>
                                                </div>

                                                <div class="knowledge-section">
                                                    <strong class="text-success d-block mb-2">Recommended Treatments</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['treatments'] ?? 'No data available yet.')) !!}</div>
                                                </div>

                                                <div class="knowledge-section">
                                                    <strong class="d-block mb-2 text-warning">Common Causes / Biology</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['causes'] ?? '—')) !!}</div>
                                                </div>

                                                @if(!$isPest)
                                                    <div class="knowledge-section">
                                                        <strong class="text-info d-block mb-2">Nutrient Deficiency</strong>
                                                        <div class="small text-light">{!! nl2br(e($ikb['nutrient_deficiency'] ?? '—')) !!}</div>
                                                    </div>
                                                @endif

                                                <div class="knowledge-section">
                                                    <strong class="text-danger d-block mb-2">{{ $isPest ? 'Damage Symptoms' : 'Grain / Paddy Damage' }}</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['grain_damage'] ?? '—')) !!}</div>
                                                </div>

                                                @if($isPest)
                                                    <div class="knowledge-section">
                                                        <strong class="text-info d-block mb-2">Natural Enemies</strong>
                                                        <div class="small text-light">{!! nl2br(e($ikb['natural_enemies'] ?? '—')) !!}</div>
                                                    </div>
                                                @endif

                                                <div class="knowledge-section mb-0">
                                                    <strong class="text-success d-block mb-2">Prevention Tips</strong>
                                                    <div class="small text-light">{!! nl2br(e($ikb['prevention'] ?? '—')) !!}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="text-secondary small">No pest detections yet.</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    @endforeach
</div>

<div id="detectionDetailBackdrop" class="detection-detail-backdrop"></div>

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
        
        document.getElementById('imageModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('modalImageBig').src = '';
        });
    });

    function showImageModal(src) {
        document.getElementById('modalImageBig').src = src;
        imgModal.show();
    }

    // Toggle the floating detail panel for a detection row (only one open at a time)
    function toggleDetectionDetail(detId) {
        const panel = document.getElementById('detail-' + detId);
        const backdrop = document.getElementById('detectionDetailBackdrop');
        if (!panel) return;

        const isOpen = panel.classList.contains('show');

        document.querySelectorAll('.detection-detail-panel.show').forEach(p => {
            p.classList.remove('show');
        });

        if (!isOpen) {
            panel.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
        } else {
            if (backdrop) backdrop.classList.remove('show');
        }
    }

    document.getElementById('detectionDetailBackdrop')?.addEventListener('click', function () {
        document.querySelectorAll('.detection-detail-panel.show').forEach(p => {
            p.classList.remove('show');
        });
        this.classList.remove('show');
    });

    // --- Print helpers ---
    function printInstanceCard(btn) {
        const card = btn.closest('.instance-full-card');
        if (!card) return;
        openPrintWindow(card.outerHTML, 'Detection Scan');
    }

    function printDetectionPanel(detId, className) {
        const panel = document.getElementById('detail-' + detId);
        if (!panel) return;
        openPrintWindow(panel.innerHTML, className ? (className + ' - Full Report') : 'Detection Report');
    }

    function getPageStyles() {
        // Reuse the page's own <style> blocks so the print window renders every
        // component (gauge, knowledge-section, rg-report-* header, badges) exactly
        // like it looks on screen, instead of guessing a subset of rules by hand.
        let css = '';
        document.querySelectorAll('style').forEach(s => { css += s.innerHTML + '\n'; });
        return css;
    }

    function openPrintWindow(innerHtml, title) {
        const printWindow = window.open('', '_blank', 'width=900,height=700');
        if (!printWindow) { alert('Please allow pop-ups to print.'); return; }
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>${title}</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
                <style>${getPageStyles()}</style>
                <style>
                    /* Print-only overrides layered on top of the page's real styles above */
                    body { background:#fff !important; color:#111 !important; padding:24px; font-family: Arial, sans-serif; }
                    h4.print-title { margin-bottom: 20px; color:#111; }
                    .text-white, .text-light, .rg-report-name, .rg-report-stat-value,
                    .rg-report-gauge-center span { color:#111 !important; }
                    .text-secondary, .rg-report-stat-label, .rg-report-gauge-center small { color:#555 !important; }
                    .bg-dark, .bg-secondary, .rg-report-stat, .bg-opacity-25, .bg-opacity-50,
                    .instance-full-card { background:#f7f7f7 !important; }
                    .gauge-track { stroke: #ddd !important; }
                    .btn, .detection-detail-close, .detection-detail-print { display:none !important; }
                    img { max-width: 220px; }
                    .instance-full-card {
                        border: 1px solid #ccc !important;
                        page-break-inside: avoid;
                        margin-bottom: 16px;
                    }
                    /* Knowledge sections (Description, Treatments, Causes, etc.) get an
                       underline only — no boxed border — matching the on-screen look. */
                    .knowledge-section {
                        background: transparent !important;
                        border: none !important;
                        border-bottom: 1px solid #ccc !important;
                        page-break-inside: avoid;
                    }
                    .knowledge-section:last-child { border-bottom: none !important; }
                    .detection-detail-panel, .detection-instances-list { position:static !important; max-height:none !important; overflow:visible !important; width:auto !important; }
                </style>
            </head>
            <body>
                <h4 class="print-title">${title}</h4>
                ${innerHtml}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => { printWindow.print(); }, 500);
    }

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

    function deleteInstance(id) {
        if(!confirm("Are you sure you want to delete this individual detection and its photo?")) return;

        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ action: 'delete_instance', id: id })
        })
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                location.reload();
            } else {
                alert('Failed to delete this detection.');
            }
        })
        .catch(err => console.error(err));
    }

    function deleteDetection(fieldKey, classKey) {
        if(!confirm("Are you sure you want to delete this entire detection record and all its images?")) return;

        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ action: 'delete_detection', class_key: classKey, field_key: fieldKey })
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
    
    // --- FIXED: Offline IndexedDB Script ---
let dbInstance = null;

document.addEventListener("DOMContentLoaded", async () => {
    const dbPromise = new Promise((resolve, reject) => {
        const request = indexedDB.open('CropSenseDB', 1);
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });

    try {
        dbInstance = await dbPromise;
        
        if (!dbInstance.objectStoreNames.contains('offline_history')) return;

        const tx = dbInstance.transaction('offline_history', 'readonly');
        const store = tx.objectStore('offline_history');
        const request = store.getAll();

        request.onsuccess = () => {
            // FIXED: Correct variable naming to prevent the crash
            const allOfflineRecords = request.result; 

            const currentUserId = {{ Auth::id() }};
            const offlineRecords = allOfflineRecords.filter(record => record.user_id === currentUserId);

            if (offlineRecords && offlineRecords.length > 0) {
                const noHistoryAlert = document.getElementById('no-history-alert');
                if(noHistoryAlert) noHistoryAlert.style.display = 'none';

                const container = document.getElementById('history-container');
                
                offlineRecords.forEach(record => {
                    const isPest = record.groq_data?.is_pest || false;
                    const kb = record.groq_data || {};
                    const formattedName = record.class_key.replace(/_/g, ' ').toUpperCase();

                    // --- JAVASCRIPT SEVERITY LOGIC FOR OFFLINE RECORDS ---
                    const severityMapJS = {
                        'healthy_rice_plant': 0, 'bacterial_leaf_blight': 60, 'leaf_blast': 80, 'rice_false_smut': 30,
                        'sheath_blight': 40, 'tungro_virus': 85, 'brown_planthopper': 90, 'leaf_folders': 20,
                        'leafhopper': 30, 'rice_bug': 80, 'rice_gall_midge': 40, 'rice_leaf_roller': 20,
                        'rice_stem_borer': 30, 'snail': 75
                    };
                    
                    let severityVal = record.groq_data?.severity_percent ?? severityMapJS[record.class_key] ?? 'N/A';
                    
                    let sevColor = 'text-light';
                    if(severityVal === 0) sevColor = 'text-success';
                    else if(severityVal <= 30) sevColor = 'text-info';
                    else if(severityVal <= 50) sevColor = 'text-warning';
                    else if(severityVal > 50) sevColor = 'text-danger';

                    const severityText = severityVal !== 'N/A' ? `${severityVal}%` : severityVal;
                    // -----------------------------------------------------

                    // Helper to format text safely
                    const formatText = (text) => text ? text.replace(/\n/g, '<br>') : '—';

                    let extraSections = '';
                    if (isPest) {
                        extraSections = `
                            <div class="knowledge-section">
                                <strong class="text-danger d-block mb-2">Damage Symptoms</strong>
                                <div class="small text-light">${formatText(kb.grain_damage)}</div>
                            </div>
                            <div class="knowledge-section">
                                <strong class="text-info d-block mb-2">Natural Enemies</strong>
                                <div class="small text-light">${formatText(kb.natural_enemies)}</div>
                            </div>
                        `;
                    } else {
                        extraSections = `
                            <div class="knowledge-section">
                                <strong class="text-info d-block mb-2">Nutrient Deficiency</strong>
                                <div class="small text-light">${formatText(kb.nutrient_deficiency)}</div>
                            </div>
                            <div class="knowledge-section">
                                <strong class="text-danger d-block mb-2">Grain / Paddy Damage</strong>
                                <div class="small text-light">${formatText(kb.grain_damage)}</div>
                            </div>
                        `;
                    }

                    // Card HTML rendering the "Not sync yet" warning
                    const cardHtml = `
                        <div class="col-lg-6 offline-card" id="offline-record-${record.id}">
                            <div class="card bg-secondary bg-opacity-10 border-warning h-100 position-relative shadow" style="border-width: 2px;">
                            
                            <!-- TOP-RIGHT BADGE & DELETE BUTTON -->
                            <div class="position-absolute top-0 end-0 me-3 mt-3 z-3 d-flex gap-2 align-items-center">
                                <span class="badge bg-warning text-dark px-3 py-2 fw-bold shadow-sm sync-badge">
                                    <i class="fas fa-cloud-upload-alt me-1"></i> Not sync yet
                                </span>
                                <button class="btn btn-sm btn-outline-danger shadow-sm bg-dark" onclick="deleteOfflineRecord(${record.id}, this)" title="Delete offline record">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>

                            <div class="card-body p-4 opacity-75 mt-3">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="fs-2">${isPest ? '🐛' : '🌾'}</div>
                                    <div>
                                        <h5 class="mb-1 fw-bold text-white">${formattedName}</h5>
                                        <div class="d-flex gap-2 align-items-center flex-wrap">
                                            <span class="badge ${isPest ? 'bg-warning text-dark' : 'bg-success'}">${isPest ? 'PEST' : 'DISEASE'}</span>
                                            <span class="badge bg-secondary bg-opacity-50 border border-secondary text-light">Confidence: ${record.confidence}%</span>
                                            <span class="badge bg-secondary bg-opacity-50 border border-secondary ${sevColor}">Severity: ${severityText}</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="knowledge-section">
                                    <strong class="text-white d-block mb-2">Description / About</strong>
                                    <div class="small text-light">${formatText(kb.description)}</div>
                                </div>

                                <div class="knowledge-section">
                                    <strong class="text-success d-block mb-2">Recommended Treatments</strong>
                                    <div class="small text-light">${formatText(kb.treatments)}</div>
                                </div>

                                <div class="knowledge-section">
                                    <strong class="d-block mb-2 text-warning">Common Causes / Biology</strong>
                                    <div class="small text-light">${formatText(kb.causes)}</div>
                                </div>

                                ${extraSections}

                                <div class="knowledge-section mb-0">
                                    <strong class="text-success d-block mb-2">Prevention Tips</strong>
                                    <div class="small text-light">${formatText(kb.prevention)}</div>
                                </div>

                                <div class="mt-4 pt-3 border-top border-secondary">
                                    <p class="text-secondary small mb-2 text-end">Offline Image (1)</p>
                                    <div class="image-gallery d-flex flex-wrap gap-2 justify-content-end">
                                        <div class="img-container">
                                            <img src="${record.image_base64}" class="rounded-3 shadow-sm" style="width: 80px; height: 80px; object-fit: cover;" onclick="showImageModal(this.src)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    `;
                    
                    container.insertAdjacentHTML('afterbegin', cardHtml);
                });
            }
        };
    } catch(e) {
        console.log("No offline database found or error opening DB.");
    }
});

// --- NEW: Auto-Sync Listener for History Page ---
window.addEventListener('online', async () => {
    if (!dbInstance) return;
    
    console.log("Connection restored. Syncing records from history page...");
    
    const tx = dbInstance.transaction('offline_history', 'readonly');
    const store = tx.objectStore('offline_history');
    const request = store.getAll();

    request.onsuccess = async () => {
        const allRecords = request.result;
        const currentUserId = {{ Auth::id() }};
        const userRecords = allRecords.filter(record => record.user_id === currentUserId);

        if (userRecords.length > 0) {
            // 1. Visually change badges to "Syncing..."
            userRecords.forEach(record => {
                const badgeContainer = document.querySelector(`#offline-record-${record.id} .sync-badge`);
                if(badgeContainer) {
                    badgeContainer.className = 'badge bg-info text-dark px-3 py-2 fw-bold shadow-sm sync-badge';
                    badgeContainer.innerHTML = '<i class="fas fa-sync fa-spin me-1"></i> Syncing...';
                }
            });

            // 2. Process uploads
            for (const record of userRecords) {
                const { id, user_id, ...serverPayload } = record; 
                
                try {
                    const response = await fetch("{{ route('farmer.history.save') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({...serverPayload, user_id: currentUserId})
                    });

                    if (response.ok) {
                        // Remove record from IndexedDB
                        const delTx = dbInstance.transaction('offline_history', 'readwrite');
                        delTx.objectStore('offline_history').delete(id);

                        // 3. Swap badge color and text to "Synced"
                        const badgeContainer = document.querySelector(`#offline-record-${record.id} .sync-badge`);
                        if (badgeContainer) {
                            badgeContainer.className = 'badge bg-success text-white px-3 py-2 fw-bold shadow-sm sync-badge';
                            badgeContainer.innerHTML = '<i class="fas fa-check-circle me-1"></i> Synced';
                        }
                    }
                } catch(e) {
                    console.error("Failed to sync record", e);
                }
            }
            
            // 4. Brief delay to let the user see the green "Synced" state before reload
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        }
    };
});

    // Delete a specific offline record from IndexedDB
    function deleteOfflineRecord(id, buttonElement) {
        if(!confirm("Are you sure you want to delete this unsynced offline record?")) return;

        if (!dbInstance) {
            alert("Database not ready.");
            return;
        }

        const tx = dbInstance.transaction('offline_history', 'readwrite');
        const store = tx.objectStore('offline_history');
        const request = store.delete(id);

        request.onsuccess = () => {
            // Remove the card from the screen visually
            const cardWrapper = buttonElement.closest('.offline-card');
            if (cardWrapper) {
                cardWrapper.remove();
            }

            // Check if there are any cards left, if not, show the "No History" alert
            const remainingCards = document.querySelectorAll('.col-lg-6');
            if (remainingCards.length === 0) {
                const noHistoryAlert = document.getElementById('no-history-alert');
                if (noHistoryAlert) noHistoryAlert.style.display = 'block';
            }
        };

        request.onerror = () => {
            alert("Failed to delete offline record.");
        };
    }
</script>
@endsection