@extends('layouts.admin')

@section('title', 'RICEGUARD AI • Detection History')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 text-white">All Detection History</h4>
            <p class="text-secondary mb-0">View all AI detections and user history</p>
        </div>
    </div>

    @php
        // Severity Map matching the farmer side implementation
        $severityMap = [
            'healthy_rice_plant' => 0, 'bacterial_leaf_blight' => 60, 'leaf_blast' => 80, 
            'rice_false_smut' => 30, 'sheath_blight' => 40, 'tungro_virus' => 85, 
            'brown_planthopper' => 90, 'leaf_folders' => 20, 'leafhopper' => 30, 
            'rice_bug' => 80, 'rice_gall_midge' => 40, 'rice_leaf_roller' => 20, 
            'rice_stem_borer' => 30, 'snail' => 75,
            // canonical 23-class keys (YOLO11n taxonomy) - same values as the farmer History page
            'bacterial_leaf_streak' => 35, 'brown_spot' => 30, 'downy_mildew' => 35,
            'applesnail_eggs' => 75, 'dead_heart' => 30, 'green_leafhopper' => 30,
            'rice_gall_midg' => 40, 'rice_hispa' => 35, 'rice_thrips' => 15,
            'rice_water_weevil' => 35, 'whorl_maggot' => 20
        ];

        // Fallback severity label per class (English) - same as the farmer History page
        $severityLabelMap = [
            'healthy_rice_plant' => 'HEALTHY', 'bacterial_leaf_blight' => 'SEVERE', 'leaf_blast' => 'SEVERE',
            'rice_false_smut' => 'MODERATE', 'sheath_blight' => 'MODERATE', 'tungro_virus' => 'SEVERE',
            'brown_planthopper' => 'SEVERE', 'leaf_folders' => 'LOW', 'leafhopper' => 'MODERATE',
            'rice_bug' => 'SEVERE', 'rice_gall_midge' => 'MODERATE', 'rice_leaf_roller' => 'LOW',
            'rice_stem_borer' => 'MODERATE', 'snail' => 'SEVERE',
            'bacterial_leaf_streak' => 'MODERATE', 'brown_spot' => 'MODERATE', 'downy_mildew' => 'MODERATE',
            'applesnail_eggs' => 'SEVERE', 'dead_heart' => 'MODERATE', 'green_leafhopper' => 'MODERATE',
            'rice_gall_midg' => 'MODERATE', 'rice_hispa' => 'MODERATE', 'rice_thrips' => 'LOW',
            'rice_water_weevil' => 'MODERATE', 'whorl_maggot' => 'LOW'
        ];
        $severityLabelsEn = ['HEALTHY', 'LOW', 'MODERATE', 'SEVERE', 'UNKNOWN'];

        // Severity wording/translations live in RiceTaxonomy (same source the farmer side uses).
        $taxPayload = [];
        try {
            if (class_exists(\App\Support\RiceTaxonomy::class)) {
                $taxPayload = \App\Support\RiceTaxonomy::payload();
            }
        } catch (\Throwable $e) {
            $taxPayload = [];
        }

        // Which model classified each scan, and (for YOLO11n) where its boxes
        // are. Normally these columns come straight off $histories; if the
        // controller's query doesn't select them, they're looked up here in
        // ONE query by scan id so the page still shows the real model.
        $historyRows  = $histories instanceof \Illuminate\Pagination\AbstractPaginator
            ? $histories->getCollection() : collect($histories);
        $firstRow     = $historyRows->first();
        $extraById    = collect();
        if ($firstRow && data_get($firstRow, 'source') === null && data_get($firstRow, 'detection_boxes') === null) {
            $extraCols = array_values(array_filter(
                ['source', 'detection_boxes'],
                fn ($c) => \Illuminate\Support\Facades\Schema::hasColumn('user_detections', $c)
            ));
            $ids = $historyRows->map(fn ($r) => data_get($r, 'id'))->filter()->values()->all();
            if ($extraCols && $ids) {
                $extraById = \Illuminate\Support\Facades\DB::table('user_detections')
                    ->whereIn('id', $ids)->select(array_merge(['id'], $extraCols))->get()->keyBy('id');
            }
        }

        // Each scan's own saved severity snapshot (same `groq_snapshot` the farmer
        // History page reads). Looked up by scan id only if the controller's query
        // doesn't already return it.
        $snapById = collect();
        if ($firstRow && data_get($firstRow, 'groq_snapshot') === null
            && \Illuminate\Support\Facades\Schema::hasColumn('user_detections', 'groq_snapshot')) {
            $snapIds = $historyRows->map(fn ($r) => data_get($r, 'id'))->filter()->values()->all();
            if ($snapIds) {
                $snapById = \Illuminate\Support\Facades\DB::table('user_detections')
                    ->whereIn('id', $snapIds)->select('id', 'groq_snapshot')->get()->keyBy('id');
            }
        }
    @endphp

    <div class="card bg-dark border border-secondary shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="border-bottom border-secondary">
                        <tr>
                            <th class="text-secondary">Date & Time</th>
                            <th class="text-secondary">Farmer</th>
                            <th class="text-secondary">Type</th>
                            <th class="text-secondary">Detection Result</th>
                            <th class="text-secondary">Severity</th>
                            <th class="text-secondary">Confidence</th>
                            <th class="text-secondary text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($histories as $history)
                            @php
                                // Safeguard: Process the image data to prevent asset() from ruining Base64 text strings
                                $processedImg = '';
                                if (!empty($history->image_path)) {
                                    if (str_starts_with($history->image_path, 'data:image/')) {
                                        $processedImg = $history->image_path;
                                    } else {
                                        $processedImg = asset($history->image_path);
                                    }
                                } elseif (!empty($history->image_url)) {
                                    if (str_starts_with($history->image_url, 'data:image/')) {
                                        $processedImg = $history->image_url;
                                    } else {
                                        $processedImg = asset($history->image_url);
                                    }
                                }

                                // Severity - same source order as the farmer History page:
                                // this scan's own saved snapshot first, then the per-class fallback.
                                // Shown in English on the admin side.
                                $cKey = strtolower(trim((string) ($history->class_key ?? str_replace(' ', '_', strtolower($history->readable_name ?? '')))));

                                $snapRaw = data_get($history, 'groq_snapshot') ?? data_get($snapById->get(data_get($history, 'id')), 'groq_snapshot');
                                $snap = is_string($snapRaw) && $snapRaw !== '' ? json_decode($snapRaw, true) : (is_array($snapRaw) ? $snapRaw : []);
                                if (!is_array($snap)) $snap = [];

                                // Damage level (%)
                                $snapPct     = $snap['severity_percent'] ?? null;
                                $severityVal = is_numeric($snapPct) ? (int) $snapPct : ($severityMap[$cKey] ?? null);

                                // Severity label. A snapshot saved for a farmer can hold a translated
                                // label (Tagalog/Cebuano/Hiligaynon) - map it back to the English one.
                                $snapLabel = strtoupper(trim((string) ($snap['severity_label'] ?? '')));
                                if ($snapLabel !== '' && !in_array($snapLabel, $severityLabelsEn, true)) {
                                    $found = null;
                                    foreach (($taxPayload['severity_labels'] ?? []) as $labelsByKey) {
                                        if (!is_array($labelsByKey)) continue;
                                        foreach ($labelsByKey as $enKey => $localized) {
                                            if (mb_strtoupper(trim((string) $localized)) === $snapLabel) { $found = strtoupper((string) $enKey); break 2; }
                                        }
                                    }
                                    $snapLabel = $found ?? '';
                                }
                                $sevLabel = $snapLabel !== '' ? $snapLabel : ($severityLabelMap[$cKey] ?? null);

                                // Severity message in English (taxonomy first; snapshot only if it is already English).
                                $sevMessage = $taxPayload['severity'][$cKey]['messages']['english'] ?? null;
                                if (empty($sevMessage) && strtolower((string) ($snap['language'] ?? '')) === 'english') {
                                    $sevMessage = $snap['severity_message'] ?? null;
                                }

                                // Badge color (percent first, label as backup)
                                $sevColor = 'bg-secondary text-light';
                                $sevText  = 'text-light';
                                if ($sevLabel === 'UNKNOWN') {
                                    // keep neutral
                                } elseif ($severityVal !== null) {
                                    if ($severityVal === 0)      { $sevColor = 'bg-success text-white'; $sevText = 'text-success'; }
                                    elseif ($severityVal <= 30)  { $sevColor = 'bg-info text-dark';     $sevText = 'text-info'; }
                                    elseif ($severityVal <= 50)  { $sevColor = 'bg-warning text-dark';  $sevText = 'text-warning'; }
                                    else                         { $sevColor = 'bg-danger text-white';  $sevText = 'text-danger'; }
                                } elseif ($sevLabel !== null) {
                                    $byLabel = [
                                        'HEALTHY'  => ['bg-success text-white', 'text-success'],
                                        'LOW'      => ['bg-info text-dark',     'text-info'],
                                        'MODERATE' => ['bg-warning text-dark',  'text-warning'],
                                        'SEVERE'   => ['bg-danger text-white',  'text-danger'],
                                    ];
                                    [$sevColor, $sevText] = $byLabel[$sevLabel] ?? [$sevColor, $sevText];
                                }

                                $severityLabelDisplay = $sevLabel ?: 'N/A';
                                $severityDisplay = ($sevLabel || $severityVal !== null)
                                    ? trim(($sevLabel ?: '') . ($sevLabel && $severityVal !== null ? ' - ' : '') . ($severityVal !== null ? $severityVal . '%' : ''))
                                    : 'N/A';

                                // Exact model used for THIS scan — same three engines as the
                                // detection page. Scans saved before the `source` column
                                // existed were all classified by the on-device MobileNetV2.
                                $extra     = $extraById->get(data_get($history, 'id'));
                                $rawSource = strtolower((string) (data_get($history, 'source') ?? data_get($extra, 'source') ?? ''));
                                [$modelLabel, $modelStyle] = match ($rawSource) {
                                    'yolo11n' => ['YOLO11n',     'background:rgba(16,185,129,.18);color:#34d399;border:1px solid rgba(16,185,129,.45);'],
                                    'groq'    => ['Groq AI',     'background:rgba(59,130,246,.18);color:#60a5fa;border:1px solid rgba(59,130,246,.45);'],
                                    default   => ['MobileNetV2', 'background:rgba(148,163,184,.16);color:#cbd5e1;border:1px solid rgba(148,163,184,.4);'],
                                };

                                // YOLO11n boxes (original-photo pixel coordinates + the size
                                // they were measured against), only for YOLO11n scans.
                                $boxesJson = '';
                                $boxesSrcW = '';
                                $boxesSrcH = '';
                                if ($rawSource === 'yolo11n') {
                                    $rawBoxes = data_get($history, 'detection_boxes') ?? data_get($extra, 'detection_boxes');
                                    $decoded  = is_string($rawBoxes) ? json_decode($rawBoxes, true) : (is_array($rawBoxes) ? $rawBoxes : null);
                                    if (is_array($decoded) && !empty($decoded['boxes'])) {
                                        $boxesJson = json_encode($decoded['boxes']);
                                        $boxesSrcW = $decoded['src_w'] ?? '';
                                        $boxesSrcH = $decoded['src_h'] ?? '';
                                    }
                                }

                                // Timestamps are shown in Philippine time (Asia/Manila).
                                $scannedAt = \Carbon\Carbon::parse($history->created_at ?? now())->timezone('Asia/Manila');
                            @endphp
                            <tr>
                                <td>{{ $scannedAt->format('M d, Y h:i A') }}</td>
                                <td>
                                    <div class="fw-bold text-white">{{ $history->user_name ?? 'Unknown Farmer' }}</div>
                                    <small class="text-secondary">{{ $history->user_email ?? 'No email' }}</small>
                                </td>
                                <td><span class="badge" style="{{ $modelStyle }}">{{ $modelLabel }}</span></td>
                                <td><span class="badge bg-primary">{{ $history->readable_name ?? 'N/A' }}</span></td>
                                <td>
                                    <span class="badge {{ $sevColor }}">{{ $severityDisplay }}</span>
                                </td>
                                <td>{{ $history->confidence ?? 0 }}%</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-info text-dark fw-bold shadow-sm"
                                        data-model="{{ $modelLabel }}"
                                        data-boxes="{{ $boxesJson }}"
                                        data-boxes-src-w="{{ $boxesSrcW }}"
                                        data-boxes-src-h="{{ $boxesSrcH }}"
                                        data-scan-id="{{ data_get($history, 'id') }}"
                                        data-sev-percent="{{ $severityVal }}"
                                        data-sev-text="{{ $sevText }}"
                                        data-sev-message="{{ $sevMessage }}"
                                        onclick="viewDetection(
                                            '{{ addslashes($history->user_name ?? 'Unknown Farmer') }}',
                                            '{{ addslashes($history->readable_name ?? 'N/A') }}',
                                            '{{ $history->confidence ?? 0 }}%',
                                            '{{ $scannedAt->format('F j, Y h:i A') }} (PHT)',
                                            '{{ addslashes($processedImg) }}',
                                            `<span class='badge {{ $sevColor }}'>{{ $severityLabelDisplay }}</span>`,
                                            this
                                        )">
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-secondary">
                                    <i class="fas fa-history fs-1 mb-3 opacity-50"></i>
                                    <br>No detection history found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="viewDetectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark border border-secondary shadow-lg rounded-4">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-white fw-bold"><i class="fas fa-microscope text-primary me-2"></i>Diagnosis Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                
                <div class="text-center mb-4 bg-black rounded-3 overflow-hidden border border-secondary" style="position: relative; min-height: 200px; display:flex; align-items:center; justify-content:center;">
                    <button type="button" id="modalDownloadBtn" onclick="downloadDetectionImage()"
                        class="btn btn-sm btn-dark border-secondary shadow"
                        style="position: absolute; top: 8px; right: 8px; z-index: 5; opacity: .9; display: none;"
                        title="Download image" aria-label="Download image">
                        <i class="fas fa-download"></i>
                    </button>
                    <div id="modalImageWrap" style="position: relative; display: none; max-width: 100%;">
                        <img id="modalImage" src="" class="img-fluid" style="max-height: 300px; display: block;" alt="Detection Image">
                        <canvas id="modalImageBoxes" style="position: absolute; left: 0; top: 0; pointer-events: none;"></canvas>
                    </div>
                    <div id="noImageText" class="text-secondary p-4" style="display:none;">
                        <i class="fas fa-image fs-1 mb-2 opacity-50"></i><br>No Image Provided
                    </div>
                </div>

                <div class="bg-secondary bg-opacity-10 rounded-3 p-3 border border-secondary">
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2">
                        <span class="text-secondary">Farmer Name</span>
                        <span id="modalFarmer" class="fw-bold text-white text-end"></span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2">
                        <span class="text-secondary">AI Diagnosis</span>
                        <span id="modalDiagnosis" class="fw-bold text-primary text-end"></span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2">
                        <span class="text-secondary">Model Used</span>
                        <span id="modalModel" class="fw-bold text-white text-end"></span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2">
                        <span class="text-secondary">Severity Level</span>
                        <span id="modalSeverity" class="fw-bold text-end"></span>
                    </div>
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2">
                        <span class="text-secondary">Damage Level</span>
                        <span id="modalDamage" class="fw-bold text-end"></span>
                    </div>
                    <div id="modalSeverityMsg" class="small fst-italic text-light border-bottom border-secondary pb-2 mb-2" style="display:none;"></div>
                    <div class="d-flex justify-content-between border-bottom border-secondary pb-2 mb-2">
                        <span class="text-secondary">Confidence Score</span>
                        <span id="modalConfidence" class="fw-bold text-success text-end"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Date Scanned</span>
                        <span id="modalDate" class="text-white text-end small"></span>
                    </div>
                </div>
                
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Close Window</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// YOLO11n boxes for the scan currently open in the modal (null for other models).
let pendingBoxes = null, pendingSrcW = 0, pendingSrcH = 0;
let pendingFileName = 'detection';

// Boxes are in the ORIGINAL photo's pixel space (src_w x src_h); scale them
// onto whatever size the photo actually rendered at inside the modal.
function drawModalBoxes() {
    const canvas = document.getElementById('modalImageBoxes');
    const img = document.getElementById('modalImage');
    if (!canvas || !img) return;

    // Modal still fading in (or photo not loaded) => the <img> reads 0x0.
    // shown.bs.modal / the image's load event call this again once it's real.
    const w = img.clientWidth, h = img.clientHeight;
    if (!w || !h) return;

    canvas.width = w;
    canvas.height = h;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, w, h);

    if (!pendingBoxes || !pendingBoxes.length || !pendingSrcW || !pendingSrcH) return;

    const scale = Math.min(w / pendingSrcW, h / pendingSrcH);
    const ox = (w - pendingSrcW * scale) / 2, oy = (h - pendingSrcH * scale) / 2;

    pendingBoxes.forEach(d => {
        if (!d || !d.box) return;
        const x = ox + d.box.x * scale, y = oy + d.box.y * scale;
        const bw = d.box.width * scale, bh = d.box.height * scale;

        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, bw, bh);

        const label = d.label || d.className || '';
        if (!label) return;
        ctx.font = '600 12px system-ui, sans-serif';
        const textW = ctx.measureText(label).width + 10;
        const labelH = 18;
        ctx.fillStyle = '#10b981';
        ctx.fillRect(x, Math.max(0, y - labelH), textW, labelH);
        ctx.fillStyle = '#06281f';
        ctx.fillText(label, x + 5, Math.max(12, y - 5));
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('viewDetectionModal');
    modalEl.addEventListener('shown.bs.modal', drawModalBoxes);
    modalEl.addEventListener('hidden.bs.modal', () => {
        pendingBoxes = null;
        const c = document.getElementById('modalImageBoxes');
        c.getContext('2d').clearRect(0, 0, c.width, c.height);
    });
    document.getElementById('modalImage').addEventListener('load', drawModalBoxes);
});

// ---- Download (for model training) -------------------------------------
function triggerDownload(href, filename) {
    const a = document.createElement('a');
    a.href = href;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
}

function imageExtension(src) {
    const m = /^data:image\/(\w+)/.exec(src);
    if (m) return m[1] === 'jpeg' ? 'jpg' : m[1];
    const e = (src.split('?')[0].match(/\.(jpe?g|png|webp)$/i) || [])[1];
    return e ? e.toLowerCase().replace('jpeg', 'jpg') : 'jpg';
}

// YOLO11n scan with boxes -> full-resolution copy of the photo with the boxes
// burned in. Any other scan -> the original photo, untouched.
function downloadDetectionImage() {
    const img = document.getElementById('modalImage');
    if (!img || !img.getAttribute('src') || !img.naturalWidth) return;

    const hasBoxes = pendingBoxes && pendingBoxes.length && pendingSrcW && pendingSrcH;
    if (!hasBoxes) {
        triggerDownload(img.src, pendingFileName + '.' + imageExtension(img.src));
        return;
    }

    const nw = img.naturalWidth, nh = img.naturalHeight;
    const canvas = document.createElement('canvas');
    canvas.width = nw;
    canvas.height = nh;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(img, 0, 0, nw, nh);

    // Same math as the modal preview, at the photo's real size.
    const k = Math.max(1, Math.max(nw, nh) / 640);
    const scale = Math.min(nw / pendingSrcW, nh / pendingSrcH);
    const ox = (nw - pendingSrcW * scale) / 2, oy = (nh - pendingSrcH * scale) / 2;

    pendingBoxes.forEach(d => {
        if (!d || !d.box) return;
        const x = ox + d.box.x * scale, y = oy + d.box.y * scale;
        const bw = d.box.width * scale, bh = d.box.height * scale;

        ctx.strokeStyle = '#10b981';
        ctx.lineWidth = 2 * k;
        ctx.strokeRect(x, y, bw, bh);

        const label = d.label || d.className || '';
        if (!label) return;
        ctx.font = '600 ' + (12 * k) + 'px system-ui, sans-serif';
        const textW = ctx.measureText(label).width + 10 * k;
        const labelH = 18 * k;
        ctx.fillStyle = '#10b981';
        ctx.fillRect(x, Math.max(0, y - labelH), textW, labelH);
        ctx.fillStyle = '#06281f';
        ctx.fillText(label, x + 5 * k, Math.max(12 * k, y - 5 * k));
    });

    const fallback = () => triggerDownload(img.src, pendingFileName + '.' + imageExtension(img.src));
    try {
        canvas.toBlob(blob => {
            if (!blob) { fallback(); return; }
            const url = URL.createObjectURL(blob);
            triggerDownload(url, pendingFileName + '_boxes.jpg');
            setTimeout(() => URL.revokeObjectURL(url), 2000);
        }, 'image/jpeg', 0.95);
    } catch (e) {
        // Canvas blocked (cross-origin photo) - at least save the plain photo.
        fallback();
    }
}

function viewDetection(farmer, diagnosis, confidence, date, imageUrl, severityHtml, btn) {
    // 1. Fill the text and HTML data
    document.getElementById('modalFarmer').innerText = farmer;
    document.getElementById('modalDiagnosis').innerText = diagnosis;
    document.getElementById('modalSeverity').innerHTML = severityHtml;
    document.getElementById('modalConfidence').innerText = confidence;
    document.getElementById('modalDate').innerText = date;
    document.getElementById('modalModel').innerText = (btn && btn.dataset.model) || '—';

    // Damage level + severity message (English)
    const dmgEl = document.getElementById('modalDamage');
    const sevPct = btn && btn.dataset.sevPercent;
    dmgEl.innerText = (sevPct !== undefined && sevPct !== '') ? sevPct + '%' : '—';
    dmgEl.className = 'fw-bold text-end ' + ((btn && btn.dataset.sevText) || 'text-light');
    const sevMsgEl = document.getElementById('modalSeverityMsg');
    const sevMsg = (btn && btn.dataset.sevMessage) || '';
    sevMsgEl.innerText = sevMsg;
    sevMsgEl.style.display = sevMsg ? 'block' : 'none';

    // File name + tooltip for the download button
    const slug = s => String(s || '').toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
    pendingFileName = [slug(diagnosis), slug(btn && btn.dataset.model), btn && btn.dataset.scanId].filter(Boolean).join('_') || 'detection';

    // YOLO11n boxes for this scan (empty for every other model).
    pendingBoxes = null;
    pendingSrcW = pendingSrcH = 0;
    if (btn && btn.dataset.boxes) {
        try { pendingBoxes = JSON.parse(btn.dataset.boxes); } catch (e) { pendingBoxes = null; }
        pendingSrcW = parseFloat(btn.dataset.boxesSrcW) || 0;
        pendingSrcH = parseFloat(btn.dataset.boxesSrcH) || 0;
    }
    const dlBtn = document.getElementById('modalDownloadBtn');
    const dlHasBoxes = !!(pendingBoxes && pendingBoxes.length && pendingSrcW && pendingSrcH);
    dlBtn.title = dlHasBoxes ? 'Download image with bounding boxes' : 'Download image';
    dlBtn.setAttribute('aria-label', dlBtn.title);

    // 2. Handle the image logic
    const imgEl = document.getElementById('modalImage');
    const wrapEl = document.getElementById('modalImageWrap');
    const noImgEl = document.getElementById('noImageText');
    const canvasEl = document.getElementById('modalImageBoxes');
    canvasEl.getContext('2d').clearRect(0, 0, canvasEl.width, canvasEl.height);

    if (imageUrl && imageUrl !== 'null' && imageUrl !== '') {
        let formattedUrl = imageUrl;

        // Secure handling for standard paths or direct Base64 strings
        if (imageUrl.startsWith('data:image/')) {
            formattedUrl = imageUrl;
        } else if (imageUrl.startsWith('http') || imageUrl.startsWith('/') || imageUrl.startsWith('uploads/')) {
            formattedUrl = imageUrl.startsWith('uploads/') ? '/' + imageUrl : imageUrl;
        } else {
            // Fallback for older database records that might be raw base64 data without prefix
            formattedUrl = 'data:image/jpeg;base64,' + imageUrl;
        }

        imgEl.src = formattedUrl;
        wrapEl.style.display = 'inline-block';
        dlBtn.style.display = 'block';

        if (noImgEl) noImgEl.style.display = 'none';
    } else {
        imgEl.removeAttribute('src');
        wrapEl.style.display = 'none';
        dlBtn.style.display = 'none';

        if (noImgEl) noImgEl.style.display = 'block';
    }

    // 3. Trigger the Bootstrap Modal (drawModalBoxes runs on shown.bs.modal
    //    and when the photo finishes loading, whichever comes last).
    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewDetectionModal')).show();
}
</script>
@endsection