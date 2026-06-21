<aside class="farmer-sidebar" id="farmerSidebar">
    <div class="p-4 border-bottom border-secondary">
        <h4 class="text-success fw-bold mb-0">🌾 Farmer Portal</h4>
    </div>
    <nav class="p-3">
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.dashboard') }}" class="nav-link {{ request()->routeIs('farmer.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.detection') }}" class="nav-link {{ request()->routeIs('farmer.detection') ? 'active' : '' }}">
                    <i class="fas fa-upload me-2"></i> Upload Detection
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.camera') }}" class="nav-link {{ request()->routeIs('farmer.camera') ? 'active' : '' }}">
                    <i class="fas fa-camera me-2"></i> Live Camera
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.history') }}" class="nav-link {{ request()->routeIs('farmer.history') ? 'active' : '' }}">
                    <i class="fas fa-history me-2"></i> History
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.announcement') }}" class="nav-link {{ request()->routeIs('farmer.announcement') ? 'active' : '' }}">
                    <i class="fas fa-bullhorn me-2"></i> Announcements
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.live_com') }}" class="nav-link {{ request()->routeIs('farmer.live_com') ? 'active' : '' }}">
                    <i class="fas fa-message me-2"></i> Messenger
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.field_map') }}" class="nav-link {{ request()->routeIs('farmer.field_map') ? 'active' : '' }}">
                    <i class="fas fa-map-location-dot me-2"></i> Field Map & Weather
                </a>
            </li>
        </ul>
    </nav>
</aside>