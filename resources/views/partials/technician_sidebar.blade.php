<div class="p-3">
    <ul class="nav flex-column">
        <li class="nav-item mb-2">
            <a href="{{ route('technician.dashboard') }}" class="nav-link text-white {{ request()->routeIs('technician.dashboard') ? 'active' : '' }}">
                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
            </a>
        </li>
        <li class="nav-item mb-2">
            <a href="{{ route('technician.records') }}" class="nav-link text-white {{ request()->routeIs('technician.records') ? 'active' : '' }}">
                <i class="fas fa-folder-open me-2"></i> Technician Record
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