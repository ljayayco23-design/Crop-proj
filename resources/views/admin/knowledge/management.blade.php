@extends('layouts.admin')

@section('title', 'Knowledge Management • RICEGUARD AI')

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

<div class="card bg-dark border-secondary shadow-sm mb-5">
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

<div class="row mt-5 mb-3">
    <div class="col-12">
        <h3 class="fw-bold text-info"><i class="fas fa-robot me-2"></i> Groq AI Discovered Data</h3>
        <p class="text-secondary">Information generated automatically by the AI during farmer scans.</p>
    </div>
</div>

<div class="card bg-dark border-info shadow-sm mb-5">
    <div class="card-body p-0">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr class="text-info border-info">
                    <th class="ps-4">Type</th>
                    <th>Discovered Class Name</th>
                    <th>Last Updated By</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($groqData as $row)
                <tr>
                    <td class="ps-4">
                        <span class="badge bg-{{ $row->type === 'disease' ? 'primary' : 'danger' }} bg-opacity-25 text-{{ $row->type === 'disease' ? 'primary' : 'danger' }} px-2 py-1 text-uppercase">
                            {{ $row->type }}
                        </span>
                    </td>
                    <td class="fw-bold text-white">{{ ucwords(str_replace('_', ' ', $row->disease)) }}</td>
                    <td class="text-secondary">{{ $row->updated_by ?? 'Groq AI' }}</td>
                    <td class="text-end pe-4">
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-link text-secondary p-1 border-0" onclick="event.stopPropagation(); toggleDropdownMenu(event, 'groq-{{ $row->id }}')">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul id="dropdown-groq-{{ $row->id }}" class="dropdown-menu dropdown-menu-end bg-secondary border-dark shadow py-1 position-absolute" style="display: none; z-index: 1050; min-width: 150px; right: 0;">
                                <li>
                                    <button type="button" class="dropdown-item text-white py-2" onclick='showDetails(@json($row))'>
                                        <i class="fas fa-eye me-2 text-info"></i> View Full Info
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider border-dark my-1"></li>
                                <li>
                                    <button type="button" class="dropdown-item text-white py-2" onclick='openGroqEditModal(@json($row))'>
                                        <i class="fas fa-edit me-2 text-warning"></i> Edit
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item text-white py-2" onclick="triggerGroqDeleteAction('{{ $row->id }}')">
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

<div class="modal fade" id="groqEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form action="{{ route('admin.knowledge.updateGroq') }}" method="POST" class="modal-content bg-dark text-white border-info">
            @csrf
            <div class="modal-header border-info">
                <h5 class="modal-title text-info"><i class="fas fa-robot me-2"></i> Edit Groq Knowledge</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="groq_id">
                
                <div class="mb-3">
                    <label class="form-label text-info">Description</label>
                    <textarea name="description" id="groq_description" rows="3" class="form-control bg-dark text-white border-secondary"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-success">Treatments</label>
                    <textarea name="treatments" id="groq_treatments" rows="3" class="form-control bg-dark text-white border-secondary"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-warning">Causes</label>
                    <textarea name="causes" id="groq_causes" rows="3" class="form-control bg-dark text-white border-secondary"></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-primary">Nutrient Deficiency (Diseases)</label>
                        <textarea name="nutrient_deficiency" id="groq_nutrient" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-danger">Grain Damage / Symptoms</label>
                        <textarea name="grain_damage" id="groq_damage" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label text-info">Natural Enemies (Pests)</label>
                    <textarea name="natural_enemies" id="groq_enemies" rows="2" class="form-control bg-dark text-white border-secondary"></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Prevention</label>
                    <textarea name="prevention" id="groq_prevention" rows="3" class="form-control bg-dark text-white border-secondary"></textarea>
                </div>
            </div>
            <div class="modal-footer border-info">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-info fw-bold text-dark">Save Changes Globally</button>
            </div>
        </form>
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
    // 1. MODAL VIEW INFO LOGIC
    // -----------------------------------------
    window.showDetails = function(row) {
        const isPest = row.type === 'pest';
        const modalEl = document.getElementById('detailsModal');
        
        document.getElementById('modalTitle').innerText = (row.disease || 'Entry').toUpperCase().replace(/_/g, ' ');
        document.getElementById('modalBody').innerHTML = `
            <div class="row mb-3">
                <div class="col-6"><strong>Type:</strong> <span class="badge bg-${isPest ? 'danger' : 'primary'} text-uppercase">${row.type || 'N/A'}</span></div>
                <div class="col-6"><strong>Updated By:</strong> ${row.updated_by || 'System'}</div>
            </div>
            <hr class="border-secondary">
            <h6 class="text-info fw-bold">About / Description:</h6> 
            <p class="text-secondary">${row.description || 'N/A'}</p>
            
            <h6 class="text-success fw-bold">Treatments:</h6> 
            <p class="text-secondary">${row.treatments || 'N/A'}</p>
            
            <h6 class="text-warning">Causes:</h6> 
            <p class="text-secondary">${row.causes || 'N/A'}</p>

            <h6 class="text-danger fw-bold">${isPest ? 'Damage Symptoms' : 'Grain Damage'}:</h6> 
            <p class="text-secondary">${row.grain_damage || 'N/A'}</p>

            
            <h6 class="text-primary fw-bold">${isPest ? 'Natural Enemies' : 'Nutrient Deficiency'}:</h6> 
            <p class="text-secondary">${isPest ? (row.natural_enemies || 'N/A') : (row.nutrient_deficiency || 'N/A')}</p>
            
            
            <h6 class="text-light fw-bold">Prevention:</h6> 
            <p class="text-secondary">${row.prevention || 'N/A'}</p>
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
    // 2. GROQ MODAL FILL LOGIC
    // -----------------------------------------
    window.openGroqEditModal = function(row) {
        document.getElementById('groq_id').value = row.id;
        document.getElementById('groq_description').value = row.description || '';
        document.getElementById('groq_treatments').value = row.treatments || '';
        document.getElementById('groq_causes').value = row.causes || '';
        document.getElementById('groq_nutrient').value = row.nutrient_deficiency || '';
        document.getElementById('groq_damage').value = row.grain_damage || '';
        document.getElementById('groq_enemies').value = row.natural_enemies || '';
        document.getElementById('groq_prevention').value = row.prevention || '';
        
        new bootstrap.Modal(document.getElementById('groqEditModal')).show();
    };

    // -----------------------------------------
    // 3. DROPDOWN TOGGLE LOGIC
    // -----------------------------------------
    window.toggleDropdownMenu = function(e, rowId) {
        e.stopPropagation();
        
        const targetMenu = document.getElementById('dropdown-' + rowId);
        const isCurrentlyVisible = targetMenu.style.display === 'block';

        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');

        if (!isCurrentlyVisible) {
            targetMenu.style.display = 'block';
        }
    };

    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.style.display = 'none');
    });

    // -----------------------------------------
    // 4. ACTION SUBMISSIONS (DELETE LOGIC)
    // -----------------------------------------
    window.triggerDeleteAction = function(id) {
        if (confirm('Are you sure you want to permanently remove this entry?')) {
            const form = document.getElementById('hidden-delete-form');
            let actionUrl = "{{ route('admin.knowledge.delete', ':id') }}";
            form.action = actionUrl.replace(':id', id);
            form.submit();
        }
    };

    window.triggerGroqDeleteAction = function(id) {
        if (confirm('Are you sure you want to permanently remove this Groq AI entry?')) {
            const form = document.getElementById('hidden-delete-form');
            let actionUrl = "{{ route('admin.knowledge.deleteGroq', ':id') }}";
            form.action = actionUrl.replace(':id', id);
            form.submit();
        }
    };
</script>
@endsection