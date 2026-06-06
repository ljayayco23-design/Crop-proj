@extends('layouts.admin')

@section('title', 'CROPSENSE AI • Manage Announcements')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold text-white">
                <i class="fas fa-bullhorn text-success me-3"></i> Manage Announcements
            </h2>
            <p class="text-secondary">Create and manage system-wide announcements</p>
        </div>
    </div>

    <div class="row g-4">
        <!-- Create Form -->
        <div class="col-lg-5">
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-transparent border-secondary">
                    <h5 class="mb-0"><i class="fas fa-plus me-2"></i> Create New Announcement</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.announcement.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label">Target Group</label>
                            <select name="target_role" class="form-select" required>
                                <option value="global">🌍 Global (All Users)</option>
                                <option value="farmer">🌾 Farmers Only</option>
                                <option value="technician">🔧 Technicians Only</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Typhoon Warning" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Message</label>
                            <textarea name="message" rows="5" class="form-control" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-paper-plane me-2"></i> Send Announcement
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- List -->
        <div class="col-lg-7">
            <div class="card bg-dark border-secondary">
                <div class="card-header bg-transparent border-secondary d-flex justify-content-between">
                    <h5 class="mb-0">All Announcements ({{ $announcements->count() }})</h5>
                </div>
                <div class="card-body">
                    @if($announcements->isEmpty())
                        <div class="text-center py-5 text-secondary">
                            <i class="fas fa-bullhorn fa-3x mb-3 opacity-50"></i>
                            <p>No announcements yet.</p>
                        </div>
                    @else
                        @foreach($announcements as $ann)
                        <div class="card mb-3 bg-dark border-secondary {{ $ann->urgent ? 'border-danger' : '' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="text-white">{{ $ann->title }}</h6>
                                        <small class="text-muted">
                                            {{ $ann->created_at->format('M j, Y g:i A') }} • 
                                            Target: <strong>{{ ucfirst($ann->role) }}</strong>
                                        </small>
                                    </div>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-link text-light" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item edit-btn" href="#" 
                                                   data-id="{{ $ann->id }}"
                                                   data-title="{{ $ann->title }}"
                                                   data-message="{{ $ann->message }}"
                                                   data-role="{{ $ann->role }}">
                                                    <i class="fas fa-edit me-2"></i>Edit
                                                </a>
                                            </li>
                                            <li>
                                                <form action="{{ route('admin.announcement.destroy', $ann) }}" method="POST">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Delete this announcement?')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <p class="mt-3 text-light">{{ nl2br(e($ann->message)) }}</p>
                                @if($ann->urgent)
                                    <span class="badge bg-danger">URGENT</span>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light border-secondary">
            <form id="editForm" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" id="edit_id" name="id">
                
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Edit Announcement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Target Group</label>
                        <select name="target_role" id="edit_role" class="form-select">
                            <option value="global">Global</option>
                            <option value="farmer">Farmers</option>
                            <option value="technician">Technicians</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Title</label>
                        <input type="text" name="title" id="edit_title" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label>Message</label>
                        <textarea name="message" id="edit_message" rows="5" class="form-control"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
document.querySelectorAll('.edit-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('edit_id').value = this.dataset.id;
        document.getElementById('edit_title').value = this.dataset.title;
        document.getElementById('edit_message').value = this.dataset.message;
        document.getElementById('edit_role').value = this.dataset.role;
        
        document.getElementById('editForm').action = `/admin/announcement/${this.dataset.id}`;
        
        new bootstrap.Modal(document.getElementById('editModal')).show();
    });
});
</script>
@endsection