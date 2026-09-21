<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RICEGUARD AI • Admin')</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body { background: #0f172a; color: #e2e8f0; overflow-x: hidden; min-height: 100vh; font-family: system-ui, -apple-system, sans-serif; }
        
        .sidebar-container { 
            width: 250px; background: #1e2937; position: fixed; top: 0; left: 0;
            height: 100vh; overflow-y: auto; z-index: 1040; transition: all 0.3s ease-in-out;
            border-right: 1px solid #334155;
        }
        
        .content-area { margin-left: 250px; padding: 20px; min-height: 100vh; transition: all 0.3s ease-in-out; }

        body.sidebar-collapsed .sidebar-container { left: -250px; }
        body.sidebar-collapsed .content-area { margin-left: 0; }

        .main-header { background: rgba(30, 41, 59, 0.98); backdrop-filter: blur(10px); border-bottom: 1px solid #334155; position: sticky; top: 0; z-index: 1030; border-radius: 8px; margin-bottom: 20px;}
        .dropdown-menu { border: 1px solid #334155 !important; border-radius: 16px !important; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5) !important; background: rgba(30,41,59,0.98) !important; backdrop-filter: blur(20px) !important; }
        .dropdown-item:hover { background: rgba(255,255,255,0.08) !important; color: white !important; }
        
       .floating-panel {
            position: fixed; 
            top: 0; 
            right: -550px; 
            width: 100%;             
            max-width: 480px;        
            height: 100vh;
            background: rgba(30,41,59,0.98); 
            backdrop-filter: blur(30px); 
            border-left: 1px solid #334155;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            z-index: 1200; 
            overflow-y: auto; 
            padding: 32px;
        }

        @media (max-width: 576px) {
            .floating-panel { padding: 20px; }
        }

        .floating-panel.show { right: 0; box-shadow: -30px 0 80px rgba(0,0,0,0.6); }
        .profile-photo { width: 140px; height: 140px; object-fit: cover; border: 5px solid rgba(59,130,246,0.5); border-radius: 50%; }
        
        .prodigy-card { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; transition: all 0.3s; }
        .prodigy-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -10px rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.5); }
        
        .prodigy-label { color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }

        /* --- Mobile Floating Sidebar --- */
        @media (max-width: 768px) {
            .sidebar-container {
                left: -250px; 
                box-shadow: 4px 0 15px rgba(0,0,0,0.5);
            }
            .content-area {
                margin-left: 0 !important; 
                width: 100%;
            }
            body.sidebar-open .sidebar-container {
                left: 0;
            }
        }

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
            border-bottom-color: #0d6efd !important;
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
            border-bottom-color: #0d6efd !important;
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
    $userFullName = $user->full_name ?? $user->name ?? 'Admin';
    
    if (!empty($user->profile_photo)) {
        $admin_pic = $user->profile_photo;
    } else {
        $admin_pic = 'https://ui-avatars.com/api/?name=' . urlencode($userFullName) . '&background=3b82f6&color=fff&size=140&bold=true';
    }
        
        $pending_approvals = 0;
        try {
            $pending_approvals = \App\Models\User::where('role', 'farmer')->where('status', 'pending')->count();
        } catch (\Exception $e) {
            $pending_approvals = 0;
        }
        $total_notifications = $pending_approvals;
    @endphp

    <div class="sidebar-container">
        @include('partials.sidebar')
    </div>

    <div class="content-area">
        <nav class="main-header navbar navbar-expand navbar-dark px-4 shadow-sm">
            <!-- Updated Toggle Button -->
            <button onclick="toggleSidebar()" class="btn btn-link text-white p-0 me-4">
                <i class="fas fa-bars fs-5"></i>
            </button>
            <ul class="navbar-nav ms-auto d-flex align-items-center gap-3">
                <li class="nav-item dropdown">
                    <a class="nav-link text-white" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="fas fa-search fs-5"></i></a>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="width: 320px;">
                        <input type="text" class="form-control bg-dark border-secondary text-white" placeholder="Search system..." onkeypress="if(event.key==='Enter') window.location.href='?search='+this.value">
                    </div>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link text-white position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-bell fs-5"></i>
                        @if($total_notifications > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">{{ $total_notifications }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 380px;">
                        <div class="p-3 border-bottom border-secondary" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); border-radius: 16px 16px 0 0;">
                            <h6 class="mb-0 text-white fw-bold"><i class="fas fa-bell me-2"></i>Notifications</h6>
                        </div>
                        <div class="p-2">
                            @if($pending_approvals > 0)
                                <a href="{{ route('admin.farmers') }}" class="dropdown-item py-3 d-flex gap-3 text-white border-bottom border-secondary border-opacity-25">
                                    <div class="text-warning"><i class="fas fa-user-clock fs-4"></i></div>
                                    <div><h6 class="mb-1">Pending Approvals</h6><small class="text-secondary">{{ $pending_approvals }} farmer(s) waiting</small></div>
                                </a>
                            @endif

                            @if($total_notifications === 0)
                                <div class="p-4 text-center text-secondary small">No new notifications</div>
                            @endif
                        </div>
                    </div>
                </li>

                <li class="nav-item dropdown">
                <a href="#" class="nav-link d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <img src="{{ $admin_pic }}" id="navbar-profile-pic" class="rounded-circle border border-2 border-primary" width="40" height="40" style="object-fit: cover;">
                    </a>

                    <div class="dropdown-menu dropdown-menu-end p-3" style="width: 280px;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="{{ $admin_pic }}" id="dropdown-profile-pic" class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                            <div>
                                <h6 class="mb-0 text-white fw-bold" id="dropdown-user-name">{{ $userFullName }}</h6>
                                <small class="text-primary text-capitalize">{{ $user->role }}</small>
                            </div>
                        </div>
                        <hr class="border-secondary">
                        <a class="dropdown-item py-2 text-white" href="#" onclick="showProfilePanel()"><i class="fas fa-cog me-3 text-info"></i>Settings</a>
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
                    <div class="text-primary"><i class="fas fa-user-circle fs-4"></i></div>
                    <div><h6 class="mb-0 fw-bold">Profile Details</h6><small class="text-secondary">Name, photo, address</small></div>
                </a>
                <a href="#" class="dropdown-item py-3 d-flex align-items-center gap-3 text-white" onclick="event.preventDefault(); openProfileSection('account')" style="cursor:pointer">
                    <div class="text-info"><i class="fas fa-shield-alt fs-4"></i></div>
                    <div><h6 class="mb-0 fw-bold">Account Center</h6><small class="text-secondary">Change your password</small></div>
                </a>
            </div>

            <div id="tab-profile" style="display:none;">
                <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="text-center mb-4 position-relative">
                        <img id="profile-pic" src="{{ $admin_pic }}" class="profile-photo shadow-lg">
                        <label for="photo-upload" class="btn btn-primary btn-sm position-absolute rounded-circle shadow" style="bottom:0; right:130px; width:35px; height:35px; line-height:22px;"><i class="fas fa-camera"></i></label>
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
                    <button type="button" onclick="saveProfile('profileForm')" class="btn btn-primary w-100 py-3 fw-bold mt-3 shadow">Save Changes</button>
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
                    <button type="submit" class="btn btn-warning w-100 py-3 fw-bold shadow">Change Password</button>
                </form>
            </div>
        </div>

        @yield('content')
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // --- Smarter Toggle Function (handles Desktop & Mobile) ---
    function toggleSidebar() {
        if (window.innerWidth <= 768) {
            document.body.classList.toggle('sidebar-open');
        } else {
            document.body.classList.toggle('sidebar-collapsed');
        }
    }

    // --- Click Outside to Close (Mobile Only) ---
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768 && document.body.classList.contains('sidebar-open')) {
            const sidebar = document.querySelector('.sidebar-container');
            const menuBtn = document.querySelector('.fa-bars').closest('button, a'); 
            
            if (!sidebar.contains(event.target) && (!menuBtn || !menuBtn.contains(event.target))) {
                document.body.classList.remove('sidebar-open');
            }
        }
    });

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

    // --- 2. Click Outside to Close Panel ---
    document.addEventListener('click', function(event) {
        const panel = document.getElementById('floatingPanel');
        const isClickInsidePanel = panel.contains(event.target);
        const isClickingTrigger = event.target.closest('[onclick*="showProfilePanel"]');
        
        // If the panel is open, the click is outside, and we didn't just click the open button
        if (panel.classList.contains('show') && !isClickInsidePanel && !isClickingTrigger) {
            panel.classList.remove('show');
            openProfileMenu();
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

    // --- 3. Smarter Save Function ---
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
                
                // Instantly update UI with the Base64 string from TiDB
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

    <script>
    window.addEventListener('pageshow', function (event) {
        // If the page was loaded from the browser cache (like hitting the back arrow)
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            // Force a hard reload from the server
            window.location.reload();
        }
    });
    </script>
</body>
</html>