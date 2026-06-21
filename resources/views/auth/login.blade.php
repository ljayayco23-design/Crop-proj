@extends('layouts.app')

@section('title', 'CROPSENSE AI • Login')

@section('content')
<div class="min-h-screen bg-[#0f172a] flex items-center justify-center p-4">
    <div class="bg-[#1e293b] rounded-3xl p-8 w-full max-w-md">

        <div class="text-center mb-8">
            <i class="fas fa-shield-alt text-5xl text-emerald-400 mb-4"></i>
            <h1 class="text-3xl font-bold">CROPSENSE AI</h1>
            <p class="text-zinc-400">Login Portal</p>
        </div>

        @if (session('error'))
            <div class="mb-6 p-4 bg-red-700 rounded-2xl text-center">{{ session('error') }}</div>
        @endif

        @if (session('success'))
            <div class="mb-6 p-4 bg-emerald-700 rounded-2xl text-center">{{ session('success') }}</div>
        @endif

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

            <button type="submit" 
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

<div id="signupModal" class="hidden fixed inset-0 bg-black/90 flex items-center justify-center z-50 px-4 overflow-y-auto py-10">
    <div class="bg-[#1e293b] rounded-3xl p-8 w-full max-w-lg shadow-2xl relative my-auto">
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
                    <label class="block text-zinc-400 text-sm mb-2">Address</label>
                    <input type="text" name="address"class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700" placeholder="Enter your full address" required>
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
                <h3 class="text-lg font-semibold text-emerald-400 border-b border-zinc-700 pb-2 mb-4">3. Field Details</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-zinc-400 text-sm mb-2">Farm Name</label>
                            <input type="text" name="farm_name" placeholder="e.g., San Jose Farmland" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                        </div>

                        <div>
                            <label class="block text-zinc-400 text-sm mb-1">Rice Field Location</label>
                            <small class="text-zinc-500 text-xs block mb-2">Search for your barangay or specific location</small>
                            <div class="flex gap-2">
                                <input type="text" id="location-search" placeholder="Search location..." class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                                <button type="button" onclick="searchLocation()" class="px-4 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold transition-all">Search</button>
                            </div>
                            <input type="hidden" name="latitude" id="lat-input" required>
                            <input type="hidden" name="longitude" id="lng-input" required>
                            <input type="hidden" name="device_latitude" id="device-lat">
                            <input type="hidden" name="device_longitude" id="device-lng">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-zinc-400 text-sm mb-2">Size (Hectares)</label>
                                <input type="number" name="farm_size" step="0.1" min="0" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                            </div>
                            <div>
                                <label class="block text-zinc-400 text-sm mb-2">Water Source</label>
                                <select name="water_source" required class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
                                    <option value="" disabled selected>Select...</option>
                                    <option value="irrigated">Irrigated</option>
                                    <option value="rainfed">Rainfed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="h-[350px] md:h-full min-h-[300px]">
                        <div id="registration-map" class="w-full h-full rounded-xl border border-zinc-700 z-10" style="min-height: 300px;"></div>
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
                    <label class="block text-zinc-400 text-sm mb-2">Password</label>
                    <input type="password" name="password" required minlength="6" class="w-full p-3 rounded-xl bg-zinc-800 text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none border border-zinc-700">
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


<div id="forgotModal" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50 px-4">
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
        let email = "{{ session('pending') }}";
        
        let interval = setInterval(() => {
            let checkUrl = `{{ url('/check-status') }}?email=${encodeURIComponent(email)}&_t=${Date.now()}`;
            
            fetch(checkUrl)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'approved') {
                        clearInterval(interval);
                        
                        document.getElementById('pending-spinner').style.display = 'none';
                        document.getElementById('pending-title').textContent = 'Na-aprubahan na!';
                        document.getElementById('pending-title').className = 'text-xl font-semibold text-emerald-400';
                        document.getElementById('pending-desc').textContent = 'Maaari ka nang mag-login. Nire-redirect...';
                        
                        setTimeout(() => {
                            window.location.href = "{{ route('login') }}"; 
                        }, 2000);
                        
                    } else if (data.status === 'declined') {
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
                .catch(error => console.error('Error checking status:', error));
        }, 3000); 
    });
</script>
@endif

@endsection

@section('scripts')


<script>
    // --- Image to Base64 Logic ---
    function setupBase64Conversion(fileInputId, hiddenInputId) {
        const fileInput = document.getElementById(fileInputId);
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onloadend = function() {
                        document.getElementById(hiddenInputId).value = reader.result;
                    };
                    reader.readAsDataURL(file);
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

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideSignupModal();
            hideForgotModal();
        }
    });
</script>
@endsection


<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
let regMap, regMarker;

document.addEventListener('DOMContentLoaded', function() {
    // 1. Initialize Map
    const MAPTILER_KEY = '{{ env("MAPTILER_API_KEY", "G32f8QO7Njff9iDKvb56") }}';
    regMap = L.map('registration-map', { zoomControl: false }).setView([10.8986, 123.4143], 13); // Default: Sagay
    L.tileLayer(`https://api.maptiler.com/maps/hybrid/{z}/{x}/{y}.jpg?key=${MAPTILER_KEY}`, { maxZoom: 19, crossOrigin: true }).addTo(regMap);
    L.control.zoom({ position: 'topleft' }).addTo(regMap);

    const defaultLatLng = [10.8986, 123.4143]; // Sagay coordinates

    // 2. Capture Real Device Location (With High Accuracy and Fallbacks)
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition((position) => {
            document.getElementById('device-lat').value = position.coords.latitude;
            document.getElementById('device-lng').value = position.coords.longitude;
            
            let latlng = [position.coords.latitude, position.coords.longitude];
            regMap.setView(latlng, 16);
            placeMarker(latlng, "Your Current Location");
            reverseGeocode(position.coords.latitude, position.coords.longitude); // Auto-fill the search bar
            
        }, (error) => {
            console.warn("Geolocation access denied or inaccurate. Defaulting to Sagay.");
            // Fallback: Drop a pin in Sagay if user denies location or it fails
            regMap.setView(defaultLatLng, 13);
            placeMarker(defaultLatLng, "Default Location (Drag to change)");
            reverseGeocode(defaultLatLng[0], defaultLatLng[1]);
            
        }, {
            enableHighAccuracy: true, // Force GPS/Wifi accuracy over IP address
            timeout: 10000,
            maximumAge: 0
        });
    } else {
        // Fallback for very old browsers
        placeMarker(defaultLatLng, "Default Location");
        reverseGeocode(defaultLatLng[0], defaultLatLng[1]);
    }

    // 3. Map Click Event (Click to pin)
    regMap.on('click', function(e) {
        placeMarker([e.latlng.lat, e.latlng.lng]);
        reverseGeocode(e.latlng.lat, e.latlng.lng);
    });
});

function placeMarker(latlng, popupText = "Farm Location") {
    if (regMarker) regMap.removeLayer(regMarker);
    regMarker = L.marker(latlng, { draggable: true }).addTo(regMap);
    regMarker.bindPopup(popupText).openPopup();
    
    // Update Hidden Inputs
    document.getElementById('lat-input').value = latlng[0];
    document.getElementById('lng-input').value = latlng[1];

    // Drag event
    regMarker.on('dragend', function(event) {
        let position = regMarker.getLatLng();
        document.getElementById('lat-input').value = position.lat;
        document.getElementById('lng-input').value = position.lng;
        reverseGeocode(position.lat, position.lng);
    });
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
                placeMarker(latlng);
            } else {
                alert("Location not found.");
            }
        });
}

// Converts map pin back into text for the search bar
function reverseGeocode(lat, lng) {
    fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
        .then(res => res.json())
        .then(data => {
            if (data && data.display_name) {
                document.getElementById('location-search').value = data.display_name;
            }
        });
}
</script>