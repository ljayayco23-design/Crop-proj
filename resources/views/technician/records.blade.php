@extends('layouts.technician')

@section('title', 'Farmers Records • RICEGUARD AI')

@section('content')
<style>
    .knowledge-section { border-bottom: 1px solid rgba(255,255,255,0.08); padding: 10px 2px 12px; margin-bottom: 12px; }
    .knowledge-section:last-child { border-bottom: none; }
    .image-gallery img { transition: all 0.2s; cursor: pointer; border: 2px solid transparent; }
    .image-gallery img:hover { transform: scale(1.05); border-color: #3b82f6; z-index: 10; position: relative;}
    .img-container { position: relative; display: inline-block; }

    /* Same collapsed-row + floating-detail-panel pattern as the farmer's
       own Detection History page (resources/views/farmer/history.blade.php)
       — kept visually identical on purpose so both sides feel like the
       same product. */
    .detection-row { position: relative; }
    .detection-row-header { padding: 10px 4px; border-bottom: 1px solid #334155; flex-wrap: wrap; row-gap: 6px; }
    .detection-row-header:last-child { border-bottom: none; }
    .detection-name-toggle { color: #fff; text-decoration: underline; cursor: pointer; font-weight: 500; word-break: break-word; }
    .detection-name-toggle:hover { color: #3b82f6; }
    .detection-date { white-space: nowrap; }

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
    @media (min-width: 992px) {
        .detection-detail-panel { width: min(1000px, 85vw); padding: 32px; }
    }
    @media (max-width: 576px) {
        .detection-detail-panel { width: 96vw; max-height: 92vh; padding: 16px; border-radius: 8px; }
        .detection-detail-panel .fs-2 { font-size: 1.5rem !important; }
        .detection-detail-panel h5 { font-size: 1.05rem; }
        .knowledge-section { padding: 12px; }
        .image-gallery img { width: 64px !important; height: 64px !important; }
        .detection-row-header { gap: 6px !important; }
        .detection-row-header > div { flex-wrap: wrap; row-gap: 6px; justify-content: flex-end; }
    }
    .detection-detail-panel.show { display: block; }
    /* Print + Close now sit together, top-right, on the detail panel
       ("main container") for one category's records. */
    .detection-detail-actions { position: absolute; top: 10px; right: 10px; display: flex; align-items: center; gap: 8px; z-index: 5; }

    /* One outer container per farmer, so with multiple farmers on screen
       it's always obvious whose records you're looking at. */
    .farmer-container { border-left: 3px solid #10b981 !important; }

    /* ---------- Report-style detection header ----------
       Picture + type badge/name + confidence gauge, and the Severity /
       Damage Level stat chips — same visual language as the farmer's own
       Detection History page (resources/views/farmer/history.blade.php),
       reused here so both sides render identically. Without these rules
       the gauge SVG has no stroke/size constraints and renders as a
       solid filled black shape instead of a thin colored arc. */
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
        <h4 class="fw-bold mb-1">Farmers Records & Knowledge Base</h4>
        <p class="text-secondary mb-0">View farmer detections and manage global shared knowledge</p>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success bg-success text-white border-0 shadow-sm alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
    </div>
@endif

@php
    // --- AGRONOMIC DATA MAP FOR SEVERITY --- (unchanged)
    $severityMap = [
        'healthy_rice_plant' => 0, 'bacterial_leaf_blight' => 60, 'leaf_blast' => 80, 
        'rice_false_smut' => 30, 'sheath_blight' => 40, 'tungro_virus' => 85, 
        'brown_planthopper' => 90, 'leaf_folders' => 20, 'leafhopper' => 30, 
        'rice_bug' => 80, 'rice_gall_midge' => 40, 'rice_leaf_roller' => 20, 
        'rice_stem_borer' => 30, 'snail' => 75
    ];
@endphp

<div id="records-container">
    @forelse($allUsersData as $userData)
        <div class="card bg-dark border-secondary shadow-sm mb-5 farmer-container">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4 border-bottom border-secondary pb-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center bg-info text-white" style="width:48px;height:48px;font-size:24px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <h5 class="mb-0 fw-bold text-white">{{ $userData['user_name'] }}</h5>
                        <small class="text-info">{{ $userData['email'] }}</small>
                    </div>
                </div>

                @php
                    $hasAnyForFarmer = collect($userData['fieldSections'] ?? [])->contains(function ($sec) {
                        return !empty($sec['diseases']) || !empty($sec['pests']);
                    });
                @endphp

                @if(!$hasAnyForFarmer)
                    <p class="text-secondary mb-0">This farmer has no detection records yet.</p>
                @else
                    @foreach($userData['fieldSections'] as $section)
                        <div class="field-history-container mb-4 p-3 rounded-3 border border-secondary bg-secondary bg-opacity-10">
                            <h6 class="fw-bold text-white mb-3 d-flex align-items-center gap-2">
                                <i class="fas fa-map-marker-alt text-success"></i> {{ $section['label'] }}
                            </h6>

                            @if(empty($section['diseases']) && empty($section['pests']))
                                <p class="text-secondary mb-0 small">No detections recorded for this field yet.</p>
                            @else
                                <div class="row g-4">
                                    <div class="col-lg-6">
                                        <h6 class="text-success text-uppercase small fw-bold mb-3"><i class="fas fa-disease me-1"></i> Diseases</h6>
                                        @forelse($section['diseases'] as $det)
                                            @include('partials.detection_row', [
                                                'det' => $det,
                                                'severityMap' => $severityMap,
                                                'mode' => 'technician',
                                                'ownerId' => $userData['user_id'] ?? null,
                                            ])
                                        @empty
                                            <p class="text-secondary small">No disease detections yet.</p>
                                        @endforelse
                                    </div>
                                    <div class="col-lg-6">
                                        <h6 class="text-warning text-uppercase small fw-bold mb-3"><i class="fas fa-bug me-1"></i> Pests</h6>
                                        @forelse($section['pests'] as $det)
                                            @include('partials.detection_row', [
                                                'det' => $det,
                                                'severityMap' => $severityMap,
                                                'mode' => 'technician',
                                                'ownerId' => $userData['user_id'] ?? null,
                                            ])
                                        @empty
                                            <p class="text-secondary small">No pest detections yet.</p>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-5">
            <p class="text-secondary mb-0">No farmers found.</p>
        </div>
    @endforelse
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

    // Same floating-detail-panel toggle as the farmer's Detection History
    // page — only one open at a time, closes on backdrop click.
    function toggleDetectionDetail(detId) {
        const panel = document.getElementById('detail-' + detId);
        const backdrop = document.getElementById('detectionDetailBackdrop');
        if (!panel) return;

        const isOpen = panel.classList.contains('show');
        document.querySelectorAll('.detection-detail-panel.show').forEach(p => p.classList.remove('show'));

        if (!isOpen) {
            panel.classList.add('show');
            if (backdrop) backdrop.classList.add('show');
        } else if (backdrop) {
            backdrop.classList.remove('show');
        }
    }

    document.getElementById('detectionDetailBackdrop')?.addEventListener('click', function () {
        document.querySelectorAll('.detection-detail-panel.show').forEach(p => p.classList.remove('show'));
        this.classList.remove('show');
    });

    // Reuse the page's own <style> blocks so the print window renders every
    // component (gauge, knowledge-section, rg-report-* header, badges) exactly
    // like it looks on screen, instead of guessing a subset of rules by hand.
    function getPageStyles() {
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
                    .btn, .detection-detail-actions, .instance-print-btn { display:none !important; }
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

    // Prints a whole category's detail panel (the "main container" — class
    // name + every scan of it). Works on a clone so the on-screen page is
    // untouched, and forces the detail panel open/static on the clone so
    // it actually shows up on paper. Every interactive control (close +
    // both print buttons) is stripped from the clone first.
    function printRow(link) {
        const row = link.closest('.detection-row');
        if (!row) return;

        const clone = row.cloneNode(true);
        const panel = clone.querySelector('.detection-detail-panel');
        if (panel) {
            panel.classList.add('show');
            panel.style.position = 'static';
            panel.style.transform = 'none';
            panel.style.maxHeight = 'none';
            panel.style.overflow = 'visible';
            panel.style.width = '100%';
            panel.style.boxShadow = 'none';
        }
        clone.querySelectorAll('.detection-detail-actions, .instance-print-btn').forEach(el => el.remove());

        openPrintWindow(clone.outerHTML, 'Detection Report');
    }

    // Prints just one individual scan ("individual record") rather than
    // the whole category.
    function printInstance(btn) {
        const card = btn.closest('.instance-full-card');
        if (!card) return;

        const clone = card.cloneNode(true);
        clone.querySelectorAll('.instance-print-btn').forEach(el => el.remove());

        openPrintWindow(clone.outerHTML, 'Detection Scan');
    }
</script>
@endsection