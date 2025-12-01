@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const activeSection = document.getElementById('active-users');
            const archivedSection = document.getElementById('archived-users');
            const searchInput = document.getElementById('users-search');
            const roleFilter = document.getElementById('role-filter');
            const archiveToggleBtn = document.getElementById('archive-toggle-users');
            const archiveToggleLabel = archiveToggleBtn ? archiveToggleBtn.querySelector('.button-label') : null;
            const archiveToggleIcon = archiveToggleBtn ? archiveToggleBtn.querySelector('.button-icon i') : null;

            let showingArchived = false;

            function setView(showArchived) {
                showingArchived = showArchived;

                if (activeSection && archivedSection) {
                    activeSection.style.display = showArchived ? 'none' : '';
                    archivedSection.style.display = showArchived ? '' : 'none';
                }

                if (archiveToggleLabel && archiveToggleIcon) {
                    if (showArchived) {
                        archiveToggleLabel.textContent = 'Back to users';
                        archiveToggleIcon.classList.remove('fa-clock-rotate-left');
                        archiveToggleIcon.classList.add('fa-users');
                    } else {
                        archiveToggleLabel.textContent = 'View archived';
                        archiveToggleIcon.classList.remove('fa-users');
                        archiveToggleIcon.classList.add('fa-clock-rotate-left');
                    }
                }

                applyFilters();
            }

            function applyFilters() {
                const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
                const role = roleFilter ? roleFilter.value.trim().toLowerCase() : '';

                [activeSection, archivedSection].forEach(section => {
                    if (!section) return;
                    const table = section.querySelector('table');
                    if (!table) return;

                    table.querySelectorAll('tbody tr').forEach(row => {
                        // full text search
                        const text = row.innerText.toLowerCase();

                        // Find the role text robustly:
                        // 1) prefer an element with data-role attribute
                        // 2) else try to use the 3rd td (cells[2]) as a fallback
                        let roleText = '';
                        const roleNode = row.querySelector('[data-role]');
                        if (roleNode) {
                            roleText = roleNode.innerText.trim().toLowerCase();
                        } else {
                            const cells = row.querySelectorAll('td');
                            if (cells && cells.length >= 3) {
                                roleText = cells[2].innerText.trim().toLowerCase();
                            } else {
                                roleText = '';
                            }
                        }

                        const matchesSearch = !query || text.includes(query);
                        const matchesRole = !role || (roleText && (roleText === role || roleText.includes(role)));

                        row.style.display = (matchesSearch && matchesRole) ? '' : 'none';
                    });
                });
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
                searchInput.addEventListener('input', applyFilters);
            }

            if (roleFilter) {
                roleFilter.addEventListener('change', applyFilters);
            }

            // Action buttons (view/edit/archive/restore/delete)
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.action-btn');
                if (!btn) return;

                const id = btn.getAttribute('data-id');
                const action = Array.from(btn.classList).find(cls =>
                    ['view', 'edit', 'archive', 'restore', 'delete'].includes(cls)
                );

                switch (action) {
                    case 'view':
                        // TODO: Hook into a dedicated user details modal if available
                        alert('View user #' + id);
                        break;

                    case 'edit':
                        // TODO: Hook into edit user flow
                        alert('Edit user #' + id);
                        break;

                    case 'archive':
                        if (confirm('Are you sure you want to archive this user?')) {
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
                        }
                        break;

                    case 'restore':
                        if (confirm('Restore this user?')) {
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
                        }
                        break;

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

            // default view
            setView(false);
        });
    </script>

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} tab">

            <div class="d-flex align-items-center" style="gap: 10px;">
                {{-- SEARCH FIRST (swapped) --}}
                @include('components.search', [
                    'searchClass' => 'users',
                    'searchId' => 'users-search',
                ])

                {{-- ROLE DROPDOWN SECOND (swapped) --}}
                <select id="role-filter" class="form-select form-select-sm" style="width: 170px; max-width: 190px;">
                    <option value="">All roles</option>
                    <option value="Admin">Admin</option>
                    <option value="HR Manager">HR Manager</option>
                    <option value="Payroll Officer">Payroll Officer</option>
                    <option value="Supervisor">Supervisor</option>
                    <option value="Worker">Worker</option>
                </select>
            </div>

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
                    'buttonIcon' => '<i class="fa-solid fa-clock-rotate-left"></i>',
                    'buttonLabel' => 'View archived',
                    'buttonModal' => false,
                    'btnAttribute' => 'id="archive-toggle-users"'
                ])

            </div>

        </div>

        <div class="container {{ $pageClass }} table-component">

            <!-- Active Users Table -->
            <div id="active-users" class="tab-content active">
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
                        return [
                            // User cell with avatar initials and name
                            '<div class="d-flex align-items-center">'
                                . '<div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:12px;font-weight:600;">'
                                    . substr($user->full_name ?? $user->username, 0, 2)
                                . '</div>'
                                . '<span class="fw-semibold">' . e($user->full_name ?? $user->username) . '</span>'
                            . '</div>',
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
                            now()->parse($user->created_at)->format('M d, Y'),
                            // Actions
                            '<div class="users-actions d-flex align-items-center gap-1">'
                                . '<button type="button" class="btn btn-outline-info btn-sm btn-icon action-btn view" data-id="' . $user->id . '">'
                                    . '<i class="fa-solid fa-eye"></i>'
                                . '</button>'
                                . '<button type="button" class="btn btn-outline-warning btn-sm btn-icon action-btn edit" data-id="' . $user->id . '">'
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
            </div>

            <!-- Archived Users Table -->
            <div id="archived-users" class="tab-content hidden">
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
                        return [
                            '<div class="d-flex align-items-center">'
                                . '<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-size:12px;font-weight:600;">'
                                    . substr($user->full_name ?? $user->username, 0, 2)
                                . '</div>'
                                . '<span class="text-muted">' . e($user->full_name ?? $user->username) . '</span>'
                            . '</div>',
                            '<span class="text-muted">' . e($user->email) . '</span>',
                            // Role with data-role attribute
                            '<span data-role="' . e($user->role) . '" class="badge rounded-pill bg-secondary text-dark">'
                                . e($user->role ?? 'N/A')
                            . '</span>',
                            '<span class="text-muted">' . now()->parse($user->deleted_at)->format('M d, Y') . '</span>',
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
                'inputType' => 'email',
                'inputSrc' => 'users',
                'inputVar' => 'email',
                'inputName' => 'email',
                'inputLabel' => 'Email Address',
                'inputPlaceholder' => 'Enter email address',
                'inputInDecrement' => false,
            ])->render() . '

            {{-- role --}}
            ' . view('components.select', [
                'selectType' => 'normal',
                'selectSrc' => 'users',
                'selectVar' => 'role',
                'selectName' => 'role',
                'selectLabel' => 'Role',
                'selectData' => [
                    'Admin' => 'Admin',
                    'HR Manager' => 'HR Manager',
                    'Payroll Officer' => 'Payroll Officer',
                    'Supervisor' => 'Supervisor',
                    'Worker' => 'Worker',
                ],
                'isShort' => false,
            ])->render() . '

            {{-- password --}}
            ' . view('components.input-field', [
                'inputType' => 'password',
                'inputSrc' => 'users',
                'inputVar' => 'password',
                'inputName' => 'password',
                'inputLabel' => 'Initial Password',
                'inputPlaceholder' => 'Enter initial password (min. 8 characters)',
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
