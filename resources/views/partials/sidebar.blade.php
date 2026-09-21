<aside class="main-sidebar sidebar-dark-primary elevation-4" id="adminSidebar" style="width: 250px; transition: width 0.3s; background: #1e2937; position: fixed; height: 100vh; overflow-y: auto; z-index: 1040;">

    <a href="{{ route('admin.dashboard') }}" class="brand-link text-decoration-none text-white d-flex align-items-center gap-2 p-3 border-bottom border-secondary">
        <span class="d-flex align-items-center justify-content-center rounded-circle bg-white shadow-sm flex-shrink-0" style="height:36px;width:36px;overflow:hidden;border:2px solid rgba(16,185,129,0.5);">
            <img src="{{ asset('img/logo.jpg') }}" alt="RiceGuard AI Logo" style="height:100%;width:100%;object-fit:cover;">
        </span>
        <span class="brand-text font-weight-light fs-5"><strong>RICEGUARD</strong> AI</span>
    </a>

    <div class="sidebar mt-3 px-2">
        <ul class="nav nav-pills flex-column mb-auto">
            
            <li class="nav-item mb-1">
                <a href="{{ route('admin.dashboard') }}" class="nav-link text-white {{ request()->routeIs('admin.dashboard') ? 'active bg-primary' : '' }}">
                    <i class="fas fa-tachometer-alt me-2 width-20"></i> Dashboard
                </a>
            </li>

            <li class="nav-item mb-1">
                <a href="{{ route('admin.announcement') }}" class="nav-link text-white {{ request()->routeIs('admin.announcement') ? 'active bg-primary' : '' }}">
                    <i class="fas fa-bullhorn me-2 width-20"></i> Announcements
                </a>
            </li>

            <li class="nav-item mb-1">
                <a href="{{ route('admin.documents') }}" class="nav-link text-white {{ request()->routeIs('admin.documents') ? 'active bg-primary' : '' }}">
                    <i class="fas fa-file-contract me-2 width-20"></i> Verification Docs
                </a>
            </li>

            @unless(\App\Models\Permission::isHidden('admin', 'user_management'))
            <li class="nav-item mb-1">
                <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#userLogCollapse" role="button" aria-expanded="false" aria-controls="userLogCollapse">
                    <span><i class="fas fa-users-cog me-2 width-20"></i> User</span>
                    <i class="fas fa-chevron-down fs-7"></i>
                </a>
                <div class="collapse {{ request()->is('admin/farmer*') || request()->is('admin/technician*') || request()->is('admin/admins*') || request()->is('admin/users*') ? 'show' : '' }}" id="userLogCollapse">
                    <ul class="nav flex-column ms-3 mt-1 border-start border-secondary ps-2">
                        <li class="nav-item">
                            <a href="{{ route('admin.users') }}" class="nav-link text-light {{ request()->routeIs('admin.farmers', 'admin.technicians', 'admin.admins', 'admin.users') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-user-friends me-2 fs-7"></i> User Log
                            </a>
                        </li>
                        @if(\App\Models\Permission::can(auth()->user()->role, 'user_management', 'create'))
                        <li class="nav-item">
                            <a href="{{ route('admin.account.create') }}" class="nav-link text-light {{ request()->routeIs('admin.account.create') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-user-plus me-2 fs-7"></i> Create Account
                            </a>
                        </li>
                        @endif
                    </ul>
                </div>
            </li>
            @endunless

            <li class="nav-item mb-1">
                <a href="{{ route('admin.permissions') }}" class="nav-link text-white {{ request()->routeIs('admin.permissions') ? 'active bg-primary' : '' }}">
                    <i class="fas fa-user-shield me-2 width-20"></i> Permissions
                </a>
            </li>


             <li class="nav-item mb-1">
                <a href="{{ route('admin.assignment') }}" class="nav-link text-white {{ request()->routeIs('admin.assignment') ? 'active bg-primary' : '' }}">
                    <i class="fas fa-user-shield me-2 width-20"></i> Assignment
                </a>
            </li>

            <li class="nav-item mb-1">
                <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#knowledgeCollapse" role="button" aria-expanded="false" aria-controls="knowledgeCollapse">
                    <span><i class="fas fa-book me-2 width-20"></i> Knowledge Base</span>
                    <i class="fas fa-chevron-down fs-7"></i>
                </a>
                <div class="collapse {{ request()->is('admin/knowledge*') ? 'show' : '' }}" id="knowledgeCollapse">
                    <ul class="nav flex-column ms-3 mt-1 border-start border-secondary ps-2">
                        <li class="nav-item">
                            <a href="{{ route('admin.knowledge.editor') }}" class="nav-link text-light {{ request()->routeIs('admin.knowledge.editor') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-edit me-2 fs-7"></i> Knowledge Editor
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.knowledge.management') }}" class="nav-link text-light {{ request()->routeIs('admin.knowledge.management') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-list me-2 fs-7"></i> Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.knowledge.modifier') }}" class="nav-link text-light {{ request()->routeIs('admin.knowledge.modifier') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-history me-2 fs-7"></i> Modifier History
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="nav-item mb-1">
                <a href="{{ route('admin.history') }}" class="nav-link text-white {{ request()->routeIs('admin.history') ? 'active bg-primary' : '' }}">
                    <i class="fas fa-clock me-2 width-20"></i> Diagnoses History
                </a>
            </li>

            <li class="nav-item mb-1">
                <a href="{{ route('admin.system_report') }}" class="nav-link text-white {{ request()->routeIs('admin.system_report*') ? 'active bg-primary' : '' }}">
                    <i class="fas fa-triangle-exclamation me-2 width-20"></i> System Report
                </a>
            </li>
        </ul>
    </div>
</aside>