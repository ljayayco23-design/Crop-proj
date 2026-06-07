<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'CROPSENSE AI • Admin')</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

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

/* Add a media query at the bottom of your <style> block for small phones */
@media (max-width: 576px) {
    .floating-panel { padding: 20px; }
}

        .floating-panel.show { right: 0; box-shadow: -30px 0 80px rgba(0,0,0,0.6); }
        .profile-photo { width: 140px; height: 140px; object-fit: cover; border: 5px solid rgba(59,130,246,0.5); border-radius: 50%; }
        
        .prodigy-card { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px; transition: all 0.3s; }
        .prodigy-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -10px rgba(59, 130, 246, 0.2); border-color: rgba(59, 130, 246, 0.5); }
        
        .prodigy-label { color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }
    </style>
</head>
<body data-bs-theme="dark">

    @php
    $user = Auth::user();
    $userFullName = $user->full_name ?? $user->name ?? 'Admin';
    
    // Check if profile_photo exists. Since it's a Base64 string, we use it directly!
    if (!empty($user->profile_photo)) {
        $admin_pic = $user->profile_photo;
    } else {
        $admin_pic = 'https://ui-avatars.com/api/?name=' . urlencode($userFullName) . '&background=3b82f6&color=fff&size=140&bold=true';
    }
        
        // Notifications
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
            <button onclick="document.body.classList.toggle('sidebar-collapsed')" class="btn btn-link text-white p-0 me-4"><i class="fas fa-bars fs-5"></i></button>

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
                        <a class="dropdown-item py-2 text-white" href="#" onclick="showProfilePanel(0)"><i class="fas fa-user me-3 text-primary"></i>Profile Details</a>
                        <a class="dropdown-item py-2 text-white" href="#" onclick="showProfilePanel(1)"><i class="fas fa-cog me-3 text-info"></i>Account Settings</a>
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
                <h4 id="panelTitle" class="fw-bold text-white mb-0">My Profile</h4>
<button onclick="document.getElementById('floatingPanel').classList.remove('show')" class="btn btn-link text-white text-decoration-none"><i class="fas fa-times fs-3 text-danger"></i></button>
            </div>

            <ul class="nav nav-pills nav-fill bg-dark border border-secondary rounded-3 p-1 mb-4" id="profileTabs">
                <li class="nav-item"><a class="nav-link active bg-primary text-white fw-bold" onclick="switchTab(0)" style="cursor:pointer">Profile</a></li>
                <li class="nav-item"><a class="nav-link text-white" onclick="switchTab(1)" style="cursor:pointer">Security</a></li>
            </ul>

            <div id="tab-profile">
                <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="text-center mb-4 position-relative">
                        <img id="profile-pic" src="{{ $admin_pic }}" class="profile-photo shadow-lg">
                        <label for="photo-upload" class="btn btn-primary btn-sm position-absolute rounded-circle shadow" style="bottom:0; right:130px; width:35px; height:35px; line-height:22px;"><i class="fas fa-camera"></i></label>
                        <input type="file" id="photo-upload" name="profile_photo" accept="image/*" class="d-none" onchange="document.getElementById('profile-pic').src = window.URL.createObjectURL(this.files[0])">
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control bg-dark border-secondary text-white" value="{{ $userFullName }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control bg-dark border-secondary text-white" value="{{ $user->phone ?? '' }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Address</label>
                        <textarea name="address" class="form-control bg-dark border-secondary text-white" rows="3">{{ $user->address ?? '' }}</textarea>
                    </div>
                    <button type="button" onclick="saveProfile('profileForm')" class="btn btn-primary w-100 py-3 fw-bold mt-3 shadow">Save Changes</button>
                </form>
            </div>

            <div id="tab-settings" style="display:none;">
                <form id="passwordForm" method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control bg-dark border-secondary text-white" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">New Password</label>
                        <input type="password" name="new_password" class="form-control bg-dark border-secondary text-white" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label prodigy-label">Confirm Password</label>
                        <input type="password" name="new_password_confirmation" class="form-control bg-dark border-secondary text-white" required>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 py-3 fw-bold shadow">Change Password</button>
                </form>
            </div>
        </div>

        @yield('content')
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // --- 1. Tab Switching Logic ---
    function showProfilePanel(tab) { 
        document.getElementById('floatingPanel').classList.add('show'); 
        switchTab(tab); 
    }

    function switchTab(tab) {
        const tabs = document.querySelectorAll('#profileTabs .nav-link');
        tabs[0].classList.toggle('active', tab === 0); 
        tabs[0].classList.toggle('text-white', tab !== 0);
        tabs[1].classList.toggle('active', tab === 1); 
        tabs[1].classList.toggle('text-white', tab !== 1);
        
        document.getElementById('tab-profile').style.display = tab === 0 ? 'block' : 'none';
        document.getElementById('tab-settings').style.display = tab === 1 ? 'block' : 'none';
    }

    // --- 2. Click Outside to Close Panel ---
    document.addEventListener('click', function(event) {
        const panel = document.getElementById('floatingPanel');
        const isClickInsidePanel = panel.contains(event.target);
        const isClickingTrigger = event.target.closest('[onclick*="showProfilePanel"]');
        
        // If the panel is open, the click is outside, and we didn't just click the open button
        if (panel.classList.contains('show') && !isClickInsidePanel && !isClickingTrigger) {
            panel.classList.remove('show');
        }
    });

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
</body>
</html>