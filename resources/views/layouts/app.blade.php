<!DOCTYPE html>
<html lang="tl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'RICEGUARD AI')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background:#0f172a; color:white; font-family:system-ui, sans-serif; }
    </style>
    @yield('styles')
</head>
<body>
    @yield('content')
    @yield('scripts')

    {{-- Detects an expired server session (e.g. after offline login) and sends
         the person back to /login instead of showing a raw "Unauthenticated".
         Skips itself on the login pages. --}}
    <script src="{{ asset('js/session_guard.js') }}"></script>

    <script>
let openDropdownId = null;

function toggleDropdown(event, userId) {
    event.stopPropagation();
    if (openDropdownId) {
        const prev = document.getElementById('dropdown-' + openDropdownId);
        if (prev) prev.style.display = 'none';
    }
    const dropdown = document.getElementById('dropdown-' + userId);
    if (dropdown) {
        dropdown.style.display = (dropdown.style.display === 'block') ? 'none' : 'block';
        openDropdownId = (dropdown.style.display === 'block') ? userId : null;
    }
}

function editUser(id, name, email, status) {
    document.getElementById('eid').value = id;
    document.getElementById('ename').value = name;
    document.getElementById('eemail').value = email;
    document.getElementById('estatus').value = status;
    
    new bootstrap.Modal(document.getElementById('editModal')).show();
    
    if (openDropdownId) {
        document.getElementById('dropdown-' + openDropdownId).style.display = 'none';
        openDropdownId = null;
    }
}

document.addEventListener('click', function() {
    if (openDropdownId) {
        const dropdown = document.getElementById('dropdown-' + openDropdownId);
        if (dropdown) dropdown.style.display = 'none';
        openDropdownId = null;
    }
});
</script>
</body>
</html>