@extends('layouts.farmer')

@section('title', 'CROPSENSE AI • Announcements')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold text-white">
                <i class="fas fa-bullhorn text-warning me-3"></i> Announcements
            </h2>
            <p class="text-secondary">Important updates from the Admin</p>
        </div>
    </div>

    <div class="row">
        @if($announcements->isEmpty())
            <div class="col-12">
                <div class="card bg-dark border-secondary text-center py-5">
                    <i class="fas fa-bullhorn fa-4x text-muted mb-3"></i>
                    <h5 class="text-secondary">No Announcements Yet</h5>
                    <p class="text-muted">Important messages will appear here.</p>
                </div>
            </div>
        @else
            @foreach($announcements as $ann)
            <div class="col-lg-8 col-md-10 mx-auto mb-4">
                <div class="card bg-dark border-secondary {{ $ann->urgent ? 'border-danger' : '' }}">
                    @if($ann->urgent)
                        <div class="card-header bg-danger text-white">
                            <i class="fas fa-exclamation-triangle"></i> URGENT ANNOUNCEMENT
                        </div>
                    @endif
                    <div class="card-body">
                        <h5 class="card-title text-white">{{ $ann->title }}</h5>
                        <small class="text-muted">
                            {{ $ann->created_at->format('M j, Y • g:i A') }}
                        </small>
                        <p class="mt-3 text-light">{{ nl2br(e($ann->message)) }}</p>
                        
                        @if($ann->creator)
                            <small class="text-secondary">Posted by: {{ $ann->creator->full_name }}</small>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        @endif
    </div>
</div>
@endsection