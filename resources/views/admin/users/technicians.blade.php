@extends('layouts.admin')

@section('title', 'RICEGUARD AI • Manage Technicians')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Technician Log</h4>
            <p class="text-muted mb-0">Manage all technicians</p>
        </div>
        <a href="{{ route('admin.technician.create') }}" class="btn btn-success">
            <i class="fas fa-user-plus"></i> Create New Technician
        </a>
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
                    <tbody id="technicians-table-body">
                        @forelse($technicians as $row)
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
                            <td colspan="7" class="text-center py-5 text-muted">No technicians found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('partials.admin-user-edit-modal')
@endsection

@section('scripts')
<script>
    const locationCache = {};

    function resolveLocations() {
        document.querySelectorAll('.device-location').forEach(cell => {
            if (cell.classList.contains('resolved')) return;

            const lat = cell.getAttribute('data-lat');
            const lng = cell.getAttribute('data-lng');

            if (lat && lng) {
                const cacheKey = `${lat},${lng}`;

                if (locationCache[cacheKey]) {
                    cell.innerHTML = locationCache[cacheKey];
                    cell.classList.add('resolved');
                    return;
                }

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
                        locationCache[cacheKey] = resultHtml; 
                    })
                    .catch(error => {
                        cell.innerHTML = `<span class="text-danger">Error resolving</span>`;
                    });
            }
        });
    }

    document.addEventListener("DOMContentLoaded", () => {
        resolveLocations();
    });

    setInterval(function() {
        fetch(window.location.href) 
            .then(response => response.text())
            .then(html => {
                let parser = new DOMParser();
                let doc = parser.parseFromString(html, 'text/html');
                let newTableBody = doc.querySelector('#technicians-table-body');
                
                if (newTableBody) {
                    document.getElementById('technicians-table-body').innerHTML = newTableBody.innerHTML;
                    resolveLocations();
                }
            })
            .catch(error => console.error('Error fetching updates:', error));
    }, 5000); 
</script>
@endsection