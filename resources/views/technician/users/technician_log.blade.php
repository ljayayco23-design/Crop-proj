@extends('layouts.technician')

@section('title', 'RICEGUARD AI • User Log')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">User Log</h4>
            <p class="text-muted mb-0">Technician and farmer accounts on the system.</p>
        </div>
        @if(\App\Models\Permission::can(auth()->user()->role, 'user_management', 'create'))
        <a href="{{ route('technician.account.create') }}" class="btn btn-success">
            <i class="fas fa-user-plus"></i> Create New Account
        </a>
        @endif
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    {{-- ===================== ROLE SUMMARY CARDS (Technician + Farmer only) ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card role-card role-card-technician h-100" data-role="technician" role="button" tabindex="0" onclick="selectRole('technician')">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="role-icon role-icon-technician"><i class="fas fa-user-gear"></i></div>
                    <div>
                        <h6 class="mb-0">Technician</h6>
                        <small class="text-muted">Field technicians</small>
                    </div>
                </div>
                <div class="card-footer role-card-footer d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Total Technicians</span>
                    <strong class="role-count" id="count-technician">{{ $technicians->count() }}</strong>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card role-card role-card-farmer h-100" data-role="farmer" role="button" tabindex="0" onclick="selectRole('farmer')">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="role-icon role-icon-farmer"><i class="fas fa-user"></i></div>
                    <div>
                        <h6 class="mb-0">Farmer</h6>
                        <small class="text-muted">Registered farmers</small>
                    </div>
                </div>
                <div class="card-footer role-card-footer d-flex justify-content-between align-items-center">
                    <span class="text-muted small">Total Farmers</span>
                    <strong class="role-count" id="count-farmer">{{ $farmers->count() }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== USER DATA PANEL ===================== --}}
    <div class="card uas-panel">
        <div class="card-body">

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3 uas-toolbar">
                <div>
                    <h5 class="uas-title mb-1" id="table-title">Technician Accounts</h5>
                    <p class="uas-subtitle mb-0" id="table-subtitle">List of all technician accounts and their registered address.</p>
                </div>

                <div class="d-flex flex-nowrap align-items-center gap-2 uas-filters ms-auto">
                    <div class="uas-search-wrap">
                        <i class="fas fa-search uas-search-icon"></i>
                        <input type="text" id="searchInput" class="form-control uas-input uas-search-input" placeholder="Search user or area...">
                    </div>
                    <select id="provinceFilter" class="form-select uas-input">
                        <option value="">All Provinces</option>
                    </select>
                    <select id="cityFilter" class="form-select uas-input">
                        <option value="">All Municipalities</option>
                    </select>
                    <select id="barangayFilter" class="form-select uas-input">
                        <option value="">All Barangays</option>
                    </select>
                    <div class="dropdown uas-export-wrap">
                        <button type="button" class="btn btn-outline-light dropdown-toggle uas-export-btn" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-download"></i> Export
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="javascript:void(0)" onclick="exportCsv()">Export as CSV</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table uas-table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>User Type</th>
                            <th>Province</th>
                            <th>City / Municipality</th>
                            <th>Barangay</th>
                            <th>Device Location</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th class="text-end" style="width: 90px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        @foreach(['technician' => $technicians, 'farmer' => $farmers] as $roleKey => $collection)
                            @forelse($collection as $row)
                                <tr class="role-row"
                                    data-role="{{ $roleKey }}"
                                    data-name="{{ strtolower($row->full_name) }}"
                                    data-email="{{ strtolower($row->email) }}"
                                    data-province="{{ $row->province->name ?? '' }}"
                                    data-city="{{ $row->city->name ?? '' }}"
                                    data-barangay="{{ $row->barangay->name ?? '' }}"
                                    style="display:none;">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if(!empty($row->profile_photo))
                                                <img src="{{ $row->profile_photo }}" alt="{{ $row->full_name }}" class="avatar-circle avatar-photo">
                                            @else
                                                <div class="avatar-circle avatar-{{ $roleKey }}">{{ strtoupper(substr($row->full_name, 0, 1)) }}</div>
                                            @endif
                                            <div>
                                                <div class="fw-semibold">{{ $row->full_name }}</div>
                                                <div class="text-muted small">{{ $row->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge role-pill role-pill-{{ $roleKey }}">{{ ucfirst($roleKey) }}</span>
                                    </td>
                                    <td>{{ $row->province->name ?? '—' }}</td>
                                    <td>{{ $row->city->name ?? '—' }}</td>
                                    <td>{{ $row->barangay->name ?? '—' }}</td>
                                    <td>
                                        @if(!empty($row->device_latitude) && !empty($row->device_longitude))
                                            <a href="https://maps.google.com/?q={{ $row->device_latitude }},{{ $row->device_longitude }}"
                                               target="_blank" rel="noopener"
                                               class="uas-geo-link uas-geo-pending"
                                               data-lat="{{ $row->device_latitude }}"
                                               data-lng="{{ $row->device_longitude }}"
                                               title="{{ $row->device_latitude }}, {{ $row->device_longitude }}">
                                                <i class="fas fa-location-dot"></i>
                                                <span class="uas-geo-text">Locating…</span>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->created_at?->format('M d, Y') }}</td>
                                    <td>
                                        <span class="badge status-pill status-{{ $row->status ?? 'pending' }}">
                                            {{ ucfirst($row->status ?? 'pending') }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @include('partials.admin-user-actions', ['user' => $row])
                                    </td>
                                </tr>
                            @empty
                                <tr class="role-row role-empty-row" data-role="{{ $roleKey }}" style="display:none;">
                                    <td colspan="9" class="text-center py-5 text-muted">No {{ $roleKey }} accounts found.</td>
                                </tr>
                            @endforelse
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center uas-footer mt-3 pt-3">
                <span class="uas-entries-label" id="entries-label">Showing 0 of 0 entries</span>
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center" id="pagination-controls"></div>
                    <select id="pageSizeSelect" class="form-select form-select-sm uas-input uas-page-size">
                        <option value="10">10 / page</option>
                        <option value="25">25 / page</option>
                        <option value="50">50 / page</option>
                    </select>
                </div>
            </div>

        </div>
    </div>
</div>

@include('partials.admin-user-edit-modal')

{{-- User Info modal (same as the original farmer info modal — works for any role) --}}
<div class="modal fade" id="userInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-light border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-success"><i class="fas fa-id-card me-2"></i> User Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <h6 class="text-secondary border-bottom border-secondary pb-2">Personal Information</h6>
                        <p class="mb-1"><strong>Name:</strong> <span id="info_name"></span></p>
                        <p class="mb-1"><strong>Email:</strong> <span id="info_email"></span></p>
                        <p class="mb-1"><strong>Phone:</strong> <span id="info_phone"></span></p>
                        <p class="mb-1"><strong>Date of Birth:</strong> <span id="info_dob"></span></p>
                        <p class="mb-1"><strong>Address:</strong> <span id="info_address"></span></p>
                        <p class="mb-1"><strong>Status:</strong> <span id="info_status" class="badge"></span></p>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-secondary border-bottom border-secondary pb-2">Farm Details</h6>
                        <p class="mb-1"><strong>Farm Name:</strong> <span id="info_farm_name"></span></p>
                        <p class="mb-1"><strong>Role:</strong> <span id="info_role" class="text-capitalize"></span></p>
                        <p class="mb-1"><strong>Location:</strong> <span id="info_location"></span></p>
                        <p class="mb-1"><strong>Farm Size:</strong> <span id="info_size"></span></p>
                        <p class="mb-1"><strong>Water Source:</strong> <span id="info_water" class="text-capitalize"></span></p>
                    </div>

                    <div class="col-12 mt-4">
                        <h6 class="text-secondary border-bottom border-secondary pb-2">Verification Documents (<span id="info_id_type" class="text-capitalize"></span>)</h6>
                        <div class="row mt-3">
                            <div class="col-md-12 text-center">
                                <p class="text-muted small mb-2">ID Document</p>
                                <img id="info_doc_img" src="" class="img-fluid rounded border border-secondary" style="max-height: 250px; object-fit: contain; display: none;">
                                <span id="no_doc" class="text-muted fst-italic">No Document Found</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* ---------- Role summary cards (top) ---------- */
    .role-card {
        cursor: pointer;
        background: #101a2c;
        border: 1px solid rgba(255,255,255,.08);
        transition: transform .15s ease, box-shadow .15s ease, border-color .15s ease;
    }
    .role-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.25); }
    .role-card.active-role { border-color: var(--role-accent, #4d8dff); box-shadow: 0 0 0 1px var(--role-accent, #4d8dff); }
    .role-card-admin      { --role-accent: #4d8dff; }
    .role-card-technician { --role-accent: #22c55e; }
    .role-card-farmer     { --role-accent: #f59e0b; }
    .role-card-footer { border-top: 1px solid rgba(255,255,255,.08); background: transparent; }

    .role-icon {
        width: 42px; height: 42px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem; flex-shrink: 0;
    }
    .role-icon-admin      { background: rgba(77,141,255,.18); color: #4d8dff; }
    .role-icon-technician { background: rgba(34,197,94,.18); color: #22c55e; }
    .role-icon-farmer     { background: rgba(245,158,11,.18); color: #f59e0b; }

    .role-card-admin h6      { color: #4d8dff; }
    .role-card-technician h6 { color: #22c55e; }
    .role-card-farmer h6     { color: #f59e0b; }
    .role-count { color: inherit; }

    /* ---------- Data panel (bottom) — matches the reference screenshot ---------- */
    .uas-panel {
        background: #101a2c;
        border: 1px solid rgba(255,255,255,.06);
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0,0,0,.25);
    }
    .uas-title { color: #f1f5f9; font-weight: 600; }
    .uas-subtitle { color: #8b93a7; font-size: .875rem; }

    .uas-input.form-control,
    .uas-input.form-select {
        background: #0b1422;
        border-color: rgba(255,255,255,.1);
        color: #e2e8f0;
    }
    .uas-input.form-control::placeholder { color: #6b7688; }
    .uas-input.form-control:focus,
    .uas-input.form-select:focus {
        background: #0b1422;
        color: #e2e8f0;
        border-color: #4d8dff;
        box-shadow: 0 0 0 .2rem rgba(77,141,255,.2);
    }

    /* ---------- Toolbar: search / filters / export on one line ---------- */
    .uas-toolbar { flex-wrap: nowrap; }
    .uas-filters {
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 2px; /* keeps content from clipping on very narrow screens */
        scrollbar-width: none;      /* Firefox: hide the "<>" scroll arrows */
        -ms-overflow-style: none;   /* old Edge/IE */
    }
    .uas-filters::-webkit-scrollbar { display: none; } /* Chrome/Safari/Edge */
    .uas-filters .uas-input#searchInput { min-width: 190px; flex: 1 1 190px; }
    .uas-filters select.uas-input { min-width: 130px; max-width: 170px; flex: 0 0 auto; }
    .uas-search-wrap { position: relative; flex: 1 1 190px; min-width: 190px; }
    .uas-search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7688;
        font-size: .8rem;
        pointer-events: none;
    }
    .uas-search-input { padding-left: 32px; width: 100%; }
    .uas-export-wrap { flex: 0 0 auto; margin-right: 0; }
    .uas-export-btn {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        white-space: nowrap;
        margin-right: 0;
    }
    @media (max-width: 992px) {
        .uas-toolbar { flex-wrap: wrap; }
        .uas-filters { width: 100%; }
    }

    .uas-table thead th {
        background: #16223a;
        color: #9aa4b8;
        border-bottom: none;
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        padding: .85rem 1rem;
        white-space: nowrap;
    }
    .uas-table tbody td {
        padding: .85rem 1rem;
        border-color: rgba(255,255,255,.05);
        color: #e2e8f0;
        vertical-align: middle;
    }
    .uas-table tbody tr:hover { background: rgba(255,255,255,.03); }

    .avatar-circle {
        width: 34px; height: 34px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .85rem; color: #fff; flex-shrink: 0;
    }
    .avatar-admin      { background: #4d8dff; }
    .avatar-technician { background: #22c55e; }
    .avatar-farmer     { background: #f59e0b; }
    .avatar-photo      { object-fit: cover; border: none; }

    .uas-geo-link {
        color: #4d8dff;
        text-decoration: none;
        white-space: normal;
        font-size: .82rem;
        display: inline-flex;
        align-items: flex-start;
        gap: .3rem;
        max-width: 220px;
        line-height: 1.3;
    }
    .uas-geo-link i { font-size: .75rem; margin-top: .15rem; flex-shrink: 0; }
    .uas-geo-link:hover { color: #7db0ff; text-decoration: underline; }
    .uas-geo-pending .uas-geo-text { color: #6b7688; font-style: italic; }

    .role-pill, .status-pill {
        padding: .35em .75em;
        border-radius: 50px;
        font-weight: 600;
        font-size: .72rem;
    }
    .role-pill-admin      { background: rgba(77,141,255,.15); color: #4d8dff; }
    .role-pill-technician { background: rgba(34,197,94,.15); color: #22c55e; }
    .role-pill-farmer     { background: rgba(245,158,11,.15); color: #f59e0b; }

    .status-approved { background: rgba(34,197,94,.15); color: #22c55e; }
    .status-pending   { background: rgba(245,158,11,.15); color: #f59e0b; }
    .status-declined  { background: rgba(239,68,68,.15); color: #ef4444; }

    .uas-footer { border-top: 1px solid rgba(255,255,255,.06); }
    .uas-entries-label { color: #8b93a7; font-size: .85rem; }

    .uas-page-btn {
        border: 1px solid rgba(255,255,255,.1);
        background: transparent;
        color: #cbd5e1;
        width: 30px; height: 30px;
        border-radius: 6px;
        font-size: .8rem;
        margin: 0 2px;
        display: inline-flex; align-items: center; justify-content: center;
    }
    .uas-page-btn.active { background: #4d8dff; color: #fff; border-color: #4d8dff; }
    .uas-page-btn:disabled { opacity: .35; cursor: not-allowed; }
    .uas-page-dots { color: #6b7688; padding: 0 .35rem; }
    .uas-page-size { width: auto; }
</style>
@endsection

@section('scripts')
<script>
    const roleLabels = { technician: 'Technician Accounts', farmer: 'Farmer Accounts' };
    const subtitleLabels = {
        technician: 'List of all technician accounts and their registered address.',
        farmer: 'List of all farmer accounts and their registered address.',
    };

    let activeRole = '{{ $activeRole }}';
    let currentPage = 1;
    let pageSize = 10;
    let infoModal;

    function selectRole(role) {
        activeRole = role;
        currentPage = 1;

        document.querySelectorAll('.role-card').forEach(card => {
            card.classList.toggle('active-role', card.dataset.role === role);
        });

        document.getElementById('table-title').innerText = roleLabels[role] || 'User Accounts';
        document.getElementById('table-subtitle').innerText = subtitleLabels[role] || '';

        // Reset filters when switching tabs, then rebuild them for the new role
        document.getElementById('searchInput').value = '';
        document.getElementById('provinceFilter').value = '';
        document.getElementById('cityFilter').value = '';
        document.getElementById('barangayFilter').value = '';

        buildLocationFilters();
        renderTable();
    }

    function getMatchedRows() {
        const search = document.getElementById('searchInput').value.trim().toLowerCase();
        const province = document.getElementById('provinceFilter').value;
        const city = document.getElementById('cityFilter').value;
        const barangay = document.getElementById('barangayFilter').value;

        return Array.from(document.querySelectorAll('#users-table-body tr.role-row:not(.role-empty-row)')).filter(row => {
            if (row.dataset.role !== activeRole) return false;

            const matchesSearch = !search ||
                row.dataset.name.includes(search) ||
                row.dataset.email.includes(search) ||
                row.dataset.province.toLowerCase().includes(search) ||
                row.dataset.city.toLowerCase().includes(search) ||
                row.dataset.barangay.toLowerCase().includes(search);
            const matchesProvince = !province || row.dataset.province === province;
            const matchesCity = !city || row.dataset.city === city;
            const matchesBarangay = !barangay || row.dataset.barangay === barangay;

            return matchesSearch && matchesProvince && matchesCity && matchesBarangay;
        });
    }

    function renderTable() {
        // Hide everything first
        document.querySelectorAll('#users-table-body tr.role-row').forEach(row => row.style.display = 'none');

        const matched = getMatchedRows();
        const total = matched.length;
        const totalPages = Math.max(1, Math.ceil(total / pageSize));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;

        const start = total === 0 ? 0 : (currentPage - 1) * pageSize;
        const end = Math.min(start + pageSize, total);

        matched.slice(start, end).forEach(row => row.style.display = '');

        if (total === 0) {
            const emptyRow = document.querySelector(`#users-table-body tr.role-empty-row[data-role="${activeRole}"]`);
            if (emptyRow) emptyRow.style.display = '';
        }

        document.getElementById('entries-label').innerText = total === 0
            ? 'Showing 0 of 0 entries'
            : `Showing ${start + 1} to ${end} of ${total} entries`;

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const container = document.getElementById('pagination-controls');
        container.innerHTML = '';

        const makeBtn = (label, page, opts = {}) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'uas-page-btn' + (opts.active ? ' active' : '');
            btn.innerText = label;
            btn.disabled = !!opts.disabled;
            btn.addEventListener('click', () => { currentPage = page; renderTable(); });
            return btn;
        };

        container.appendChild(makeBtn('‹', Math.max(1, currentPage - 1), { disabled: currentPage === 1 }));

        const addDots = () => {
            const span = document.createElement('span');
            span.className = 'uas-page-dots';
            span.innerText = '…';
            container.appendChild(span);
        };

        const pages = new Set([1, totalPages, currentPage, currentPage - 1, currentPage + 1]);
        let lastPrinted = 0;
        for (let p = 1; p <= totalPages; p++) {
            if (!pages.has(p)) continue;
            if (p - lastPrinted > 1) addDots();
            container.appendChild(makeBtn(String(p), p, { active: p === currentPage }));
            lastPrinted = p;
        }

        container.appendChild(makeBtn('›', Math.min(totalPages, currentPage + 1), { disabled: currentPage === totalPages }));
    }

    function buildLocationFilters() {
        const rows = document.querySelectorAll(`#users-table-body tr.role-row[data-role="${activeRole}"]:not(.role-empty-row)`);
        const provinces = new Set(), cities = new Set(), barangays = new Set();

        rows.forEach(row => {
            if (row.dataset.province) provinces.add(row.dataset.province);
            if (row.dataset.city) cities.add(row.dataset.city);
            if (row.dataset.barangay) barangays.add(row.dataset.barangay);
        });

        fillSelect('provinceFilter', provinces, 'All Provinces');
        fillSelect('cityFilter', cities, 'All Municipalities');
        fillSelect('barangayFilter', barangays, 'All Barangays');
    }

    function fillSelect(id, values, placeholder) {
        const select = document.getElementById(id);
        const current = select.value;
        select.innerHTML = `<option value="">${placeholder}</option>`;
        Array.from(values).sort().forEach(v => {
            const opt = document.createElement('option');
            opt.value = v;
            opt.textContent = v;
            select.appendChild(opt);
        });
        if (Array.from(values).includes(current)) select.value = current;
    }

    function updateRoleCounts() {
        ['technician', 'farmer'].forEach(role => {
            const count = document.querySelectorAll(`#users-table-body tr.role-row[data-role="${role}"]:not(.role-empty-row)`).length;
            const el = document.getElementById('count-' + role);
            if (el) el.innerText = count;
        });
    }

    function exportCsv() {
        const rows = [['Name', 'Email', 'User Type', 'Province', 'City/Municipality', 'Barangay', 'Device Location', 'Joined', 'Status']];

        getMatchedRows().forEach(row => {
            const cells = row.querySelectorAll('td');
            rows.push([
                cells[0].querySelector('.fw-semibold')?.innerText.trim() || '',
                cells[0].querySelector('.text-muted')?.innerText.trim() || '',
                cells[1].innerText.trim(),
                cells[2].innerText.trim(),
                cells[3].innerText.trim(),
                cells[4].innerText.trim(),
                cells[5].innerText.trim(),
                cells[6].innerText.trim(),
                cells[7].innerText.trim(),
            ]);
        });

        const csv = rows.map(r => r.map(v => `"${(v || '').replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `${activeRole}_accounts.csv`;
        link.click();
    }

    document.getElementById('searchInput').addEventListener('input', () => { currentPage = 1; renderTable(); });
    document.getElementById('provinceFilter').addEventListener('change', () => { currentPage = 1; renderTable(); });
    document.getElementById('cityFilter').addEventListener('change', () => { currentPage = 1; renderTable(); });
    document.getElementById('barangayFilter').addEventListener('change', () => { currentPage = 1; renderTable(); });
    document.getElementById('pageSizeSelect').addEventListener('change', function () {
        pageSize = parseInt(this.value, 10) || 10;
        currentPage = 1;
        renderTable();
    });

    // ---------- Device Location: reverse-geocode raw coordinates into a readable address ----------
    // Free-tier Nominatim only allows ~1 request/sec, so we resolve pending pins one at a time
    // and cache results by "lat,lng" so re-renders / the 5s auto-refresh don't re-fetch the same spot.
    const geoCache = {};
    let geoResolveRunning = false;

    async function resolveGeoLocations() {
        if (geoResolveRunning) return;
        geoResolveRunning = true;

        const links = Array.from(document.querySelectorAll('.uas-geo-pending'));
        for (const link of links) {
            const lat = link.dataset.lat;
            const lng = link.dataset.lng;
            const key = `${lat},${lng}`;
            const textEl = link.querySelector('.uas-geo-text');
            if (!textEl) { link.classList.remove('uas-geo-pending'); continue; }

            if (geoCache[key]) {
                textEl.textContent = geoCache[key];
                link.classList.remove('uas-geo-pending');
                continue;
            }

            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=16`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await res.json();
                const label = (data && data.display_name) ? data.display_name : `${lat}, ${lng}`;
                geoCache[key] = label;
                textEl.textContent = label;
            } catch (err) {
                textEl.textContent = `${lat}, ${lng}`;
            }

            link.classList.remove('uas-geo-pending');
            await new Promise(r => setTimeout(r, 1100)); // stay under Nominatim's rate limit
        }

        geoResolveRunning = false;
    }

    document.addEventListener('DOMContentLoaded', () => {
        selectRole(activeRole);
        updateRoleCounts();
        resolveGeoLocations();

        const modalElement = document.getElementById('userInfoModal');
        if (modalElement) infoModal = new bootstrap.Modal(modalElement);
    });

    function viewFarmerInfo(userId) {
        if (infoModal) infoModal.show();
        document.getElementById('info_name').innerText = 'Loading...';

        const fetchUrl = `{{ url('technician/users') }}/${userId}/info`;

        fetch(fetchUrl, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(response => {
                if (!response.ok) throw new Error(`Server responded with status: ${response.status}`);
                return response.json();
            })
            .then(user => {
                document.getElementById('info_name').innerText = user.full_name || 'N/A';
                document.getElementById('info_email').innerText = user.email || 'N/A';
                document.getElementById('info_phone').innerText = user.phone || 'N/A';
                document.getElementById('info_dob').innerText = user.dob || 'N/A';
                document.getElementById('info_address').innerText = user.address || 'N/A';

                document.getElementById('info_farm_name').innerText = user.farm_name || 'N/A';
                document.getElementById('info_role').innerText = user.role || 'N/A';
                document.getElementById('info_size').innerText = (user.farm_size ? user.farm_size + ' hectares' : 'N/A');

                let locString = [];
                if (user.latitude && user.longitude) locString.push(`Lat: ${user.latitude}, Lng: ${user.longitude}`);
                if (user.municipality) locString.push(user.municipality);
                document.getElementById('info_location').innerText = locString.length ? locString.join(' | ') : 'N/A';

                document.getElementById('info_water').innerText = user.water_source || 'N/A';
                document.getElementById('info_id_type').innerText = user.id_type || 'N/A';

                let statusBadge = document.getElementById('info_status');
                if (statusBadge) {
                    statusBadge.innerText = (user.status || 'pending').toUpperCase();
                    statusBadge.className = 'badge ' + (user.status === 'approved' ? 'bg-success' : (user.status === 'declined' ? 'bg-danger' : 'bg-warning'));
                }

                const renderImage = (imgElement, noImgElement, base64Data) => {
                    if (base64Data && base64Data.length > 100) {
                        let src = base64Data.startsWith('data:image') ? base64Data : `data:image/jpeg;base64,${base64Data}`;
                        imgElement.src = src;
                        imgElement.style.display = 'block';
                        if (noImgElement) noImgElement.style.display = 'none';
                    } else {
                        imgElement.style.display = 'none';
                        if (noImgElement) noImgElement.style.display = 'block';
                    }
                };

                renderImage(document.getElementById('info_doc_img'), document.getElementById('no_doc'), user.document_photo);
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('info_name').innerText = 'Failed to load user info.';
            });
    }

    // 5-second silent refresh — reloads all three role sections and re-applies the active tab/filters/page
    setInterval(function () {
        const fetchUrl = new URL(window.location.origin + window.location.pathname);
        fetchUrl.searchParams.set('_t', Date.now());

        fetch(fetchUrl.toString(), {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache, no-store, must-revalidate'
            }
        })
            .then(response => {
                if (!response.ok) throw new Error('Network response failed');
                return response.text();
            })
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTableBody = doc.querySelector('#users-table-body');
                const currentTableBody = document.querySelector('#users-table-body');

                if (newTableBody && currentTableBody && currentTableBody.innerHTML !== newTableBody.innerHTML) {
                    currentTableBody.innerHTML = newTableBody.innerHTML;
                    updateRoleCounts();
                    buildLocationFilters();
                    renderTable();
                    resolveGeoLocations();
                }
            })
            .catch(error => console.error('Error fetching updates:', error));
    }, 5000);
</script>
@endsection