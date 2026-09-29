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
        <p class="text-secondary">Created automatically the first time Groq detects a class. Edits here are what the detection page reuses for that class and dialect.</p>
    </div>
</div>

<div class="card bg-dark border-info shadow-sm mb-5">
    <div class="card-body p-0">
        <table class="table table-dark table-hover mb-0 align-middle">
            <thead>
                <tr class="text-info border-info">
                    <th class="ps-4">Type</th>
                    <th>Discovered Class Name</th>
                    <th>Dialects</th>
                    <th>Last Updated By</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($groqClasses as $cls)
                <tr>
                    <td class="ps-4">
                        <span class="badge bg-{{ $cls['type'] === 'disease' ? 'primary' : 'danger' }} bg-opacity-25 text-{{ $cls['type'] === 'disease' ? 'primary' : 'danger' }} px-2 py-1 text-uppercase">
                            {{ $cls['type'] }}
                        </span>
                    </td>
                    <td class="fw-bold text-white">{{ $cls['name'] }}</td>
                    <td>
                        @foreach($cls['dialects'] as $lang => $d)
                            <span class="badge bg-secondary bg-opacity-50 text-white me-1">{{ $lang === 'cebuano' ? 'Cebuano' : ucfirst($lang) }}</span>
                        @endforeach
                    </td>
                    <td class="text-secondary" id="groq-updby-{{ $cls['key'] }}">{{ $cls['updated_by'] }}</td>
                    <td class="text-end pe-4">
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-link text-secondary p-1 border-0" onclick="event.stopPropagation(); toggleDropdownMenu(event, 'groq-{{ $cls['key'] }}')">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <ul id="dropdown-groq-{{ $cls['key'] }}" class="dropdown-menu dropdown-menu-end bg-secondary border-dark shadow py-1 position-absolute" style="display: none; z-index: 1050; min-width: 170px; right: 0;">
                                <li>
                                    <button type="button" class="dropdown-item text-white py-2" onclick="openGroqModalByKey('{{ $cls['key'] }}')">
                                        <i class="fas fa-eye me-2 text-info"></i> View / Update
                                    </button>
                                </li>
                                <li>
                                    <button type="button" class="dropdown-item text-white py-2" onclick="deleteGroqClass('{{ $cls['key'] }}')">
                                        <i class="fas fa-trash-alt me-2 text-danger"></i> Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-secondary py-5">No Groq detections saved yet. They appear here automatically after a farmer detects with Groq AI.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<style>
    .groq-dialect-btn { border: 1px solid #0dcaf0; color: #0dcaf0; background: transparent; }
    .groq-dialect-btn.active { background: #0dcaf0; color: #000; font-weight: 700; }
    .groq-dialect-btn:disabled { opacity: .35; border-color: #6c757d; color: #6c757d; cursor: not-allowed; }
    .groq-info-block { background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.08); border-radius: 12px; padding: 14px 16px; margin-bottom: 12px; }
    .groq-info-block textarea { background: transparent; border: 0; color: #cbd5e1; width: 100%; resize: none; padding: 0; margin-top: 8px; overflow: hidden; min-height: 3.5em; }
    .groq-info-block textarea:focus { outline: none; box-shadow: none; }
    .groq-info-block:focus-within { border-color: rgba(13,202,240,.6); }
</style>

<div class="modal fade" id="groqModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white border-info">
            <div class="modal-header border-info">
                <h5 class="modal-title text-info"><i class="fas fa-robot me-2"></i> <span id="groqModalTitle"></span>
                    <span id="groqModalType" class="badge ms-2 text-uppercase"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-2 mb-3" id="groqDialectBar">
                    @foreach(['tagalog' => 'Tagalog', 'english' => 'English', 'cebuano' => 'Cebuano (Bisaya)', 'hiligaynon' => 'Hiligaynon'] as $code => $label)
                        <button type="button" class="btn btn-sm groq-dialect-btn" data-lang="{{ $code }}" onclick="selectGroqDialect('{{ $code }}')">{{ $label }}</button>
                    @endforeach
                </div>
                <div id="groqFields"></div>
            </div>
            <div class="modal-footer border-info justify-content-between">
                <button type="button" class="btn btn-outline-danger" onclick="deleteGroqClass(groqState.key)">
                    <i class="fas fa-trash-alt me-1"></i> Delete Detection Data
                </button>
                <div class="d-flex align-items-center gap-2">
                    <span id="groqSaveNote" class="small text-success d-none"><i class="fas fa-check me-1"></i>Saved</span>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="groqSaveBtn" class="btn btn-info fw-bold text-dark d-none" onclick="saveGroqDialect()">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<form id="groq-delete-form" method="POST" action="{{ route('admin.knowledge.deleteGroqClass') }}" class="d-none">
    @csrf
    <input type="hidden" name="disease" id="groq-delete-disease">
</form>

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

    // -----------------------------------------
    // 5. GROQ DETECTION DATA (view / edit / save / delete)
    // -----------------------------------------
    const GROQ_SECTIONS = {
        disease: [
            ['description',         'Description',        'text-white',   'fa-circle-info'],
            ['treatments',          'Treatment',          'text-success', 'fa-spray-can-sparkles'],
            ['causes',              'Causes',             'text-warning', 'fa-question-circle'],
            ['prevention',          'Prevention',         'text-info',    'fa-shield-heart'],
            ['nutrient_deficiency', 'Nutrient Deficiency','text-warning', 'fa-leaf'],
            ['grain_damage',        'Grain Damage',       'text-danger',  'fa-seedling'],
        ],
        pest: [
            ['description',         'Description',        'text-white',   'fa-circle-info'],
            ['treatments',          'Treatment',          'text-success', 'fa-spray-can-sparkles'],
            ['causes',              'Causes',             'text-warning', 'fa-question-circle'],
            ['prevention',          'Prevention',         'text-info',    'fa-shield-heart'],
            ['grain_damage',        'Damage Symptoms',    'text-danger',  'fa-wheat-awn'],
            ['natural_enemies',     'Natural Enemies',    'text-success', 'fa-bug-slash'],
        ],
    };

    const groqState = { cls: null, key: null, lang: null, baseline: {} };

    function autoSize(el) { el.style.height = 'auto'; el.style.height = (el.scrollHeight + 2) + 'px'; }

    // Whole data set embedded once, escaped for safe use inside the page (apostrophes,
    // quotes and tags in the text can't break it, unlike inlining JSON in an onclick).
    const GROQ_CLASSES = @json($groqClasses, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP);

    window.openGroqModalByKey = function(key) {
        const cls = GROQ_CLASSES.find(c => c.key === key);
        if (cls) window.openGroqModal(cls);
    };

    window.openGroqModal = function(cls) {
        groqState.cls = cls;
        groqState.key = cls.key;
        document.getElementById('groqModalTitle').textContent = cls.name;
        const badge = document.getElementById('groqModalType');
        badge.textContent = cls.type;
        badge.className = 'badge ms-2 text-uppercase bg-' + (cls.type === 'disease' ? 'primary' : 'danger');

        document.querySelectorAll('#groqDialectBar .groq-dialect-btn').forEach(btn => {
            const has = !!cls.dialects[btn.dataset.lang];
            btn.disabled = !has;
            btn.title = has ? '' : 'Not detected in this dialect yet';
        });

        const first = ['tagalog', 'english', 'cebuano', 'hiligaynon'].find(l => cls.dialects[l]);
        groqState.lang = null;
        renderGroqDialect(first);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('groqModal')).show();
    };

    window.selectGroqDialect = function(lang) {
        if (lang === groqState.lang) return;
        if (isGroqDirty() && !confirm('You have unsaved changes in this dialect. Discard them?')) return;
        renderGroqDialect(lang);
    };

    function renderGroqDialect(lang) {
        const cls = groqState.cls;
        const data = cls.dialects[lang] || {};
        groqState.lang = lang;
        groqState.baseline = {};

        document.querySelectorAll('#groqDialectBar .groq-dialect-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.lang === lang);
        });

        const wrap = document.getElementById('groqFields');
        wrap.innerHTML = '';
        GROQ_SECTIONS[cls.type].forEach(([field, label, color, icon]) => {
            groqState.baseline[field] = data[field] || '';
            const block = document.createElement('div');
            block.className = 'groq-info-block';
            block.innerHTML = `<strong class="${color}"><i class="fa-solid ${icon} me-2"></i>${label}:</strong>`;
            const ta = document.createElement('textarea');
            ta.rows = 2;
            ta.dataset.field = field;
            ta.value = groqState.baseline[field];
            ta.addEventListener('input', () => { autoSize(ta); refreshGroqSaveBtn(); });
            block.appendChild(ta);
            wrap.appendChild(block);
        });
        // size after they're in the DOM
        // A hidden modal reports scrollHeight 0 (that collapsed the boxes and made
        // them look empty), so size only once visible; 'shown.bs.modal' below covers
        // the first open, this covers switching dialect while it is already open.
        if (document.getElementById('groqModal').classList.contains('show')) {
            requestAnimationFrame(() => wrap.querySelectorAll('textarea').forEach(autoSize));
        }
        refreshGroqSaveBtn();
    }

    function currentGroqValues() {
        const vals = {};
        document.querySelectorAll('#groqFields textarea').forEach(t => vals[t.dataset.field] = t.value);
        return vals;
    }

    function isGroqDirty() {
        const vals = currentGroqValues();
        return Object.keys(groqState.baseline).some(k => (vals[k] ?? '') !== groqState.baseline[k]);
    }

    function refreshGroqSaveBtn() {
        document.getElementById('groqSaveBtn').classList.toggle('d-none', !isGroqDirty());
        document.getElementById('groqSaveNote').classList.add('d-none');
    }

    window.saveGroqDialect = async function() {
        const btn = document.getElementById('groqSaveBtn');
        const vals = currentGroqValues();
        btn.disabled = true;
        try {
            const res = await fetch("{{ route('admin.knowledge.saveGroqEntry') }}", {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ disease: groqState.key, language: groqState.lang, ...vals })
            });
            const out = await res.json();
            if (!res.ok || !out.success) throw new Error(out.message || 'Save failed');

            // Make what was just saved the new baseline (and keep the in-memory copy current).
            groqState.baseline = { ...vals };
            groqState.cls.dialects[groqState.lang] = { ...(groqState.cls.dialects[groqState.lang] || {}), ...vals, updated_by: out.updated_by };
            const cell = document.getElementById('groq-updby-' + groqState.key);
            if (cell) cell.textContent = out.updated_by;

            btn.classList.add('d-none');
            document.getElementById('groqSaveNote').classList.remove('d-none');
        } catch (e) {
            alert('Could not save: ' + e.message);
        } finally {
            btn.disabled = false;
        }
    };

    window.deleteGroqClass = function(key) {
        if (!key) return;
        if (!confirm('Delete ALL Groq detection data for this class (every dialect and saved version)? The main knowledge base is not affected. The next Groq detection will generate it again.')) return;
        document.getElementById('groq-delete-disease').value = key;
        document.getElementById('groq-delete-form').submit();
    };

    document.getElementById('groqModal').addEventListener('shown.bs.modal', function() {
        document.querySelectorAll('#groqFields textarea').forEach(autoSize);
    });

    // Warn before closing the modal with unsaved edits.
    document.getElementById('groqModal').addEventListener('hide.bs.modal', function(e) {
        if (isGroqDirty() && !confirm('You have unsaved changes. Close without saving?')) e.preventDefault();
    });
</script>
@endsection