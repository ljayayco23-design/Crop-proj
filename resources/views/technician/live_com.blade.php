@extends('layouts.technician')

@section('title', 'Live Messenger | RICEGUARD AI')

@section('content')
<style>
    /* ============================================================
       RICEGUARD MESSENGER — built from scratch.
       Two-pane layout, glass sidebar, gradient bubbles with tails,
       deterministic per-person avatar colors (no stock Bootstrap
       "dropdown" look anywhere in here).
       ============================================================ */

    :root {
        --rg-bg: #0b1220;
        --rg-panel: #101a2c;
        --rg-panel-alt: #0d1728;
        --rg-border: #223049;
        --rg-text: #e7ecf5;
        --rg-muted: #8996ad;
        --rg-accent: #10b981;
        --rg-accent-2: #34d399;
        --rg-accent-soft: rgba(16, 185, 129, 0.14);
        --rg-danger: #f87171;
    }

    .rg-shell {
        height: calc(100vh - 120px);
        min-height: 520px;
        display: grid;
        grid-template-columns: 320px 1fr;
        background: var(--rg-bg);
        border-radius: 18px;
        overflow: hidden;
        border: 1px solid var(--rg-border);
        box-shadow: 0 20px 60px -20px rgba(0, 0, 0, 0.6);
        font-family: inherit;
    }

    /* ---------- Sidebar ---------- */
    .rg-sidebar {
        background: linear-gradient(180deg, var(--rg-panel) 0%, var(--rg-panel-alt) 100%);
        border-right: 1px solid var(--rg-border);
        display: flex;
        flex-direction: column;
        min-height: 0;
    }

    .rg-sidebar-head {
        padding: 20px 18px 14px;
        border-bottom: 1px solid var(--rg-border);
    }

    .rg-sidebar-title {
        display: flex;
        align-items: center;
        gap: 10px;
        color: var(--rg-text);
        font-weight: 800;
        font-size: 1.05rem;
        margin-bottom: 14px;
    }

    .rg-sidebar-title .rg-dot {
        width: 9px; height: 9px; border-radius: 50%;
        background: var(--rg-accent);
        box-shadow: 0 0 0 4px var(--rg-accent-soft);
    }

    .rg-search {
        position: relative;
    }

    .rg-search i {
        position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
        color: var(--rg-muted); font-size: 0.85rem;
    }

    .rg-search input {
        width: 100%;
        background: #0a1220;
        border: 1px solid var(--rg-border);
        color: var(--rg-text);
        border-radius: 12px;
        padding: 9px 12px 9px 34px;
        font-size: 0.85rem;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .rg-search input:focus {
        border-color: var(--rg-accent);
        box-shadow: 0 0 0 3px var(--rg-accent-soft);
    }

    .rg-conv-list {
        flex: 1;
        overflow-y: auto;
        list-style: none;
        padding: 10px 10px 16px;
        margin: 0;
    }

    .rg-conv-list::-webkit-scrollbar { width: 6px; }
    .rg-conv-list::-webkit-scrollbar-thumb { background: var(--rg-border); border-radius: 6px; }

    .rg-section-label {
        color: var(--rg-muted);
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        padding: 10px 10px 6px;
    }

    .rg-conv-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px;
        border-radius: 14px;
        cursor: pointer;
        color: var(--rg-text);
        transition: background 0.15s, transform 0.1s;
        margin-bottom: 3px;
    }

    .rg-conv-item:hover { background: rgba(255, 255, 255, 0.04); }

    .rg-conv-item.active {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.22), rgba(16, 185, 129, 0.06));
        box-shadow: inset 0 0 0 1px rgba(16, 185, 129, 0.35);
    }

    .rg-avatar {
        flex-shrink: 0;
        width: 42px; height: 42px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 800;
        font-size: 0.85rem;
        color: #06120c;
        position: relative;
    }

    .rg-avatar.rg-avatar-group {
        background: linear-gradient(135deg, var(--rg-accent-2), var(--rg-accent));
        color: #06241a;
        font-size: 1.1rem;
    }

    .rg-conv-name {
        font-weight: 700;
        font-size: 0.88rem;
        line-height: 1.25;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .rg-conv-sub {
        font-size: 0.72rem;
        color: var(--rg-muted);
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .rg-role-chip {
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 1px 7px;
        border-radius: 999px;
    }

    .rg-role-chip.role-technician { background: rgba(59, 130, 246, 0.18); color: #93c5fd; }
    .rg-role-chip.role-farmer { background: rgba(16, 185, 129, 0.18); color: #6ee7b7; }

    .rg-empty-area {
        margin: 14px 10px;
        padding: 18px 14px;
        border-radius: 14px;
        border: 1px dashed var(--rg-border);
        color: var(--rg-muted);
        font-size: 0.8rem;
        line-height: 1.5;
        text-align: center;
    }

    .rg-empty-area i { color: var(--rg-accent); font-size: 1.4rem; margin-bottom: 8px; display: block; }

    /* ---------- Main pane ---------- */
    .rg-main {
        display: flex;
        flex-direction: column;
        min-height: 0;
        background:
            radial-gradient(1200px 400px at 100% 0%, rgba(16, 185, 129, 0.05), transparent 60%),
            var(--rg-bg);
    }

    .rg-main-head {
        padding: 16px 22px;
        border-bottom: 1px solid var(--rg-border);
        display: flex; align-items: center; justify-content: space-between;
        background: rgba(16, 24, 40, 0.6);
        backdrop-filter: blur(10px);
    }

    .rg-main-head-left { display: flex; align-items: center; gap: 12px; }

    .rg-main-title { color: var(--rg-text); font-weight: 800; font-size: 1rem; }
    .rg-main-subtitle { color: var(--rg-muted); font-size: 0.72rem; margin-top: 1px; }

    .rg-editing-badge {
        display: flex; align-items: center; gap: 6px;
        background: rgba(245, 158, 11, 0.16);
        color: #fbbf24;
        font-size: 0.72rem; font-weight: 700;
        padding: 5px 12px;
        border-radius: 999px;
    }

    .rg-messages {
        flex: 1;
        overflow-y: auto;
        padding: 26px 24px;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }

    .rg-messages::-webkit-scrollbar { width: 7px; }
    .rg-messages::-webkit-scrollbar-thumb { background: var(--rg-border); border-radius: 6px; }

    .rg-msg-row {
        display: flex;
        flex-direction: column;
        max-width: 62%;
        margin-bottom: 10px;
        position: relative;
    }

    .rg-msg-row.mine { align-self: flex-end; align-items: flex-end; }
    .rg-msg-row.theirs { align-self: flex-start; align-items: flex-start; }

    .rg-msg-meta {
        font-size: 0.68rem;
        color: var(--rg-muted);
        margin-bottom: 4px;
        padding: 0 4px;
        font-weight: 600;
    }

    .rg-bubble {
        padding: 11px 16px;
        border-radius: 18px;
        color: white;
        font-size: 0.9rem;
        line-height: 1.45;
        word-wrap: break-word;
        position: relative;
        box-shadow: 0 4px 14px -6px rgba(0, 0, 0, 0.5);
    }

    .rg-msg-row.mine .rg-bubble {
        background: linear-gradient(135deg, var(--rg-accent-2), #059669);
        border-bottom-right-radius: 5px;
        padding-right: 44px;
    }

    .rg-msg-row.theirs .rg-bubble {
        background: #1c2942;
        border: 1px solid var(--rg-border);
        border-bottom-left-radius: 5px;
    }

    .rg-msg-time {
        font-size: 0.64rem;
        color: var(--rg-muted);
        margin-top: 3px;
        padding: 0 4px;
    }

    .rg-msg-dropdown { position: absolute; top: 8px; right: 6px; }

    .rg-msg-dropdown-btn {
        background: rgba(0, 0, 0, 0.12);
        border: none;
        color: rgba(255, 255, 255, 0.85);
        width: 22px; height: 22px;
        border-radius: 50%;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.7rem;
        transition: background 0.15s;
    }
    .rg-msg-dropdown-btn:hover { background: rgba(0, 0, 0, 0.3); }

    .rg-msg-dropdown-menu {
        position: absolute; right: 0; top: 26px;
        background: #101a2c;
        border: 1px solid var(--rg-border);
        border-radius: 10px;
        box-shadow: 0 10px 30px -8px rgba(0, 0, 0, 0.6);
        display: none;
        min-width: 140px;
        z-index: 50;
        padding: 6px;
        overflow: hidden;
    }

    .rg-msg-dropdown-menu.show { display: block; }

    .rg-msg-dropdown-menu button {
        display: flex; align-items: center; gap: 8px;
        width: 100%; text-align: left;
        padding: 8px 10px;
        background: none; border: none; border-radius: 7px;
        color: #cbd5e1; font-size: 0.8rem; cursor: pointer;
        transition: background 0.15s, color 0.15s;
    }
    .rg-msg-dropdown-menu button:hover { background: rgba(255, 255, 255, 0.06); color: white; }
    .rg-msg-dropdown-menu button.danger { color: var(--rg-danger); }
    .rg-msg-dropdown-menu button.danger:hover { background: rgba(248, 113, 113, 0.14); }

    .rg-empty-thread {
        margin: auto;
        text-align: center;
        color: var(--rg-muted);
    }
    .rg-empty-thread i { font-size: 2rem; color: var(--rg-accent); margin-bottom: 10px; display: block; }

    /* ---------- Composer ---------- */
    .rg-composer {
        padding: 16px 20px;
        background: rgba(16, 24, 40, 0.7);
        border-top: 1px solid var(--rg-border);
        display: flex; align-items: center; gap: 10px;
    }

    .rg-composer input[type="text"] {
        flex: 1;
        background: #0a1220;
        border: 1px solid var(--rg-border);
        color: var(--rg-text);
        border-radius: 999px;
        padding: 12px 18px;
        outline: none;
        font-size: 0.9rem;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .rg-composer input[type="text"]:focus {
        border-color: var(--rg-accent);
        box-shadow: 0 0 0 3px var(--rg-accent-soft);
    }

    .rg-send-btn {
        width: 46px; height: 46px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, var(--rg-accent-2), #059669);
        color: #06241a;
        display: flex; align-items: center; justify-content: center;
        font-size: 1rem;
        cursor: pointer;
        flex-shrink: 0;
        transition: transform 0.12s, filter 0.12s;
        box-shadow: 0 8px 20px -8px rgba(16, 185, 129, 0.7);
    }
    .rg-send-btn:hover { filter: brightness(1.08); transform: translateY(-1px); }
    .rg-send-btn:active { transform: translateY(0); }

    @media (max-width: 820px) {
        .rg-shell { grid-template-columns: 1fr; }
        .rg-sidebar { display: none; }
    }
</style>

<div class="rg-shell shadow-lg mt-3">
    <!-- ============ Sidebar ============ -->
    <aside class="rg-sidebar">
        <div class="rg-sidebar-head">
            <div class="rg-sidebar-title"><span class="rg-dot"></span> Messenger</div>
            <div class="rg-search">
                <i class="fas fa-search"></i>
                <input type="text" id="convSearch" placeholder="Search people..." oninput="filterConversations(this.value)">
            </div>
        </div>
        <ul class="rg-conv-list" id="userList">
            <li class="rg-empty-area"><i class="fas fa-spinner fa-spin"></i>Loading your area...</li>
        </ul>
    </aside>

    <!-- ============ Main pane ============ -->
    <section class="rg-main">
        <div class="rg-main-head">
            <div class="rg-main-head-left">
                <div class="rg-avatar rg-avatar-group" id="chatHeaderAvatar"><i class="fas fa-users"></i></div>
                <div>
                    <div class="rg-main-title" id="chatHeaderTitle">Global Group Chat</div>
                    <div class="rg-main-subtitle" id="chatHeaderSubtitle">Everyone in your area</div>
                </div>
            </div>
            <span id="editingBadge" class="rg-editing-badge d-none"><i class="fas fa-pen"></i> Editing message</span>
        </div>

        <div class="rg-messages" id="chatMessages">
            <div class="rg-empty-thread"><div class="spinner-border spinner-border-sm"></div><div class="mt-2">Loading messages...</div></div>
        </div>

        <div class="rg-composer">
            <input type="hidden" id="editMessageId" value="">
            <input type="text" id="chatInput" placeholder="Type a message..." onkeypress="handleEnter(event)">
            <button class="rg-send-btn" onclick="sendMessage()" title="Send">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
    const authUserId = {{ Auth::id() }};
    let currentChat = 'group';
    let chatInterval = null;
    let lastUserData = [];

    document.addEventListener("DOMContentLoaded", () => {
        loadUsers();
        loadMessages();
        startPolling();
    });

    // Close any open message menus if the user clicks anywhere else.
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.rg-msg-dropdown')) {
            document.querySelectorAll('.rg-msg-dropdown-menu').forEach(menu => menu.classList.remove('show'));
        }
    });

    function startPolling() {
        if (chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(loadMessages, 3000);
    }

    // ---------- Avatar helpers ----------
    const AVATAR_PALETTE = ['#10b981', '#3b82f6', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#f97316', '#ef4444'];

    function initialsFor(name) {
        if (!name) return '?';
        const parts = name.trim().split(/\s+/);
        const first = parts[0] ?? '';
        return parts.length > 1 ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase() : (first.slice(0, 2)).toUpperCase();
    }

    function colorFor(seed) {
        let hash = 0;
        const str = String(seed);
        for (let i = 0; i < str.length; i++) hash = str.charCodeAt(i) + ((hash << 5) - hash);
        return AVATAR_PALETTE[Math.abs(hash) % AVATAR_PALETTE.length];
    }

    // ---------- Sidebar ----------
    function switchChat(id, name, role, element) {
        currentChat = id;

        const avatar = document.getElementById('chatHeaderAvatar');
        if (id === 'group') {
            avatar.className = 'rg-avatar rg-avatar-group';
            avatar.style.background = '';
            avatar.innerHTML = '<i class="fas fa-users"></i>';
            document.getElementById('chatHeaderSubtitle').textContent = 'Everyone in your area';
        } else {
            avatar.className = 'rg-avatar';
            avatar.style.background = colorFor(id);
            avatar.innerHTML = initialsFor(name);
            document.getElementById('chatHeaderSubtitle').textContent = role ? role.charAt(0).toUpperCase() + role.slice(1) : '';
        }

        document.getElementById('chatHeaderTitle').textContent = name;

        document.querySelectorAll('.rg-conv-item').forEach(el => el.classList.remove('active'));
        if (element) element.classList.add('active');

        document.getElementById('chatMessages').innerHTML = '<div class="rg-empty-thread"><div class="spinner-border spinner-border-sm"></div><div class="mt-2">Loading messages...</div></div>';
        loadMessages();
        startPolling();
    }

    function renderConversationList(data) {
        lastUserData = data;
        const list = document.getElementById('userList');

        let html = `
            <li class="rg-conv-item ${currentChat === 'group' ? 'active' : ''}" data-name="global group chat everyone" onclick="switchChat('group', 'Global Group Chat', null, this)">
                <div class="rg-avatar rg-avatar-group"><i class="fas fa-users"></i></div>
                <div style="min-width:0;">
                    <div class="rg-conv-name">Global Group Chat</div>
                    <div class="rg-conv-sub">Everyone in your area</div>
                </div>
            </li>`;

        if (data.length === 0) {
            html += `
                <li class="rg-empty-area">
                    <i class="fas fa-map-marker-alt"></i>
                    No one in your assigned area yet.<br>Ask an admin to assign you a barangay.
                </li>`;
        } else {
            html += `<li class="rg-section-label">In your area</li>`;
            data.forEach(user => {
                const isActive = currentChat == user.id ? 'active' : '';
                const bg = colorFor(user.id);
                html += `
                    <li class="rg-conv-item ${isActive}" data-name="${(user.full_name + ' ' + user.role).toLowerCase()}" onclick="switchChat('${user.id}', '${user.full_name.replace(/'/g, "\\'")}', '${user.role}', this)">
                        <div class="rg-avatar" style="background:${bg}">${initialsFor(user.full_name)}</div>
                        <div style="min-width:0;">
                            <div class="rg-conv-name">${user.full_name}</div>
                            <div class="rg-conv-sub"><span class="rg-role-chip role-${user.role}">${user.role}</span></div>
                        </div>
                    </li>
                `;
            });
        }

        list.innerHTML = html;
    }

    function loadUsers() {
        fetch('{{ url("/chat/users") }}')
            .then(res => res.json())
            .then(renderConversationList);
    }

    function filterConversations(term) {
        const t = term.trim().toLowerCase();
        document.querySelectorAll('#userList .rg-conv-item').forEach(item => {
            const haystack = item.getAttribute('data-name') || '';
            item.style.display = haystack.includes(t) ? '' : 'none';
        });
    }

    // ---------- Messages ----------
    function toggleMenu(id) {
        document.querySelectorAll('.rg-msg-dropdown-menu').forEach(menu => {
            if (menu.id !== 'menu-' + id) menu.classList.remove('show');
        });
        document.getElementById('menu-' + id).classList.toggle('show');
    }

    function loadMessages() {
        let url = currentChat === 'group' ? '{{ url("/chat/messages") }}' : `{{ url("/chat/messages") }}?to_user=${currentChat}`;

        fetch(url)
            .then(res => res.json())
            .then(messages => {
                const box = document.getElementById('chatMessages');

                if (messages.length === 0) {
                    box.innerHTML = '<div class="rg-empty-thread"><i class="fas fa-comment-dots"></i>No messages yet. Say hello 👋</div>';
                    return;
                }

                let html = '';
                messages.forEach(msg => {
                    const isMine = msg.from_user_id === authUserId;
                    const rowClass = isMine ? 'mine' : 'theirs';
                    const time = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

                    let actionButtons = '';
                    if (isMine) {
                        const safeMsg = msg.message.replace(/'/g, "\\'").replace(/"/g, "&quot;");
                        actionButtons = `
                            <div class="rg-msg-dropdown">
                                <button class="rg-msg-dropdown-btn" onclick="toggleMenu(${msg.id})">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="rg-msg-dropdown-menu" id="menu-${msg.id}">
                                    <button onclick="editMsg(${msg.id}, '${safeMsg}')"><i class="fas fa-pen"></i> Edit</button>
                                    <button class="danger" onclick="deleteMsg(${msg.id})"><i class="fas fa-trash"></i> Delete</button>
                                </div>
                            </div>
                        `;
                    }

                    const senderLabel = !isMine ? `<div class="rg-msg-meta">${msg.sender_name || 'User'}</div>` : '';

                    html += `
                        <div class="rg-msg-row ${rowClass}">
                            ${senderLabel}
                            <div class="rg-bubble">${msg.message}${actionButtons}</div>
                            <div class="rg-msg-time">${time}</div>
                        </div>
                    `;
                });

                const isAtBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 50;
                const isDropdownOpen = document.querySelector('.rg-msg-dropdown-menu.show');

                if (!isDropdownOpen) {
                    box.innerHTML = html;
                    if (isAtBottom) box.scrollTop = box.scrollHeight;
                }
            });
    }

    function handleEnter(e) {
        if (e.key === 'Enter') sendMessage();
    }

    function sendMessage() {
        const input = document.getElementById('chatInput');
        const editId = document.getElementById('editMessageId');
        const badge = document.getElementById('editingBadge');
        const message = input.value.trim();

        if (!message) return;

        let formData = new URLSearchParams();
        formData.append('message', message);

        if (editId.value) {
            formData.append('edit_id', editId.value);
        } else if (currentChat === 'group') {
            formData.append('is_group', '1');
        } else {
            formData.append('to_user', currentChat);
        }

        fetch('{{ url("/chat/send") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData.toString()
        }).then(res => res.json()).then(data => {
            if (data.success) {
                input.value = '';
                editId.value = '';
                badge.classList.add('d-none');

                document.querySelectorAll('.rg-msg-dropdown-menu').forEach(menu => menu.classList.remove('show'));

                loadMessages();
                setTimeout(() => {
                    const box = document.getElementById('chatMessages');
                    box.scrollTop = box.scrollHeight;
                }, 100);
            }
        });
    }

    function editMsg(id, text) {
        document.getElementById('editMessageId').value = id;
        document.getElementById('editingBadge').classList.remove('d-none');
        const input = document.getElementById('chatInput');
        input.value = text;
        input.focus();
        document.getElementById('menu-' + id).classList.remove('show');
    }

    function deleteMsg(id) {
        document.getElementById('menu-' + id).classList.remove('show');
        if (!confirm('Are you sure you want to delete this message?')) return;

        fetch(`{{ url("/chat/delete") }}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(res => res.json()).then(data => {
            if (data.success) {
                document.querySelectorAll('.rg-msg-dropdown-menu').forEach(menu => menu.classList.remove('show'));
                loadMessages();
            }
        });
    }
</script>
@endsection