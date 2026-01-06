@extends('layouts.app')

@section('content')

    @include('partials.menu')

    @php
        $filters = $filters ?? [];
        $showArchived = $showArchived ?? false;
    @endphp

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const activeSection = document.getElementById('active-users');
            const archivedSection = document.getElementById('archived-users');
            const searchInput = document.getElementById('users-search');
            const searchHiddenInput = document.getElementById('users-filter-search');
            const roleFilter = document.getElementById('role-filter');
            const employmentTypeFilter = document.getElementById('employment-type-filter');
            const archiveToggleBtn = document.getElementById('archive-toggle-users');
            const archiveToggleLabel = archiveToggleBtn ? archiveToggleBtn.querySelector('.button-label') : null;
            const archiveToggleIcon = archiveToggleBtn ? archiveToggleBtn.querySelector('.button-icon i') : null;

            const $wrapper = document.querySelector('.wrapper.users');
            let showingArchived = $wrapper && ($wrapper.dataset.archived === '1' || $wrapper.dataset.archived === 1);

            const filterForm = document.getElementById('users-filter-form');
            let searchSubmitTimer = null;

            function syncSearchToHidden() {
                if (searchHiddenInput && searchInput) {
                    searchHiddenInput.value = searchInput.value;
                }
            }

            function setView(showArchived) {
                showingArchived = showArchived;

                const url = new URL(window.location.href);

                if (showArchived) {
                    url.searchParams.set('archived', '1');
                } else {
                    url.searchParams.delete('archived');
                }

                url.searchParams.delete('page');
                url.searchParams.delete('archived_page');

                window.location.href = url.toString();
            }

            function submitFilters() {
                if (!filterForm) {
                    return;
                }

                syncSearchToHidden();
                filterForm.submit();
            }

            // If archiveToggleBtn isn't found by id (older markup), try to find a button by text as a fallback
            if (!archiveToggleBtn) {
                const allButtons = Array.from(document.querySelectorAll('button, a'));
                const fallback = allButtons.find(el => (el.innerText || '').trim().toLowerCase().includes('view archived') || (el.innerText || '').trim().toLowerCase().includes('back to users'));
                if (fallback) {
                    // assign id so other code can reuse it
                    fallback.id = 'archive-toggle-users';
                }
            }

            if (archiveToggleBtn) {
                archiveToggleBtn.addEventListener('click', function () {
                    setView(!showingArchived);
                });
            } else {
                // try again after potential fallback assignment
                const maybeBtn = document.getElementById('archive-toggle-users');
                if (maybeBtn) {
                    maybeBtn.addEventListener('click', function () {
                        setView(!showingArchived);
                    });
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    syncSearchToHidden();

                    if (searchSubmitTimer) {
                        clearTimeout(searchSubmitTimer);
                    }

                    searchSubmitTimer = setTimeout(function () {
                        submitFilters();
                    }, 400);
                });
            }

            document.addEventListener('click', function (e) {
                const row = e.target.closest('.table-container.users-table tbody tr, .table-container.archived-users-table tbody tr');
                if (!row) {
                    return;
                }

                if (e.target.closest('a, button, input, textarea, select, label, form')) {
                    return;
                }

                const preview = row.querySelector('.user-row-preview');
                if (!preview) {
                    return;
                }

                const name = preview.dataset.name || '';
                const email = preview.dataset.email || '';
                const role = preview.dataset.role || '';
                const employmentType = preview.dataset.employmentType || '';
                const employmentStartDate = preview.dataset.employmentStartDate || '';
                const birthdate = preview.dataset.birthdate || '';
                const gender = preview.dataset.gender || '';
                const sssNumber = preview.dataset.sssNumber || '';
                const philhealthNumber = preview.dataset.philhealthNumber || '';
                const pagibigNumber = preview.dataset.pagibigNumber || '';
                const registeredAt = preview.dataset.registered || '';
                const archivedAt = preview.dataset.archivedAt || '';

                const setText = function (id, value) {
                    const el = document.getElementById(id);
                    if (el) {
                        el.textContent = value || 'N/A';
                    }
                };

                setText('user-details-name', name);
                setText('user-details-email', email);
                setText('user-details-role', role);
                setText('user-details-employment-type', employmentType);
                setText('user-details-employment-start-date', employmentStartDate);
                setText('user-details-birthdate', birthdate);
                setText('user-details-gender', gender);
                setText('user-details-sss', sssNumber);
                setText('user-details-philhealth', philhealthNumber);
                setText('user-details-pagibig', pagibigNumber);
                setText('user-details-registered', registeredAt);
                setText('user-details-archived', archivedAt);

                const modalEl = document.getElementById('userDetailsModal');
                if (!modalEl) {
                    return;
                }

                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            });

            if (roleFilter) {
                roleFilter.addEventListener('change', submitFilters);
            }

            if (employmentTypeFilter) {
                employmentTypeFilter.addEventListener('change', submitFilters);
            }

            if (filterForm) {
                filterForm.addEventListener('submit', function () {
                    syncSearchToHidden();
                });
            }

            // Action buttons (edit/archive/restore/delete)
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.action-btn');
                if (!btn) return;

                const id = btn.getAttribute('data-id');
                const action = Array.from(btn.classList).find(cls =>
                    ['edit', 'archive', 'restore', 'delete'].includes(cls)
                );

                switch (action) {
                    case 'edit': {
                        const modalEl = document.getElementById('addUsersModal');
                        const form = document.getElementById('addUsersForm');
                        if (!modalEl || !form) {
                            alert('Edit form is not available.');
                            break;
                        }

                        // Mark modal as edit mode so JS validators can adjust behaviour
                        modalEl.dataset.mode = 'edit';

                        // Point form to update route with PUT method
                        form.action = '/users/' + id;

                        let methodInput = form.querySelector('input[name="_method"]');
                        if (!methodInput) {
                            methodInput = document.createElement('input');
                            methodInput.type = 'hidden';
                            methodInput.name = '_method';
                            form.appendChild(methodInput);
                        }
                        methodInput.value = 'PUT';

                        const fullNameInput = form.querySelector('[name="full_name"]');
                        const emailInput = form.querySelector('[name="email"]');
                        const roleSelect = form.querySelector('[name="role"]');
                        const employmentTypeSelect = form.querySelector('[name="employment_type"]');
                        const employmentStartDateInput = form.querySelector('[name="employment_start_date"]');
                        const passwordInput = form.querySelector('[name="password"]');
                        const birthdateInput = form.querySelector('[name="birthdate"]');
                        const genderSelect = form.querySelector('[name="gender"]');
                        const sssInput = form.querySelector('[name="sss_number"]');
                        const philhealthInput = form.querySelector('[name="philhealth_number"]');
                        const pagibigInput = form.querySelector('[name="pagibig_number"]');

                        const name = btn.getAttribute('data-name') || '';
                        const email = btn.getAttribute('data-email') || '';
                        const role = btn.getAttribute('data-role') || '';
                        const employmentType = btn.getAttribute('data-employment-type') || '';
                        const employmentStartDate = btn.getAttribute('data-employment-start-date') || '';
                        const birthdate = btn.getAttribute('data-birthdate') || '';
                        const gender = btn.getAttribute('data-gender') || '';
                        const sssNumber = btn.getAttribute('data-sss-number') || '';
                        const philhealthNumber = btn.getAttribute('data-philhealth-number') || '';
                        const pagibigNumber = btn.getAttribute('data-pagibig-number') || '';

                        if (fullNameInput) {
                            fullNameInput.value = name;
                            fullNameInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        if (emailInput) {
                            emailInput.value = email;
                            emailInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        if (roleSelect && role) {
                            let matched = false;
                            Array.from(roleSelect.options).forEach(function (opt) {
                                if (opt.text.trim().toLowerCase() === role.trim().toLowerCase()) {
                                    roleSelect.value = opt.value;
                                    matched = true;
                                }
                            });
                            if (!matched) {
                                roleSelect.value = '';
                            }
                            roleSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        if (employmentTypeSelect) {
                            employmentTypeSelect.value = employmentType || '';
                            employmentTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        if (employmentStartDateInput) {
                            employmentStartDateInput.value = employmentStartDate || '';
                            employmentStartDateInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        if (birthdateInput) {
                            birthdateInput.value = birthdate || '';
                            birthdateInput.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        if (genderSelect) {
                            genderSelect.value = gender || '';
                            genderSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        }

                        if (sssInput) {
                            sssInput.value = sssNumber || '';
                            sssInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        if (philhealthInput) {
                            philhealthInput.value = philhealthNumber || '';
                            philhealthInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        if (pagibigInput) {
                            pagibigInput.value = pagibigNumber || '';
                            pagibigInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        if (passwordInput) {
                            // Leave password blank for edits; filled value means change password
                            passwordInput.value = '';
                            passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                        }

                        // Show modal
                        const bsModal = new bootstrap.Modal(modalEl);
                        bsModal.show();
                        break;
                    }

                    case 'archive': {
                        const message = 'Are you sure you want to archive this user?';

                        const proceedArchive = function () {
                            fetch(`/users/${id}/archive`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                }
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        window.location.reload();
                                    } else {
                                        alert('Failed to archive user: ' + (data.message || 'Unknown error'));
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('An error occurred while archiving the user');
                                });
                        };

                        if (typeof window.appConfirm === 'function') {
                            window.appConfirm(message).then(function (ok) {
                                if (!ok) {
                                    return;
                                }
                                proceedArchive();
                            });
                        } else if (window.confirm(message)) {
                            proceedArchive();
                        }

                        break;
                    }

                    case 'restore': {
                        const message = 'Restore this user?';

                        const proceedRestore = function () {
                            fetch(`/users/${id}/restore`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json'
                                }
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        window.location.reload();
                                    } else {
                                        alert('Failed to restore user: ' + (data.message || 'Unknown error'));
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    alert('An error occurred while restoring the user');
                                });
                        };

                        if (typeof window.appConfirm === 'function') {
                            window.appConfirm(message).then(function (ok) {
                                if (!ok) {
                                    return;
                                }
                                proceedRestore();
                            });
                        } else if (window.confirm(message)) {
                            proceedRestore();
                        }

                        break;
                    }

                    case 'delete':
                        const deleteForm = document.getElementById('deleteUserForm');
                        if (deleteForm) {
                            deleteForm.action = `/users/${id}`;

                            const deleteModal = new bootstrap.Modal(document.getElementById('deleteUsersModal'));
                            deleteModal.show();

                            deleteForm.onsubmit = function (event) {
                                event.preventDefault();

                                fetch(deleteForm.action, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json'
                                    }
                                })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            window.location.reload();
                                        } else {
                                            alert('Failed to delete user: ' + (data.message || 'Unknown error'));
                                            deleteModal.hide();
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('An error occurred while deleting the user');
                                        deleteModal.hide();
                                    });
                            };
                        }
                        break;
                }
            });

            syncSearchToHidden();

            // Hard-override cursor on users tables so rows never look clickable.
            document.querySelectorAll('.table-container.users-table tbody tr, .table-container.archived-users-table tbody tr')
                .forEach(function (row) {
                    row.style.cursor = 'pointer';
                });

            // Ensure the New button opens the modal in create mode
            const addUserBtn = document.getElementById('users-add-users');
            if (addUserBtn) {
                addUserBtn.addEventListener('click', function () {
                    const modalEl = document.getElementById('addUsersModal');
                    const form = document.getElementById('addUsersForm');
                    if (!modalEl || !form) {
                        return;
                    }

                    modalEl.dataset.mode = 'create';

                    const passwordInput = form.querySelector('[name="password"]');
                    if (passwordInput) {
                        passwordInput.value = '';
                        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    form.action = '{{ route('users.store') }}';

                    const methodInput = form.querySelector('input[name="_method"]');
                    if (methodInput) {
                        methodInput.parentNode.removeChild(methodInput);
                    }

                    // Reset fields; console/modal-step scripts will update their views
                    form.reset();
                });
            }

        });
    </script>

    <div class="wrapper {{ $pageClass }}" data-archived="{{ $showArchived ? '1' : '0' }}">

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-user-group"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Manage system user accounts and roles</p>
                </div>
            </div>
        </div>

        <div class="container {{ $pageClass }} tab">

            <form id="users-filter-form" method="GET" action="{{ route('users') }}" class="d-flex align-items-end" style="gap: 8px;">
                @if ($showArchived)
                    <input type="hidden" name="archived" value="1">
                @endif

                @include('components.search', [
                    'searchClass' => 'users',
                    'searchId' => 'users-search',
                    'searchValue' => $filters['search'] ?? '',
                ])
                <input type="hidden" name="search" id="users-filter-search" value="{{ $filters['search'] ?? '' }}">

                @php
                    $currentRoleKey = strtolower(auth()->user()->role ?? '');
                @endphp
                <select id="role-filter" name="role" class="tab-select">
                    <option value="">All roles</option>
                    @if ($currentRoleKey === 'superadmin')
                        <option value="Admin" @if(($filters['role'] ?? '') === 'Admin') selected @endif>Admin</option>
                    @endif
                    <option value="HR" @if(($filters['role'] ?? '') === 'HR') selected @endif>HR</option>
                    <option value="Manager" @if(($filters['role'] ?? '') === 'Manager') selected @endif>Manager</option>
                    <option value="Supervisor" @if(($filters['role'] ?? '') === 'Supervisor') selected @endif>Supervisor</option>
                    <option value="Worker" @if(($filters['role'] ?? '') === 'Worker') selected @endif>Worker</option>
                </select>

                <select id="employment-type-filter" name="employment_type" class="tab-select">
                    <option value="">All types</option>
                    <option value="regular" @if(($filters['employment_type'] ?? '') === 'regular') selected @endif>Regular</option>
                    <option value="part_time" @if(($filters['employment_type'] ?? '') === 'part_time') selected @endif>Part-time</option>
                </select>
            </form>

            <div class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonVar' => 'users-add',
                    'buttonSrc' => 'users',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                    'buttonModal' => true,
                    'buttonTarget' => 'addUsersModal'
                ])

                {{-- Ensure archive toggle has an ID the JS can find --}}
                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonVar' => 'archive-toggle',
                    'buttonSrc' => 'users',
                    'buttonIcon' => $showArchived ? '<i class="fa-solid fa-users"></i>' : '<i class="fa-solid fa-clock-rotate-left"></i>',
                    'buttonLabel' => $showArchived ? 'Back to users' : 'View archived',
                    'buttonModal' => false,
                    'btnAttribute' => 'id="archive-toggle-users"'
                ])

            </div>

        </div>

        <div class="container {{ $pageClass }} table-component">

            <!-- Active Users Table -->
            <div id="active-users" class="tab-content active" style="{{ $showArchived ? 'display: none;' : '' }}">
                @include('components.table', [
                    'tableClass' => 'users-table',
                    'tableCol' => [
                        'user',
                        'email',
                        'role',
                        'registered',
                        'actions',
                    ],
                    'tableLabel' => [
                        'User',
                        'Email',
                        'Role',
                        'Registered',
                        'Actions',
                    ],
                    'tableData' => $users->map(function ($user) {
                        $displayName = $user->full_name ?? $user->username;
                        $employmentType = $user->employment_type ?? \App\Models\User::EMPLOYMENT_TYPE_REGULAR;
                        $employmentTypeLabel = $employmentType === \App\Models\User::EMPLOYMENT_TYPE_PART_TIME ? 'Part-time' : 'Regular';
                        $registeredAt = now()->parse($user->created_at)->format('M d, Y');

                        return [
                            // User cell with avatar initials and name
                            '<div class="d-flex align-items-center">'
                                . '<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:12px;font-weight:600;">'
                                    . substr($displayName, 0, 2)
                                . '</div>'
                                . '<div class="d-flex flex-column">'
                                    . '<span class="fw-semibold">' . e($displayName) . '</span>'
                                    . '<span class="badge bg-light text-dark mt-1" style="font-size: 0.7rem;">' . e($employmentTypeLabel) . '</span>'
                                . '</div>'
                            . '</div>'
                            . '<span class="user-row-preview" style="display:none"'
                                . ' data-id="' . e($user->id) . '"'
                                . ' data-name="' . e($displayName) . '"'
                                . ' data-email="' . e($user->email) . '"'
                                . ' data-role="' . e($user->role ?? '') . '"'
                                . ' data-employment-type="' . e($employmentTypeLabel) . '"'
                                . ' data-employment-start-date="' . e($user->employment_start_date ? now()->parse($user->employment_start_date)->format('M d, Y') : '') . '"'
                                . ' data-birthdate="' . e($user->userCredential?->birthdate ? now()->parse($user->userCredential->birthdate)->format('M d, Y') : '') . '"'
                                . ' data-gender="' . e($user->userCredential?->gender ?? '') . '"'
                                . ' data-sss-number="' . e($user->userCredential?->sss_number ?? '') . '"'
                                . ' data-philhealth-number="' . e($user->userCredential?->philhealth_number ?? '') . '"'
                                . ' data-pagibig-number="' . e($user->userCredential?->pagibig_number ?? '') . '"'
                                . ' data-registered="' . e($registeredAt) . '"'
                            . '></span>',
                            // Email
                            e($user->email),
                            // Role pill with data-role attribute for reliable JS lookup
                            '<span data-role="' . e($user->role) . '" class="badge rounded-pill ' 
                                . ($user->role === 'Admin'
                                    ? 'bg-warning text-dark'
                                    : ($user->role === 'Superadmin'
                                        ? 'bg-danger'
                                        : 'bg-primary'))
                                . '">'
                                . e($user->role ?? 'N/A')
                            . '</span>',
                            // Registered date
                            $registeredAt,
                            // Actions with data attributes for JS
                            '<div class="users-actions d-flex align-items-center gap-1">'
                                . '<button type="button" class="btn btn-outline-warning btn-sm btn-icon action-btn edit" title="Edit user" aria-label="Edit user"'
                                    . ' data-id="' . $user->id . '"'
                                    . ' data-name="' . e($displayName) . '"'
                                    . ' data-email="' . e($user->email) . '"'
                                    . ' data-role="' . e($user->role) . '"'
                                    . ' data-employment-type="' . e($user->employment_type ?? 'regular') . '"'
                                    . ' data-employment-start-date="' . e($user->employment_start_date ? now()->parse($user->employment_start_date)->format('Y-m-d') : '') . '"'
                                    . ' data-birthdate="' . e($user->userCredential?->birthdate ? now()->parse($user->userCredential->birthdate)->format('Y-m-d') : '') . '"'
                                    . ' data-gender="' . e($user->userCredential?->gender ?? '') . '"'
                                    . ' data-sss-number="' . e($user->userCredential?->sss_number ?? '') . '"'
                                    . ' data-philhealth-number="' . e($user->userCredential?->philhealth_number ?? '') . '"'
                                    . ' data-pagibig-number="' . e($user->userCredential?->pagibig_number ?? '') . '"'
                                    . ' data-registered="' . e($registeredAt) . '">' 
                                    . '<i class="fa-solid fa-pen"></i>'
                                . '</button>'
                                . '<button type="button" class="btn btn-outline-secondary btn-sm btn-icon action-btn archive" data-id="' . $user->id . '" title="Archive user">'
                                    . '<i class="fa-solid fa-box-archive"></i>'
                                . '</button>'
                            . '</div>',
                        ];
                    })->toArray(),
                    'rawColumns' => ['user', 'role', 'actions'],
                ])

                <div class="mt-2">
                    @include('components.pagination', [
                        'paginationClass' => 'users-active',
                        'paginator' => $users ?? null,
                    ])
                </div>
            </div>

            <!-- Archived Users Table -->
            <div id="archived-users" class="tab-content hidden" style="{{ $showArchived ? '' : 'display: none;' }}">
                @include('components.table', [
                    'tableClass' => 'archived-users-table',
                    'tableCol' => [
                        'user',
                        'email',
                        'role',
                        'archived-date',
                        'actions',
                    ],
                    'tableLabel' => [
                        'User',
                        'Email',
                        'Role',
                        'Archived Date',
                        'Actions',
                    ],
                    'tableData' => $archivedUsers->map(function ($user) {
                        $employmentType = $user->employment_type ?? \App\Models\User::EMPLOYMENT_TYPE_REGULAR;
                        $employmentTypeLabel = $employmentType === \App\Models\User::EMPLOYMENT_TYPE_PART_TIME ? 'Part-time' : 'Regular';
                        $displayName = $user->full_name ?? $user->username;
                        $archivedAt = $user->deleted_at ? now()->parse($user->deleted_at)->format('M d, Y') : '';
                        return [
                            '<div class="d-flex align-items-center">'
                                . '<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:12px;font-weight:600;">'
                                    . substr($user->full_name ?? $user->username, 0, 2)
                                . '</div>'
                                . '<div class="d-flex flex-column">'
                                    . '<span class="text-muted">' . e($displayName) . '</span>'
                                    . '<span class="badge bg-light text-dark mt-1" style="font-size: 0.7rem;">' . e($employmentTypeLabel) . '</span>'
                                . '</div>'
                            . '</div>'
                            . '<span class="user-row-preview" style="display:none"'
                                . ' data-id="' . e($user->id) . '"'
                                . ' data-name="' . e($displayName) . '"'
                                . ' data-email="' . e($user->email) . '"'
                                . ' data-role="' . e($user->role ?? '') . '"'
                                . ' data-employment-type="' . e($employmentTypeLabel) . '"'
                                . ' data-employment-start-date="' . e($user->employment_start_date ? now()->parse($user->employment_start_date)->format('M d, Y') : '') . '"'
                                . ' data-birthdate="' . e($user->userCredential?->birthdate ? now()->parse($user->userCredential->birthdate)->format('M d, Y') : '') . '"'
                                . ' data-gender="' . e($user->userCredential?->gender ?? '') . '"'
                                . ' data-sss-number="' . e($user->userCredential?->sss_number ?? '') . '"'
                                . ' data-philhealth-number="' . e($user->userCredential?->philhealth_number ?? '') . '"'
                                . ' data-pagibig-number="' . e($user->userCredential?->pagibig_number ?? '') . '"'
                                . ' data-registered="' . e($user->created_at ? now()->parse($user->created_at)->format('M d, Y') : '') . '"'
                                . ' data-archived-at="' . e($archivedAt) . '"'
                            . '></span>',
                            '<span class="text-muted">' . e($user->email) . '</span>',
                            // Role with data-role attribute
                            '<span data-role="' . e($user->role) . '" class="badge rounded-pill bg-secondary text-dark">'
                                . e($user->role ?? 'N/A')
                            . '</span>',
                            '<span class="text-muted">' . e($archivedAt) . '</span>',
                            '<div class="users-actions d-flex align-items-center gap-1">'
                                . '<button type="button" class="btn btn-outline-success btn-sm btn-icon action-btn restore" data-id="' . $user->id . '" title="Restore user">'
                                    . '<i class="fa-solid fa-rotate-left"></i>'
                                . '</button>'
                                . '<button type="button" class="btn btn-outline-danger btn-sm btn-icon action-btn delete" data-id="' . $user->id . '" title="Permanently delete">'
                                    . '<i class="fa-solid fa-trash"></i>'
                                . '</button>'
                            . '</div>',
                        ];
                    })->toArray(),
                    'rawColumns' => ['user', 'email', 'role', 'archived-date', 'actions'],
                ])

                <div class="mt-2">
                    @include('components.pagination', [
                        'paginationClass' => 'users-archived',
                        'paginator' => $archivedUsers ?? null,
                    ])
                </div>
            </div>

        </div>

    </div>

@endsection

@section('modal')

    @include('components.modal', [
        'modalClass' => 'users-modal',
        'modalId' => 'addUsersModal',
        'modalForm' => 'addUsersForm',
        'modalRoute' => 'users.store',
        'modalBody1Class' => 'input-fields',
        'modalBody2Class' => 'review-fields',
        'modalHeader' => '
            <div class="modal-title">
                New User
            </div>
            ' . view('components.button', [
                'buttonType' => 'icon modal-close',
                'buttonVar' => 'users-modal-close',
                'buttonIcon' => '<i class="fa-solid fa-xmark"></i>',
                'isModalClose' => true,
            ])->render() . '
        ',
        'modalBody1' => '
            {{-- error handling --}}
            ' . view('components.modal-error')->render() . '

            {{-- full name --}}
            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'users',
                'inputVar' => 'full-name',
                'inputName' => 'full_name',
                'inputLabel' => 'Full Name',
                'inputPlaceholder' => 'Enter full name',
                'inputInDecrement' => false,
            ])->render() . '

            {{-- email --}}
            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'users',
                'inputVar' => 'email',
                'inputName' => 'email',
                'inputLabel' => 'Email address',
                'inputPlaceholder' => 'Enter email address',
                'isEmail' => true,
                'isRequired' => true,
            ])->render() . '

            {{-- role --}}
            ' . view('components.select', [
                'selectType' => 'normal',
                'selectSrc' => 'users',
                'selectVar' => 'role',
                'selectName' => 'role',
                'selectLabel' => 'Role',
                'selectData' => (isset($currentRoleKey) && $currentRoleKey === 'superadmin')
                    ? [
                        'Admin' => 'Admin',
                        'HR' => 'HR',
                        'Manager' => 'Manager',
                        'Supervisor' => 'Supervisor',
                        'Worker' => 'Worker',
                    ]
                    : [
                        'HR' => 'HR',
                        'Manager' => 'Manager',
                        'Supervisor' => 'Supervisor',
                        'Worker' => 'Worker',
                    ],
                'isShort' => false,
            ])->render() . '

            ' . view('components.date', [
                'dateSrc' => 'users',
                'dateVar' => 'birthdate',
                'dateName' => 'birthdate',
                'dateLabel' => 'Birthdate',
                'isRequired' => true,
            ])->render() . '

            ' . view('components.select', [
                'selectType' => 'normal',
                'selectSrc' => 'users',
                'selectVar' => 'gender',
                'selectName' => 'gender',
                'selectLabel' => 'Gender',
                'selectData' => [
                    'Male' => 'Male',
                    'Female' => 'Female',
                ],
                'isShort' => false,
            ])->render() . '

            {{-- government IDs --}}
            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'users',
                'inputVar' => 'sss-number',
                'inputName' => 'sss_number',
                'inputLabel' => 'SSS number',
                'inputPlaceholder' => 'Enter SSS number (optional)',
                'inputInDecrement' => false,
            ])->render() . '

            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'users',
                'inputVar' => 'philhealth-number',
                'inputName' => 'philhealth_number',
                'inputLabel' => 'PhilHealth number',
                'inputPlaceholder' => 'Enter PhilHealth number (optional)',
                'inputInDecrement' => false,
            ])->render() . '

            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'users',
                'inputVar' => 'pagibig-number',
                'inputName' => 'pagibig_number',
                'inputLabel' => 'Pag-IBIG number',
                'inputPlaceholder' => 'Enter Pag-IBIG number (optional)',
                'inputInDecrement' => false,
            ])->render() . '
            ' . ((isset($currentRoleKey) && $currentRoleKey === 'admin')
                ? view('components.select', [
                    'selectType' => 'normal',
                    'selectSrc' => 'users',
                    'selectVar' => 'employment-type',
                    'selectName' => 'employment_type',
                    'selectLabel' => 'Employment type',
                    'selectData' => [
                        'regular' => 'Regular',
                        'part_time' => 'Part-time',
                    ],
                    'isShort' => false,
                ])->render()
                : '') . '

            ' . ((isset($currentRoleKey) && $currentRoleKey === 'admin')
                ? view('components.date', [
                    'dateSrc' => 'users',
                    'dateVar' => 'employment-start-date',
                    'dateName' => 'employment_start_date',
                    'dateLabel' => 'Employment start date',
                    'isRequired' => false,
                ])->render()
                : '') . '

            {{-- password --}}
            ' . view('components.input-field', [
                'inputType' => 'password',
                'inputSrc' => 'users',
                'inputVar' => 'password',
                'inputName' => 'password',
                'inputLabel' => 'Initial Password',
                'inputPlaceholder' => 'Enter initial password (min. 12 characters)',
                'inputInDecrement' => false,
            ])->render() . '
        ',
        'modalBody2' => '
            {{-- modal console --}}
            <span class="info">
                Please review if these fields are correct:
            </span>
            ' . view('components.modal-console', [
                'consoleItems' => [
                    ['label' => 'Full name', 'value' => 'N/A'],
                    ['label' => 'Email', 'value' => 'N/A'],
                    ['label' => 'Role', 'value' => 'N/A'],
                    ['label' => 'Birthdate', 'value' => 'N/A'],
                    ['label' => 'Gender', 'value' => 'N/A'],
                    ['label' => 'SSS number', 'value' => 'N/A'],
                    ['label' => 'PhilHealth number', 'value' => 'N/A'],
                    ['label' => 'Pag-IBIG number', 'value' => 'N/A'],
                    ['label' => 'Employment type', 'value' => 'N/A'],
                    ['label' => 'Employment start date', 'value' => 'N/A'],
                    ['label' => 'Password', 'value' => 'N/A (hidden)'],
                ],
            ])->render() . '
        ',
        'modalFooter' => '
            ' . view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'discard',
                'buttonSrc' => 'users',
                'buttonLabel' => 'Discard',
                'isModalClose' => true,
                'btnAttribute' => 'data-action="discard"',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'previous',
                'buttonSrc' => 'users',
                'buttonLabel' => 'Go back',
                'hideBtn' => true,
                'btnAttribute' => 'data-action="back"',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'main',
                'buttonVar' => 'next',
                'buttonSrc' => 'users',
                'buttonLabel' => 'Proceed',
                'btnAttribute' => 'data-action="next"',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'main',
                'buttonVar' => 'submit',
                'buttonSrc' => 'users',
                'buttonLabel' => 'Submit',
                'isSubmit' => true,
                'hideBtn' => true,
                'btnAttribute' => 'data-action="submit"',
            ])->render() . '
        ',
    ])

    <div class="modal fade" id="userDetailsModal" tabindex="-1" aria-labelledby="userDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userDetailsModalLabel">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="fw-semibold" id="user-details-name">N/A</div>
                    <div class="small text-muted mb-3" id="user-details-email">N/A</div>

                    <div class="row g-3">
                        <div class="col-6">
                            <div class="small text-muted">Role</div>
                            <div id="user-details-role">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Employment type</div>
                            <div id="user-details-employment-type">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Employment start date</div>
                            <div id="user-details-employment-start-date">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Birthdate</div>
                            <div id="user-details-birthdate">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Gender</div>
                            <div id="user-details-gender">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">SSS number</div>
                            <div id="user-details-sss">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">PhilHealth number</div>
                            <div id="user-details-philhealth">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Pag-IBIG number</div>
                            <div id="user-details-pagibig">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Registered</div>
                            <div id="user-details-registered">N/A</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Archived</div>
                            <div id="user-details-archived">N/A</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- delete --}}
    <!-- Delete Confirmation Modal -->
    <div class="modal fade delete-users" id="deleteUsersModal" tabindex="-1">
        <div class="modal-dialog confirm">
            <div class="modal-content confirm">
                <div class="modal-body confirm">
                    <div class="modal-container confirm">
                        <div class="confirm-icon delete">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div class="confirm-label">
                            Are you sure you want to delete this user? This action cannot be undone.
                        </div>
                    </div>
                    <div class="modal-container confirm-buttons">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form id="deleteUserForm" method="POST" style="display: inline-block;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
