@extends('layouts.admin')

@section('title', 'RiceGuard AI • Technician Log')

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
                            <th>Status</th>
                            <th>Joined</th>
                            <th class="text-end" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($technicians as $row)
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
                            <td colspan="5" class="text-center py-5 text-muted">No technicians found.</td>
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