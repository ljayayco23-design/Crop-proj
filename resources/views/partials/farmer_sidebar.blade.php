<aside class="farmer-sidebar" id="farmerSidebar">
    <div class="p-4 border-bottom border-secondary d-flex align-items-center gap-2">
        <span class="d-flex align-items-center justify-content-center rounded-circle bg-white shadow-sm flex-shrink-0" style="height:36px;width:36px;overflow:hidden;border:2px solid rgba(16,185,129,0.5);">
            <img src="{{ asset('img/logo.jpg') }}" alt="RiceGuard AI Logo" style="height:100%;width:100%;object-fit:cover;">
        </span>
        <span class="text-success fw-bold mb-0"><strong>🌾RICEGUARD</strong> AI</span>
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
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.schedule') }}" class="nav-link {{ request()->routeIs('farmer.schedule*') ? 'active' : '' }}">
                    <i class="fas fa-calendar-days me-2"></i> Schedule
                </a>
            </li>
            {{-- Feedback & Support: the farmer's wrong-detection reports (with the
                 technician's resolution), improvement suggestions and general
                 feedback — all on one page. Route name is unchanged. --}}
            <li class="nav-item mb-2">
                <a href="{{ route('farmer.reports') }}" class="nav-link {{ request()->routeIs('farmer.reports') ? 'active' : '' }}">
                    <i class="fas fa-headset me-2"></i> Feedback &amp; Support
                </a>
            </li>
            <!-- Chatbot Sidebar Navigation Trigger Button -->
            <li class="nav-item mb-2">
                <a href="#" id="sidebar-chat-btn" class="nav-link">
                    <i class="fas fa-comment-dots me-2"></i> AI Assistant
                </a>
            </li>
        </ul>
    </nav>
</aside>