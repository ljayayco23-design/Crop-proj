<div class="p-3 border-bottom border-secondary d-flex align-items-center gap-2">
    <span class="d-flex align-items-center justify-content-center rounded-circle bg-white shadow-sm flex-shrink-0" style="height:36px;width:36px;overflow:hidden;border:2px solid rgba(16,185,129,0.5);">
        <img src="{{ asset('img/logo.jpg') }}" alt="RiceGuard AI Logo" style="height:100%;width:100%;object-fit:cover;">
    </span>
    <span class="text-success fw-bold mb-0"><strong>🌾RICEGUARD</strong> AI</span>
</div>

<div class="p-3">
    <ul class="nav flex-column">
        <li class="nav-item mb-2">
            <a href="{{ route('technician.dashboard') }}" class="nav-link text-white {{ request()->routeIs('technician.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
        
            @unless(\App\Models\Permission::isHidden('technician', 'user_management'))
            <li class="nav-item mb-1">
                <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#userLogCollapse" role="button" aria-expanded="false" aria-controls="userLogCollapse">
                    <span><i class="fas fa-users-cog me-2 width-20"></i> User</span>
                    <i class="fas fa-chevron-down fs-7"></i>
                </a>
                <div class="collapse {{ request()->is('technician/farmer*') || request()->is('technician/technician*') || request()->is('technician/users*') ? 'show' : '' }}" id="userLogCollapse">
                    <ul class="nav flex-column ms-3 mt-1 border-start border-secondary ps-2">
                        <li class="nav-item">
                            <a href="{{ route('technician.users') }}" class="nav-link text-light {{ request()->routeIs('technician.farmers', 'technician.technician', 'technician.users') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-user-friends me-2 fs-7"></i> User Log
                            </a>
                        </li>
                        @if(\App\Models\Permission::can(auth()->user()->role, 'user_management', 'create'))
                        <li class="nav-item">
                            <a href="{{ route('technician.account.create') }}" class="nav-link text-light {{ request()->routeIs('technician.account.create') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-user-plus me-2 fs-7"></i> Create Account
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
                </i>
            @endunless

        {{-- MY ASSIGNMENT — always visible to any technician (no
             user_management permission gate, since it isn't part of that
             module and every technician is allowed to check their own
             assignment status). Route already exists in web.php:
             GET /technician/assignments -> AdminAssignmentController@index
             -> renders resources/views/technician/assignment.blade.php.
             The page itself stays empty/read-only until an admin or
             developer has actually assigned this technician somewhere —
             nothing extra to gate here in the sidebar. --}}
        <li class="nav-item mb-2">
            <a href="{{ route('technician.assignment') }}" class="nav-link text-white {{ request()->routeIs('technician.assignment') ? 'active' : '' }}">
                <i class="fas fa-map-location-dot me-2"></i> My Assignment
            </a>
        </li>

        <li class="nav-item mb-2">
            <a href="{{ route('technician.records') }}" class="nav-link text-white {{ request()->routeIs('technician.records') ? 'active' : '' }}">
                <i class="fas fa-folder-open me-2"></i> Technician Record
            </a>
        </li>
        
        {{-- FARMER REPORTS — the review queue for problems farmers raised
             on their detection results. Visible to any technician: it is
             their own review work, not part of the user_management module,
             so it carries no Permission gate. Route:
             GET /technician/reports -> FarmerReportController@index --}}
        <li class="nav-item mb-2">
            <a href="{{ route('technician.reports') }}" class="nav-link text-white {{ request()->routeIs('technician.reports') ? 'active' : '' }}">
                <i class="fas fa-flag me-2"></i> Farmer Reports
            </a>
        </li>

        <!-- Announcements -->
        <li class="nav-item mb-2">
            <a href="{{ route('technician.announcement') }}" class="nav-link text-white {{ request()->routeIs('technician.announcement') ? 'active' : '' }}">
                <i class="fas fa-bullhorn me-2"></i> Announcements
            </a>
        </li>

        <li class="nav-item mb-2">
            <a href="{{ route('technician.live_com') }}" class="nav-link text-white {{ request()->routeIs('technician.live_com') ? 'active' : '' }}">
                <i class="fas fa-message me-2"></i> Messenger
            </a>
        </li>

        <li class="nav-item mb-2">
                <a href="{{ route('technician.field_map') }}" class="nav-link text-white{{ request()->routeIs('technician.field_map') ? 'active' : '' }}">
                    <i class="fas fa-map-location-dot me-2"></i> Map & Weather
                </a>
            </li>

            <a href="{{ route('technician.documents') }}" class="nav-link text-white mb-2">
    <i class="fas fa-file-contract me-2"></i> Documents
</a>
    </ul>
</div>