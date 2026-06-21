@extends('layouts.admin')

@section('title', isset($record) ? 'Edit Knowledge Entry • CROPSENSE AI' : 'Knowledge Editor • CROPSENSE AI')

@section('content')
<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold">{{ isset($record) ? 'Edit Knowledge Entry' : 'Knowledge Base Editor' }}</h2>
            <p class="text-secondary">Configure diagnosis fallback guidelines and recommendations parameters.</p>
        </div>
        <a href="{{ route('admin.knowledge.management') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Management
        </a>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger bg-danger bg-opacity-25 border-danger text-white mb-4">
        {{ session('error') }}
    </div>
@endif

@if(session('success'))
    <div class="alert alert-success bg-success text-white p-3 rounded mb-4">
        {{ session('success') }}
    </div>
@endif

<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="d-flex gap-2 mb-4">
            <button type="button" id="btn-disease" class="btn btn-primary px-4 py-2 flex-fill fw-bold" onclick="switchMode('disease')" {{ isset($record) && $record->type !== 'disease' ? 'disabled' : '' }}>
                <i class="fas fa-virus me-2"></i> Disease Information
            </button>
            <button type="button" id="btn-pest" class="btn btn-outline-danger px-4 py-2 flex-fill fw-bold" onclick="switchMode('pest')" {{ isset($record) && $record->type !== 'pest' ? 'disabled' : '' }}>
                <i class="fas fa-bug me-2"></i> Pest Information
            </button>
        </div>

        <div class="card bg-dark border-secondary shadow">
            <div class="card-body p-4">
                <form action="{{ route('admin.knowledge.store') }}" method="POST">
                    @csrf
                    
                    @if(isset($record))
                        <input type="hidden" name="record_id" value="{{ $record->id }}">
                    @endif
                    
                    <input type="hidden" name="type" id="form-type" value="{{ $record->type ?? 'disease' }}">

                    <div id="disease-form-section">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-primary">Disease Name</label>
                            <select name="disease" id="disease-select" class="form-select bg-dark text-white border-secondary">
                                <option value="">-- Choose Disease --</option>
                               @foreach($diseaseNames as $key => $name)
                                    @if(!in_array($key, $savedKeys ?? []) || (isset($record) && $record->disease === $key))
                                        <option value="{{ $key }}" {{ (isset($record) && $record->disease === $key) ? 'selected' : '' }}>{{ $name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">description / Description</label>
                            <textarea name="description" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Enter general description...">{{ (isset($record) && $record->type === 'disease') ? $record->description : '' }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Recommended Treatments</label>
                            <textarea name="treatments" rows="4" class="form-control bg-dark text-white border-secondary" placeholder="Enter treatment prescriptions...">{{ (isset($record) && $record->type === 'disease') ? $record->treatments : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Common Causes</label>
                            <textarea name="causes" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Enter environmental causes...">{{ (isset($record) && $record->type === 'disease') ? $record->causes : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Nutrient Deficiency</label>
                            <textarea name="nutrient_deficiency" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Describe critical missing elements...">{{ (isset($record) && $record->type === 'disease') ? $record->nutrient_deficiency : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Grain Damage</label>
                            <textarea name="grain_damage" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Describe side-effects on crop yield grains...">{{ (isset($record) && $record->type === 'disease') ? $record->grain_damage : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Prevention Tips</label>
                            <textarea name="prevention_tips" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Proactive management steps...">{{ (isset($record) && $record->type === 'disease') ? $record->prevention : '' }}</textarea>
                        </div>
                    </div>

                    <div id="pest-form-section" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-danger">Pest Name</label>
                            <select name="disease" id="pest-select" class="form-select bg-dark text-white border-secondary">
                                <option value="">-- Choose Pest --</option>
                               @foreach($pestNames as $key => $name)
                                    @if(!in_array($key, $savedKeys ?? []) || (isset($record) && $record->disease === $key))
                                        <option value="{{ $key }}" {{ (isset($record) && $record->disease === $key) ? 'selected' : '' }}>{{ $name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">About / Description</label>
                            <textarea name="description" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Enter general description...">{{ (isset($record) && $record->type === 'pest') ? $record->description : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Recommended Treatments</label>
                            <textarea name="treatments" rows="4" class="form-control bg-dark text-white border-secondary" placeholder="Enter pest eradication remedies...">{{ (isset($record) && $record->type === 'pest') ? $record->treatments : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Common Causes</label>
                            <textarea name="causes" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="What conditions invite outbreaks...">{{ (isset($record) && $record->type === 'pest') ? $record->causes : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Damage Symptoms</label>
                            <textarea name="damage_symptoms" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Identify leaf structural damage indicators...">{{ (isset($record) && $record->type === 'pest') ? $record->grain_damage : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Natural Enemies</label>
                            <textarea name="natural_enemies" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="List helpful predatory insects...">{{ (isset($record) && $record->type === 'pest') ? $record->natural_enemies : '' }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">Prevention</label>
                            <textarea name="prevention" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="Cultural prevention habits...">{{ (isset($record) && $record->type === 'pest') ? $record->prevention : '' }}</textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 py-2.5 fw-bold mt-4 shadow-sm">
                        <i class="fas fa-save me-2"></i> {{ isset($record) ? 'Update Knowledge Entry' : 'Save Knowledge Entry' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
function switchMode(mode) {
    const typeInput = document.getElementById('form-type');
    const diseaseBtn = document.getElementById('btn-disease');
    const pestBtn = document.getElementById('btn-pest');
    
    const diseaseSection = document.getElementById('disease-form-section');
    const pestSection = document.getElementById('pest-form-section');
    
    typeInput.value = mode;

    if (mode === 'disease') {
        diseaseBtn.className = 'btn btn-primary px-4 py-2 flex-fill fw-bold';
        pestBtn.className = 'btn btn-outline-danger px-4 py-2 flex-fill fw-bold';
        
        diseaseSection.style.display = 'block';
        pestSection.style.display = 'none';
        
        diseaseSection.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
        pestSection.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
    } else {
        diseaseBtn.className = 'btn btn-outline-primary px-4 py-2 flex-fill fw-bold';
        pestBtn.className = 'btn btn-danger px-4 py-2 flex-fill fw-bold';
        
        diseaseSection.style.display = 'none';
        pestSection.style.display = 'block';
        
        diseaseSection.querySelectorAll('input, select, textarea').forEach(el => el.disabled = true);
        pestSection.querySelectorAll('input, select, textarea').forEach(el => el.disabled = false);
    }
}

document.addEventListener("DOMContentLoaded", function() {
    switchMode(document.getElementById('form-type').value);
});
</script>
@endsection