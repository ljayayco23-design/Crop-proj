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
            <div class="mb-6 p-4 bg-red-700 rounded-2xl text-center">{{ session('error') }}</div>
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
                <input type="email" name="email" id="email_input" required 
                       class="w-full p-4 rounded-2xl bg-zinc-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div class="mb-4 relative">
                <label class="block text-zinc-400 text-sm mb-2">Password</label>
                <input type="password" name="password" id="password_input" required 
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

        <form method="POST" action="{{ route('login.post') }}" id="signupForm" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="action" value="signup">
            
            <input type="hidden" name="document_photo_base64" id="document_photo_base64">
            <input type="hidden" name="selfie_photo_base64" id="selfie_photo_base64">

            <div class="form-step active">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">1. Personal Identity</h3>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Full Name <span class="text-zinc-500 text-xs">(Buong Pangalan)</span></label>
                    <input type="text" name="full_name" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                </div>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Date of Birth</label>
                    <input type="date" name="dob" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                </div>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Mobile Number</label>
                    <input type="tel" name="mobile" pattern="[0-9]{11}" placeholder="09123456789" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
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
                            <input type="text" name="farm_name" placeholder="e.g., San Jose Farmland" required 
                                class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700 transition-all">
                        </div>

                        <div>
                            <label class="block text-zinc-400 text-sm mb-1 font-medium">Rice Field Location</label>
                            <small class="text-zinc-500 text-xs block mb-2">Search barangay, street, or city (Google Maps style)</small>
                            <div class="flex gap-2">
                                <input type="text" id="location-search" placeholder="Search location..." 
                                    class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                                <button type="button" onclick="searchLocation()" class="px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition-all shadow-lg">Search</button>
                            </div>
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
                                <input type="text" name="rice_variety" placeholder="e.g., IR64" class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="block text-zinc-400 text-sm mb-1 font-medium">Size (Hectares)</label>
                            <input type="number" name="farm_size" step="0.01" min="0" placeholder="e.g. 3 or 3000" required 
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
                        <p class="text-xs text-zinc-400 mt-2 text-center">Tip: Click map or drag pin to adjust location. Hectares automatically generate shape.</p>
                    </div>
                </div>
            </div>

            <div class="form-step hidden">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">4. Verification</h3>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Type of Valid ID</label>
                    <select name="id_type" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
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
                    <input type="file" id="document_upload" accept="image/*" required class="w-full p-2 rounded-xl bg-zinc-800 text-zinc-400 border border-zinc-700 cursor-pointer">
                </div>
                <!-- <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Take a Live Selfie</label>
                    <input type="file" id="selfie_upload" accept="image/*" capture="user" required class="w-full p-2 rounded-xl bg-zinc-800 text-zinc-400 border border-zinc-700 cursor-pointer">
                </div> -->
            </div>

            <div class="form-step hidden">
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">5. Security</h3>
                <div class="mb-4">
                    <label class="block text-zinc-400 text-sm mb-2">Email Address</label>
                    <input type="email" name="email" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                </div>
            <div class="mb-6">
                    <label class="block text-zinc-400 text-sm mb-2">
                        Password
                        <span class="block text-zinc-500 text-xs mt-1 leading-snug">
                            (Use at least 8 characters, including an uppercase letter, lowercase letter, number, and special character.)
                        </span>
                    </label>
                    
                    <div class="relative">
                        <input type="password" name="password" id="signup_password_input" required minlength="6" 
                            class="w-full p-3 pr-12 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-300 cursor-pointer text-lg flex items-center" id="toggleSignupPassword">
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

@endsection

@section('scripts')


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
    
    setupBase64Conversion('document_upload', 'document_photo_base64');
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
        const activeInputs = formSteps[currentStep].querySelectorAll("input[required], select[required]");
        let allValid = true;
        activeInputs.forEach(input => {
            if (!input.checkValidity()) {
                input.reportValidity();
                allValid = false;
            }
        });
        return allValid;
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
    placeMarker(defaultLatLng, "Default Farm Location");
    reverseGeocode(defaultLatLng[0], defaultLatLng[1]);

    // Map Click Event to relocate pin and redraw polygon
    regMap.on('click', function(e) {
        placeMarker([e.latlng.lat, e.latlng.lng]);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
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
        reverseGeocode(position.lat, position.lng);
        drawHectarePolygon();
    });
}

function drawHectarePolygon() {
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
}

function searchLocation() {
    let query = document.getElementById('location-search').value;
    if (!query) return;

    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                let latlng = [data[0].lat, data[0].lon];
                regMap.setView(latlng, 16);
                placeMarker(latlng, data[0].display_name);
                drawHectarePolygon();
            } else {
                alert("Location not found.");
            }
        });
}

function reverseGeocode(lat, lng) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(res => res.json())
        .then(data => {
            if (data && data.display_name) {
                document.getElementById('location-search').value = data.display_name;
                const addressInput = document.querySelector('input[name="address"]');
                if (addressInput) addressInput.value = data.display_name;
            }
        });
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

    async function goOffline(email, password) {
        const row = await attemptOfflineLogin(email, password);
        if (row) {
            showMsg('Naka-offline login gamit ang naka-cache na account. Nagbubukas...', true);
            setTimeout(() => { window.location.href = row.redirect; }, 350);
        } else {
            showMsg('Walang naka-cache na account na tugma dito sa device na ito. Kailangan mo munang mag-login nang isang beses habang online.', false);
            submitBtn.disabled = false;
        }
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

            const data = await res.json();

            if (data.success) {
                // Cache this device's credentials for next time, then go.
                await cacheForOffline(email, password, {
                    redirect: data.redirect,
                    role: data.role,
                    full_name: data.full_name,
                });
                window.location.href = data.redirect;
            } else {
                showMsg(data.message || 'Mali ang email o password.', false);
                submitBtn.disabled = false;
            }
        } catch (err) {
            // fetch() itself failed — no real connectivity even though
            // navigator.onLine said yes (flaky wifi, server unreachable,
            // timed out, etc). Fall back to the offline check.
            await goOffline(email, password);
        }
    });
})();
</script>