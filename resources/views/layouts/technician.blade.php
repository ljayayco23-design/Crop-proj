<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RICEGUARD AI • Technician Dashboard')</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body { background: #0f172a; color: #e2e8f0; overflow-x: hidden; min-height: 100vh; font-family: system-ui, -apple-system, sans-serif;}
        .sidebar-container { 
            width: 250px; 
            background: rgba(30,41,59,0.98); 
            backdrop-filter: blur(30px);
            position: fixed; 
            top: 0; 
            left: -250px; 
            height: 100vh; 
            overflow-y: auto; 
            z-index: 1200; 
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            border-right: 1px solid #334155; 
        }

        /* New class to slide the sidebar into view with a shadow */
        body.sidebar-show .sidebar-container { 
            left: 0; 
            box-shadow: 30px 0 80px rgba(0,0,0,0.6); 
        }

        /* Content area no longer has margin-left, preventing stretching/resizing */
        .content-area { 
            margin-left: 0; 
            padding: 20px; 
            min-height: 100vh; 
        }
        .main-header { background: rgba(30,41,59,0.98); backdrop-filter: blur(10px); border-bottom: 1px solid #334155; position: sticky; top: 0; z-index: 1030; border-radius: 8px; margin-bottom: 20px;}
        .nav-link.active { background: #0ea5e9 !important; color: white !important; border-radius: 5px; }

        .dropdown-menu { border: 1px solid #334155 !important; border-radius: 16px !important; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5) !important; background: rgba(30,41,59,0.98) !important; backdrop-filter: blur(20px) !important; }
        .dropdown-item:hover { background: rgba(255,255,255,0.08) !important; color: white !important; }
        
        /* Updated Floating Panel for Responsiveness */
        .floating-panel { 
            position: fixed; 
            top: 0; 
            right: -550px; 
            width: 100%;             /* Allow it to be fully flexible */
            max-width: 480px;        /* Cap the size on desktop */
            height: 100vh; 
            background: rgba(30,41,59,0.98); 
            backdrop-filter: blur(30px); 
            border-left: 1px solid #334155; 
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            z-index: 1200; 
            overflow-y: auto; 
            padding: 32px; 
        }

        /* Media query for small phones */
        @media (max-width: 576px) {
            .floating-panel { padding: 20px; }
        }

        .floating-panel.show { right: 0; box-shadow: -30px 0 80px rgba(0,0,0,0.6); }
        .profile-photo { width: 140px; height: 140px; object-fit: cover; border: 5px solid rgba(14,165,233,0.5); border-radius: 50%; }

        .prodigy-label { color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }

        /* Profile Details / Account Center fields: underline only, no boxed border */
        .floating-panel input.field-underline,
        .floating-panel textarea.field-underline {
            background: transparent !important;
            border: none !important;
            border-bottom: 2px solid #495057 !important;
            border-radius: 0 !important;
            padding-left: 0.25rem;
            padding-right: 0.25rem;
            transition: border-color .15s ease-in-out;
        }
        .floating-panel input.field-underline:focus,
        .floating-panel textarea.field-underline:focus {
            border-bottom-color: #0dcaf0 !important;
            box-shadow: none !important;
            outline: none;
        }
        /* Selects keep their arrow but lose the boxed border, same underline look */
        .floating-panel select.field-underline {
            background-color: transparent !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23ffffff'%3e%3cpath d='M8 11L3 6h10l-5 5z'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.25rem center !important;
            background-size: 14px !important;
            border: none !important;
            border-bottom: 2px solid #495057 !important;
            border-radius: 0 !important;
            padding-left: 0.25rem;
            padding-right: 1.5rem;
            transition: border-color .15s ease-in-out;
        }
        .floating-panel select.field-underline:focus {
            border-bottom-color: #0dcaf0 !important;
            box-shadow: none !important;
            outline: none;
        }
        .floating-panel select.field-underline:disabled {
            border-bottom-color: #343a40 !important;
            opacity: 0.6;
        }
        .floating-panel select.field-underline option {
            background-color: #212529;
            color: #fff;
        }
    </style>
</head>
<body data-bs-theme="dark">

    @php
        $user = Auth::user();
        $userFullName = $user->full_name ?? $user->name ?? 'Technician';

        // Base64 Image Handling (matching admin logic)
        if (!empty($user->profile_photo)) {
            $profile_pic = $user->profile_photo;
        } else {
            $profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($userFullName) . '&background=0ea5e9&color=fff&size=140&bold=true';
        }

        // ============================================================
        // NOTIFICATIONS (technician)
        // Every source below is scoped to THIS technician's own id/
        // barangay assignment(s) only — same rules
        // FarmerReportController@technicianQuery and
        // AdminAssignmentController already use for this role — so a
        // technician never sees another technician's assignments,
        // reports, or messages.
        // ============================================================
        $notifCookieName = 'rg_notif_seen_' . $user->role . '_' . $user->id;
        $notifSeenAt = request()->cookie($notifCookieName)
            ? \Carbon\Carbon::parse(request()->cookie($notifCookieName))
            : now()->subDays(14); // first-ever visit: only flag the last 2 weeks as "new", not all history

        $notificationItems = [];

        try {
            // ---- 1) New assignments (barangay coverage) given to THIS technician ----
            $myAssignments = \App\Models\Assignment::where('user_id', $user->id)
                ->where('user_type', 'technician')
                ->with(['barangay', 'assignedBy'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            foreach ($myAssignments as $a) {
                $assignerName = optional($a->assignedBy)->full_name ?? optional($a->assignedBy)->name ?? 'An administrator';
                $assignerRole = optional($a->assignedBy)->role ?? 'admin';
                $notificationItems[] = [
                    'icon' => 'fa-map-location-dot', 'color' => 'primary',
                    'title' => 'New Assignment',
                    'subtitle' => 'Assigned to ' . (optional($a->barangay)->name ?? 'a barangay') . ' by ' . $assignerName . ' (' . ucfirst($assignerRole) . ')',
                    'timestamp' => $a->created_at,
                    'url' => route('technician.assignment'),
                ];
            }
        } catch (\Exception $e) {}

        try {
            // ---- 2) Admin has taken action on a report THIS technician escalated ----
            $barangayIds = \App\Models\Assignment::active()
                ->where('user_id', $user->id)
                ->where('user_type', 'technician')
                ->pluck('barangay_id');

            $adminActedReports = \App\Models\FarmerReport::with('farmer')
                ->whereHas('farmer', fn ($q) => $q->whereIn('barangay_id', $barangayIds))
                ->where('technician_id', $user->id)
                ->whereNotNull('escalated_at')
                ->where('admin_status', '!=', 'pending')
                ->orderByDesc('admin_status_updated_at')
                ->limit(5)
                ->get();

            $adminStatusLabels = \App\Http\Controllers\FarmerReportController::ADMIN_STATUSES;

            foreach ($adminActedReports as $r) {
                $statusLabel = $adminStatusLabels[$r->admin_status]['label'] ?? ucfirst($r->admin_status);
                $notificationItems[] = [
                    'icon' => 'fa-check-double', 'color' => 'info',
                    'title' => 'Admin Update on Escalated Report',
                    'subtitle' => 'Admin marked your escalated report as "' . $statusLabel . '"',
                    'timestamp' => $r->admin_status_updated_at ?? $r->updated_at,
                    'url' => route('technician.reports'),
                ];
            }
        } catch (\Exception $e) {}

        try {
            // ---- 3) New farmer reports waiting in this technician's queue ----
            $barangayIds = $barangayIds ?? \App\Models\Assignment::active()
                ->where('user_id', $user->id)
                ->where('user_type', 'technician')
                ->pluck('barangay_id');

            $newFarmerReports = \App\Models\FarmerReport::with('farmer')
                ->whereHas('farmer', fn ($q) => $q->whereIn('barangay_id', $barangayIds))
                ->whereNull('reviewed_at')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            foreach ($newFarmerReports as $r) {
                $farmerName = optional($r->farmer)->full_name ?? optional($r->farmer)->name ?? 'A farmer';
                $notificationItems[] = [
                    'icon' => 'fa-triangle-exclamation', 'color' => 'warning',
                    'title' => 'New Farmer Report',
                    'subtitle' => $farmerName . ' submitted a report that needs your review',
                    'timestamp' => $r->created_at,
                    'url' => route('technician.reports'),
                ];
            }
        } catch (\Exception $e) {}

        try {
            // ---- 4) Messages (fixed: only actually-new messages, not the all-time total) ----
            $unreadMessagesCount = DB::table('messages')
                ->where('to_user_id', $user->id)
                ->where('created_at', '>', $notifSeenAt)
                ->count();
            $latestMessage = DB::table('messages')
                ->where('to_user_id', $user->id)
                ->orderByDesc('created_at')
                ->first();

            if ($latestMessage) {
                $notificationItems[] = [
                    'icon' => 'fa-message', 'color' => 'info',
                    'title' => 'Farmer Messages',
                    'subtitle' => $unreadMessagesCount > 0 ? $unreadMessagesCount . ' new message(s)' : 'View inbox',
                    'timestamp' => $latestMessage->created_at,
                    'url' => route('technician.live_com'),
                ];
            }
        } catch (\Exception $e) {}

        try {
            // ---- 5) Admin announcements (kept from the old feature) ----
            $latestAnnouncement = DB::table('announcements')->orderByDesc('created_at')->first();
            if ($latestAnnouncement) {
                $notificationItems[] = [
                    'icon' => 'fa-bullhorn', 'color' => 'warning',
                    'title' => 'Admin Updates',
                    'subtitle' => $latestAnnouncement->title ?? 'New announcement posted',
                    'timestamp' => $latestAnnouncement->created_at,
                    'url' => route('technician.announcement'),
                ];
            }
        } catch (\Exception $e) {}

        // Sort newest first and flag which ones are "new" since this technician last opened the bell.
        usort($notificationItems, fn ($a, $b) => strtotime($b['timestamp']) <=> strtotime($a['timestamp']));
        foreach ($notificationItems as &$ni) {
            $ni['is_new'] = \Carbon\Carbon::parse($ni['timestamp'])->gt($notifSeenAt);
            $ni['time_human'] = \Carbon\Carbon::parse($ni['timestamp'])->diffForHumans();
        }
        unset($ni);

        $total_notifications = collect($notificationItems)->where('is_new', true)->count();

        // ---- Quick Search index: technician's own pages only ----
        $technicianSearchIndex = [
            ['label' => 'Dashboard', 'url' => route('technician.dashboard'), 'icon' => 'fa-gauge-high'],
            ['label' => 'Farmer Reports', 'url' => route('technician.reports'), 'icon' => 'fa-flag'],
            ['label' => 'My Assignment', 'url' => route('technician.assignment'), 'icon' => 'fa-map-location-dot'],
            ['label' => 'Farmers', 'url' => route('technician.farmers'), 'icon' => 'fa-user'],
            ['label' => 'Technicians', 'url' => route('technician.technicians'), 'icon' => 'fa-user-gear'],
            ['label' => 'Field Map', 'url' => route('technician.field_map'), 'icon' => 'fa-map'],
            ['label' => 'Records', 'url' => route('technician.records'), 'icon' => 'fa-clock-rotate-left'],
            ['label' => 'Documents', 'url' => route('technician.documents'), 'icon' => 'fa-file-lines'],
            ['label' => 'Announcements', 'url' => route('technician.announcement'), 'icon' => 'fa-bullhorn'],
            ['label' => 'Messages', 'url' => route('technician.live_com'), 'icon' => 'fa-message'],
        ];
    @endphp

    <div class="sidebar-container">
        @include('partials.technician_sidebar')
    </div>

    <div class="content-area">
        <nav class="main-header navbar navbar-expand navbar-dark px-4 shadow-sm">
            <button id="sidebarToggleBtn" onclick="document.body.classList.toggle('sidebar-show'); event.stopPropagation();" class="btn btn-link text-white p-0 me-4">
                <i class="fas fa-bars fs-5"></i>
            </button>
            <ul class="navbar-nav ms-auto d-flex align-items-center gap-3">
                <li class="nav-item dropdown" id="quickSearchDropdown">
                    <a class="nav-link text-white" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="fas fa-search fs-5"></i></a>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="width: 320px;">
                        <input type="text" id="quickSearchInput" class="form-control bg-dark border-secondary text-white" placeholder="Search records..." autocomplete="off" oninput="renderQuickSearchResults(this.value)" onkeypress="if(event.key==='Enter') goToTopSearchResult()">
                        <div id="quickSearchResults" class="mt-2" style="max-height: 260px; overflow-y: auto;"></div>
                    </div>
                </li>

                <li class="nav-item dropdown" id="notifDropdown">
                    <a class="nav-link text-white position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-bell fs-5"></i>
                        @if($total_notifications > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="notifBadge" style="font-size: 0.6rem;">{{ $total_notifications }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 360px;">
                        <div class="p-3 border-bottom border-secondary" style="background: linear-gradient(135deg, #0ea5e9 0%, #0369a1 100%); border-radius: 16px 16px 0 0;">
                            <h6 class="mb-0 text-white fw-bold"><i class="fas fa-bell me-2"></i>Notifications</h6>
                        </div>
                        <div class="p-2" style="max-height: 400px; overflow-y: auto;">
                            @forelse($notificationItems as $ni)
                                <a href="{{ $ni['url'] }}" class="dropdown-item py-3 d-flex gap-3 text-white border-bottom border-secondary border-opacity-25">
                                    <div class="text-{{ $ni['color'] }}"><i class="fas {{ $ni['icon'] }} fs-4"></i></div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 d-flex align-items-center gap-2">{{ $ni['title'] }} @if($ni['is_new'])<span class="badge bg-danger" style="font-size:0.5rem;">NEW</span>@endif</h6>
                                        <small class="text-secondary d-block">{{ $ni['subtitle'] }}</small>
                                        <small class="text-secondary" style="font-size: 0.7rem;">{{ $ni['time_human'] }}</small>
                                    </div>
                                </a>
                            @empty
                                <div class="p-4 text-center text-secondary small">No new notifications</div>
                            @endforelse
                        </div>
                    </div>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <img src="{{ $profile_pic }}" id="navbar-profile-pic" class="rounded-circle border border-2 border-info" width="40" height="40" style="object-fit: cover;">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="width: 280px;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="{{ $profile_pic }}" id="dropdown-profile-pic" class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                            <div>
                                <h6 class="mb-0 text-white fw-bold" id="dropdown-user-name">{{ $userFullName }}</h6>
                                <small class="text-info text-capitalize">Technician</small>
                            </div>
                        </div>
                        <hr class="border-secondary">
                        <a class="dropdown-item py-2 text-white" href="#" onclick="showProfilePanel()"><i class="fas fa-cog me-3 text-primary"></i>Settings</a>
                        <hr class="border-secondary">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item py-2 text-danger fw-bold"><i class="fas fa-sign-out-alt me-3"></i>Sign Out</button>
                        </form>
                    </div>
                </li>
            </ul>
        </nav>

        <div id="floatingPanel" class="floating-panel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 id="panelTitle" class="fw-bold text-white mb-0">Settings</h4>
                <button onclick="handlePanelClose()" class="btn btn-link text-white text-decoration-none"><i class="fas fa-times fs-3 text-danger"></i></button>
            </div>

            <!-- Menu list: shown first. Only the names are clickable; content
                 only appears once one of them is tapped (see openProfileSection()). -->
            <div id="profile-menu-list">
                <a href="#" class="dropdown-item py-3 d-flex align-items-center gap-3 text-white border-bottom border-secondary border-opacity-25" onclick="event.preventDefault(); openProfileSection('profile')" style="cursor:pointer">
                    <div class="text-info"><i class="fas fa-user-circle fs-4"></i></div>
                    <div><h6 class="mb-0 fw-bold">Profile Details</h6><small class="text-secondary">Name, photo, address</small></div>
                </a>
                <a href="#" class="dropdown-item py-3 d-flex align-items-center gap-3 text-white" onclick="event.preventDefault(); openProfileSection('account')" style="cursor:pointer">
                    <div class="text-primary"><i class="fas fa-shield-alt fs-4"></i></div>
                    <div><h6 class="mb-0 fw-bold">Account Center</h6><small class="text-secondary">Change your password</small></div>
                </a>
            </div>

            <div id="tab-profile" style="display:none;">
                <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="text-center mb-4 position-relative">
                        <img id="profile-pic" src="{{ $profile_pic }}" class="profile-photo shadow-lg">
                        <label for="photo-upload" class="btn btn-info btn-sm position-absolute rounded-circle shadow" style="bottom:0; right:130px; width:35px; height:35px; line-height:22px;"><i class="fas fa-camera text-dark"></i></label>
                        <input type="file" id="photo-upload" accept="image/*" class="d-none" onchange="handleProfilePhotoChange(this)">
                        <input type="hidden" name="profile_photo_base64" id="profile_photo_base64">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control field-underline text-white" value="{{ $userFullName }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control field-underline text-white" value="{{ $user->phone ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Assigned Area</label>
                        <input type="text" class="form-control field-underline text-white" value="{{ $user->address ?? 'Not yet assigned' }}" disabled readonly>
                        <small class="text-secondary d-block mt-2">Your province, city, and barangay are set when your account is created and can only be changed by an administrator.</small>
                    </div>

                    <button type="button" onclick="saveProfile('profileForm')" class="btn btn-info w-100 py-3 fw-bold mt-3 shadow text-dark">Save Changes</button>
                </form>
            </div>

            <div id="tab-settings" style="display:none;">
                <form id="passwordForm" method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control field-underline text-white" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">New Password</label>
                        <input type="password" name="new_password" class="form-control field-underline text-white" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label prodigy-label">Confirm Password</label>
                        <input type="password" name="new_password_confirmation" class="form-control field-underline text-white" required>
                    </div>
                    <button type="submit" class="btn btn-info w-100 py-3 fw-bold shadow text-dark">Change Password</button>
                </form>
            </div>
        </div>

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // --- 1. Panel now opens on a menu of clickable names (Profile Details /
    // Account Center). Content for either only renders once its name is
    // tapped — see openProfileSection(). currentProfileView tracks which
    // screen is showing so the X button knows whether to close the whole
    // panel or just step back to the menu.
    let currentProfileView = 'menu';

    function showProfilePanel() {
        document.getElementById('floatingPanel').classList.add('show');
        openProfileMenu();
    }

    function openProfileMenu() {
        currentProfileView = 'menu';
        document.getElementById('panelTitle').textContent = 'Settings';
        document.getElementById('profile-menu-list').style.display = 'block';
        document.getElementById('tab-profile').style.display = 'none';
        document.getElementById('tab-settings').style.display = 'none';
    }

    function openProfileSection(section) {
        currentProfileView = section;
        document.getElementById('profile-menu-list').style.display = 'none';
        document.getElementById('tab-profile').style.display = section === 'profile' ? 'block' : 'none';
        document.getElementById('tab-settings').style.display = section === 'account' ? 'block' : 'none';
        document.getElementById('panelTitle').textContent = section === 'profile' ? 'Profile Details' : 'Account Center';
    }

    // The X button: from the menu it closes the panel back to the main
    // dashboard; from a section it steps back to the menu instead.
    function handlePanelClose() {
        if (currentProfileView === 'menu') {
            document.getElementById('floatingPanel').classList.remove('show');
        } else {
            openProfileMenu();
        }
    }

    // --- 2. Click Outside to Close Panels (Profile & Sidebar) ---
    document.addEventListener('click', function(event) {
        // 1. Floating Profile Panel Logic
        const panel = document.getElementById('floatingPanel');
        const isClickInsidePanel = panel.contains(event.target);
        const isClickingTrigger = event.target.closest('[onclick*="showProfilePanel"]');
        
        if (panel.classList.contains('show') && !isClickInsidePanel && !isClickingTrigger) {
            panel.classList.remove('show');
            openProfileMenu();
        }

        // 2. Floating Sidebar Logic
        const sidebar = document.querySelector('.sidebar-container');
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickingSidebarTrigger = event.target.closest('#sidebarToggleBtn');
        
        // If sidebar is open, and click is not inside sidebar or on the toggle button, close it
        if (document.body.classList.contains('sidebar-show') && !isClickInsideSidebar && !isClickingSidebarTrigger) {
            document.body.classList.remove('sidebar-show');
        }
    });

    // --- Profile photo: compress client-side before it ever leaves the browser ---
    // Same approach as the registration page's document/selfie upload — resize to a
    // max width on a canvas, re-encode as JPEG at 60% quality, then send that (much
    // smaller) Base64 string instead of the raw file.
    async function compressImageFile(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);

            img.onload = () => {
                URL.revokeObjectURL(objectUrl);
                const canvas = document.createElement('canvas');

                const MAX_WIDTH = 512;
                const scale = Math.min(MAX_WIDTH / img.width, 1);

                canvas.width = img.width * scale;
                canvas.height = img.height * scale;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                resolve(canvas.toDataURL('image/jpeg', 0.6));
            };
            img.onerror = (err) => reject(err);
            img.src = objectUrl;
        });
    }

    async function handleProfilePhotoChange(input) {
        const file = input.files[0];
        if (!file) return;

        try {
            const compressedBase64 = await compressImageFile(file);
            document.getElementById('profile_photo_base64').value = compressedBase64;
            document.getElementById('profile-pic').src = compressedBase64;
        } catch (error) {
            console.error('Photo compression error:', error);
        }
    }

    // --- 3. Instant UI Update Save Logic ---
    // --- Quick Search (role-scoped to the technician's own pages only) ---
    const quickSearchIndex = @json($technicianSearchIndex);

    function renderQuickSearchResults(term) {
        const box = document.getElementById('quickSearchResults');
        term = term.trim().toLowerCase();
        if (!term) { box.innerHTML = ''; return; }

        const matches = quickSearchIndex.filter(item => item.label.toLowerCase().includes(term));
        if (matches.length === 0) {
            box.innerHTML = '<div class="text-secondary small px-2 py-2">No matching pages found.</div>';
            return;
        }
        box.innerHTML = matches.map(item => `
            <a href="${item.url}" class="dropdown-item py-2 text-white d-flex align-items-center gap-2">
                <i class="fas ${item.icon} text-info"></i> <span>${item.label}</span>
            </a>
        `).join('');
    }

    function goToTopSearchResult() {
        const term = document.getElementById('quickSearchInput').value.trim().toLowerCase();
        if (!term) return;
        const match = quickSearchIndex.find(item => item.label.toLowerCase().includes(term));
        if (match) window.location.href = match.url;
    }

    // --- Notifications: mark as seen (cookie) the moment the bell dropdown opens ---
    (function () {
        const notifDropdown = document.getElementById('notifDropdown');
        if (!notifDropdown) return;
        notifDropdown.addEventListener('show.bs.dropdown', function () {
            const cookieName = @json($notifCookieName);
            document.cookie = cookieName + '=' + encodeURIComponent(new Date().toISOString()) + ';path=/;max-age=31536000';
            const badge = document.getElementById('notifBadge');
            if (badge) badge.remove();
            document.querySelectorAll('#notifDropdown .badge.bg-danger').forEach(b => {
                if (b.textContent.trim() === 'NEW') b.remove();
            });
        });
    })();

    async function saveProfile(formId) {
        const form = document.getElementById(formId);
        const formData = new FormData(form);
        const btn = form.querySelector('button[type="button"]');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        try {
            const res = await fetch(form.action, { 
                method: 'POST', 
                body: formData,
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json' 
                }
            });

            const data = await res.json();

            if (res.ok) { 
                alert(data.message || 'Profile updated successfully!'); 
                
                // Instantly update UI with the Base64 string/name from the backend response
                if(data.user) {
                    const newPic = data.user.profile_photo_url;
                    document.getElementById('navbar-profile-pic').src = newPic;
                    document.getElementById('dropdown-profile-pic').src = newPic;
                    document.getElementById('profile-pic').src = newPic;
                    document.getElementById('dropdown-user-name').innerText = data.user.full_name;
                }
            } else {
                if (res.status === 422 && data.errors) {
                    let errorMsg = 'Could not save. Please fix these errors:\n\n';
                    for (const key in data.errors) {
                        errorMsg += `- ${data.errors[key][0]}\n`;
                    }
                    alert(errorMsg);
                } else {
                    alert(data.message || 'Failed to update. Server error occurred.');
                }
            }
        } catch(e) { 
            console.error("Save Error:", e);
            alert('A network error occurred. Please try again.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    </script>
    @yield('scripts')
</body>
</html>