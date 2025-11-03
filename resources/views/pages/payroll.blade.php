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
            ])->render() . '
        ',
        'modalFooter' => '
        
        ',
    ])

@endsection