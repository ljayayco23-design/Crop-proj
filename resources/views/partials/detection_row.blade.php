@php
    // Shared single-detection-category row + floating detail panel.
    // Markup mirrors resources/views/farmer/history.blade.php's inline
    // block so both pages look/feel identical — this is just that same
    // block extracted so it can be @include()'d once per $det instead of
    // being duplicated for diseases and pests.
    //
    // Expected variables:
    //   $det          - one entry from $section['diseases']/['pests']
    //                    (field_key, class_key, class_name, is_pest, instances[])
    //   $severityMap  - class_key => fallback damage percent
    //   $mode         - 'technician' (read-only on the farmer's own scans;
    //                    header has no delete button, and each instance +
    //                    the whole category get their own Print button) or
    //                    anything else (defaults to the farmer's own
    //                    read/write view, with delete buttons instead)
    //   $ownerId      - the farmer's user id, so detIds stay unique when
    //                    many farmers render on the same page

    $mode = $mode ?? 'farmer';
    $isTechnicianMode = $mode === 'technician';

    $severityLabelMap = [
        'healthy_rice_plant' => 'HEALTHY', 'bacterial_leaf_blight' => 'SEVERE', 'leaf_blast' => 'SEVERE',
        'rice_false_smut' => 'MODERATE', 'sheath_blight' => 'MODERATE', 'tungro_virus' => 'SEVERE',
        'brown_planthopper' => 'SEVERE', 'leaf_folders' => 'LOW', 'leafhopper' => 'MODERATE',
        'rice_bug' => 'SEVERE', 'rice_gall_midge' => 'MODERATE', 'rice_leaf_roller' => 'LOW',
        'rice_stem_borer' => 'MODERATE', 'snail' => 'SEVERE'
    ];

    $isPest = $det['is_pest'];
    $detIdRaw = ($ownerId ?? 'x') . '-' . $det['field_key'] . '-' . $det['class_key'];
    $detId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $detIdRaw);
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

            @unless($isTechnicianMode)
                <button class="btn btn-sm btn-outline-danger" onclick="deleteDetection('{{ $det['field_key'] }}', '{{ $det['class_key'] }}')" title="Delete this entire record">
                    <i class="fas fa-trash"></i>
                </button>
            @endunless
        </div>
    </div>

    <div class="detection-detail-panel" id="detail-{{ $detId }}">
        <div class="detection-detail-actions">
            @if($isTechnicianMode)
                <button type="button" class="btn btn-sm btn-outline-light" title="Print this record" onclick="printRow(this)">
                    <i class="fas fa-print"></i>
                </button>
            @endif
            <button type="button" class="btn btn-sm btn-outline-secondary detection-detail-close" onclick="toggleDetectionDetail('{{ $detId }}')" title="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

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
                    // Same three-way engine check as history.blade.php's
                    // inline block ('groq' / 'yolo11n' / fallback 'model') —
                    // this used to only ever check for 'groq' and call
                    // everything else "Model", which mislabeled every
                    // yolo11n scan shown here the same way the Report page
                    // did before it was fixed.
                    $instSource = $inst['source'] ?? 'model';
                    $ikb = $inst['kb'] ?? [];
                    // YOLO11n bounding-box data for this instance's photo,
                    // same shape history.blade.php reads — decoded
                    // independently of the image, so a missing/old row here
                    // just means no overlay is drawn, never a broken image.
                    $iBoxesJson = !empty($inst['boxes']['boxes']) ? json_encode($inst['boxes']['boxes']) : '';
                    $iBoxesSrcW = $inst['boxes']['src_w'] ?? '';
                    $iBoxesSrcH = $inst['boxes']['src_h'] ?? '';
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
                            <span class="badge {{ $instSource === 'groq' ? 'bg-info text-dark' : ($instSource === 'yolo11n' ? 'bg-success text-white' : 'bg-secondary') }}">
                                <i class="fas {{ $instSource === 'groq' ? 'fa-robot' : ($instSource === 'yolo11n' ? 'fa-bullseye' : 'fa-microchip') }} me-1"></i>
                                {{ $instSource === 'groq' ? 'Groq AI' : ($instSource === 'yolo11n' ? 'YOLO11n' : 'Model') }}
                            </span>
                            <span class="text-secondary small detection-date">{{ $inst['date'] ?? '—' }}</span>
                        </div>
                        @if($isTechnicianMode)
                            <button type="button" class="btn btn-sm btn-outline-light instance-print-btn flex-shrink-0" onclick="printInstance(this)" title="Print this scan">
                                <i class="fas fa-print"></i>
                            </button>
                        @elseif(!empty($inst['id']))
                            <button class="btn btn-sm btn-outline-danger flex-shrink-0" onclick="deleteInstance({{ $inst['id'] }})" title="Delete this detection">
                                <i class="fas fa-times"></i>
                            </button>
                        @endif
                    </div>

                    <div class="rg-report-top mb-3">
                        @if(!empty($inst['image']))
                            <img src="{{ $inst['image'] }}" class="rg-report-thumb" alt="Detected image"
                                 data-boxes="{{ $iBoxesJson }}"
                                 data-boxes-src-w="{{ $iBoxesSrcW }}"
                                 data-boxes-src-h="{{ $iBoxesSrcH }}"
                                 onclick="showImageModal(this.currentSrc || this.src, this.dataset.boxes, this.dataset.boxesSrcW, this.dataset.boxesSrcH)"
                                 style="cursor: pointer;">
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