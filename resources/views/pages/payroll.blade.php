@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} tab">

            @include('components.search', [
                'searchClass' => 'payroll',
                'searchId' => 'payroll-search',
            ])

            <div class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonVar' => 'payroll-add',
                    'buttonSrc' => 'payroll',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                    'buttonModal' => true,
                    'buttonTarget' => 'addPayrollModal'
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonVar' => 'payroll-delete',
                    'buttonSrc' => 'payroll',
                    'buttonIcon' => '<i class="fa-solid fa-trash"></i>',
                    'buttonLabel' => 'Delete',
                    'buttonModal' => false,
                ])

            </div>

        </div>

        <div class="container {{ $pageClass }} table-component">

            @include('components.table', [
                'tableClass' => 'payroll-table',
                'tableCol' => [
                    'employee-name',
                    'wage-type',
                    'min-wage',
                    'units-worked',
                    'gross-pay',
                    'deductions',
                    'net-pay',
                    'status',
                ],
                'tableLabel' => [
                    'Name of employee',
                    'Type of wage',
                    'Minimum wage',
                    'Units worked',
                    'Gross pay',
                    'Deductions',
                    'Net pay',
                    'Status',
                ],
                'tableData' => [
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                    ['CELESTIAL, ROMAR JABEZ M.', 'Weekly', 'P600', '3 days', 'P1,800', 'P0', 'P1,800', 'Pending'],
                ]
            ])

        </div>

    </div>

@endsection

{{-- add modal --}}
@section('modal')

    @include('components.modal', [
        'modalClass' => 'payroll-modal',
        'modalId' => 'addPayrollModal',
        'modalForm' => 'addPayrollForm',
        'modalRoute' => 'payroll.store',
        'modalBody1Class' => 'input-fields',
        'modalBody2Class' => 'review-fields',
        'modalHeader' => '
            <div class="modal-title">
                New Payroll
            </div>
            ' . view('components.button', [
                'buttonType' => 'icon modal-close',
                'buttonVar' => 'payroll-modal-close',
                'buttonIcon' => '<i class="fa-solid fa-xmark"></i>',
                'isModalClose' => true,
            ])->render() . '
        ',
        'modalBody1' => '
            {{-- error handling --}}
            
            {{-- employee name --}}
            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'payroll',
                'inputVar' => 'employee-name',
                'inputName' => 'employee_name',
                'inputLabel' => 'Name of employee',
                'inputPlaceholder' => 'Employee name',
                'inputInDecrement' => false,
            ])->render() . '
            {{-- wage type --}}
            ' . view('components.select', [
                'selectType' => 'normal',
                'selectSrc' => 'payroll',
                'selectVar' => 'wage-type',
                'selectName' => 'wage_type',
                'selectLabel' => 'Type of wage',
                'selectData' => [
                    'daily' => 'Daily',
                    'hourly' => 'Hourly',
                    'weekly' => 'Weekly',
                    'monthly' => 'Monthly',
                ],
                'isShort' => false,
            ])->render() .'
            {{-- minimum wage --}}
            ' . view('components.input-field', [
                'inputType' => 'amount',
                'inputSrc' => 'payroll',
                'inputVar' => 'min-wage',
                'inputName' => 'min_wage',
                'inputLabel' => 'Minimum wage',
                'inputPlaceholder' => '0.00',
                'inputInDecrement' => false,
            ])->render() . '
            {{-- units worked --}}
            <div class="container d-flex align-items-center gap-2">

                <div class="input-label">
                    Units worked
                </div>

                ' . view('components.input-field', [
                        'inputType' => 'number',
                        'inputSrc' => 'payroll',
                        'inputVar' => 'units-worked',
                        'inputName' => 'units_worked',
                        'inputPlaceholder' => '0',
                        'inputInDecrement' => true,
                        'inputStyle' => 'width: 80px;'
                    ])->render() . '

                ' . view('components.select', [
                    'selectType' => 'normal',
                    'selectSrc' => 'payroll',
                    'selectVar' => 'wage-unit',
                    'selectName' => 'wage_unit',
                    'selectData' => [
                        'day' => 'day/s',
                        'week' => 'week/s',
                        'month' => 'month/s',
                    ],
                    'selectStyle' => 'width: 170px;',
                    'isShort' => true,
                ])->render() .'

            </div>
            {{-- gross pay --}}
            ' . view('components.input-field', [
                'inputType' => 'amount',
                'inputSrc' => 'payroll',
                'inputVar' => 'gross-pay',
                'inputName' => 'gross_pay',
                'inputLabel' => 'Gross pay',
                'inputPlaceholder' => '0.00',
                'inputInDecrement' => false,
            ])->render() . '
            {{-- deductions --}}
            ' . view('components.manage-item', [
                'manageItemLabel' => 'Deductions',
                'manageItemName' => 'deductions',
                'manageItems' => []
            ])->render() . '
            {{-- status --}}
            ' . view('components.select', [
                'selectLabel' => 'Payroll status',
                'selectType' => 'normal',
                'selectSrc' => 'payroll',
                'selectVar' => 'payroll-status',
                'selectName' => 'status',
                'selectData' => [
                    'Pending' => 'Pending',
                    'Cancelled' => 'Cancelled',
                    'Completed' => 'Completed',
                ],
                'isShort' => false,
            ])->render() .'
        ',
        'modalBody2' => '
            {{-- modal console --}}
            <span class="info">
                Please review if these fields are correct:
            </span>
            ' .view('components.modal-console', [
                'consoleItems' => [
                    ['label' => 'Employee name', 'value' => 'N/A'],
                    ['label' => 'Wage type', 'value' => 'Daily'],
                    ['label' => 'Minimum wage', 'value' => '₱0.00'],
                    ['label' => 'Units worked', 'value' => '0 day/s'],
                    ['label' => 'Gross pay', 'value' => '₱0.00'],
                    ['label' => 'Deductions', 'value' => '₱0.00'],
                    ['label' => 'Net pay', 'value' => '₱0.00'],
                    ['label' => 'Status', 'value' => 'Pending'],
                ],
            ])->render() . '
        ',
        'modalFooter' => '
            ' . view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'discard',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Discard',
                'isModalClose' => true,
                'btnAttribute' => '',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'previous',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Go back',
                'hideBtn' => true,
                'btnAttribute' => '',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'main',
                'buttonVar' => 'next',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Proceed',
                'btnAttribute' => '',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'main',
                'buttonVar' => 'submit',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Submit',
                'isSubmit' => true,
                'hideBtn' => true,
                'btnAttribute' => '',
            ])->render() . '
        ',
    ])  

{{-- confirm modal --}}

{{-- delete --}}
    @include('components.confirm', [
        'confirmClass' => 'delete-payroll',
        'confirmModalId' => 'deletePayrollModal',
        'confirmType' => 'delete',
        'confirmRoute' => 'payroll.delete',
        'confirmRouteParams' => ['id' => 0],
        'confirmLabel' => 'delete',
        'confirmButtons' =>
            view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'cancel-delete',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Cancel',
                'isModalClose' => true,
            ])->render() .
            view('components.button', [
                'buttonType' => 'danger',
                'buttonVar' => 'confirm-delete',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Delete',
                'isSubmit' => true,
            ])
    ])

@endsection