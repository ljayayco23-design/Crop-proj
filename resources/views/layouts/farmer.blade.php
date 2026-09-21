<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RICEGUARD AI • Farmer Dashboard')</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <style>
        body { background: #0f172a; color: #e2e8f0; overflow-x: hidden; min-height: 100vh; font-family: system-ui, -apple-system, sans-serif; }
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

        body.sidebar-show .sidebar-container { 
            left: 0; 
            box-shadow: 30px 0 80px rgba(0,0,0,0.6); 
        }

        .content-area { 
            margin-left: 0; 
            padding: 20px; 
            min-height: 100vh; 
        }

        .main-header { background: rgba(30,41,59,0.98); backdrop-filter: blur(10px); border-bottom: 1px solid #334155; position: sticky; top: 0; z-index: 1030; border-radius: 8px; margin-bottom: 20px;}
        .nav-link.active { background: #10b981 !important; color: white !important; border-radius: 5px; }
        
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
        .profile-photo { width: 140px; height: 140px; object-fit: cover; border: 5px solid rgba(16,185,129,0.5); border-radius: 50%; }
        .prodigy-label { color: #94a3b8; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem; }

        /* Responsive Global Chat Window Styles */
        .chat-window {
            width: 380px; 
            height: 520px; 
            border: 1px solid #334155; 
            z-index: 1250;
            bottom: 30px; 
            right: 30px;
        }

        @media (max-width: 576px) {
            .chat-window {
                width: 100% !important;
                height: 100dvh !important;
                margin: 0 !important;
                bottom: 0 !important;
                right: 0 !important;
                border-radius: 0 !important;
                z-index: 9999;
            }
            .chat-header { border-radius: 0 !important; }
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
            border-bottom-color: #28a745 !important;
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
            border-bottom-color: #28a745 !important;
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
        $userFullName = $user->full_name ?? $user->name ?? 'Farmer';
        
        if (!empty($user->profile_photo)) {
            $profile_pic = $user->profile_photo;
        } else {
            $profile_pic = 'https://ui-avatars.com/api/?name=' . urlencode($userFullName) . '&background=10b981&color=fff&size=140&bold=true';
        }
        
        $total_notifications = 0;
        try {
            $total_notifications = DB::table('messages')->where('to_user_id', $user->id)->count();
        } catch (\Exception $e) {
            $total_notifications = 0;
        }
    @endphp

    <div class="sidebar-container" id="farmerSidebar">
        @include('partials.farmer_sidebar')
    </div>

    <div class="content-area" id="contentArea">
        <nav class="main-header navbar navbar-expand navbar-dark px-4 shadow-sm">
            <button id="sidebarToggleBtn" onclick="document.body.classList.toggle('sidebar-show'); event.stopPropagation();" class="btn btn-link text-white p-0 me-4"><i class="fas fa-bars fs-5"></i></button>

            <ul class="navbar-nav ms-auto d-flex align-items-center gap-3">
                <li class="nav-item dropdown">
                    <a class="nav-link text-white" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside"><i class="fas fa-search fs-5"></i></a>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="width: 320px;">
                        <input type="text" class="form-control bg-dark border-secondary text-white" placeholder="Search detections..." onkeypress="if(event.key==='Enter') window.location.href='?search='+this.value">
                    </div>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link text-white position-relative" href="#" data-bs-toggle="dropdown">
                        <i class="fas fa-bell fs-5"></i>
                        @if($total_notifications > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;">{{ $total_notifications }}</span>
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width: 340px;">
                        <div class="p-3 border-bottom border-secondary" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); border-radius: 16px 16px 0 0;">
                            <h6 class="mb-0 text-white fw-bold"><i class="fas fa-bell me-2"></i>Notifications</h6>
                        </div>
                        <div class="p-2">
                            <a href="{{ route('farmer.live_com') }}" class="dropdown-item py-3 d-flex gap-3 text-white border-bottom border-secondary border-opacity-25">
                                <div class="text-info"><i class="fas fa-message fs-4"></i></div>
                                <div><h6 class="mb-1">Messages</h6><small class="text-secondary">Contact technician</small></div>
                            </a>
                            <a href="{{ route('farmer.announcement') }}" class="dropdown-item py-3 d-flex gap-3 text-white border-bottom border-secondary border-opacity-25">
                                <div class="text-success"><i class="fas fa-bullhorn fs-4"></i></div>
                                <div><h6 class="mb-1">Announcements</h6><small class="text-secondary">View updates</small></div>
                            </a>

                            @if($total_notifications === 0)
                                <div class="p-4 text-center text-secondary small">No new notifications</div>
                            @endif
                        </div>
                    </div>
                </li>

                <li class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <img src="{{ $profile_pic }}" id="navbar-profile-pic" class="rounded-circle border border-2 border-success" width="40" height="40" style="object-fit: cover;">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end p-3" style="width: 280px;">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <img src="{{ $profile_pic }}" id="dropdown-profile-pic" class="rounded-circle" width="50" height="50" style="object-fit: cover;">
                            <div>
                                <h6 class="mb-0 text-white fw-bold" id="dropdown-user-name">{{ $userFullName }}</h6>
                                <small class="text-success text-capitalize">Farmer</small>
                            </div>
                        </div>
                        <hr class="border-secondary">
                        <a class="dropdown-item py-2 text-white" href="#" onclick="showProfilePanel()"><i class="fas fa-cog me-3 text-info"></i>Settings</a>
                        <hr class="border-secondary">
                        <form id="logout-form" method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item py-2 text-danger fw-bold"><i class="fas fa-sign-out-alt me-3"></i>Sign Out</button>
                        </form>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Floating Profile Panel -->
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
                    <div><h6 class="mb-0 fw-bold">Profile Details</h6><small class="text-secondary">Name, photo, farm address</small></div>
                </a>
                <a href="#" class="dropdown-item py-3 d-flex align-items-center gap-3 text-white" onclick="event.preventDefault(); openProfileSection('account')" style="cursor:pointer">
                    <div class="text-success"><i class="fas fa-shield-alt fs-4"></i></div>
                    <div><h6 class="mb-0 fw-bold">Account Center</h6><small class="text-secondary">Change your password</small></div>
                </a>
            </div>

            <div id="tab-profile" style="display:none;">
                <form id="profileForm" method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="text-center mb-4 position-relative">
                        <img id="profile-pic" src="{{ $profile_pic }}" class="profile-photo shadow-lg">
                        <label for="photo-upload" class="btn btn-success btn-sm position-absolute rounded-circle shadow" style="bottom:0; right:130px; width:35px; height:35px; line-height:22px;"><i class="fas fa-camera"></i></label>
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
                        <label class="form-label prodigy-label">Province</label>
                        <select name="province_id" id="pm-province-select" class="form-control field-underline text-white">
                            <option value="" disabled selected>Select province...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">City / Municipality</label>
                        <select name="city_id" id="pm-city-select" class="form-control field-underline text-white" disabled>
                            <option value="" disabled selected>Select province first...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Barangay</label>
                        <select name="barangay_id" id="pm-barangay-select" class="form-control field-underline text-white" disabled>
                            <option value="" disabled selected>Select city first...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label prodigy-label">Farm Size (Hectares)</label>
                        <input type="number" step="0.1" name="farm_size" class="form-control field-underline text-white" value="{{ $user->farm_size ?? '' }}">
                    </div>
                  
                    <div class="mb-4">
                        <label class="form-label prodigy-label">About My Farm (Bio)</label>
                        <textarea name="bio" class="form-control field-underline text-white" rows="3">{{ $user->bio ?? '' }}</textarea>
                    </div>

                    <button type="button" onclick="saveProfile('profileForm')" class="btn btn-success w-100 py-3 fw-bold mt-3 shadow">Save Changes</button>
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
                    <button type="button" onclick="savePassword('passwordForm')" class="btn btn-success w-100 py-3 fw-bold shadow">Change Password</button>
                </form>
            </div>
        </div>

        @yield('content')
    </div>

    <!-- Global Floating Chat Window -->
    <div id="chat-window" class="position-fixed bg-dark rounded-4 shadow-lg chat-window" style="display:none;flex-direction:column;">
        <div class="chat-header d-flex justify-content-between align-items-center p-3 bg-success text-white rounded-top-4">
            <h5 class="mb-0 fw-bold">RICEGUARD AI Assistant 🌾</h5>
            <button id="chat-close" class="btn-close btn-close-white"></button>
        </div>
        <div id="chat-messages" class="flex-grow-1 p-3 overflow-auto" style="background:#1e2937;"></div>
        <div class="p-3 border-top border-secondary">
            <div class="input-group">
                <input id="chat-input" type="text" autocomplete="off" class="form-control bg-dark text-white border-secondary" placeholder="Ask about rice farming...">
                <button id="chat-send" class="btn btn-success"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Panel now opens on a menu of clickable names (Profile Details /
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

        if (section === 'profile' && !window.pmLocationLoaded) {
            window.pmLocationLoaded = true;
            loadPmProvinces();
        }
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

    // ==========================================
    // PROFILE MODAL — Province/City/Barangay cascading dropdowns
    // (same pattern as the Farm Address card / registration form)
    // ==========================================
    const PM_BASE_URL = "{{ url('/') }}";
    const pmCurrentAddress = {
        province_id: @json($user->province_id ?? null),
        city_id: @json($user->city_id ?? null),
        barangay_id: @json($user->barangay_id ?? null)
    };

    const pmProvinceSelect = document.getElementById('pm-province-select');
    const pmCitySelect = document.getElementById('pm-city-select');
    const pmBarangaySelect = document.getElementById('pm-barangay-select');

    function loadPmProvinces() {
        pmProvinceSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        fetch(`${PM_BASE_URL}/locations/provinces`)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(provinces => {
                pmProvinceSelect.innerHTML = '<option value="" disabled>Select province...</option>';
                provinces.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.name;
                    if (pmCurrentAddress.province_id && String(p.id) === String(pmCurrentAddress.province_id)) {
                        opt.selected = true;
                    }
                    pmProvinceSelect.appendChild(opt);
                });
                if (pmCurrentAddress.province_id) {
                    loadPmCities(pmCurrentAddress.province_id, pmCurrentAddress.city_id);
                }
            })
            .catch(err => {
                console.error('[Profile Address] Failed to load provinces:', err);
                pmProvinceSelect.innerHTML = '<option value="" disabled selected>Failed to load provinces</option>';
            });
    }

    function loadPmCities(provinceId, preselectCityId) {
        pmCitySelect.value = '';
        pmCitySelect.disabled = true;
        pmCitySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        pmBarangaySelect.value = '';
        pmBarangaySelect.disabled = true;
        pmBarangaySelect.innerHTML = '<option value="" disabled selected>Select city first...</option>';

        fetch(`${PM_BASE_URL}/locations/cities/${provinceId}`)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(cities => {
                pmCitySelect.innerHTML = '<option value="" disabled>Select city/municipality...</option>';
                cities.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.textContent = c.name;
                    if (preselectCityId && String(c.id) === String(preselectCityId)) {
                        opt.selected = true;
                    }
                    pmCitySelect.appendChild(opt);
                });
                pmCitySelect.disabled = false;
                if (cities.length === 0) {
                    console.warn('[Profile Address] No cities returned for province', provinceId, '— check that the cities table has rows for this province_id.');
                }
                if (preselectCityId) {
                    loadPmBarangays(preselectCityId, pmCurrentAddress.barangay_id);
                }
            })
            .catch(err => {
                console.error('[Profile Address] Failed to load cities:', err);
                pmCitySelect.innerHTML = '<option value="" disabled selected>Failed to load cities</option>';
            });
    }

    function loadPmBarangays(cityId, preselectBarangayId) {
        pmBarangaySelect.value = '';
        pmBarangaySelect.disabled = true;
        pmBarangaySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';

        fetch(`${PM_BASE_URL}/locations/barangays/${cityId}`)
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(barangays => {
                pmBarangaySelect.innerHTML = '<option value="" disabled>Select barangay...</option>';
                barangays.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.id;
                    opt.textContent = b.name;
                    if (preselectBarangayId && String(b.id) === String(preselectBarangayId)) {
                        opt.selected = true;
                    }
                    pmBarangaySelect.appendChild(opt);
                });
                pmBarangaySelect.disabled = false;
                if (barangays.length === 0) {
                    console.warn('[Profile Address] No barangays returned for city', cityId, '— check that the barangays table has rows for this city_id.');
                }
            })
            .catch(err => {
                console.error('[Profile Address] Failed to load barangays:', err);
                pmBarangaySelect.innerHTML = '<option value="" disabled selected>Failed to load barangays</option>';
            });
    }

    pmProvinceSelect.addEventListener('change', function () {
        pmCurrentAddress.city_id = null;
        pmCurrentAddress.barangay_id = null;
        if (this.value) loadPmCities(this.value, null);
    });

    pmCitySelect.addEventListener('change', function () {
        pmCurrentAddress.barangay_id = null;
        if (this.value) loadPmBarangays(this.value, null);
    });

    document.addEventListener('click', function(event) {
        const panel = document.getElementById('floatingPanel');
        const isClickInsidePanel = panel.contains(event.target);
        const isClickingTrigger = event.target.closest('[onclick*="showProfilePanel"]');
        
        if (panel.classList.contains('show') && !isClickInsidePanel && !isClickingTrigger) {
            panel.classList.remove('show');
            openProfileMenu();
        }

        const sidebar = document.getElementById('farmerSidebar');
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickingSidebarTrigger = event.target.closest('#sidebarToggleBtn');
        
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
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (res.ok) { 
                alert(data.message || 'Profile updated successfully!'); 
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
                    for (const key in data.errors) { errorMsg += `- ${data.errors[key][0]}\n`; }
                    alert(errorMsg);
                } else {
                    alert(data.message || 'Failed to update. Server error occurred.');
                }
            }
        } catch(e) { 
            alert('A network error occurred. Please try again.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    async function savePassword(formId) {
        const form = document.getElementById(formId);
        const formData = new FormData(form);
        const btn = form.querySelector('button[type="button"]');
        const originalText = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        btn.disabled = true;

        try {
            const res = await fetch(form.action, { 
                method: 'POST', 
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (res.ok && data.success) { 
                alert(data.message || 'Password updated successfully!'); 
                form.reset(); 
            } else {
                if (res.status === 422 && data.errors) {
                    let errorMsg = 'Could not update password:\n\n';
                    for (const key in data.errors) { errorMsg += `- ${data.errors[key][0]}\n`; }
                    alert(errorMsg);
                } else {
                    alert(data.message || 'Failed to update password.');
                }
            }
        } catch(e) { 
            alert('A network error occurred. Please try again.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }

    // --- Chatbot Global Layout Logic ---
    function initChat() {
        const sidebarChatBtn = document.getElementById('sidebar-chat-btn');
        const chatWindow = document.getElementById('chat-window');
        const closeBtn = document.getElementById('chat-close');
        const sendBtn = document.getElementById('chat-send');
        const input = document.getElementById('chat-input');

        if (sidebarChatBtn) {
            sidebarChatBtn.addEventListener('click', (e) => {
                e.preventDefault();
                chatWindow.style.display = 'flex';
                document.body.classList.remove('sidebar-show'); // Auto-close sidebar on mobile/desktop selection
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => chatWindow.style.display = 'none');
        }
        if (sendBtn) {
            sendBtn.addEventListener('click', sendChatQuery);
        }
        if (input) {
            input.addEventListener('keypress', e => { if (e.key === 'Enter') sendChatQuery(); });
        }

        const msgContainer = document.getElementById('chat-messages');
        if (msgContainer && msgContainer.children.length === 0) {
            addChatMessage("Hello! 🌾 Ask me anything about rice farming.", false);
        }
    }

  async function sendChatQuery() {
    const input = document.getElementById('chat-input');
    const query = input.value.trim();
    if (!query) return;

    addChatMessage(query, true);
    input.value = '';

    const typing = document.createElement('div');
    typing.id = 'typing-indicator';
    typing.className = 'd-flex justify-content-start mt-2';
    typing.innerHTML = `<div class="bg-secondary bg-opacity-25 text-white px-3 py-2 rounded"><i class="fa-solid fa-spinner fa-spin"></i> Thinking...</div>`;
    document.getElementById('chat-messages').appendChild(typing);

    try {
        const langSelector = document.getElementById('language-selector');
        
        // Debug log to check payload before sending
        console.log("Sending chat query to server...");

        const response = await fetch("{{ route('farmer.chat.query') }}", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                "X-CSRF-TOKEN": "{{ csrf_token() }}" 
            },
            body: JSON.stringify({ 
                language: langSelector ? langSelector.value : 'tagalog',
                message: query 
            })
        });

        const rawText = await response.text();
        console.log("Raw Server Response:", rawText);

        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) typingIndicator.remove();

        // Check if response is valid JSON
        let data;
        try {
            data = JSON.parse(rawText);
        } catch (e) {
            // If it's not JSON, Laravel likely returned an HTML error page (like a 404 or 500 stack trace)
            addChatMessage(`⚠️ Server HTML Error (HTTP ${response.status}): Check your route or controller.`, false);
            return;
        }

        if (response.ok && data.response) {
            addChatMessage(data.response, false);
        } else {
            addChatMessage(`⚠️ API Error: ${data.error || data.message || 'Unknown server error'}`, false);
        }

    } catch (error) {
        const typingIndicator = document.getElementById('typing-indicator');
        if (typingIndicator) typingIndicator.remove();
        
        console.error("Network/Fetch Exception:", error);
        addChatMessage(`⚠️ Network Connection Failed: ${error.message}`, false);
    }
}
    function addChatMessage(text, isUser = false) {
        const container = document.getElementById('chat-messages');
        if (!container) return;
        const msg = document.createElement('div');
        msg.className = `d-flex mt-2 ${isUser ? 'justify-content-end' : 'justify-content-start'}`;
        msg.innerHTML = `<div class="${isUser ? 'bg-success' : 'bg-secondary bg-opacity-25'} text-white px-3 py-2 rounded">${text}</div>`;
        container.appendChild(msg);
        container.scrollTop = container.scrollHeight;
    }

document.addEventListener('DOMContentLoaded', () => {
    initChat();

    const logoutForm = document.getElementById('logout-form');
    if (logoutForm) {
        logoutForm.addEventListener('submit', function (e) {
            // If online, but background sync is still running
            if (navigator.onLine && typeof window.isSystemReady !== 'undefined' && !window.isSystemReady) {
                e.preventDefault();
                alert("⏳ The system is syncing data since you just went online. Please wait a few seconds before logging out.");
                return;
            }
            
            // If offline
            if (!navigator.onLine) {
                e.preventDefault();
                alert("📡 You are currently offline. Please reconnect to the internet to log out securely.");
            }
        });
    }
});

    window.addEventListener('load', () => {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('{{ asset("sw.js") }}').catch(err => console.error('Service Worker Registration Failed!', err));
        }
    });
    </script>
    @yield('scripts')
</body>
</html>