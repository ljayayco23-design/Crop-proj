@extends('layouts.technician')

@section('title', 'Live Messenger | RICEGUARD AI')

@section('content')
<style>
    /* Custom Messenger Styling to match your Dark Theme */
    .chat-layout { height: calc(100vh - 120px); display: flex; background: #1e2937; border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
    .chat-sidebar { width: 320px; background: #0f172a; border-right: 1px solid #334155; display: flex; flex-direction: column; }
    .chat-main { flex: 1; display: flex; flex-direction: column; background: #1e2937; }
    .chat-header { padding: 18px 20px; background: #0f172a; border-bottom: 1px solid #334155; color: white; font-weight: bold; font-size: 1.1rem; }
    
    .user-list { overflow-y: auto; flex: 1; list-style: none; padding: 0; margin: 0; }
    .user-item { padding: 15px 20px; cursor: pointer; color: #cbd5e1; border-bottom: 1px solid #1e2937; transition: 0.2s; display: flex; align-items: center; }
    .user-item:hover { background: #1e2937; }
    .user-item.active { background: #10b981; color: white; }
    
    .chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; }
    .message-box { max-width: 70%; padding: 12px 18px; border-radius: 15px; color: white; position: relative; word-wrap: break-word; }
    
    /* Added padding to the right so text doesn't hit the 3 dots */
    .msg-sent { background: #10b981; align-self: flex-end; border-bottom-right-radius: 0; padding-right: 45px; }
    .msg-received { background: #3b82f6; align-self: flex-start; border-bottom-left-radius: 0; }
    
    .chat-input-area { padding: 15px; background: #0f172a; border-top: 1px solid #334155; display: flex; gap: 10px; align-items: center; }
    .chat-input { flex: 1; background: #1e2937; border: 1px solid #334155; color: white; border-radius: 25px; padding: 12px 20px; outline: none; transition: border 0.2s; }
    .chat-input:focus { border-color: #10b981; }
    
    .sender-name { font-size: 0.75rem; font-weight: bold; margin-bottom: 5px; opacity: 0.8; }

    /* --- NEW: Professional Three Dots Dropdown Menu --- */
    .msg-dropdown { position: absolute; top: 10px; right: 8px; }
    
    .msg-dropdown-btn { 
        background: transparent; border: none; color: rgba(255,255,255,0.8); 
        padding: 4px 10px; border-radius: 50%; cursor: pointer; transition: 0.2s; outline: none;
    }
    .msg-dropdown-btn:hover { background: rgba(0,0,0,0.2); color: white; }
    
    .msg-dropdown-menu { 
        position: absolute; right: 0; top: 100%; background: #0f172a; 
        border: 1px solid #334155; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);
        display: none; min-width: 130px; z-index: 50; padding: 6px 0; margin-top: 5px;
    }
    
    .msg-dropdown-menu.show { display: block; }
    
    .msg-dropdown-menu button { 
        display: block; width: 100%; text-align: left; padding: 8px 16px; 
        background: none; border: none; color: #cbd5e1; font-size: 0.85rem; cursor: pointer; transition: 0.2s;
    }
    .msg-dropdown-menu button:hover { background: #1e2937; color: white; }
    .msg-dropdown-menu button.text-danger { color: #f87171; }
    .msg-dropdown-menu button.text-danger:hover { background: #ef4444; color: white; }
</style>

<div class="chat-layout shadow-lg mt-3">
    <div class="chat-sidebar">
        <div class="chat-header">
            <i class="fas fa-comments me-2"></i> Conversations
        </div>
        <ul class="user-list" id="userList">
            </ul>
    </div>

    <div class="chat-main">
        <div class="chat-header d-flex justify-content-between align-items-center">
            <span id="chatHeaderTitle"><i class="fas fa-users me-2"></i> Global Group Chat</span>
            <span id="editingBadge" class="badge bg-warning text-dark d-none"><i class="fas fa-pen me-1"></i> Editing...</span>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <div class="text-center text-secondary mt-5"><div class="spinner-border spinner-border-sm"></div> Loading messages...</div>
        </div>

        <div class="chat-input-area">
            <input type="hidden" id="editMessageId" value="">
            <input type="text" id="chatInput" class="chat-input" placeholder="Type your message..." onkeypress="handleEnter(event)">
            <button class="btn btn-success rounded-pill px-4 py-2 fw-bold" onclick="sendMessage()">
                <i class="fas fa-paper-plane me-1"></i> Send
            </button>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const authUserId = {{ Auth::id() }};
    let currentChat = 'group'; 
    let chatInterval = null;

    document.addEventListener("DOMContentLoaded", () => {
        loadUsers();
        loadMessages();
        startPolling();
    });

    // Close any open dropdowns if the user clicks anywhere else on the screen
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.msg-dropdown')) {
            document.querySelectorAll('.msg-dropdown-menu').forEach(menu => menu.classList.remove('show'));
        }
    });

    function startPolling() {
        if (chatInterval) clearInterval(chatInterval);
        chatInterval = setInterval(loadMessages, 3000);
    }

    function switchChat(id, name, element) {
        currentChat = id;
        
        const icon = id === 'group' ? '<i class="fas fa-users me-2"></i>' : '<i class="fas fa-user me-2"></i>';
        document.getElementById('chatHeaderTitle').innerHTML = icon + name;
        
        document.querySelectorAll('.user-item').forEach(el => el.classList.remove('active'));
        if (element) element.classList.add('active');

        document.getElementById('chatMessages').innerHTML = '<div class="text-center text-secondary mt-5"><div class="spinner-border spinner-border-sm"></div> Loading messages...</div>';
        loadMessages(); 
        startPolling(); 
    }

    function loadUsers() {
        fetch('{{ url("/chat/users") }}')
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('userList');
                let html = `
                    <li class="user-item ${currentChat === 'group' ? 'active' : ''}" onclick="switchChat('group', 'Global Group Chat', this)">
                        <i class="fas fa-users me-3 fs-5"></i> 
                        <div>
                            <div class="fw-bold">Global Group Chat</div>
                            <div class="small opacity-75">Everyone</div>
                        </div>
                    </li>`;
                
                data.forEach(user => {
                    const isActive = currentChat == user.id ? 'active' : '';
                    html += `
                        <li class="user-item ${isActive}" onclick="switchChat('${user.id}', '${user.full_name}', this)">
                            <i class="fas fa-user-circle me-3 fs-3"></i> 
                            <div>
                                <div class="fw-bold">${user.full_name}</div>
                                <div class="small opacity-75 text-capitalize">${user.role}</div>
                            </div>
                        </li>
                    `;
                });
                list.innerHTML = html;
            });
    }

    function toggleMenu(id) {
        // Hide all other menus first
        document.querySelectorAll('.msg-dropdown-menu').forEach(menu => {
            if (menu.id !== 'menu-' + id) menu.classList.remove('show');
        });
        // Toggle the one we clicked
        document.getElementById('menu-' + id).classList.toggle('show');
    }

    function loadMessages() {
        let url = currentChat === 'group' ? '{{ url("/chat/messages") }}' : `{{ url("/chat/messages") }}?to_user=${currentChat}`;
        
        fetch(url)
            .then(res => res.json())
            .then(messages => {
                const box = document.getElementById('chatMessages');
                
                if(messages.length === 0) {
                    box.innerHTML = '<div class="text-center text-secondary mt-5">No messages yet. Say hello! 👋</div>';
                    return;
                }

                let html = '';
                messages.forEach(msg => {
                    const isMine = msg.from_user_id === authUserId;
                    const typeClass = isMine ? 'msg-sent' : 'msg-received';
                    const time = new Date(msg.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    
                    let actionButtons = '';
                    if (isMine) {
                        const safeMsg = msg.message.replace(/'/g, "\\'").replace(/"/g, "&quot;");
                        actionButtons = `
                            <div class="msg-dropdown">
                                <button class="msg-dropdown-btn" onclick="toggleMenu(${msg.id})">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <div class="msg-dropdown-menu" id="menu-${msg.id}">
                                    <button onclick="editMsg(${msg.id}, '${safeMsg}')">
                                        <i class="fas fa-pen me-2"></i> Edit
                                    </button>
                                    <button class="text-danger" onclick="deleteMsg(${msg.id})">
                                        <i class="fas fa-trash me-2"></i> Delete
                                    </button>
                                </div>
                            </div>
                        `;
                    }

                    const senderDisplay = !isMine 
                        ? `<div class="sender-name">${msg.sender_name || 'User'} &bull; ${time}</div>` 
                        : `<div class="sender-name text-end">${time}</div>`;

                    html += `
                        <div class="message-box ${typeClass}">
                            ${senderDisplay}
                            <div>${msg.message}</div>
                            ${actionButtons}
                        </div>
                    `;
                });
                
                const isAtBottom = box.scrollHeight - box.scrollTop <= box.clientHeight + 50;
                
                // Only update the HTML if the user isn't currently interacting with a dropdown
                const isDropdownOpen = document.querySelector('.msg-dropdown-menu.show');
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
        } else {
            if (currentChat === 'group') {
                formData.append('is_group', '1');
            } else {
                formData.append('to_user', currentChat);
            }
        }

        fetch('{{ url("/chat/send") }}', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: formData.toString()
        }).then(res => res.json()).then(data => {
            if(data.success) {
                input.value = '';
                editId.value = '';
                badge.classList.add('d-none');
                
                // Close dropdowns immediately
                document.querySelectorAll('.msg-dropdown-menu').forEach(menu => menu.classList.remove('show'));
                
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
        
        // Close menu after clicking
        document.getElementById('menu-' + id).classList.remove('show');
    }

    function deleteMsg(id) {
        // Close menu after clicking
        document.getElementById('menu-' + id).classList.remove('show');
        
        if (!confirm('Are you sure you want to delete this message?')) return;
        
        fetch(`{{ url("/chat/delete") }}/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        }).then(res => res.json()).then(data => {
            if(data.success) {
                // Remove open menu state so it reloads immediately
                document.querySelectorAll('.msg-dropdown-menu').forEach(menu => menu.classList.remove('show'));
                loadMessages();
            }
        });
    }
</script>
@endsection