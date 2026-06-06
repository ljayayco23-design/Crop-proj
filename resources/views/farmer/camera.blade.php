@extends('layouts.farmer')

@section('title', 'Live Camera • CROPSENSE AI')

@section('content')
<style>
    .hidden { display: none !important; }
</style>

<div class="page-header mb-4">
    <div class="page-header-title">
        <h4 class="m-b-10 fw-bold">Live Camera • CROPSENSE AI</h4>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-lg bg-dark border-secondary">
            <div class="card-body p-0">
                <div id="camera-view" class="position-relative overflow-hidden rounded">
                    <video id="video" autoplay playsinline class="w-100 bg-black" style="height: 600px; object-fit: cover;"></video>
                    
                    <div class="position-absolute top-50 start-50 translate-middle pointer-events-none">
                        <div class="border border-success border-3 rounded-4" style="width: 280px; height: 280px; box-shadow: 0 0 30px rgba(16,185,129,0.5);"></div>
                    </div>

                    <div class="position-absolute bottom-0 start-0 end-0 p-4" style="background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);">
                        <div class="d-flex justify-content-center">
                            <button type="button" onclick="capturePhoto()" class="btn btn-light rounded-circle p-1 shadow-lg border-0" style="width: 70px; height: 70px;">                                <div class="bg-white rounded-circle border border-4 border-dark w-100 h-100"></div>
                            </button>
                        </div>
                    </div>
                </div>

                <div id="preview-view" class="hidden p-4">
                    <div class="text-center mb-3">
                        <h5 class="fw-bold">Image Captured</h5>
                    </div>
                    <div class="text-center">
                        <img id="preview-image" class="img-fluid rounded-3 shadow border border-secondary" style="max-height: 500px; width: 100%; object-fit: contain;">
                    </div>
                    <div class="d-flex gap-3 mt-4">
                      <button type="button" onclick="retake()" class="btn btn-outline-secondary py-2 flex-fill fw-bold">
                        <i class="fas fa-rotate-left me-2"></i> Retake
                    </button>
                    <button type="button" onclick="sendToIndex()" class="btn btn-success py-2 flex-fill fw-bold">
                        <i class="fas fa-magnifying-glass me-2"></i> Classify Image
                    </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
let video = null;
let canvas = null;
let capturedImage = null;

function initCamera() {
    video = document.getElementById('video');
    canvas = document.createElement('canvas');

    navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
    .then(stream => { video.srcObject = stream; })
    .catch(err => {
        alert("Cannot access camera. Please allow camera permissions in your browser settings.");
        console.error(err);
    });
}

function capturePhoto() {
    if (!video) return;
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0);

    capturedImage = canvas.toDataURL('image/jpeg', 0.92);

    document.getElementById('camera-view').classList.add('hidden');
    document.getElementById('preview-view').classList.remove('hidden');
    document.getElementById('preview-image').src = capturedImage;
}

function retake() {
    document.getElementById('preview-view').classList.add('hidden');
    document.getElementById('camera-view').classList.remove('hidden');
}

function sendToIndex() {
    if (!capturedImage) return;
    // Save image to browser memory specifically for bridging to the next page
    sessionStorage.setItem('capturedImage', capturedImage);
    window.location.href = "{{ route('farmer.detection') }}";
}

window.onload = function() {
    initCamera();
};
</script>
@endsection