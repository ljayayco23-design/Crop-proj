@extends('layouts.app')

@section('title', 'RICEGUARD AI • Login')

@section('content')
<div class="min-h-screen bg-[#0f172a] bg-cover bg-center bg-no-repeat bg-fixed flex items-center justify-center p-4 relative"
     style="background-image: linear-gradient(rgba(15,23,42,0.75), rgba(15,23,42,0.85)), url('{{ asset('img/login-bg.jpg') }}');">
    <div class="bg-[#1e293b]/95 backdrop-blur-sm rounded-3xl p-8 w-full max-w-md shadow-2xl">

        <div class="text-center mb-8">
            <button type="button" onclick="showLogoLightbox()" class="inline-flex items-center justify-center h-20 w-20 rounded-full bg-white shadow-lg ring-4 ring-emerald-400/40 mb-4 overflow-hidden hover:ring-emerald-400/70 transition-all cursor-pointer">
                <img src="{{ asset('img/logo.jpg') }}" alt="RiceGuard AI Logo" class="h-full w-full object-cover">
            </button>
            <h1 class="text-3xl font-bold">RICEGUARD AI</h1>
            <p class="text-zinc-400">Login Portal</p>
        </div>

        @if (session('error'))
            <div id="serverErrorBanner" class="mb-6 p-4 bg-red-700 rounded-2xl text-center">{{ session('error') }}</div>
        @endif

        @if ($errors->any() && !session('open_signup'))
            <div class="mb-6 p-4 bg-red-700 rounded-2xl text-sm">
                @foreach ($errors->all() as $err)
                    <div>• {{ $err }}</div>
                @endforeach
            </div>
        @endif

        @if (session('success'))
            <div class="mb-6 p-4 bg-emerald-700 rounded-2xl text-center">{{ session('success') }}</div>
        @endif

        <!-- Filled/shown by the offline-login script below when it's used
             (either to report an offline success, or that no cached
             account matches on this device). Stays empty/hidden otherwise. -->
        <div id="offlineLoginMsg" class="hidden mb-6 p-4 rounded-2xl text-center"></div>

        <form method="POST" action="{{ route('login.post') }}" id="loginForm">
            @csrf

            <div class="mb-4">
                <label class="block text-zinc-400 text-sm mb-2">Email Address</label>
                <input type="email" name="email" id="email_input" required maxlength="100" autocomplete="username" autocapitalize="none" spellcheck="false" value="{{ old('email') }}"
                       class="w-full p-4 rounded-2xl bg-zinc-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div class="mb-4 relative">
                <label class="block text-zinc-400 text-sm mb-2">Password</label>
                <input type="password" name="password" id="password_input" required maxlength="255" autocomplete="current-password"
                       class="w-full p-4 pr-12 rounded-2xl bg-zinc-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                <span class="absolute right-4 top-[42px] text-zinc-400 hover:text-zinc-300 cursor-pointer text-lg" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </span>
            </div>

            <div class="flex justify-end mb-6">
                <a href="#" onclick="showForgotModal(); return false;" class="text-emerald-400 hover:text-emerald-300 text-sm font-medium">
                    Nakalimutan ang Password?
                </a>
            </div>

            <button type="submit" id="loginSubmitBtn"
                    class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 rounded-3xl font-semibold text-lg transition-all">
                Mag-login
            </button>
        </form>

        <div class="text-center mt-6">
            <a href="#" onclick="showSignupModal()" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">
                Walang account? Mag-sign up bilang Farmer
            </a>
        </div>
    </div>
</div>

<!-- Logo Lightbox: shows the full logo image when clicked, on login or registration -->
<div id="logoLightbox" class="hidden fixed inset-0 bg-black/90 backdrop-blur-sm flex items-center justify-center z-[60] p-4" onclick="hideLogoLightbox()">
    <div class="relative max-w-md w-full" onclick="event.stopPropagation()">
        <button type="button" onclick="hideLogoLightbox()" class="absolute -top-10 right-0 text-white text-3xl leading-none hover:text-emerald-400">&times;</button>
        <img src="{{ asset('img/logo.jpg') }}" alt="RiceGuard AI Logo" class="w-full h-auto rounded-2xl shadow-2xl bg-white p-6">
    </div>
</div>

<div id="signupModal" class="hidden fixed inset-0 bg-cover bg-center bg-no-repeat bg-fixed flex items-center justify-center z-50 px-4 overflow-y-auto py-10"
     style="background-image: linear-gradient(rgba(15,23,42,0.85), rgba(15,23,42,0.9)), url('{{ asset('img/login-bg.jpg') }}');">
    <div class="bg-[#1e293b] rounded-3xl p-8 w-full max-w-lg shadow-2xl relative my-auto">
        <div class="text-center mb-2">
            <button type="button" onclick="showLogoLightbox()" class="inline-flex items-center justify-center h-16 w-16 rounded-full bg-white shadow-lg ring-4 ring-emerald-400/40 mb-3 overflow-hidden hover:ring-emerald-400/70 transition-all cursor-pointer">
                <img src="{{ asset('img/logo.jpg') }}" alt="RiceGuard AI Logo" class="h-full w-full object-cover">
            </button>
        </div>
        <h2 class="text-2xl font-bold text-center mb-2 text-emerald-400">Farmer Registration</h2>
        <div class="text-center text-sm text-zinc-400 mb-6 font-semibold" id="step-indicator">Step 1 of 5</div>

        @if (session('open_signup') && ($errors->any() || session('error')))
            <div class="mb-4 p-3 bg-red-700/90 rounded-xl text-sm text-white">
                @if (session('error'))<div>{{ session('error') }}</div>@endif
                @foreach ($errors->all() as $err)
                    <div>• {{ $err }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" id="signupForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="action" value="signup">
            
            <input type="hidden" name="document_photo_base64" id="document_photo_base64">
            <input type="hidden" name="selfie_photo_base64" id="selfie_photo_base64">
            <input type="hidden" name="id_check" id="id_check_input">

            <div class="form-step active">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">1. Personal Identity</h3>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Full Name <span class="text-zinc-500 text-xs">(Buong Pangalan)</span></label>
                    <input type="text" name="full_name" required minlength="5" maxlength="100" autocomplete="name" data-validate="full_name" value="{{ old('full_name') }}" placeholder="e.g., Juan Dela Cruz" class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                </div>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Date of Birth</label>
                    <input type="date" name="dob" required data-validate="dob" value="{{ old('dob') }}" class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                </div>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Mobile Number</label>
                    <input type="tel" name="mobile" inputmode="numeric" maxlength="11" pattern="09[0-9]{9}" title="11 digits, nagsisimula sa 09" autocomplete="tel" data-validate="mobile" value="{{ old('mobile') }}" placeholder="09123456789" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                </div>

                <div class="mb-3">
                    <label class="block text-zinc-400 text-sm mb-2">Province</label>
                    <select id="province-select" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <option value="" disabled selected>Select province...</option>
                    </select>
                    <input type="hidden" name="province" id="province-hidden">
                    <input type="hidden" name="province_id" id="province-id-hidden">
                </div>
                <div class="mb-3">
                    <label class="block text-zinc-400 text-sm mb-2">City / Municipality</label>
                    <select id="city-select" required disabled class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <option value="" disabled selected>Select province first...</option>
                    </select>
                    <input type="hidden" name="city" id="city-hidden">
                    <input type="hidden" name="city_id" id="city-id-hidden">
                </div>
                <div class="mb-3">
                    <label class="block text-zinc-400 text-sm mb-2">Barangay</label>
                    <select id="barangay-select" required disabled class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <option value="" disabled selected>Select city first...</option>
                    </select>
                    <input type="hidden" name="barangay" id="barangay-hidden">
                    <input type="hidden" name="barangay_id" id="barangay-id-hidden">
                </div>
                
            </div>

            <div class="form-step hidden">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">2. Farming Role</h3>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Farmer Category</label>
                    <select name="farmer_category" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <option value="" disabled selected>Select your role...</option>
                        <option value="owner">Land Owner</option>
                        <option value="tenant">Tenant / Sharecropper</option>
                        <option value="laborer">Farm Worker / Laborer</option>
                    </select>
                </div>
            </div>

            <div class="form-step hidden" id="step-field-details">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">3. Field Details & Mapping</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <!-- Left Column: Redesigned Input Fields -->
                    <div class="space-y-4">
                        <div>
                            <label class="block text-zinc-400 text-sm mb-1 font-medium">Farm Name <span class="text-emerald-400">*</span></label>
                            <input type="text" name="farm_name" placeholder="e.g., San Jose Farmland" required minlength="2" maxlength="100" data-validate="farm_name" value="{{ old('farm_name') }}" 
                                class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700 transition-all">
                        </div>

                        <div>
                            <label class="block text-zinc-400 text-sm mb-1 font-medium">Rice Field Location</label>
                            <small class="text-zinc-500 text-xs block mb-2">The pin starts at the barangay you chose in Step 1. Drag the pin or tap the map to mark your exact field.</small>
                            <div id="map-status" class="text-xs text-emerald-400 mb-2 min-h-[1rem]" role="status"></div>
                            <input type="hidden" name="latitude" id="lat-input" required>
                            <input type="hidden" name="longitude" id="lng-input" required>
                            <input type="hidden" name="device_latitude" id="device-lat">
                            <input type="hidden" name="device_longitude" id="device-lng">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-zinc-400 text-sm mb-1 font-medium">Growth Stage</label>
                                <select name="growth_stage" class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                                    <option value="Seedling">Seedling</option>
                                    <option value="Vegetative">Vegetative</option>
                                    <option value="Reproductive">Reproductive</option>
                                    <option value="Ripening">Ripening</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-zinc-400 text-sm mb-1 font-medium">Rice Variety</label>
                                <input type="text" name="rice_variety" placeholder="e.g., IR64" maxlength="50" data-validate="rice_variety" value="{{ old('rice_variety') }}" class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-zinc-400 text-sm mb-1 font-medium">Size (Hectares)</label>
                            <input type="number" name="farm_size" step="0.01" min="0.01" max="1000" inputmode="decimal" placeholder="e.g. 2.5" required data-validate="farm_size" value="{{ old('farm_size') }}" 
                                class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        </div>

                        <div>
                            <label class="block text-zinc-400 text-sm mb-1 font-medium">Water Source</label>
                            <select name="water_source" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                                <option value="" disabled selected>Select water source...</option>
                                <option value="irrigated">Irrigated</option>
                                <option value="rainfed">Rainfed</option>
                            </select>
                        </div>
                    </div>

                    <!-- Right Column: Interactive Map with City/Street Labels -->
                    <div class="h-[400px] md:h-full min-h-[350px] flex flex-col">
                        <div id="registration-map" class="w-full flex-1 rounded-2xl border border-zinc-700 shadow-inner z-10" style="min-height: 350px;"></div>
                        <p class="text-xs text-zinc-400 mt-2 text-center">Tip: Tap the map or drag the pin to adjust location. Hectares automatically generate shape.</p>
                    </div>
                </div>
            </div>

            <div class="form-step hidden">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">4. Verification</h3>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Type of Valid ID</label>
                    <select name="id_type" id="id_type_select" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <option value="" disabled selected>Select ID type...</option>
                        <option value="barangay_clearance">Barangay ID / Clearance</option>
                        <option value="voters_id">Voter's ID</option>
                        <option value="national_id">National ID</option>
                        <option value="farmer_id">RSBSA / Farmer's ID</option>
                        <option value="other">Other Valid ID</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Upload ID Photo</label>
                    <input type="file" id="document_upload" accept="image/jpeg,image/png,image/webp" required class="w-full p-2 rounded-xl bg-zinc-800 text-zinc-400 border border-zinc-700 cursor-pointer">
                    <p class="text-zinc-500 text-xs mt-2 leading-snug">
                        Piliin muna ang uri ng ID. Awtomatikong susuriin ang larawan: dapat malinaw, kita ang buong ID,
                        may mukha (kung may litrato ang ID) at tugma sa napiling uri.
                    </p>
                </div>
                <div id="idCheckPanel" class="hidden" role="status" aria-live="polite"></div>
                <!-- <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Take a Live Selfie</label>
                    <input type="file" id="selfie_upload" accept="image/*" capture="user" required class="w-full p-2 rounded-xl bg-zinc-800 text-zinc-400 border border-zinc-700 cursor-pointer">
                </div> -->
            </div>

            <div class="form-step hidden">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">5. Security</h3>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Email Address</label>
                    <input type="email" name="email" required maxlength="100" autocomplete="email" data-validate="email" value="{{ old('email') }}" placeholder="juan@@gmail.com" class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                </div>
            <div class="mb-6">
                    <label class="block text-zinc-400 text-sm mb-2">
                        Password
                        <span class="block text-zinc-500 text-xs mt-1 leading-snug">
                            (Use at least 8 characters, including an uppercase letter, lowercase letter, number, and special character.)
                        </span>
                    </label>
                    
                    <div class="relative">
                        <input type="password" name="password" id="signup_password_input" required minlength="8" maxlength="64" autocomplete="new-password" data-validate="password" 
                            class="w-full p-3 pr-12 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-300 cursor-pointer text-lg flex items-center" id="toggleSignupPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                    <ul id="pwChecklist" class="mt-2 space-y-1 text-xs text-zinc-500">
                        <li data-rule="len"><span>• </span>8 hanggang 64 characters</li>
                        <li data-rule="upper"><span>• </span>Malaking letra (A-Z)</li>
                        <li data-rule="lower"><span>• </span>Maliit na letra (a-z)</li>
                        <li data-rule="num"><span>• </span>Numero (0-9)</li>
                        <li data-rule="sym"><span>• </span>Special character (! @@ # $ %)</li>
                        <li data-rule="space"><span>• </span>Walang espasyo</li>
                    </ul>
                </div>
                <div class="mb-6">
                    <label class="block text-zinc-400 text-sm mb-2">Confirm Password</label>
                    <div class="relative">
                        <input type="password" name="password_confirmation" id="signup_password_confirm_input" required maxlength="64" autocomplete="new-password"
                            class="w-full p-3 pr-12 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-300 cursor-pointer text-lg flex items-center" id="toggleSignupPasswordConfirm">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button type="button" id="backBtn" class="hidden flex-1 py-3 bg-zinc-700 hover:bg-zinc-600 text-white rounded-2xl font-semibold transition-all">Back</button>
                <button type="button" id="nextBtn" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-semibold transition-all">Next</button>
                <button type="submit" id="submitBtn" class="hidden flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-2xl font-semibold transition-all">Submit Registration</button>
            </div>
            <div class="text-center mt-4" id="backToLoginContainer">
                <button onclick="hideSignupModal(); return false;" class="text-emerald-400 hover:text-emerald-300 underline underline-offset-4 text-sm font-medium transition-colors">Bumalik sa Login</button>
            </div>
        </form>

        <button onclick="hideSignupModal()" class="absolute top-4 right-6 text-zinc-500 hover:text-white text-2xl">&times;</button>
    </div>
</div>


<div id="forgotModal" class="hidden fixed inset-0 bg-cover bg-center bg-no-repeat bg-fixed flex items-center justify-center z-50 px-4"
     style="background-image: linear-gradient(rgba(15,23,42,0.85), rgba(15,23,42,0.9)), url('{{ asset('img/login-bg.jpg') }}');">
    <div class="bg-[#1e293b] rounded-3xl p-8 w-full max-w-md shadow-2xl">
        <h2 class="text-2xl font-bold text-center mb-6">Nakalimutan ang Password</h2>
        
        <form method="POST" action="{{ route('password.email') }}" id="forgotForm">
            @csrf
            <div class="mb-6">
                <label class="block text-zinc-400 text-sm mb-2">Email Address</label>
                <input type="email" name="forgot_email" id="forgot_email_input" 
                       class="w-full p-4 rounded-2xl bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-lg" 
                       required placeholder="your@email.com">
            </div>

            <button type="submit"
                    class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 rounded-3xl font-semibold text-lg transition-all">
                Ipadala ang Bagong Password
            </button>
        </form>

        <button onclick="hideForgotModal()" 
                class="w-full mt-4 py-4 bg-zinc-700 hover:bg-zinc-600 rounded-3xl text-zinc-400 font-medium transition-colors">
            Cancel
        </button>

        <p class="text-center text-xs text-zinc-500 mt-6">
            Isang secure na temporaryong password ang ipapadala sa iyong email.
        </p>
    </div>
</div>

@if (session('pending'))
<div class="fixed inset-0 bg-black/90 flex items-center justify-center z-[100]">
    <div class="text-center">
        <div id="pending-spinner" class="relative w-24 h-24 mx-auto mb-6">
            <div class="absolute inset-0 border-4 border-zinc-700 rounded-full"></div>
            <div class="absolute inset-0 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <h3 id="pending-title" class="text-xl font-semibold text-emerald-400">Naghihintay ng Approval</h3>
        <p id="pending-desc" class="text-zinc-400 mt-2">Mangyaring maghintay habang inaaprubahan ng Admin ang iyong account.</p>
        <p class="text-sm text-zinc-500 mt-6">Email: <span id="pending-email">{{ session('pending') }}</span></p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Safely inject email without Blade HTML escaping issues
        let email = {!! json_encode(session('pending')) !!};
        console.log("Checking approval status for:", email);
        
        let interval = setInterval(() => {
            let checkUrl = `{{ url('/check-status') }}?email=${encodeURIComponent(email)}&_t=${Date.now()}`;
            
            fetch(checkUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        console.error('Server Error:', response.status);
                        throw new Error('Network response failed.');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log("Current Database Status:", data.status);
                    let status = (data.status || 'none').toLowerCase();
                    
                    if (status === 'approved') {
                        clearInterval(interval);
                        
                        document.getElementById('pending-spinner').style.display = 'none';
                        document.getElementById('pending-title').textContent = 'Na-aprubahan na!';
                        document.getElementById('pending-title').className = 'text-xl font-semibold text-emerald-400';
                        document.getElementById('pending-desc').textContent = 'Maaari ka nang mag-login. Nire-redirect...';
                        
                        setTimeout(() => {
                            // Clean redirect to clear session data
                            window.location.href = "{{ route('login') }}"; 
                        }, 2000);
                        
                    } else if (status === 'declined') {
                        clearInterval(interval);
                        
                        document.getElementById('pending-spinner').style.display = 'none';
                        document.getElementById('pending-title').textContent = 'Account Declined';
                        document.getElementById('pending-title').className = 'text-xl font-semibold text-red-500';
                        document.getElementById('pending-desc').textContent = 'Ikinalulungkot namin, ngunit na-decline ang iyong account. Nire-redirect...';
                        
                        setTimeout(() => {
                            window.location.href = "{{ route('login') }}"; 
                        }, 2500);
                    }
                })
                .catch(error => console.error('Fetch Error:', error));
        }, 3000); 
    });
</script>
@endif

<!-- Login lockout popup: 5 wrong passwords in a row -> blocked for 15 seconds -->
<div id="lockoutModal" class="hidden fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-[120] p-4"
     role="alertdialog" aria-modal="true" aria-labelledby="lockoutTitle" aria-describedby="lockoutDesc">
    <div class="bg-[#1e293b] rounded-3xl p-8 w-full max-w-sm text-center shadow-2xl border border-red-500/40">
        <div class="mx-auto mb-4 h-14 w-14 rounded-full bg-red-600/20 flex items-center justify-center text-red-400 text-2xl">
            <i class="fas fa-lock"></i>
        </div>
        <h3 id="lockoutTitle" class="text-xl font-bold text-red-400 mb-2">Naka-lock ang Login</h3>
        <p id="lockoutDesc" class="text-zinc-300 text-sm mb-5">
            Limang sunod-sunod na maling subok. Maghintay muna bago mag-login ulit.
        </p>
        <div class="relative mx-auto w-28 h-28 mb-4">
            <svg viewBox="0 0 120 120" class="w-28 h-28 -rotate-90">
                <circle cx="60" cy="60" r="52" fill="none" stroke="#334155" stroke-width="8"></circle>
                <circle id="lockoutRing" cx="60" cy="60" r="52" fill="none" stroke="#ef4444" stroke-width="8" stroke-linecap="round"
                        style="transition: stroke-dashoffset .25s linear;"></circle>
            </svg>
            <div id="lockoutBig" class="absolute inset-0 flex items-center justify-center text-3xl font-bold text-white">15</div>
        </div>
        <p class="text-zinc-400 text-xs">Babalik ang login sa loob ng <span id="lockoutSeconds">15</span> segundo.</p>
    </div>
</div>

@endsection

@section('scripts')
<script>
window.__RG_SERVER_LOCK = {{ (int) session('lockout_seconds', 0) }};
@if (session('open_signup'))
document.addEventListener('DOMContentLoaded', function () { showSignupModal(); });
@endif
</script>
<script>
// ============================================================
// STRICT FIELD VALIDATORS (mirror the rules in AuthController)
// ============================================================
const RG_RX = {
    name:    /^(?=.*\s)\p{L}[\p{L}\s.'\-]*$/u,
    mobile:  /^09\d{9}$/,
    email:   /^[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}$/,
    farm:    /^[\p{L}\p{N}\s.,'#&\-]+$/u,
    variety: /^[\p{L}\p{N}\s.\-]+$/u,
};
const RG_DISPOSABLE = ['mailinator.com','guerrillamail.com','10minutemail.com','tempmail.com','temp-mail.org',
    'yopmail.com','trashmail.com','sharklasers.com','getnada.com','dispostable.com','maildrop.cc','fakeinbox.com'];

function rgPasswordChecks(v) {
    return {
        len:   v.length >= 8 && v.length <= 64,
        upper: /[A-Z]/.test(v),
        lower: /[a-z]/.test(v),
        num:   /\d/.test(v),
        sym:   /[^A-Za-z0-9\s]/.test(v),
        space: v.length > 0 && !/\s/.test(v),
    };
}

function rgAgeOn(dobStr, today) {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(dobStr || '');
    if (!m) return null;
    const y = +m[1], mo = +m[2], d = +m[3];
    const dt = new Date(y, mo - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return null; // e.g. 2001-02-31
    let age = today.getFullYear() - y;
    if (today.getMonth() < mo - 1 || (today.getMonth() === mo - 1 && today.getDate() < d)) age--;
    return age;
}

// Each validator returns '' when OK, otherwise the message to show.
const RG_VALIDATORS = {
    full_name(v) {
        v = v.trim().replace(/\s+/g, ' ');
        if (v.length < 5) return 'Ilagay ang buong pangalan (hal. Juan Dela Cruz).';
        if (v.length > 100) return 'Masyadong mahaba ang pangalan (hanggang 100 characters).';
        if (!RG_RX.name.test(v)) return 'Letra, espasyo, tuldok, gitling at apostrophe lang ang pwede. Kailangan ng first name at last name.';
        return '';
    },
    dob(v) {
        const age = rgAgeOn(v, new Date());
        if (age === null) return 'Ilagay ang tamang petsa ng kapanganakan.';
        if (age < 18) return 'Kailangang 18 taong gulang pataas para mag-register.';
        if (age > 100) return 'Hindi valid ang petsa ng kapanganakan.';
        return '';
    },
    mobile(v) {
        return RG_RX.mobile.test(v) ? '' : 'Ang mobile number ay dapat 11 digits at nagsisimula sa 09 (hal. 09123456789).';
    },
    email(v) {
        v = v.trim();
        if (v.length > 100) return 'Masyadong mahaba ang email.';
        if (!RG_RX.email.test(v)) return 'Hindi valid ang email. Dapat may tamang domain (hal. juan@gmail.com).';
        const domain = v.split('@')[1].toLowerCase();
        if (RG_DISPOSABLE.includes(domain)) return 'Hindi tinatanggap ang temporary email. Gumamit ng totoong email.';
        return '';
    },
    farm_name(v) {
        v = v.trim();
        if (v.length < 2) return 'Ilagay ang pangalan ng sakahan.';
        if (!RG_RX.farm.test(v)) return 'May hindi pinapayagang character sa pangalan ng sakahan.';
        return '';
    },
    farm_size(v) {
        const n = Number(v);
        if (v === '' || !isFinite(n)) return 'Ilagay ang laki ng sakahan sa hectares.';
        if (n < 0.01) return 'Ang laki ng sakahan ay dapat hindi bababa sa 0.01 ha.';
        if (n > 1000) return 'Ang laki ng sakahan ay hindi dapat lumampas sa 1000 ha.';
        return '';
    },
    rice_variety(v) {
        v = v.trim();
        if (v === '') return '';
        return RG_RX.variety.test(v) && v.length <= 50 ? '' : 'Letra, numero, espasyo, tuldok at gitling lang (hanggang 50).';
    },
    password(v) {
        const c = rgPasswordChecks(v);
        if (!c.len)   return 'Ang password ay dapat 8 hanggang 64 characters.';
        if (!c.space) return 'Bawal ang espasyo sa password.';
        if (!c.upper) return 'Maglagay ng kahit isang malaking letra (A-Z).';
        if (!c.lower) return 'Maglagay ng kahit isang maliit na letra (a-z).';
        if (!c.num)   return 'Maglagay ng kahit isang numero (0-9).';
        if (!c.sym)   return 'Maglagay ng kahit isang special character (hal. ! @ # $ %).';
        return '';
    },
};

// ============================================================
// ID PHOTO CHECK helpers (pure functions, no DOM)
// ============================================================
const RG_ID = {
    MIN_SIDE: 600,        // shortest side of the picked photo (px)
    MIN_SHARPNESS: 60,    // Laplacian variance, lower = blurry
    MIN_BRIGHT: 55,       // 0-255
    MAX_BRIGHT: 215,
    MIN_TEXT_CHARS: 25,
    FACE_MIN_RATIO: 0.003,  // face area / photo area. Smaller = no usable face
    FACE_MAX_RATIO: 0.30,   // bigger = a selfie / close-up, not an ID card
    MAX_FACES: 3,
    STRICT_TYPE: false,   // true = reject when none of the words of the chosen ID type are found; false = only warn (admin reviews)
    STRICT_NAME: false,   // true = also reject when the typed name is not on the ID
    UPLOAD_MAX_SIDE: 1280,
    ANALYSIS_MAX_SIDE: 1400,
};

// What each ID type must look like. 'keywords' are printed on that ID (lowercase).
const RG_ID_RULES = {
    national_id:        { label: 'National ID',      face: true,  keywords: ['philsys', 'philippine identification', 'pambansang', 'pagkakakilanlan', 'identification', 'republic of the philippines', 'republika ng pilipinas', 'pilipinas', 'philippines'] },
    voters_id:          { label: "Voter's ID",       face: true,  keywords: ['comelec', 'commission on elections', 'voter', 'botante'] },
    barangay_clearance: { label: 'Barangay ID / Clearance', face: false, keywords: ['barangay', 'brgy', 'punong', 'kapitan', 'purok'] },
    farmer_id:          { label: "RSBSA / Farmer's ID", face: true, keywords: ['rsbsa', 'farmer', 'magsasaka', 'agriculture', 'agrikultura', 'registry'] },
    other:              { label: 'Other Valid ID',   face: true,  keywords: [] },
};

function rgLaplacianVariance(gray, w, h) {
    let sum = 0, sq = 0, n = 0;
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const i = y * w + x;
            const l = 4 * gray[i] - gray[i - w] - gray[i + w] - gray[i - 1] - gray[i + 1];
            sum += l; sq += l * l; n++;
        }
    }
    if (!n) return 0;
    const mean = sum / n;
    return sq / n - mean * mean;
}

function rgMeanBrightness(gray) {
    let s = 0;
    for (let i = 0; i < gray.length; i++) s += gray[i];
    return gray.length ? s / gray.length : 0;
}

function rgLevenshtein(a, b) {
    const dp = Array.from({ length: a.length + 1 }, (_, i) => [i]);
    for (let j = 1; j <= b.length; j++) dp[0][j] = j;
    for (let i = 1; i <= a.length; i++)
        for (let j = 1; j <= b.length; j++)
            dp[i][j] = Math.min(dp[i - 1][j] + 1, dp[i][j - 1] + 1, dp[i - 1][j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
    return dp[a.length][b.length];
}

// Share (0..1) of the typed name's words that appear on the ID. Tolerates 1 OCR typo in longer words.
function rgNameScore(fullName, ocrText) {
    const norm = s => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const tokens = norm(fullName).split(/[^a-z]+/).filter(t => t.length >= 2);
    if (!tokens.length) return 0;
    const words = norm(ocrText).split(/[^a-z]+/).filter(Boolean);
    const hit = t => words.some(w => w === t || (t.length >= 5 && w.length >= 4 && rgLevenshtein(w, t) <= 1));
    return tokens.filter(hit).length / tokens.length;
}

function rgKeywordHit(idType, ocrText) {
    const words = (RG_ID_RULES[idType] || { keywords: [] }).keywords;
    if (!words.length) return true;
    const t = ocrText.toLowerCase();
    return words.some(w => t.includes(w));
}

// ============================================================
// SIGNUP: live helpers (DOB range, mobile digits, password checklist)
// ============================================================
document.addEventListener('DOMContentLoaded', function () {
    const pad = n => String(n).padStart(2, '0');
    const dob = document.querySelector('#signupForm input[name="dob"]');
    if (dob) {
        const t = new Date();
        dob.max = `${t.getFullYear() - 18}-${pad(t.getMonth() + 1)}-${pad(t.getDate())}`;
        dob.min = `${t.getFullYear() - 100}-${pad(t.getMonth() + 1)}-${pad(t.getDate())}`;
    }

    const mobile = document.querySelector('#signupForm input[name="mobile"]');
    if (mobile) mobile.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });

    // A previous custom message must not stay stuck once the user edits the field.
    document.querySelectorAll('#signupForm [data-validate]').forEach(el =>
        el.addEventListener('input', () => el.setCustomValidity('')));

    const pw = document.getElementById('signup_password_input');
    const list = document.getElementById('pwChecklist');
    if (pw && list) pw.addEventListener('input', function () {
        const c = rgPasswordChecks(pw.value);
        list.querySelectorAll('li').forEach(li => {
            const ok = c[li.dataset.rule];
            li.classList.toggle('text-emerald-400', ok);
            li.classList.toggle('text-zinc-500', !ok);
            li.firstChild.textContent = ok ? '✓ ' : '• ';
        });
    });
});

// ============================================================
// ID PHOTO CHECK: quality -> face -> text on the ID (per ID type)
// Runs in the browser, so nothing extra to install on the server.
// ============================================================
// Local copy first (public/model/tiny_face_detector_model-*), then the public jsDelivr copy of the same files.
const RG_MODEL_URLS = [
    "{{ asset('model') }}",
    'https://cdn.jsdelivr.net/gh/justadudewhohacks/face-api.js@0.22.2/weights',
];
const RG_FACEAPI_JS = 'https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js';
const RG_TESS_JS    = 'https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js';

const idState = { ok: false, busy: false, run: 0 };

function rgLoadScript(src) {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[data-rg-src="${src}"]`);
        if (existing) {
            if (existing.dataset.loaded) return resolve();
            existing.addEventListener('load', resolve);
            existing.addEventListener('error', () => reject(new Error('load ' + src)));
            return;
        }
        const s = document.createElement('script');
        s.src = src; s.async = true; s.dataset.rgSrc = src;
        s.onload = () => { s.dataset.loaded = '1'; resolve(); };
        s.onerror = () => reject(new Error('load ' + src));
        document.head.appendChild(s);
    });
}

function rgWithTimeout(promise, ms, label) {
    return Promise.race([promise, new Promise((_, rej) => setTimeout(() => rej(new Error(label + ' timeout')), ms))]);
}

async function rgDecode(file) {
    if (window.createImageBitmap) {
        try { return await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (e) { /* fall through */ }
    }
    return await new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => { URL.revokeObjectURL(url); resolve(img); };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('decode')); };
        img.src = url;
    });
}

function rgScaled(src, maxSide) {
    const w = src.width, h = src.height;
    const k = Math.min(1, maxSide / Math.max(w, h));
    const c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * k));
    c.height = Math.max(1, Math.round(h * k));
    c.getContext('2d').drawImage(src, 0, 0, c.width, c.height);
    return c;
}

function rgGray(canvas) {
    const { width: w, height: h } = canvas;
    const d = canvas.getContext('2d').getImageData(0, 0, w, h).data;
    const g = new Float32Array(w * h);
    for (let i = 0, p = 0; i < g.length; i++, p += 4) g[i] = 0.299 * d[p] + 0.587 * d[p + 1] + 0.114 * d[p + 2];
    return { gray: g, w, h };
}

class RgReject extends Error {}

/**
 * Throws RgReject(message) when the photo is not acceptable for this ID type.
 * Returns { dataUri, meta } when it passes (meta.status = 'passed' | 'unverified').
 */
async function rgAnalyzeId(file, idType, fullName, onStatus) {
    const rule = RG_ID_RULES[idType];
    if (!rule) throw new RgReject('Piliin muna ang uri ng ID.');
    if (file.size > 15 * 1024 * 1024) throw new RgReject('Masyadong malaki ang file (hanggang 15 MB).');

    let bmp;
    try { bmp = await rgDecode(file); }
    catch (e) { throw new RgReject('Hindi mabuksan ang larawan. Gumamit ng JPG, PNG o WEBP.'); }

    // ---- 1. quality ----
    onStatus('Sinusuri ang kalidad ng larawan...');
    if (Math.min(bmp.width, bmp.height) < RG_ID.MIN_SIDE)
        throw new RgReject('Masyadong maliit ang larawan. Lumapit nang kaunti at kunan ulit.');

    const small = rgGray(rgScaled(bmp, 640));
    const bright = rgMeanBrightness(small.gray);
    const sharp = rgLaplacianVariance(small.gray, small.w, small.h);
    if (bright < RG_ID.MIN_BRIGHT) throw new RgReject('Masyadong madilim ang larawan. Humanap ng mas maliwanag na lugar.');
    if (bright > RG_ID.MAX_BRIGHT) throw new RgReject('Masyadong maliwanag o may glare. Iwasan ang flash at direktang ilaw.');
    if (sharp < RG_ID.MIN_SHARPNESS) throw new RgReject('Malabo ang larawan. Hawakan nang steady at kunan ulit.');

    const meta = { status: 'passed', sharpness: Math.round(sharp), brightness: Math.round(bright) };
    const analysis = rgScaled(bmp, RG_ID.ANALYSIS_MAX_SIDE);

    // ---- 2 + 3. face and text. If the libraries cannot load (CDN down, very old phone)
    //      the photo is NOT blocked; it is marked "unverified" for the admin instead. ----
    try {
        onStatus('Hinahanap ang mukha sa ID...');
        await rgWithTimeout(rgLoadScript(RG_FACEAPI_JS), 20000, 'face lib');
        if (!faceapi.nets.tinyFaceDetector.isLoaded) {
            let modelLoaded = false;
            for (const url of RG_MODEL_URLS) {
                try {
                    await rgWithTimeout(faceapi.nets.tinyFaceDetector.loadFromUri(url), 12000, 'face model');
                    modelLoaded = true;
                    break;
                } catch (e) {
                    console.warn('[ID check] face model not available at', url, e);
                }
            }
            if (!modelLoaded) throw new Error('face model unavailable');
        }
        const dets = await rgWithTimeout(
            faceapi.detectAllFaces(analysis, new faceapi.TinyFaceDetectorOptions({ inputSize: 608, scoreThreshold: 0.4 })),
            20000, 'face detect');

        const area = analysis.width * analysis.height;
        const faces = dets.map(d => (d.box.width * d.box.height) / area).filter(r => r >= RG_ID.FACE_MIN_RATIO);
        meta.faces = faces.length;
        meta.face_ratio = faces.length ? +Math.max(...faces).toFixed(4) : 0;

        if (rule.face && faces.length === 0)
            throw new RgReject('Walang nakitang mukha sa ID. Kunan ang harap ng ID kung nasaan ang larawan.');
        if (faces.length > RG_ID.MAX_FACES)
            throw new RgReject('Maraming mukha ang nakita. Isang ID lang ang dapat nasa larawan.');
        if (faces.length && meta.face_ratio > RG_ID.FACE_MAX_RATIO)
            throw new RgReject('Mukhang selfie o close-up ng mukha ito, hindi ID. Kunan ang buong ID card.');

        onStatus('Binabasa ang text sa ID...');
        await rgWithTimeout(rgLoadScript(RG_TESS_JS), 30000, 'ocr lib');
        const worker = await rgWithTimeout(Tesseract.createWorker('eng', 1, {
            logger: m => { if (m.status === 'recognizing text') onStatus('Binabasa ang text sa ID... ' + Math.round(m.progress * 100) + '%'); },
        }), 30000, 'ocr worker');
        let text = '';
        try { text = (await rgWithTimeout(worker.recognize(analysis), 60000, 'ocr')).data.text || ''; }
        finally { try { await worker.terminate(); } catch (e) {} }

        if (text.replace(/\s/g, '').length < RG_ID.MIN_TEXT_CHARS)
            throw new RgReject('Walang mabasang text. Siguraduhing kita ang buong ID at malinaw ang sulat.');

        console.info('[ID check] OCR text:', text);
        meta.type_keyword_match = rgKeywordHit(idType, text);
        if (RG_ID.STRICT_TYPE && !meta.type_keyword_match)
            throw new RgReject(`Hindi mukhang ${rule.label} ang larawan. Piliin ang tamang uri ng ID o kunan ulit.`);

        meta.name_match = +rgNameScore(fullName, text).toFixed(2);
        console.info('[ID check] result:', JSON.stringify(meta));
        if (RG_ID.STRICT_NAME && meta.name_match === 0)
            throw new RgReject('Hindi nakita ang pangalan mo sa ID. Siguraduhing ang ID ay sa iyo.');
    } catch (e) {
        if (e instanceof RgReject) throw e;
        console.warn('[ID check] automatic analysis unavailable:', e);
        meta.status = 'unverified';
    }

    onStatus('Inihahanda ang larawan...');
    const dataUri = rgScaled(bmp, RG_ID.UPLOAD_MAX_SIDE).toDataURL('image/jpeg', 0.8);
    return { dataUri, meta };
}

function rgIdPanel(kind, lines, thumb) {
    const panel = document.getElementById('idCheckPanel');
    if (!panel) return;
    const styles = {
        hidden: 'hidden',
        busy:   'mb-4 rounded-xl p-3 text-sm bg-zinc-800 border border-zinc-700 text-zinc-300',
        fail:   'mb-4 rounded-xl p-3 text-sm bg-red-700/30 border border-red-600 text-red-200',
        pass:   'mb-4 rounded-xl p-3 text-sm bg-emerald-700/20 border border-emerald-600 text-emerald-200',
        warn:   'mb-4 rounded-xl p-3 text-sm bg-yellow-600/20 border border-yellow-500 text-yellow-200',
    };
    panel.className = styles[kind] || styles.hidden;
    panel.replaceChildren();
    (lines || []).forEach(t => { const d = document.createElement('div'); d.textContent = t; panel.appendChild(d); });
    if (thumb) {
        const img = document.createElement('img');
        img.src = thumb; img.alt = 'ID preview';
        img.className = 'mt-3 rounded-lg max-h-40 object-contain bg-black/30';
        panel.appendChild(img);
    }
}

async function rgRunIdAnalysis() {
    const fileInput = document.getElementById('document_upload');
    const typeSel = document.querySelector('#signupForm select[name="id_type"]');
    const hidden = document.getElementById('document_photo_base64');
    const metaInput = document.getElementById('id_check_input');
    const nameInput = document.querySelector('#signupForm input[name="full_name"]');
    const nextButton = document.getElementById('nextBtn');
    const file = fileInput && fileInput.files[0];
    const run = ++idState.run;

    idState.ok = false;
    hidden.value = ''; metaInput.value = '';
    if (!file) { idState.busy = false; nextButton.disabled = false; rgIdPanel('hidden'); return; }
    if (!typeSel.value) {
        fileInput.value = '';
        rgIdPanel('fail', ['Piliin muna ang uri ng ID, tapos i-upload ang larawan.']);
        return;
    }

    idState.busy = true; nextButton.disabled = true;
    rgIdPanel('busy', ['Sinusuri ang larawan...']);
    try {
        const { dataUri, meta } = await rgAnalyzeId(file, typeSel.value, nameInput ? nameInput.value : '',
            msg => { if (run === idState.run) rgIdPanel('busy', [msg]); });
        if (run !== idState.run) return;               // a newer pick replaced this one
        hidden.value = dataUri;
        metaInput.value = JSON.stringify(meta);
        idState.ok = true;
        if (meta.status === 'unverified') {
            rgIdPanel('warn', ['Natanggap ang larawan, pero hindi na-run ang awtomatikong pagsusuri. Ang Admin na ang titingin.'], dataUri);
        } else if (meta.type_keyword_match === false) {
            const lbl = (RG_ID_RULES[typeSel.value] || {}).label || 'ID';
            rgIdPanel('warn', ['Natanggap ang larawan, pero hindi namin makumpirma na ito ay ' + lbl + '. Titingnan ito ng Admin.'], dataUri);
        } else if (meta.name_match === 0) {
            rgIdPanel('warn', ['Maayos ang larawan, pero hindi namin makita ang pangalan mo sa ID. Titingnan ito ng Admin.'], dataUri);
        } else {
            rgIdPanel('pass', ['✓ Malinaw ang larawan at tugma sa napiling uri ng ID.'], dataUri);
        }
    } catch (e) {
        if (run !== idState.run) return;
        console.warn('[ID check] rejected:', e && e.message);
        fileInput.value = '';                            // force a new pick
        rgIdPanel('fail', [e instanceof RgReject ? e.message : 'Hindi masuri ang larawan. Subukan ulit.']);
    } finally {
        if (run === idState.run) { idState.busy = false; nextButton.disabled = false; }
    }
}

// Called by validateStepInput() on the Verification step.
function rgIdGate() {
    const fileInput = document.getElementById('document_upload');
    if (idState.busy) { rgIdPanel('busy', ['Hintayin muna matapos ang pagsusuri ng larawan...']); return false; }
    if (!idState.ok || !document.getElementById('document_photo_base64').value) {
        rgIdPanel('fail', ['Mag-upload ng malinaw na larawan ng ID na pumasa sa pagsusuri.']);
        if (fileInput) fileInput.focus();
        return false;
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('document_upload');
    const typeSel = document.querySelector('#signupForm select[name="id_type"]');
    if (fileInput) fileInput.addEventListener('change', rgRunIdAnalysis);
    // The checks depend on the ID type, so a new type re-checks the same photo.
    if (typeSel) typeSel.addEventListener('change', () => { if (fileInput && fileInput.files[0]) rgRunIdAnalysis(); });

    // confirm-password eye icon
    const t = document.getElementById('toggleSignupPasswordConfirm');
    const i = document.getElementById('signup_password_confirm_input');
    if (t && i) t.addEventListener('click', function () {
        i.type = i.type === 'password' ? 'text' : 'password';
        const ic = this.querySelector('i'); ic.classList.toggle('fa-eye'); ic.classList.toggle('fa-eye-slash');
    });
});

// ============================================================
// LOGIN LOCKOUT: 5 wrong passwords in a row -> 15 second block
// The server is the real lock (AuthController). This part shows the
// popup + countdown and remembers it if the page is refreshed.
// ============================================================
window.RGLock = (function () {
    const MAX = 5, DEFAULT_SECONDS = 15;
    const LOCK_KEY = 'rg_login_lock_until', FAIL_KEY = 'rg_offline_fails';
    let timer = null, total = DEFAULT_SECONDS;

    const $ = id => document.getElementById(id);
    const lockUntil = () => { try { return parseInt(localStorage.getItem(LOCK_KEY) || '0', 10) || 0; } catch (e) { return 0; } };
    const isLocked = () => lockUntil() > Date.now();

    function setFormDisabled(disabled) {
        ['email_input', 'password_input'].forEach(id => { const el = $(id); if (el) el.disabled = disabled; });
        const btn = $('loginSubmitBtn');
        if (btn) {
            btn.disabled = disabled;
            btn.classList.toggle('opacity-50', disabled);
            btn.classList.toggle('cursor-not-allowed', disabled);
            if (!disabled) btn.innerText = 'Mag-login';
        }
    }

    function render() {
        const left = Math.ceil((lockUntil() - Date.now()) / 1000);
        if (left <= 0) return end();
        const sec = $('lockoutSeconds'), big = $('lockoutBig'), ring = $('lockoutRing'), btn = $('loginSubmitBtn');
        if (sec) sec.textContent = left;
        if (big) big.textContent = left;
        if (ring) { const c = 2 * Math.PI * 52; ring.style.strokeDasharray = c; ring.style.strokeDashoffset = c * (1 - left / total); }
        if (btn) btn.innerText = `Naka-lock (${left}s)`;
    }

    function begin() {
        clearInterval(timer);
        const modal = $('lockoutModal');
        setFormDisabled(true);
        const pw = $('password_input'); if (pw) pw.value = '';
        if (modal) modal.classList.remove('hidden');
        render();
        timer = setInterval(render, 250);
    }

    function end() {
        clearInterval(timer);
        try { localStorage.removeItem(LOCK_KEY); } catch (e) {}
        const modal = $('lockoutModal'); if (modal) modal.classList.add('hidden');
        setFormDisabled(false);
        const banner = $('serverErrorBanner'); if (banner) banner.classList.add('hidden');
        const box = $('offlineLoginMsg');
        if (box) {
            box.textContent = 'Maaari ka nang mag-login ulit.';
            box.classList.remove('hidden', 'bg-red-700'); box.classList.add('bg-emerald-700');
            setTimeout(() => box.classList.add('hidden'), 4000);
        }
        const pw = $('password_input'); if (pw) pw.focus();
    }

    function start(seconds) {
        total = Math.max(1, Math.ceil(seconds || DEFAULT_SECONDS));
        try { localStorage.setItem(LOCK_KEY, String(Date.now() + total * 1000)); } catch (e) {}
        begin();
    }

    // failed OFFLINE attempts are counted here (the server cannot see them)
    function offlineFail(email) {
        let all = {}; try { all = JSON.parse(localStorage.getItem(FAIL_KEY) || '{}'); } catch (e) {}
        const k = (email || '').toLowerCase();
        all[k] = (all[k] || 0) + 1;
        const n = all[k];
        if (n >= MAX) delete all[k];
        try { localStorage.setItem(FAIL_KEY, JSON.stringify(all)); } catch (e) {}
        return n;
    }
    function clearOffline(email) {
        try { const all = JSON.parse(localStorage.getItem(FAIL_KEY) || '{}'); delete all[(email || '').toLowerCase()]; localStorage.setItem(FAIL_KEY, JSON.stringify(all)); } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', function () {
        const fromServer = parseInt(window.__RG_SERVER_LOCK || 0, 10);
        if (fromServer > 0) start(fromServer);
        else if (isLocked()) { total = DEFAULT_SECONDS; begin(); }
    });

    return { start, isLocked, offlineFail, clearOffline, MAX };
})();

</script>


<script>
// --- Image to Base64 Logic with Compression (Synced with Farmer Detection) ---
    async function compressImageFile(file) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const objectUrl = URL.createObjectURL(file);
            
            img.onload = () => {
                URL.revokeObjectURL(objectUrl);
                const canvas = document.createElement('canvas');
                
                // Matches the detection side settings
                const MAX_WIDTH = 512; 
                const scale = Math.min(MAX_WIDTH / img.width, 1); 
                
                canvas.width = img.width * scale;
                canvas.height = img.height * scale;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                // Compress image to JPEG at 60% quality
                resolve(canvas.toDataURL('image/jpeg', 0.6)); 
            };
            img.onerror = (err) => reject(err);
            img.src = objectUrl;
        });
    }

    function setupBase64Conversion(fileInputId, hiddenInputId) {
        const fileInput = document.getElementById(fileInputId);
        if (fileInput) {
            fileInput.addEventListener('change', async function() {
                const file = this.files[0];
                if (file) {
                    try {
                        const compressedBase64 = await compressImageFile(file);
                        document.getElementById(hiddenInputId).value = compressedBase64;
                    } catch (error) {
                        console.error("Compression error:", error);
                    }
                }
            });
        }
    }
    
    // 'document_upload' is handled by the ID photo check (rgRunIdAnalysis)
    setupBase64Conversion('selfie_upload', 'selfie_photo_base64');

    // --- Multi-Step Form Logic ---
    const formSteps = document.querySelectorAll(".form-step");
    const nextBtn = document.getElementById("nextBtn");
    const backBtn = document.getElementById("backBtn");
    const submitBtn = document.getElementById("submitBtn");
    const stepIndicator = document.getElementById("step-indicator");
    let currentStep = 0;

function updateFormDisplay() {
        formSteps.forEach((step, index) => {
            step.classList.toggle("hidden", index !== currentStep);
            step.classList.toggle("active", index === currentStep);
        });

        stepIndicator.textContent = `Step ${currentStep + 1} of ${formSteps.length}`;
        backBtn.classList.toggle("hidden", currentStep === 0);

        // Hide "Bumalik sa Login" if proceeding past Step 1
        const backToLoginContainer = document.getElementById("backToLoginContainer");
        if (backToLoginContainer) {
            backToLoginContainer.classList.toggle("hidden", currentStep > 0);
        }

        if (currentStep === formSteps.length - 1) {
            nextBtn.classList.add("hidden");
            submitBtn.classList.remove("hidden");
        } else {
            nextBtn.classList.remove("hidden");
            submitBtn.classList.add("hidden");
        }
    }

    function validateStepInput() {
        const step = formSteps[currentStep];
        const inputs = step.querySelectorAll("input[required], select[required], input[data-validate]");
        for (const input of inputs) {
            if (input.type === 'file') continue; // the ID photo is checked by rgIdGate() below
            input.setCustomValidity('');
            const rule = RG_VALIDATORS[input.dataset.validate || input.name];
            if (rule) {
                const msg = rule(input.value);
                if (msg) input.setCustomValidity(msg);
            }
            if (!input.checkValidity()) { input.reportValidity(); return false; }
        }

        const pw = step.querySelector('input[name="password"]');
        const confirm = step.querySelector('input[name="password_confirmation"]');
        if (pw && confirm) {
            confirm.setCustomValidity(confirm.value === pw.value ? '' : 'Hindi magkapareho ang password.');
            if (!confirm.checkValidity()) { confirm.reportValidity(); return false; }
        }

        if (step.querySelector('#document_upload') && !rgIdGate()) return false;

        if (currentStep === formSteps.length - 1 && !document.getElementById('document_photo_base64').value) {
            alert('Mag-upload muna ng larawan ng ID sa Step 4.');
            return false;
        }
        return true;
    }

// Replace your existing nextBtn click listener with this one
    if(nextBtn && backBtn) {
        nextBtn.addEventListener("click", () => {
            if (validateStepInput()) {
                currentStep++;
                updateFormDisplay();
                
                // FIX: If user just entered Step 3 (index 2), recalculate map size
                if (currentStep === 2 && typeof regMap !== 'undefined') {
                    setTimeout(() => {
                        regMap.invalidateSize();
                        if (typeof regMarker !== 'undefined' && regMarker) {
                            regMap.setView(regMarker.getLatLng(), regMap.getZoom());
                        }
                    }, 200);
                }
            }
        });
        backBtn.addEventListener("click", () => {
            currentStep--;
            updateFormDisplay();
        });
    }

// --- SILENT Location Trigger on Final Submit (Mobile Optimized) ---
    const signupForm = document.getElementById('signupForm');
    
    if (submitBtn) {
        submitBtn.addEventListener("click", function(e) {
            e.preventDefault(); 
            
            // 1. Check if the form is valid first
            if (!validateStepInput()) return; 

            // 2. NEW FIX: Disable the button immediately to prevent double-clicks
            submitBtn.disabled = true;
            submitBtn.innerText = "Processing..."; 
            // Optional: If you use Tailwind CSS, this makes the button look disabled
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed'); 

            // 3. If we successfully pre-fetched it, submit immediately
            if (document.getElementById('device-lat').value) {
                if (typeof signupForm.requestSubmit === 'function') {
                    signupForm.requestSubmit();
                } else {
                    signupForm.submit();
                }
                return;
            }

            // 4. Fallback: Request location (Mobile friendly settings)
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        // Success! Save real device location
                        document.getElementById('device-lat').value = position.coords.latitude;
                        document.getElementById('device-lng').value = position.coords.longitude;
                        
                        if (typeof signupForm.requestSubmit === 'function') {
                            signupForm.requestSubmit();
                        } else {
                            signupForm.submit();
                        }
                    },
                    (error) => {
                        console.warn("Location error:", error);
                        if (typeof signupForm.requestSubmit === 'function') {
                            signupForm.requestSubmit();
                        } else {
                            signupForm.submit();
                        }
                    },
                    // MOBILE FIX: enableHighAccuracy set to false prevents timeouts on phones
                    { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
                );
            } else {
                if (typeof signupForm.requestSubmit === 'function') {
                    signupForm.requestSubmit();
                } else {
                    signupForm.submit();
                }
            }
        });
    }
        
    </script>


<script>
    // Reusable Password Visibility Toggle Function
    function setupPasswordToggle(toggleId, inputId) {
        const togglePassword = document.getElementById(toggleId);
        const passwordInput = document.getElementById(inputId);

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
    }

    // Initialize toggles when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        setupPasswordToggle('togglePassword', 'password_input');       // Login Form
        setupPasswordToggle('toggleSignupPassword', 'signup_password_input'); // Signup Form
    });

// Signup Modal Functions
    function showSignupModal() {
        document.getElementById('signupModal').classList.remove('hidden');

        // MOBILE FIX: Pre-fetch location early while they are filling out the form
        if (navigator.geolocation && !document.getElementById('device-lat').value) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    document.getElementById('device-lat').value = position.coords.latitude;
                    document.getElementById('device-lng').value = position.coords.longitude;
                },
                (error) => console.warn("Early location fetch error:", error),
                { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 }
            );
        }
    }
        function hideSignupModal() {
        document.getElementById('signupModal').classList.add('hidden');
    }

    // Forgot Password Modal Functions
    function showForgotModal() {
        const modal = document.getElementById('forgotModal');
        const emailInput = document.getElementById('email_input');
        modal.classList.remove('hidden');
        if (emailInput && emailInput.value) {
            document.getElementById('forgot_email_input').value = emailInput.value;
        }
    }
    function hideForgotModal() {
        document.getElementById('forgotModal').classList.add('hidden');
    }

    // Logo Lightbox Functions
    function showLogoLightbox() {
        document.getElementById('logoLightbox').classList.remove('hidden');
    }
    function hideLogoLightbox() {
        document.getElementById('logoLightbox').classList.add('hidden');
    }

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideLogoLightbox();
            hideSignupModal();
            hideForgotModal();
        }
    });
    // --- Login Button Double-Click Prevention ---
    const loginForm = document.getElementById('loginForm');
    const loginSubmitBtn = document.getElementById('loginSubmitBtn');

    if (loginForm && loginSubmitBtn) {
        loginForm.addEventListener('submit', function() {
            // Only disable if the HTML5 validation passes
            if (loginForm.checkValidity()) {
                loginSubmitBtn.disabled = true;
                loginSubmitBtn.innerText = "Processing...";
                loginSubmitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    }
</script>
@endsection


<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/@turf/turf@6/turf.min.js"></script>

<script>
let regMap, regMarker, sizePolygon;

document.addEventListener('DOMContentLoaded', function() {
    // 1. MapTiler layers (Hybrid Default + Streets Fallback)
    const MAPTILER_KEY = '{{ env("MAPTILER_API_KEY", "G32f8QO7Njff9iDKvb56") }}';
    
    const hybridLayer = L.tileLayer(`https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=${MAPTILER_KEY}`, { 
        maxZoom: 19, crossOrigin: true, attribution: '&copy; MapTiler'
    });
    const streetsLayer = L.tileLayer(`https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=${MAPTILER_KEY}`, { 
        maxZoom: 19, crossOrigin: true, attribution: '&copy; MapTiler'
    });

    // Initialize Map centered on Sagay City with Hybrid as default
    regMap = L.map('registration-map', { 
        zoomControl: false,
        layers: [hybridLayer] // Hybrid is now default
    }).setView([10.8986, 123.4143], 14);
    
    // Add Layer Control Dropdown at top left
    const baseLayers = {
        "Hybrid Map": hybridLayer,
        "Original Streets": streetsLayer
    };
    L.control.layers(baseLayers, null, { position: 'topleft' }).addTo(regMap);
    L.control.zoom({ position: 'topleft' }).addTo(regMap);

    const defaultLatLng = [10.8986, 123.4143]; 
    placeMarker(defaultLatLng, "Farm Location");

    // Map Click Event to relocate pin and redraw polygon
    regMap.on('click', function(e) {
        placeMarker([e.latlng.lat, e.latlng.lng]);
        drawHectarePolygon();
    });

    // Real-time listeners
    const healthSelect = document.querySelector('select[name="crop_health"]');
    if (healthSelect) healthSelect.addEventListener('change', updatePinColor);

    const sizeInput = document.querySelector('input[name="farm_size"]');
    if (sizeInput) sizeInput.addEventListener('input', drawHectarePolygon);
});

// Generate dynamic REAL PIN shape with health color
function getHealthIcon(healthStatus) {
    let color = '#94a3b8'; // Gray (No data)
    if (healthStatus === 'Healthy') color = '#10b981';    // Green
    if (healthStatus === 'Monitoring') color = '#eab308'; // Yellow
    if (healthStatus === 'At Risk') color = '#ef4444';    // Red

    // Real Marker SVG instead of a circle
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" width="32" height="42">
        <path fill="${color}" stroke="#ffffff" stroke-width="8" d="M172.268 501.67C26.97 291.031 0 269.413 0 192 0 85.961 85.961 0 192 0s192 85.961 192 192c0 77.413-26.97 99.031-172.268 309.67-9.535 13.774-29.93 13.773-39.464 0zM192 272c44.183 0 80-35.817 80-80s-35.817-80-80-80-80 35.817-80 80 35.817 80 80 80z"/>
    </svg>`;

    return L.divIcon({
        className: 'custom-pin-icon',
        html: svg,
        iconSize: [32, 42],
        iconAnchor: [16, 42],
        popupAnchor: [0, -42]
    });
}

function updatePinColor() {
    if (regMarker) {
        const health = document.querySelector('select[name="crop_health"]').value;
        regMarker.setIcon(getHealthIcon(health));
    }
}

function placeMarker(latlng, popupText = "Farm Location") {
    if (regMarker) regMap.removeLayer(regMarker);
    
    const health = document.querySelector('select[name="crop_health"]')?.value || 'No data';
    
    regMarker = L.marker(latlng, { 
        draggable: true,
        icon: getHealthIcon(health)
    }).addTo(regMap);
    
    regMarker.bindPopup(popupText).openPopup();
    
    document.getElementById('lat-input').value = latlng[0];
    document.getElementById('lng-input').value = latlng[1];

    // Drag event updates coordinates and re-centers polygon shape
    regMarker.on('drag', function(event) {
        drawHectarePolygon(); // Update polygon in real-time while dragging
    });

    regMarker.on('dragend', function(event) {
        let position = regMarker.getLatLng();
        document.getElementById('lat-input').value = position.lat;
        document.getElementById('lng-input').value = position.lng;
        drawHectarePolygon();
    });
}

function drawHectarePolygon() {
  try {
    if (typeof turf === 'undefined') return;   // library not loaded (offline/CDN blocked): skip the shape, keep the pin
    const sizeInput = document.querySelector('input[name="farm_size"]').value;
    let hectares = parseFloat(sizeInput);
    
    if (sizePolygon) regMap.removeLayer(sizePolygon);
    if (!hectares || hectares <= 0 || !regMarker) return;

    if (hectares > 100) {
        hectares = hectares / 10000; 
    }

    const center = [regMarker.getLatLng().lng, regMarker.getLatLng().lat];
    const radiusInKilometers = Math.sqrt((hectares * 10000) / Math.PI) / 1000;
    
    const options = { steps: 64, units: 'kilometers' };
    const circle = turf.circle(center, radiusInKilometers, options);
    const healthColor = getHealthIcon(document.querySelector('select[name="crop_health"]')?.value).options.html.match(/fill="(#[a-zA-Z0-9]+)"/)[1];

    sizePolygon = L.geoJSON(circle, {
        style: {
            color: healthColor || '#10b981',
            weight: 2,
            fillOpacity: 0.25
        }
    }).addTo(regMap);
  } catch (err) {
    console.warn('Could not draw farm-size shape:', err);
  }
}

// ---- Dropdown -> map: pin the selected barangay automatically ----
let pinRequestId = 0;

function setMapStatus(msg, isWarn) {
    const el = document.getElementById('map-status');
    if (!el) return;
    el.textContent = msg || '';
    el.className = 'text-xs mb-2 min-h-[1rem] ' + (isWarn ? 'text-amber-400' : 'text-emerald-400');
}

function geocodeOnce(query) {
    return fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&countrycodes=ph&q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => (data && data.length > 0)
            ? { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon) }
            : null);
}

// Called when the Barangay dropdown changes (see the dropdown script below).
// Tries "barangay, city, province" first, then falls back to "city, province".
function pinFromDropdowns(barangay, city, province) {
    if (!regMap || !barangay) return;
    const myRequest = ++pinRequestId;   // ignore stale answers if the dropdown changes quickly
    setMapStatus('Hinahanap ang ' + barangay + '...');

    const attempts = [
        { q: `${barangay}, ${city}, ${province}, Philippines`, zoom: 16, label: barangay, exact: true },
        { q: `${city}, ${province}, Philippines`,              zoom: 14, label: city,     exact: false },
    ];

    (async () => {
        for (const a of attempts) {
            try {
                const hit = await geocodeOnce(a.q);
                if (myRequest !== pinRequestId) return;
                if (hit) {
                    const latlng = [hit.lat, hit.lng];
                    regMap.setView(latlng, a.zoom);
                    placeMarker(latlng, 'Farm Location');
                    drawHectarePolygon();
                    setMapStatus(a.exact
                        ? 'Nailagay ang pin sa ' + a.label + '. I-drag ang pin sa eksaktong bukid mo.'
                        : 'Hindi makita ang barangay sa mapa, kaya sa ' + a.label + ' muna inilagay ang pin. I-drag ito sa bukid mo.',
                        !a.exact);
                    return;
                }
            } catch (err) {
                console.warn('Geocode failed:', err);
            }
        }
        if (myRequest === pinRequestId) {
            setMapStatus('Hindi makita sa mapa. I-tap ang mapa o i-drag ang pin sa bukid mo.', true);
        }
    })();
}

document.addEventListener('DOMContentLoaded', function () {
    const BASE_URL = "{{ url('/') }}"; // resolves correctly in artisan serve AND xampp subfolders

    const provinceSelect = document.getElementById('province-select');
    const citySelect = document.getElementById('city-select');
    const barangaySelect = document.getElementById('barangay-select');

    const provinceHidden = document.getElementById('province-hidden');
    const cityHidden = document.getElementById('city-hidden');
    const barangayHidden = document.getElementById('barangay-hidden');

    const provinceIdHidden = document.getElementById('province-id-hidden');
    const cityIdHidden = document.getElementById('city-id-hidden');
    const barangayIdHidden = document.getElementById('barangay-id-hidden');

    if (!provinceSelect) return;

    fetch(`${BASE_URL}/locations/provinces`)
        .then(res => res.json())
        .then(provinces => {
            provinces.forEach(p => {
                provinceSelect.insertAdjacentHTML('beforeend', `<option value="${p.id}">${p.name}</option>`);
            });
        });

    provinceSelect.addEventListener('change', function () {
        // store the selected NAME (for display) AND the ID (required by backend validation)
        provinceHidden.value = this.options[this.selectedIndex].text;
        provinceIdHidden.value = this.value;
        cityHidden.value = '';
        barangayHidden.value = '';
        cityIdHidden.value = '';
        barangayIdHidden.value = '';

        citySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        barangaySelect.innerHTML = '<option value="" disabled selected>Select city first...</option>';
        citySelect.disabled = true;
        barangaySelect.disabled = true;
        if (!this.value) return;

        fetch(`${BASE_URL}/locations/cities/${this.value}`)
            .then(res => res.json())
            .then(cities => {
                citySelect.innerHTML = '<option value="" disabled selected>Select city/municipality...</option>';
                cities.forEach(c => {
                    citySelect.insertAdjacentHTML('beforeend', `<option value="${c.id}">${c.name}</option>`);
                });
                citySelect.disabled = false;
            });
    });

    citySelect.addEventListener('change', function () {
        cityHidden.value = this.options[this.selectedIndex].text;
        cityIdHidden.value = this.value;
        barangayHidden.value = '';
        barangayIdHidden.value = '';

        barangaySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        barangaySelect.disabled = true;
        if (!this.value) return;

        fetch(`${BASE_URL}/locations/barangays/${this.value}`)
            .then(res => res.json())
            .then(barangays => {
                barangaySelect.innerHTML = '<option value="" disabled selected>Select barangay...</option>';
                barangays.forEach(b => {
                    barangaySelect.insertAdjacentHTML('beforeend', `<option value="${b.id}">${b.name}</option>`);
                });
                barangaySelect.disabled = false;
            });
    });

    barangaySelect.addEventListener('change', function () {
        barangayHidden.value = this.options[this.selectedIndex].text;
        barangayIdHidden.value = this.value;

        // Auto-pin the map to the chosen barangay
        pinFromDropdowns(
            this.options[this.selectedIndex].text,
            citySelect.options[citySelect.selectedIndex]?.text || '',
            provinceSelect.options[provinceSelect.selectedIndex]?.text || ''
        );
    });
});
</script>


<script>
    // Automatically force a fresh page reload if loaded from browser cache (BFCache)
    window.addEventListener('pageshow', function (event) {
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });
</script>

<script>
    window.addEventListener('pageshow', function (event) {
        // If the page was loaded from the browser cache (like hitting the back arrow)
        if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
            // Force a hard reload from the server
            window.location.reload();
        }
    });
</script>

<script>
// ============================================================
// OFFLINE LOGIN
// ------------------------------------------------------------
// The first time someone logs in on THIS device while online, we cache
// (in IndexedDB, not localStorage — survives longer, and works from the
// service worker's origin too) a SHA-256 hash of their password plus
// their role/redirect/name. We never store the raw password.
//
// Next time they open this same cached login page with no connection,
// the exact same form re-hashes what they type and compares it to what
// was saved. A match logs them straight into whichever dashboard URL
// they landed on last time — which sw.js has already cached from that
// earlier successful visit (see the 'navigate' handler in sw.js: every
// page it fetches successfully while online gets cached, so the
// dashboard HTML is already sitting there offline).
//
// This is a convenience layer, not real authentication: there's no
// server round-trip while offline, so it only proves "this device has
// seen this email+password combination succeed once before." Treat it
// the same way you'd treat any other locally-cached PWA session.
// ============================================================
(function () {
  // exists. Wait for the DOM so the form is really found.
  function init() {
    const loginForm = document.getElementById('loginForm');
    if (!loginForm) return;

    const submitBtn   = document.getElementById('loginSubmitBtn');
    const emailInput  = document.getElementById('email_input');
    const passInput   = document.getElementById('password_input');
    const msgBox      = document.getElementById('offlineLoginMsg');

    const DB_NAME  = 'riceguard_offline_auth';
    const STORE    = 'credentials';
    const SALT_KEY = 'rg_offline_salt';

    function openDb() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open(DB_NAME, 1);
            req.onupgradeneeded = () => {
                const db = req.result;
                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'email' });
                }
            };
            req.onsuccess = () => resolve(req.result);
            req.onerror = () => reject(req.error);
        });
    }

    // Per-device salt so the stored hash isn't just a bare SHA-256(password)
    // that would match across every device. Generated once, kept in
    // localStorage (fine to lose — worst case, a re-login online restores it).
    function getSalt() {
        let salt = localStorage.getItem(SALT_KEY);
        if (!salt) {
            salt = crypto.getRandomValues(new Uint8Array(16)).join('-');
            localStorage.setItem(SALT_KEY, salt);
        }
        return salt;
    }

    async function hashPassword(email, password) {
        const data = new TextEncoder().encode(getSalt() + ':' + email.toLowerCase() + ':' + password);
        const digest = await crypto.subtle.digest('SHA-256', data);
        return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
    }

    async function cacheForOffline(email, password, { redirect, role, full_name }) {
        try {
            const passwordHash = await hashPassword(email, password);
            const db = await openDb();
            db.transaction(STORE, 'readwrite').objectStore(STORE).put({
                email: email.toLowerCase(),
                passwordHash,
                redirect,
                role,
                full_name,
                savedAt: Date.now(),
            });
        } catch (e) {
            console.warn('[Offline Login] could not cache this login for offline use:', e);
        }
    }

    async function attemptOfflineLogin(email, password) {
        try {
            const passwordHash = await hashPassword(email, password);
            const db = await openDb();
            return await new Promise((resolve) => {
                const req = db.transaction(STORE, 'readonly').objectStore(STORE).get(email.toLowerCase());
                req.onsuccess = () => {
                    const row = req.result;
                    resolve(row && row.passwordHash === passwordHash ? row : null);
                };
                req.onerror = () => resolve(null);
            });
        } catch (e) {
            return null;
        }
    }

    function showMsg(text, ok) {
        msgBox.textContent = text;
        msgBox.classList.remove('hidden', 'bg-emerald-700', 'bg-red-700');
        msgBox.classList.add(ok ? 'bg-emerald-700' : 'bg-red-700');
    }

    // Puts the Login button back to normal. The separate double-click
    // listener (further up) sets it to "Processing..." on every submit, so a
    // failed attempt has to restore the text too, not just re-enable it.
    function resetBtn() {
        if (window.RGLock && window.RGLock.isLocked()) return; // stays blocked while locked
        submitBtn.disabled = false;
        submitBtn.innerText = 'Mag-login';
        submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    // Flag read by public/js/session_guard.js: "this page was opened through
    // OFFLINE login, so there is no real server session yet".
    const OFFLINE_SESSION_FLAG = 'rg_offline_session';

    // The app can live in a sub-folder (http://localhost/Crop-proj/public). A bare
    // root path such as "/admin/dashboard" ignores that folder and lands on
    // http://localhost/admin/dashboard (404). The server now sends a full URL,
    // but a login cached on this device by an older version may still hold a bare
    // path — so any root-relative redirect is prefixed with the app's base URL.
    const RG_BASE_URL = "{{ url('/') }}";
    function rgFixRedirect(url) {
        try {
            if (typeof url === 'string' && url.startsWith('/') && !url.startsWith('//')) {
                const basePath = new URL(RG_BASE_URL, window.location.origin).pathname.replace(/\/$/, '');
                if (basePath && !url.startsWith(basePath + '/')) {
                    return RG_BASE_URL.replace(/\/$/, '') + url;
                }
            }
        } catch (e) {}
        return url;
    }

    async function goOffline(email, password) {
        const row = await attemptOfflineLogin(email, password);
        if (row) {
            if (window.RGLock) window.RGLock.clearOffline(email);
            try { localStorage.setItem(OFFLINE_SESSION_FLAG, '1'); } catch (e) {}
            showMsg('Naka-offline login gamit ang naka-cache na account. Nagbubukas...', true);
            setTimeout(() => { window.location.href = rgFixRedirect(row.redirect); }, 350);
        } else {
            // 5 wrong offline attempts in a row lock the form too
            const fails = window.RGLock ? window.RGLock.offlineFail(email) : 0;
            if (window.RGLock && fails >= window.RGLock.MAX) {
                resetBtn();
                window.RGLock.start(15);
                return;
            }
            showMsg('Walang naka-cache na account na tugma dito sa device na ito. Kailangan mo munang mag-login nang isang beses habang online.', false);
            resetBtn();
        }
    }

    // Sent here by session_guard.js when the server session had expired.
    if (new URLSearchParams(window.location.search).get('expired')) {
        showMsg('Nag-expire ang iyong session. Paki-login ulit.', false);
    }

    loginForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        const email = emailInput.value.trim();
        const password = passInput.value;
        submitBtn.disabled = true;

        // No connection at all: don't even try the network, go straight
        // to the offline check.
        if (!navigator.onLine) {
            await goOffline(email, password);
            return;
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 8000);

            const res = await fetch(loginForm.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: new FormData(loginForm),
                signal: controller.signal,
            });
            clearTimeout(timeoutId);

            let data;
            try { data = await res.json(); }
            catch (parseErr) { if (res.status === 429) data = { locked: true }; else throw parseErr; }

            // Server says: too many wrong attempts -> show the countdown popup.
            if (res.status === 429 || data.locked) {
                const wait = parseInt(data.retry_after || res.headers.get('Retry-After') || '15', 10) || 15;
                resetBtn();
                if (window.RGLock) window.RGLock.start(wait);
                return;
            }

            if (data.success) {
                if (window.RGLock) window.RGLock.clearOffline(email);
                // Cache this device's credentials for next time, then go.
                await cacheForOffline(email, password, {
                    redirect: data.redirect,
                    role: data.role,
                    full_name: data.full_name,
                });
                // A real server login happened, so any "offline session" marker is stale.
                try { localStorage.removeItem(OFFLINE_SESSION_FLAG); } catch (e) {}
                window.location.href = rgFixRedirect(data.redirect);
            } else {
                showMsg(data.message || 'Mali ang email o password.', false);
                resetBtn();
            }
        } catch (err) {
            // fetch() itself failed — no real connectivity even though
            // navigator.onLine said yes (flaky wifi, server unreachable,
            // timed out, etc). Fall back to the offline check.
            await goOffline(email, password);
        }
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
</script>