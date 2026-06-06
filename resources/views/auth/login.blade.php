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

<div id="signupModal" class="hidden fixed inset-0 bg-black/80 flex items-center justify-center z-50 px-4">
    <div class="bg-[#1e293b] rounded-3xl p-8 w-full max-w-md">
        <h2 class="text-2xl font-bold text-center mb-6">Mag-sign Up bilang Farmer</h2>

        <form method="POST" action="{{ route('login.post') }}" id="signupForm">
            @csrf
            <input type="hidden" name="action" value="signup">

            <div class="mb-4">
                <label class="block text-zinc-400 text-sm mb-2">Buong Pangalan</label>
                <input type="text" name="full_name" required 
                       class="w-full p-4 rounded-2xl bg-zinc-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div class="mb-4">
                <label class="block text-zinc-400 text-sm mb-2">Email Address</label>
                <input type="email" name="email" required 
                       class="w-full p-4 rounded-2xl bg-zinc-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div class="mb-6">
                <label class="block text-zinc-400 text-sm mb-2">Password</label>
                <input type="password" name="password" required 
                       class="w-full p-4 rounded-2xl bg-zinc-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <button type="submit" 
                    class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 rounded-3xl font-semibold transition-all">
                Mag-sign Up
            </button>
        </form>

        <button onclick="hideSignupModal()" 
                class="w-full mt-4 py-4 bg-zinc-700 hover:bg-zinc-600 rounded-3xl text-zinc-400 transition-colors">
            Cancel
        </button>
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
        <div class="relative w-24 h-24 mx-auto mb-6">
            <div class="absolute inset-0 border-4 border-zinc-700 rounded-full"></div>
            <div class="absolute inset-0 border-4 border-emerald-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <h3 class="text-xl font-semibold text-emerald-400">Naghihintay ng Approval</h3>
        <p class="text-zinc-400 mt-2">Mangyaring maghintay habang inaaprubahan ng Admin ang iyong account.</p>
        <p class="text-sm text-zinc-500 mt-6">Email: {{ session('pending') }}</p>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    // Password Visibility Toggle
    document.addEventListener('DOMContentLoaded', function() {
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password_input');

        if (togglePassword && passwordInput) {
            togglePassword.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        }
    });

    // Signup Modal
    function showSignupModal() {
        document.getElementById('signupModal').classList.remove('hidden');
    }
    function hideSignupModal() {
        document.getElementById('signupModal').classList.add('hidden');
    }

    // Forgot Password Modal
    function showForgotModal() {
        const modal = document.getElementById('forgotModal');
        const emailInput = document.getElementById('email_input');
        modal.classList.remove('hidden');
        
        // Auto-fill email if they already typed it in the login form
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