<!-- Forgot Password Modal -->
<div id="forgotModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50 px-4">
    <div class="bg-[#1e293b] rounded-3xl p-8 w-full max-w-md">
        <h2 class="text-2xl font-bold text-center mb-6">Nakalimutan ang Password</h2>
        
        <form method="POST" action="{{ route('forgot.password') }}">
            @csrf
            <input type="hidden" name="role" value="farmer">

            <div class="mb-6">
                <label class="block text-zinc-400 text-sm mb-2">Email Address</label>
                <input type="email" name="email" id="forgot_email_input" 
                       class="w-full p-4 rounded-2xl bg-zinc-800 focus:ring-2 focus:ring-emerald-500" 
                       required placeholder="your@email.com">
            </div>

            <button type="submit" 
                    class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 rounded-3xl font-semibold">
                Ipadala ang Bagong Password
            </button>
        </form>

        <button onclick="hideForgotModal()" 
                class="w-full mt-4 py-4 bg-zinc-700 hover:bg-zinc-600 rounded-3xl text-zinc-400 font-medium">
            Cancel
        </button>

        <p class="text-center text-xs text-zinc-500 mt-6">
            Isang secure na temporaryong password ang ipapadala sa iyong email.
        </p>
    </div>
</div>

<script>
function showForgotModal() {
    document.getElementById('forgotModal').classList.remove('hidden');
}

function hideForgotModal() {
    document.getElementById('forgotModal').classList.add('hidden');
}
</script>