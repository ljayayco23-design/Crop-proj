// public/js/session_guard.js
// Load on every logged-in page (put it in layouts/app.blade.php before </body>).
//
// Problem it solves: "offline login" opens the cached dashboard WITHOUT a real
// Laravel session. When the internet comes back, every server call answers
// "Unauthenticated" (401). This script notices that and sends the person to
// the login page with a clear message, instead of showing a raw error.
(function () {
    const FLAG = 'rg_offline_session';
    const path = location.pathname;

    // Never run on the login pages (would cause a redirect loop).
    if (path === '/login' || path.startsWith('/admin/login')) return;

    function toLogin() {
        try { localStorage.removeItem(FLAG); } catch (e) {}
        location.replace('/login?expired=1');
    }

    // 1) Any fetch() that comes back 401, or that Laravel redirected to /login,
    //    means the server session is gone.
    const realFetch = window.fetch.bind(window);
    window.fetch = async function (input, init) {
        const res = await realFetch(input, init);
        try {
            const target = new URL(typeof input === 'string' ? input : input.url, location.href);
            if (target.origin === location.origin && navigator.onLine) {
                const redirectedToLogin = res.redirected && new URL(res.url).pathname === '/login';
                if (res.status === 401 || redirectedToLogin) toLogin();
            }
        } catch (e) {}
        return res;
    };

    // 2) If this page was opened through OFFLINE login, check the real session
    //    as soon as we are online.
    async function verifySession() {
        if (!navigator.onLine) return;
        let flagged = false;
        try { flagged = localStorage.getItem(FLAG) === '1'; } catch (e) {}
        if (!flagged) return;
        try {
            const r = await realFetch('/session-check', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            if (r.status === 401) toLogin();
            else if (r.ok) { try { localStorage.removeItem(FLAG); } catch (e) {} }
        } catch (e) { /* still offline / server unreachable: try again later */ }
    }

    window.addEventListener('online', verifySession);
    document.addEventListener('DOMContentLoaded', verifySession);
})();