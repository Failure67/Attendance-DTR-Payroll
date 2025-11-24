@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} tab">

            @include('components.search', [
                'searchClass' => 'users',
                'searchId' => 'users-search',    
            ])

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

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonVar' => 'users-delete',
                    'buttonSrc' => 'users',
                    'buttonIcon' => '<i class="fa-solid fa-trash"></i>',
                    'buttonLabel' => 'Delete',
                    'buttonModal' => false,
                ])

            </div>

        </div>

        <div class="container {{ $pageClass }} table-component">

            @include('components.table', [
                'tableClass' => 'users-table',
                'tableCol' => [
                    'user-id',
                    'full-name',
                    'email',
                    'role',
                    'status',
                ],
                'tableLabel' => [
                    'User ID',
                    'Full Name',
                    'Email Address',
                    'Role',
                    'Status',
                ],
                'tableData' => $users->map(function($user) {
                    return [
                        $user->id,
                        $user->full_name ?? $user->username,
                        $user->email,
                        $user->role ?? 'N/A',
                        'Active',
                    ];
                })->toArray()
            ])

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
                    'Accounting' => 'Accounting',
                    'Payroll Officer' => 'Payroll Officer',
                    'Project Manager' => 'Project Manager',
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
    @include('components.confirm', [
        'confirmClass' => 'delete-users',
        'confirmModalId' => 'deleteUsersModal',
        'confirmType' => 'delete',
        'confirmRoute' => 'users.delete',
        'confirmRouteParams' => ['id' => 0],
        'confirmLabel' => 'delete',
        'confirmButtons' =>
            view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'cancel-delete',
                'buttonSrc' => 'users',
                'buttonLabel' => 'Cancel',
                'isModalClose' => true,
            ])->render() .
            view('components.button', [
                'buttonType' => 'danger',
                'buttonVar' => 'confirm-delete',
                'buttonSrc' => 'users',
                'buttonLabel' => 'Delete',
                'isSubmit' => true,
            ])
    ])

@endsection