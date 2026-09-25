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
            'rice_stem_borer' => 30, 'snail' => 75
        ];

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

                                // Calculate Severity Value and Badge Color
                                $cKey = $history->class_key ?? str_replace(' ', '_', strtolower($history->readable_name));
                                $severityVal = $severityMap[$cKey] ?? 'N/A';
                                
                                $sevColor = 'bg-secondary text-light';
                                if($severityVal === 0) $sevColor = 'bg-success text-white';
                                elseif($severityVal !== 'N/A' && $severityVal <= 30) $sevColor = 'bg-info text-dark';
                                elseif($severityVal !== 'N/A' && $severityVal <= 50) $sevColor = 'bg-warning text-dark';
                                elseif($severityVal !== 'N/A' && $severityVal > 50) $sevColor = 'bg-danger text-white';
                                
                                $severityDisplay = $severityVal !== 'N/A' ? $severityVal . '%' : 'N/A';

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
                                        onclick="viewDetection(
                                            '{{ addslashes($history->user_name ?? 'Unknown Farmer') }}',
                                            '{{ addslashes($history->readable_name ?? 'N/A') }}',
                                            '{{ $history->confidence ?? 0 }}%',
                                            '{{ $scannedAt->format('F j, Y h:i A') }} (PHT)',
                                            '{{ addslashes($processedImg) }}',
                                            `<span class='badge {{ $sevColor }}'>{{ $severityDisplay }}</span>`,
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
                
                <div class="text-center mb-4 bg-black rounded-3 overflow-hidden border border-secondary" style="min-height: 200px; display:flex; align-items:center; justify-content:center;">
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

function viewDetection(farmer, diagnosis, confidence, date, imageUrl, severityHtml, btn) {
    // 1. Fill the text and HTML data
    document.getElementById('modalFarmer').innerText = farmer;
    document.getElementById('modalDiagnosis').innerText = diagnosis;
    document.getElementById('modalSeverity').innerHTML = severityHtml;
    document.getElementById('modalConfidence').innerText = confidence;
    document.getElementById('modalDate').innerText = date;
    document.getElementById('modalModel').innerText = (btn && btn.dataset.model) || '—';

    // YOLO11n boxes for this scan (empty for every other model).
    pendingBoxes = null;
    pendingSrcW = pendingSrcH = 0;
    if (btn && btn.dataset.boxes) {
        try { pendingBoxes = JSON.parse(btn.dataset.boxes); } catch (e) { pendingBoxes = null; }
        pendingSrcW = parseFloat(btn.dataset.boxesSrcW) || 0;
        pendingSrcH = parseFloat(btn.dataset.boxesSrcH) || 0;
    }

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

        if (noImgEl) noImgEl.style.display = 'none';
    } else {
        imgEl.removeAttribute('src');
        wrapEl.style.display = 'none';

        if (noImgEl) noImgEl.style.display = 'block';
    }

    // 3. Trigger the Bootstrap Modal (drawModalBoxes runs on shown.bs.modal
    //    and when the photo finishes loading, whichever comes last).
    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewDetectionModal')).show();
}
</script>
@endsection