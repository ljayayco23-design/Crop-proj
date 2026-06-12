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
                            <td colspan="5" class="text-center py-5 text-muted">No farmers found.</td>
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
    // Silently fetch the latest table data every 5 seconds
    setInterval(function() {
        fetch(window.location.href) // Fetch the current page URL
            .then(response => response.text())
            .then(html => {
                // Parse the new HTML
                let parser = new DOMParser();
                let doc = parser.parseFromString(html, 'text/html');
                
                // Find the new table body from the background fetch
                let newTableBody = doc.querySelector('#farmers-table-body');
                
                // If we found it, replace the current table body with the new one
                if (newTableBody) {
                    document.getElementById('farmers-table-body').innerHTML = newTableBody.innerHTML;
                }
            })
            .catch(error => console.error('Error fetching updates:', error));
    }, 5000); // 5000 milliseconds = 5 seconds
</script>
@endsection