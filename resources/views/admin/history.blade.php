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
    @endphp

    <div class="card bg-dark border border-secondary shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="border-bottom border-secondary">
                        <tr>
                            <th class="text-secondary">Date & Time</th>
                            <th class="text-secondary">Farmer</th>
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
                            @endphp
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($history->created_at ?? now())->format('M d, Y h:i A') }}</td>
                                <td>
                                    <div class="fw-bold text-white">{{ $history->user_name ?? 'Unknown Farmer' }}</div>
                                    <small class="text-secondary">{{ $history->user_email ?? 'No email' }}</small>
                                </td>
                                <td><span class="badge bg-primary">{{ $history->readable_name ?? 'N/A' }}</span></td>
                                <td>
                                    <span class="badge {{ $sevColor }}">{{ $severityDisplay }}</span>
                                </td>
                                <td>{{ $history->confidence ?? 0 }}%</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-info text-dark fw-bold shadow-sm" 
                                        onclick="viewDetection(
                                            '{{ addslashes($history->user_name ?? 'Unknown Farmer') }}',
                                            '{{ addslashes($history->readable_name ?? 'N/A') }}',
                                            '{{ $history->confidence ?? 0 }}%',
                                            '{{ \Carbon\Carbon::parse($history->created_at ?? now())->format('F j, Y h:i A') }}',
                                            '{{ addslashes($processedImg) }}',
                                            `<span class='badge {{ $sevColor }}'>{{ $severityDisplay }}</span>`
                                        )">
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-secondary">
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
                    <img id="modalImage" src="" class="img-fluid" style="max-height: 300px; display:none;" alt="Detection Image">
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
function viewDetection(farmer, diagnosis, confidence, date, imageUrl, severityHtml) {
    // 1. Fill the text and HTML data
    document.getElementById('modalFarmer').innerText = farmer;
    document.getElementById('modalDiagnosis').innerText = diagnosis;
    document.getElementById('modalSeverity').innerHTML = severityHtml;
    document.getElementById('modalConfidence').innerText = confidence;
    document.getElementById('modalDate').innerText = date;

    // 2. Handle the image logic
    const imgEl = document.getElementById('modalImage');
    const noImgEl = document.getElementById('noImageText');

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
        imgEl.style.display = 'block';
        
        if (noImgEl) noImgEl.style.display = 'none';
    } else {
        imgEl.src = '';
        imgEl.style.display = 'none';
        
        if (noImgEl) noImgEl.style.display = 'block';
    }

    // 3. Trigger the Bootstrap Modal
    var modalElement = document.getElementById('viewDetectionModal');
    var myModal = new bootstrap.Modal(modalElement);
    myModal.show();
}
</script>
@endsection