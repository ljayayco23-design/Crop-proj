@extends('layouts.admin')

@section('title', 'RICEGUARD AI • Create Technician')

@section('content')
<div class="page-header">
    <div class="page-header-title">
        <h5 class="m-b-10">Create New Technician</h5>
        <p class="text-muted mb-0">Add a new technician to the system</p>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body p-5">
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('admin.technician.store') }}">
                    @csrf

                    <div class="mb-4">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3">
                        Create Technician Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection