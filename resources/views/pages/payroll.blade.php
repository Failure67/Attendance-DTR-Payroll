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
                    'buttonId' => 'payroll-add',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                    'buttonModal' => true,
                    'buttonTarget' => 'addPayrollModal'
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonId' => 'payroll-delete',
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

@section('modal')

    @include('components.modal', [
        'modalClass' => 'payroll-modal',
        'modalId' => 'addPayrollModal',
        'modalHeader' => '
            <div class="modal-title">
                New Payroll
            </div>
            ' . view('components.button', [
                'buttonType' => 'icon modal-close',
                'buttonId' => 'attendance-add',
                'buttonIcon' => '<i class="fa-solid fa-xmark"></i>',
                'buttonModal' => true,
            ])->render() . '
        ',
        'modalBody' => '
            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'payroll',
                'inputVar' => 'employee-name',
                'inputName' => 'employee_name',
                'inputLabel' => 'Name of employee',
                'inputPlaceholder' => 'Employee name',
                'inputInDecrement' => false,
            ])->render() . '
            ' . view('components.select', [
                'selectType' => 'long',
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
            ])->render() .'
            ' . view('components.input-field', [
                'inputType' => 'amount',
                'inputSrc' => 'payroll',
                'inputVar' => 'min-wage',
                'inputName' => 'min_wage',
                'inputLabel' => 'Minimum wage',
                'inputPlaceholder' => '0.00',
                'inputInDecrement' => false,
            ])->render() . '
            <div class="container mt-2 d-flex align-items-center gap-2">
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
                    'selectType' => 'short',
                    'selectSrc' => 'payroll',
                    'selectVar' => 'wage-unit',
                    'selectData' => [
                        'day' => 'day/s',
                        'week' => 'week/s',
                        'month' => 'month/s',
                    ],
                    'selectStyle' => 'width: 170px;'
                ])->render() .'
            </div>
            ' . view('components.input-field', [
                'inputType' => 'amount',
                'inputSrc' => 'payroll',
                'inputVar' => 'gross-pay',
                'inputName' => 'gross_pay',
                'inputLabel' => 'Gross pay',
                'inputPlaceholder' => '0.00',
                'inputInDecrement' => false,
            ])->render() . '
        ',
        'modalFooter' => '
        
        ',
    ])

@endsection