<aside class="main-sidebar sidebar-dark-primary elevation-4" id="adminSidebar" style="width: 250px; transition: width 0.3s; background: #1e2937; position: fixed; height: 100vh; overflow-y: auto; z-index: 1040;">
    
    <a href="{{ route('admin.dashboard') }}" class="brand-link text-decoration-none text-white d-block p-3 border-bottom border-secondary">
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

            <li class="nav-item mb-1">
                <a class="nav-link text-white d-flex justify-content-between align-items-center" data-bs-toggle="collapse" href="#userLogCollapse" role="button" aria-expanded="false" aria-controls="userLogCollapse">
                    <span><i class="fas fa-users-cog me-2 width-20"></i> User Log</span>
                    <i class="fas fa-chevron-down fs-7"></i>
                </a>
                <div class="collapse {{ request()->is('admin/farmer*') || request()->is('admin/technician*') ? 'show' : '' }}" id="userLogCollapse">
                    <ul class="nav flex-column ms-3 mt-1 border-start border-secondary ps-2">
                        <li class="nav-item">
                            <a href="{{ route('admin.farmers') }}" class="nav-link text-light {{ request()->routeIs('admin.farmers') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-user-friends me-2 fs-7"></i> Farmers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.technicians') }}" class="nav-link text-light {{ request()->routeIs('admin.technicians') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-tools me-2 fs-7"></i> Technician Log
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('admin.technician.create') }}" class="nav-link text-light {{ request()->routeIs('admin.technician.create') ? 'text-primary fw-bold' : '' }}">
                                <i class="fas fa-user-plus me-2 fs-7"></i> Create Technician
                            </a>
                        </li>
                    </ul>
                </div>
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
        </ul>
    </div>
</aside>

<script>
function toggleAdminSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const contentArea = document.querySelector('.content-area');
    
    if (sidebar.style.width === '0px' || sidebar.style.width === '') {
        sidebar.style.width = '250px';
        if (contentArea) contentArea.style.marginLeft = '250px';
    } else {
        sidebar.style.width = '0px';
        if (contentArea) contentArea.style.marginLeft = '0px';
    }
}
</script>