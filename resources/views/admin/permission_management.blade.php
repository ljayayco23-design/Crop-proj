@extends('layouts.admin')

@section('title', 'RICEGUARD AI • Permission Management')

@section('content')
<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-header-title">
        <h5 class="m-b-10">Permission Management</h5>
        <p class="text-muted mb-0">Control what each role can do per module.</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.permissions.update') }}">
    @csrf

    @foreach ($modules as $moduleKey => $moduleLabel)
        <div class="card mb-4">
            <div class="card-header">
                <strong>{{ $moduleLabel }}</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 16%">Role</th>
                            <th class="text-center">View | Info</th>
                            <th class="text-center">Create</th>
                            <th class="text-center">Edit</th>
                            <th class="text-center">Delete</th>
                            <th class="text-center">Hide</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Editable rows: technician always, admin only when the actor is developer.
                             "View" is a master switch for that role's whole User Log page; "Info"
                             gates the Info button/panel for that role's accounts specifically. Both
                             live in one bordered cell so they read as one grouped control. --}}
                        @foreach ($editableRoles as $role)
                            @php $row = $matrices[$moduleKey][$role] ?? []; @endphp
                            <tr>
                                <td class="text-capitalize fw-semibold">{{ $role }}</td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-3">
                                        <div class="form-check form-switch mb-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                id="{{ $moduleKey }}-{{ $role }}-view"
                                                name="permissions[{{ $moduleKey }}][{{ $role }}][can_view]"
                                                value="1"
                                                {{ !empty($row['can_view']) ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label small" for="{{ $moduleKey }}-{{ $role }}-view">View</label>
                                        </div>
                                        <div class="form-check form-switch mb-0">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                id="{{ $moduleKey }}-{{ $role }}-info"
                                                name="permissions[{{ $moduleKey }}][{{ $role }}][can_info]"
                                                value="1"
                                                {{ !empty($row['can_info']) ? 'checked' : '' }}
                                            >
                                            <label class="form-check-label small" for="{{ $moduleKey }}-{{ $role }}-info">Info</label>
                                        </div>
                                    </div>
                                </td>
                                @foreach (['can_create' => 'Create', 'can_edit' => 'Edit', 'can_delete' => 'Delete', 'hide_module' => 'Hide'] as $field => $label)
                                    <td class="text-center">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                name="permissions[{{ $moduleKey }}][{{ $role }}][{{ $field }}]"
                                                value="1"
                                                {{ !empty($row[$field]) ? 'checked' : '' }}
                                            >
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        {{-- Locked rows: farmer has no admin/technician-style panel today, so it's
                             always shown blocked and can't be turned on from this screen. --}}
                        @foreach ($lockedRoles as $role)
                            <tr class="table-secondary">
                                <td class="text-capitalize fw-semibold">{{ $role }}</td>
                                <td colspan="5" class="text-center text-muted">
                                    <i class="fa-solid fa-lock me-1"></i> Blocked
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary px-4">
        <i class="fas fa-save me-1"></i> Save Permissions
    </button>
</form>
@endsection