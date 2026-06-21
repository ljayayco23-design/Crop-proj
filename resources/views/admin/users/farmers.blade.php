@extends('layouts.admin')

@section('title', 'RiceGuard AI • Manage Farmers')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Manage Farmers</h4>
            <p class="text-muted mb-0">Approve, edit, and manage farmer accounts</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Registered Address</th>
                            <th>Device Location</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-end" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="farmers-table-body">
                        @forelse($farmers as $row)
                        <tr>
                            <td><strong>{{ $row->full_name }}</strong></td>
                            <td>{{ $row->email }}</td>
                            <td>{{ $row->address ?? 'N/A' }}</td>
                          <td class="device-location" data-lat="{{ $row->device_latitude }}" data-lng="{{ $row->device_longitude }}">
                            @if($row->device_latitude && $row->device_longitude)
                                <span class="text-info"><i class="fas fa-spinner fa-spin me-1"></i> Resolving...</span>
                            @else
                                <span class="text-muted">Not Captured</span>
                            @endif
                        </td>
                            <td>
                                <span class="badge {{ $row->status == 'approved' ? 'bg-success' : ($row->status == 'declined' ? 'bg-danger' : 'bg-warning') }}">
                                    {{ ucfirst($row->status ?? 'pending') }}
                                </span>
                            </td>
                            <td>{{ $row->created_at?->format('M d, Y') }}</td>
                            <td class="text-end">
                                @include('partials.admin-user-actions', ['user' => $row])
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No farmers found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('partials.admin-user-edit-modal')

<div class="modal fade" id="farmerInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-success"><i class="fas fa-id-card me-2"></i> Farmer Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="text-secondary border-bottom border-secondary pb-2">Personal Information</h6>
                        <p class="mb-1"><strong>Name:</strong> <span id="info_name"></span></p>
                        <p class="mb-1"><strong>Email:</strong> <span id="info_email"></span></p>
                        <p class="mb-1"><strong>Phone:</strong> <span id="info_phone"></span></p>
                        <p class="mb-1"><strong>Date of Birth:</strong> <span id="info_dob"></span></p>
                        <p class="mb-1"><strong>Address:</strong> <span id="info_address"></span></p>
                        <p class="mb-1"><strong>Status:</strong> <span id="info_status" class="badge"></span></p>
                    </div>
                    
                    <div class="col-md-6">
                        <h6 class="text-secondary border-bottom border-secondary pb-2">Farm Details</h6>
                        <p class="mb-1"><strong>Farm Name:</strong> <span id="info_farm_name"></span></p>
                        <p class="mb-1"><strong>Role:</strong> <span id="info_role" class="text-capitalize"></span></p>
                        <p class="mb-1"><strong>Location:</strong> <span id="info_location"></span></p>
                        <p class="mb-1"><strong>Farm Size:</strong> <span id="info_size"></span></p>
                        <p class="mb-1"><strong>Water Source:</strong> <span id="info_water" class="text-capitalize"></span></p>
                    </div>

                    <div class="col-12 mt-4">
                        <h6 class="text-secondary border-bottom border-secondary pb-2">Verification Documents (<span id="info_id_type" class="text-capitalize"></span>)</h6>
                        <div class="row mt-3">
                            <div class="col-md-12 text-center">
                                <p class="text-muted small mb-2">ID Document</p>
                                <img id="info_doc_img" src="" class="img-fluid rounded border border-secondary" style="max-height: 250px; object-fit: contain; display: none;">
                                <span id="no_doc" class="text-muted fst-italic">No Document Found</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
const locationCache = {};

    function resolveLocations() {
        document.querySelectorAll('.device-location').forEach(cell => {
            // Skip if already resolved
            if (cell.classList.contains('resolved')) return;

            const lat = cell.getAttribute('data-lat');
            const lng = cell.getAttribute('data-lng');

            if (lat && lng) {
                const cacheKey = `${lat},${lng}`;

                // Check if we already looked up this coordinate
                if (locationCache[cacheKey]) {
                    cell.innerHTML = locationCache[cacheKey];
                    cell.classList.add('resolved');
                    return;
                }

                // If not in cache, fetch from API
                fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                    .then(res => res.json())
                    .then(data => {
                        let resultHtml = '';
                        if (data && data.display_name) {
                            resultHtml = `<span class="text-success" style="font-size: 0.85rem;"><i class="fas fa-map-marker-alt me-1"></i> ${data.display_name}</span>`;
                        } else {
                            resultHtml = `<span class="text-warning">Location not found</span>`;
                        }
                        
                        cell.innerHTML = resultHtml;
                        cell.classList.add('resolved');
                        locationCache[cacheKey] = resultHtml; // Save to cache for next 5-second refresh
                    })
                    .catch(error => {
                        cell.innerHTML = `<span class="text-danger">Error resolving</span>`;
                    });
            }
        });
    }

    // 2. Run immediately on page load
    document.addEventListener("DOMContentLoaded", () => {
        resolveLocations();
        infoModal = new bootstrap.Modal(document.getElementById('farmerInfoModal'));
    });

    // 3. Update the 5-second refresh interval
    setInterval(function() {
        fetch(window.location.href) 
            .then(response => response.text())
            .then(html => {
                let parser = new DOMParser();
                let doc = parser.parseFromString(html, 'text/html');
                let newTableBody = doc.querySelector('#farmers-table-body');
                
                if (newTableBody) {
                    document.getElementById('farmers-table-body').innerHTML = newTableBody.innerHTML;
                    
                    // RE-RUN THE GEOCODING ON THE NEW HTML!
                    resolveLocations();
                }
            })
            .catch(error => console.error('Error fetching updates:', error));
    }, 5000); 

    let infoModal;

function viewFarmerInfo(userId) {
    infoModal.show();
    document.getElementById('info_name').innerText = 'Loading...';

        const fetchUrl = `{{ url('admin/users') }}/${userId}/info`;

        fetch(fetchUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }
            return response.json();
        })
        .then(user => {
            document.getElementById('info_name').innerText = user.full_name || 'N/A';
            document.getElementById('info_email').innerText = user.email || 'N/A';
            document.getElementById('info_phone').innerText = user.phone || 'N/A';
            document.getElementById('info_dob').innerText = user.dob || 'N/A';
            document.getElementById('info_address').innerText = user.address || 'N/A';
            
            document.getElementById('info_farm_name').innerText = user.farm_name || 'N/A';
            document.getElementById('info_role').innerText = user.farmer_category || 'N/A';
            document.getElementById('info_size').innerText = (user.farm_size ? user.farm_size + ' hectares' : 'N/A');
            
            let locString = [];
            if(user.latitude && user.longitude) locString.push(`Lat: ${user.latitude}, Lng: ${user.longitude}`);
            if(user.municipality) locString.push(user.municipality);
            document.getElementById('info_location').innerText = locString.length ? locString.join(' | ') : 'N/A';
            
            document.getElementById('info_water').innerText = user.water_source || 'N/A';
            document.getElementById('info_id_type').innerText = user.id_type || 'N/A';

            let statusBadge = document.getElementById('info_status');
            if (statusBadge) {
                statusBadge.innerText = (user.status || 'pending').toUpperCase();
                statusBadge.className = 'badge ' + (user.status === 'approved' ? 'bg-success' : (user.status === 'declined' ? 'bg-danger' : 'bg-warning'));
            }

            const renderImage = (imgElement, noImgElement, base64Data) => {
                if (base64Data && base64Data.length > 100) { 
                    let src = base64Data.startsWith('data:image') ? base64Data : `data:image/jpeg;base64,${base64Data}`;
                    imgElement.src = src;
                    imgElement.style.display = 'block';
                    if(noImgElement) noImgElement.style.display = 'none';
                } else {
                    imgElement.style.display = 'none';
                    if(noImgElement) noImgElement.style.display = 'block';
                }
            };

            renderImage(document.getElementById('info_doc_img'), document.getElementById('no_doc'), user.document_photo);
            // Removed selfie render since we deleted the column
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('info_name').innerText = 'Failed to load user info.';
        });
}

// Reverse geocode all device locations on page load
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll('.device-location').forEach(cell => {
        const lat = cell.getAttribute('data-lat');
        const lng = cell.getAttribute('data-lng');
        
        if (lat && lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.display_name) {
                        cell.innerHTML = `<span class="text-success" style="font-size: 0.85rem;"><i class="fas fa-map-marker-alt me-1"></i> ${data.display_name}</span>`;
                    } else {
                        cell.innerHTML = `<span class="text-warning">Location not found</span>`;
                    }
                })
                .catch(error => {
                    cell.innerHTML = `<span class="text-danger">Error resolving</span>`;
                });
        }
    });
});
</script>
@endsection