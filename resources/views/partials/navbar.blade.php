<nav class="main-header navbar navbar-expand navbar-dark px-3 rounded mb-4">
    <ul class="navbar-nav">
        <li class="nav-item">
            <a class="nav-link text-white" href="#" role="button" onclick="toggleAdminSidebar(); event.preventDefault();">
                <i class="fas fa-bars fs-5"></i>
            </a>
        </li>
    </ul>

    <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white fw-semibold" href="#" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle me-1 fs-5"></i> Admin Account
            </a>
            <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary shadow">
                <li>
                    <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                       onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                        <i class="fas fa-sign-out-alt me-2"></i> Sign Out
                    </a>
                </li>
            </ul>
        </li>
    </ul>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
        @csrf
    </form>
</nav>