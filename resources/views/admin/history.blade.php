@extends('layouts.admin')

@section('title', 'CROPSENSE AI • Detection History')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 text-white">All Detection History</h4>
            <p class="text-secondary mb-0">View all AI detections and user history</p>
        </div>
    </div>

    <div class="card bg-dark border border-secondary shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover mb-0 align-middle">
                    <thead class="border-bottom border-secondary">
                        <tr>
                            <th class="text-secondary">Date & Time</th>
                            <th class="text-secondary">Farmer</th>
                            <th class="text-secondary">Detection Result</th>
                            <th class="text-secondary">Confidence</th>
                            <th class="text-secondary text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($histories as $history)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($history->created_at ?? now())->format('M d, Y h:i A') }}</td>
                                <td>
                                    <div class="fw-bold text-white">{{ $history->user_name ?? 'Unknown Farmer' }}</div>
                                    <small class="text-secondary">{{ $history->user_email ?? 'No email' }}</small>
                                </td>
                                <td><span class="badge bg-primary">{{ $history->readable_name ?? 'N/A' }}</span></td>
                                <td>{{ $history->confidence ?? 0 }}%</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-info text-dark fw-bold shadow-sm" 
                                        onclick="viewDetection(
                                            '{{ addslashes($history->user_name ?? 'Unknown Farmer') }}',
                                            '{{ addslashes($history->readable_name) }}',
                                            '{{ $history->confidence }}%',
                                            '{{ \Carbon\Carbon::parse($history->created_at)->format('F j, Y h:i A') }}',
                                            '{{ $history->image_url }}'
                                        )">
                                        <i class="fas fa-eye me-1"></i> View
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-secondary">
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
    function viewDetection(farmer, diagnosis, confidence, date, imageUrl) {
        // Fill the text data
        document.getElementById('modalFarmer').innerText = farmer;
        document.getElementById('modalDiagnosis').innerText = diagnosis;
        document.getElementById('modalConfidence').innerText = confidence;
        document.getElementById('modalDate').innerText = date;
        
        // Handle the image logic
        const imgEl = document.getElementById('modalImage');
        const noImgEl = document.getElementById('noImageText');
        
        if (imageUrl && imageUrl !== 'null' && imageUrl !== '') {
            imgEl.src = imageUrl;
            imgEl.style.display = 'block';
            noImgEl.style.display = 'none';
        } else {
            imgEl.src = '';
            imgEl.style.display = 'none';
            noImgEl.style.display = 'block';
        }
        
        // Trigger the Bootstrap Modal
        var myModal = new bootstrap.Modal(document.getElementById('viewDetectionModal'));
        myModal.show();
    }
</script>
@endsection