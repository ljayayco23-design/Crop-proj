@extends('layouts.admin')

@section('title', 'RICEGUARD AI • Permission Management')

@section('content')
<style>
    /* =====================================================================
       MOBILE: collapse each module's permission table into stacked cards,
       the same pattern used on the User Log page (data-label -> CSS Grid
       area). Only kicks in below 768px — tablets/desktop keep the plain
       table above untouched.
    ===================================================================== */
    @media (max-width: 767.98px) {
        .page-header { flex-wrap: wrap; }

        .pm-module-card .card-header { font-size: .95rem; }
        .pm-module-card .card-body { padding: 0; }

        .pm-table thead { display: none; }
        .pm-table,
        .pm-table tbody { display: block; width: 100%; }

        .pm-table tbody tr {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-areas:
                "role     role"
                "viewinfo viewinfo"
                "create   edit"
                "delete   hide";
            column-gap: 10px;
            border: none;
            border-bottom: 1px solid rgba(0,0,0,.08);
            padding: 14px 14px 10px;
            margin: 0;
        }
        .pm-table tbody tr:last-child { border-bottom: none; }

        .pm-table tbody td {
            display: block;
            width: 100%;
            border: none;
            padding: 0;
            white-space: normal;
        }

        .pm-table tbody td[data-label="Role"] {
            grid-area: role;
            font-size: 1rem;
            padding-bottom: 10px;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(0,0,0,.08);
            text-align: left !important;
        }

        /* View|Info stays ONE table cell, but on mobile we lay its two
           switches out as their own boxed buttons so they visually match
           Create/Edit/Delete/Hide below them, instead of a plain row. */
        .pm-table tbody td[data-label="View | Info"] {
            grid-area: viewinfo;
            margin-bottom: 8px;
        }
        .pm-table tbody td[data-label="View | Info"] .d-flex {
            display: grid !important;
            grid-template-columns: 1fr 1fr;
            gap: 10px !important;
        }

        .pm-table tbody td[data-label="Create"] { grid-area: create; }
        .pm-table tbody td[data-label="Edit"]   { grid-area: edit; }
        .pm-table tbody td[data-label="Delete"] { grid-area: delete; }
        .pm-table tbody td[data-label="Hide"]   { grid-area: hide; }

        .pm-table tbody td[data-label="Create"],
        .pm-table tbody td[data-label="Edit"],
        .pm-table tbody td[data-label="Delete"],
        .pm-table tbody td[data-label="Hide"] {
            background: rgba(0,0,0,.03);
            border: 1px solid rgba(0,0,0,.06);
            border-radius: 10px;
            padding: 8px 10px 10px;
            margin-bottom: 8px;
        }
        /* View / Info: box goes on a plain wrapper div (.pm-vi-box), NOT on
           .form-check — the theme's own switch styling was overriding
           background/border set directly on .form-check, which is why the
           box wasn't showing. !important guards against that here too. */
        .pm-table tbody td[data-label="View | Info"] .pm-vi-box {
            background: rgba(0,0,0,.03) !important;
            border: 1px solid rgba(0,0,0,.06) !important;
            border-radius: 10px !important;
            padding: 8px 10px 10px !important;
        }
        .pm-table tbody td[data-label="View | Info"] .pm-vi-box .form-check {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
        }
        .pm-table tbody td[data-label="Create"]::before,
        .pm-table tbody td[data-label="Edit"]::before,
        .pm-table tbody td[data-label="Delete"]::before,
        .pm-table tbody td[data-label="Hide"]::before {
            content: attr(data-label);
            display: block;
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
            margin-bottom: 4px;
            text-align: center;
        }
        .pm-table tbody td[data-label="Create"] .form-check,
        .pm-table tbody td[data-label="Edit"] .form-check,
        .pm-table tbody td[data-label="Delete"] .form-check,
        .pm-table tbody td[data-label="Hide"] .form-check {
            justify-content: center !important;
        }

        /* View / Info boxes: switch + label stacked and centered, same
           footprint as the Create/Edit/Delete/Hide boxes beside them. */
        .pm-table tbody td[data-label="View | Info"] .pm-vi-box {
            display: flex;
        }
        .pm-table tbody td[data-label="View | Info"] .form-check {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            margin: 0 !important;
            padding-left: 0 !important;
            width: 100%;
        }
        /* Bootstrap's .form-check-input normally floats left with a
           margin-left:-1.5em that cancels the parent's padding-left:1.5em.
           That trick only works in block/float layout — once the parent
           becomes a flex column (above), the negative margin has nothing
           to cancel and just shoves the switch off-center. Zero it out so
           the switch sits flush, matching Create/Edit/Delete/Hide beside it. */
        .pm-table tbody td[data-label="View | Info"] .form-check-input {
            margin-left: 0 !important;
            float: none !important;
        }
        .pm-table tbody td[data-label="View | Info"] .form-check-label {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
            order: -1;
        }

        .pm-save-btn { width: 100%; }
    }

    /* =====================================================================
       Locked (farmer) rows: render with the same switch layout as the
       editable roles (all off, disabled) instead of a plain "Blocked"
       row, and never take on Bootstrap's light .table-secondary tint —
       keep them on the same background as every other row/card.
    ===================================================================== */
    .pm-table tbody tr.pm-locked-row,
    .pm-table tbody tr.pm-locked-row > td {
        background-color: transparent !important;
    }
    .pm-table tbody tr.pm-locked-row .form-check-input:disabled {
        opacity: .35;
        cursor: not-allowed;
    }
    .pm-table tbody tr.pm-locked-row .form-check-label {
        opacity: .6;
    }
    .pm-table tbody tr.pm-locked-row td[data-label="Role"] i.fa-lock {
        opacity: .6;
    }
</style>

<div class="page-header d-flex justify-content-between align-items-start mb-4">
    <div class="page-header-title">
        <h5 class="m-b-10">Permission Management</h5>
        <p class="text-muted mb-0">Control what each role can do per module.</p>
    </div>
</div>

@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<form method="POST" action="{{ route('admin.permissions.update') }}">
    @csrf

    @foreach ($modules as $moduleKey => $moduleLabel)
        <div class="card mb-4 pm-module-card">
            <div class="card-header">
                <strong>{{ $moduleLabel }}</strong>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-bordered mb-0 align-middle pm-table">
                    <thead>
                        <tr>
                            <th style="width: 16%">Role</th>
                            <th class="text-center">View | Info</th>
                            <th class="text-center">Create</th>
                            <th class="text-center">Edit</th>
                            <th class="text-center">Delete</th>
                            <th class="text-center">Hide</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Editable rows: technician always, admin only when the actor is developer.
                             "View" is a master switch for that role's whole User Log page; "Info"
                             gates the Info button/panel for that role's accounts specifically. Both
                             live in one bordered cell so they read as one grouped control. --}}
                        @foreach ($editableRoles as $role)
                            @php $row = $matrices[$moduleKey][$role] ?? []; @endphp
                            <tr>
                                <td class="text-capitalize fw-semibold" data-label="Role">{{ $role }}</td>
                                <td class="text-center" data-label="View | Info">
                                    <div class="d-flex justify-content-center gap-3">
                                        <div class="pm-vi-box">
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="{{ $moduleKey }}-{{ $role }}-view"
                                                    name="permissions[{{ $moduleKey }}][{{ $role }}][can_view]"
                                                    value="1"
                                                    {{ !empty($row['can_view']) ? 'checked' : '' }}
                                                >
                                                <label class="form-check-label small" for="{{ $moduleKey }}-{{ $role }}-view">View</label>
                                            </div>
                                        </div>
                                        <div class="pm-vi-box">
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="{{ $moduleKey }}-{{ $role }}-info"
                                                    name="permissions[{{ $moduleKey }}][{{ $role }}][can_info]"
                                                    value="1"
                                                    {{ !empty($row['can_info']) ? 'checked' : '' }}
                                                >
                                                <label class="form-check-label small" for="{{ $moduleKey }}-{{ $role }}-info">Info</label>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                @foreach (['can_create' => 'Create', 'can_edit' => 'Edit', 'can_delete' => 'Delete', 'hide_module' => 'Hide'] as $field => $label)
                                    <td class="text-center" data-label="{{ $label }}">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input
                                                class="form-check-input"
                                                type="checkbox"
                                                role="switch"
                                                name="permissions[{{ $moduleKey }}][{{ $role }}][{{ $field }}]"
                                                value="1"
                                                {{ !empty($row[$field]) ? 'checked' : '' }}
                                            >
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        {{-- Locked rows: farmer has no admin/technician-style panel today, so
                             every switch is shown off and disabled — same layout as the
                             editable roles above, just locked and greyed out. --}}
                        @foreach ($lockedRoles as $role)
                            <tr class="pm-locked-row">
                                <td class="text-capitalize fw-semibold" data-label="Role">
                                    {{ $role }}
                                    <i class="fa-solid fa-lock ms-2 small" title="Locked — cannot be changed"></i>
                                </td>
                                <td class="text-center" data-label="View | Info">
                                    <div class="d-flex justify-content-center gap-3">
                                        <div class="pm-vi-box">
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="{{ $moduleKey }}-{{ $role }}-view"
                                                    disabled
                                                >
                                                <label class="form-check-label small" for="{{ $moduleKey }}-{{ $role }}-view">View</label>
                                            </div>
                                        </div>
                                        <div class="pm-vi-box">
                                            <div class="form-check form-switch mb-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    id="{{ $moduleKey }}-{{ $role }}-info"
                                                    disabled
                                                >
                                                <label class="form-check-label small" for="{{ $moduleKey }}-{{ $role }}-info">Info</label>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                @foreach (['Create', 'Edit', 'Delete', 'Hide'] as $label)
                                    <td class="text-center" data-label="{{ $label }}">
                                        <div class="form-check form-switch d-flex justify-content-center">
                                            <input class="form-check-input" type="checkbox" role="switch" disabled>
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
              </div>
            </div>
        </div>
    @endforeach

    <button type="submit" class="btn btn-primary px-4 pm-save-btn">
        <i class="fas fa-save me-1"></i> Save Permissions
    </button>
</form>
@endsection