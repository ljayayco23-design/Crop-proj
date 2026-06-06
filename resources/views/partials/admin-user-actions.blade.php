<div class="dropdown">
    <button class="btn btn-sm btn-link text-light p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fa-solid fa-ellipsis-vertical"></i>
    </button>
    
    <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 180px;">
        <a class="dropdown-item" href="javascript:void(0)" 
           onclick="editUser({{ $user->id }}, '{{ addslashes($user->full_name) }}', '{{ addslashes($user->email) }}', '{{ $user->status ?? "pending" }}')">
            <i class="fa-solid fa-pen me-2"></i> Edit
        </a>

        @if($user->status == 'pending')
            <a class="dropdown-item text-success" href="{{ route('admin.users.approve', $user->id) }}">
                <i class="fa-solid fa-check-circle me-2"></i> Approve
            </a>
            <a class="dropdown-item text-danger" href="{{ route('admin.users.decline', $user->id) }}" 
               onclick="return confirm('Decline this request?')">
                <i class="fa-solid fa-xmark-circle me-2"></i> Decline
            </a>
        @endif

        <a class="dropdown-item text-danger" href="{{ route('admin.users.delete', $user->id) }}" 
           onclick="return confirm('Delete this user permanently?')">
            <i class="fa-solid fa-trash me-2"></i> Delete
        </a>
    </div>
</div>