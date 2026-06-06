
@extends('layouts.admin')

@section('title', 'Knowledge Management • RiceGuard AI')

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-white">Knowledge Management</h2>
            <p class="text-secondary">Review diagnostic criteria logic mapping structures.</p>
        </div>
        <a href="{{ route('admin.knowledge.editor') }}" class="btn btn-success fw-bold shadow-sm">
            <i class="fas fa-plus me-2"></i> Add New Entry
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card bg-dark border-secondary shadow-sm">
    <div class="card-body p-0">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr class="text-secondary border-secondary">
                    <th class="ps-4">Type</th>
                    <th>Name</th>
                    <th>Configured By</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($savedData as $row)
                <tr>
                    <td class="ps-4">
                        <span class="badge bg-{{ $row->type === 'disease' ? 'primary' : 'danger' }} bg-opacity-25 text-{{ $row->type === 'disease' ? 'primary' : 'danger' }} border border-{{ $row->type === 'disease' ? 'primary' : 'danger' }} px-2 py-1 fw-semibold text-uppercase">
                            {{ $row->type }}
                        </span>
                    </td>
                    <td class="fw-bold text-white">
                        {{ $row->type === 'disease' ? ($diseaseNames[$row->disease] ?? ucfirst($row->disease)) : ($pestNames[$row->disease] ?? ucfirst($row->disease)) }}
                    </td>
                    <td class="text-secondary">{{ $row->updated_by ?? 'System' }}</td>
                    <td class="text-end pe-4">
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-link text-secondary p-1 border-0" onclick="event.stopPropagation(); toggleDropdownMenu(event, '{{ $row->id }}')">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul id="dropdown-{{ $row->id }}" class="dropdown-menu dropdown-menu-end bg-secondary border-dark shadow py-1 position-absolute" style="display: none; z-index: 1050; min-width: 150px; right: 0;">
                                <li>
                                    <button type="button" class="dropdown-item text-white py-2" onclick='showDetails(@json($row))'>
    <i class="fas fa-eye me-2 text-info"></i> View Full Info
</button>
                                </li>
                                <li><hr class="dropdown-divider border-dark my-1"></li>
                                <li>
                                    <a class="dropdown-item text-white py-2" href="{{ route('admin.knowledge.editor', $row->id) }}">
                                        <i class="fas fa-edit me-2 text-warning"></i> Edit
                                    </a>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item text-white py-2" onclick="triggerDeleteAction('{{ $row->id }}')">
                                        <i class="fas fa-trash-alt me-2 text-danger"></i> Remove
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="modalTitle">Entry Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>
</div>

<form id="hidden-delete-form" method="POST" class="d-none">@csrf</form>
@endsection

@section('scripts')
<script>
    // -----------------------------------------
    // 1. MODAL LOGIC (Working perfectly)
    // -----------------------------------------
    window.showDetails = function(row) {
        const isPest = row.type === 'pest';
        const modalEl = document.getElementById('detailsModal');
        
        document.getElementById('modalTitle').innerText = (row.disease || 'Entry').toUpperCase().replace('_', ' ');
        document.getElementById('modalBody').innerHTML = `
            <div class="row mb-3">
                <div class="col-6"><strong>Type:</strong> ${row.type ? row.type.toUpperCase() : 'N/A'}</div>
                <div class="col-6"><strong>Updated By:</strong> ${row.updated_by || 'System'}</div>
            </div>
            <hr class="border-secondary">
            <h6>Treatments:</h6> <p class="text-secondary">${row.treatments || 'N/A'}</p>
            <h6>Causes:</h6> <p class="text-secondary">${row.causes || 'N/A'}</p>
            <h6>${isPest ? 'Natural Enemies' : 'Nutrient Deficiency'}:</h6> 
            <p class="text-secondary">${row.nutrient_deficiency || 'N/A'}</p>
            <h6>${isPest ? 'Damage Symptoms' : 'Grain Damage'}:</h6> 
            <p class="text-secondary">${row.grain_damage || 'N/A'}</p>
            <h6>Prevention:</h6> <p class="text-secondary">${row.prevention || 'N/A'}</p>
        `;

        try {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        } catch (e) {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    };

    document.querySelectorAll('.btn-close').forEach(btn => {
        btn.onclick = () => {
            const modal = document.getElementById('detailsModal');
            modal.classList.remove('show');
            modal.style.display = 'none';
        }
    });

    // -----------------------------------------
    // 2. DROPDOWN LOGIC (Fixed)
    // -----------------------------------------
    window.toggleDropdownMenu = function(e, rowId) {
        e.stopPropagation(); // Stop click from immediately triggering the document closer below
        
        const targetMenu = document.getElementById('dropdown-' + rowId);
        const isCurrentlyVisible = targetMenu.style.display === 'block';

        // Close all open dropdowns first
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');

        // If the one we clicked wasn't visible, open it
        if (!isCurrentlyVisible) {
            targetMenu.style.display = 'block';
        }
    };

    // Listen for clicks anywhere on the page to close the dropdowns
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
    });

    // -----------------------------------------
    // 3. DELETE LOGIC (Fixed)
    // -----------------------------------------
    // -----------------------------------------
    // 3. DELETE LOGIC (Corrected to match web.php)
    // -----------------------------------------
    window.triggerDeleteAction = function(id) {
        if (confirm('Are you sure you want to permanently remove this entry?')) {
            const form = document.getElementById('hidden-delete-form');
            
            // Generate the exact route URL matching 'admin.knowledge.delete'
            let actionUrl = "{{ route('admin.knowledge.delete', ':id') }}";
            actionUrl = actionUrl.replace(':id', id);
            
            form.action = actionUrl;
            
            // Because your route uses Route::post(), we just use the standard hidden POST form.
            form.submit();
        }
    };
</script>
@endsection