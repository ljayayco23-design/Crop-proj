@php
    $__actorRole = auth()->user()->role;
    $__isDeveloper = $__actorRole === 'developer';
    $__targetRole  = $user->role;

    // The Info button used to be gated by the ACTOR's own "View" flag.
    // "View" is now a master switch for the whole page (see
    // AdminUserController::userLog()), so Info visibility moved here:
    // gated by the TARGET row's own "Info" flag for admin/technician
    // accounts specifically. Developer is exempt (always sees it), and
    // farmer keeps the original unconditional behavior — Info was never
    // split out for farmer, and if the row is showing at all the master
    // switch upstream already allowed it.
    $__canView = $__isDeveloper
        || $__targetRole === 'farmer'
        || \App\Models\Permission::can($__targetRole, 'user_management', 'info');

    $__canEdit   = \App\Models\Permission::can($__actorRole, 'user_management', 'edit');
    $__canDelete = \App\Models\Permission::can($__actorRole, 'user_management', 'delete');
    $__hasAnyAction = $__canView || $__canEdit || $__canDelete;

    // This partial is shared by BOTH the admin and technician panels, so the
    // route prefix has to follow whichever panel the actor is in — otherwise
    // a technician's Approve/Decline/Delete links point at admin-only routes.
    $__routePrefix = in_array($__actorRole, ['admin', 'developer'], true) ? 'admin' : 'technician';
@endphp

@if ($__hasAnyAction)
<div class="dropdown">
    <button class="btn btn-sm btn-link text-light p-0" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
        <i class="fa-solid fa-ellipsis-vertical"></i>
    </button>

    <div class="dropdown-menu dropdown-menu-end shadow" style="min-width: 180px;">

        @if ($__canView)
            <a class="dropdown-item text-info" href="javascript:void(0)"
               onclick="viewFarmerInfo({{ $user->id }})">
                <i class="fa-solid fa-eye me-2"></i> Info
            </a>
        @endif

        @if ($__canEdit)
            <a class="dropdown-item" href="javascript:void(0)"
               onclick="editUser({{ $user->id }}, '{{ addslashes($user->full_name) }}', '{{ addslashes($user->email) }}', '{{ $user->status ?? "pending" }}')">
                <i class="fa-solid fa-pen me-2"></i> Edit
            </a>

            @if($user->status == 'pending')
                <form method="POST" action="{{ route($__routePrefix.'.users.approve', $user->id) }}" class="m-0">
                    @csrf
                    <button type="submit" class="dropdown-item text-success">
                        <i class="fa-solid fa-check-circle me-2"></i> Approve
                    </button>
                </form>
                <form method="POST" action="{{ route($__routePrefix.'.users.decline', $user->id) }}" class="m-0"
                      onsubmit="return confirm('Decline this request?')">
                    @csrf
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="fa-solid fa-xmark-circle me-2"></i> Decline
                    </button>
                </form>
            @endif
        @endif

        @if ($__canDelete)
            <form method="POST" action="{{ route($__routePrefix.'.users.delete', $user->id) }}" class="m-0"
                  onsubmit="return confirm('Delete this user permanently?')">
                @csrf
                <button type="submit" class="dropdown-item text-danger">
                    <i class="fa-solid fa-trash me-2"></i> Delete
                </button>
            </form>
        @endif
    </div>
</div>
@endif