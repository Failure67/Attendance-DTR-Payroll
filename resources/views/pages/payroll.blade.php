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

            @php
                $currentFilters = $filters ?? [];
                $exportQuery = array_filter([
                    'employee_id' => $currentFilters['employee_id'] ?? null,
                    'status' => $currentFilters['status'] ?? null,
                    'period_start' => $currentFilters['period_start'] ?? null,
                    'period_end' => $currentFilters['period_end'] ?? null,
                ], function ($value) {
                    return !is_null($value) && $value !== '';
                });
                $exportUrl = route('payroll.export') . (count($exportQuery) ? ('?' . http_build_query($exportQuery)) : '');
            @endphp

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
                    'buttonType' => 'secondary',
                    'buttonVar' => 'payroll-edit',
                    'buttonSrc' => 'payroll',
                    'buttonIcon' => '<i class="fa-solid fa-pen"></i>',
                    'buttonLabel' => 'Edit',
                    'buttonModal' => false,
                ])

                @include('components.button', [
                    'buttonType' => 'secondary',
                    'buttonVar' => 'payroll-process',
                    'buttonSrc' => 'payroll',
                    'buttonIcon' => '<i class="fa-solid fa-gears"></i>',
                    'buttonLabel' => 'Process from attendance',
                    'btnAttribute' => 'onclick="window.location.href=\'' . route('payroll.process') . '\'"',
                ])

                @include('components.button', [
                    'buttonType' => 'secondary',
                    'buttonVar' => 'payroll-export',
                    'buttonSrc' => 'payroll',
                    'buttonIcon' => '<i class="fa-solid fa-file-export"></i>',
                    'buttonLabel' => 'Export CSV',
                    'btnAttribute' => 'onclick="window.location.href=\'' . $exportUrl . '\'"',
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

        <div class="container {{ $pageClass }} mb-1">
            <form method="GET" action="{{ route('payroll') }}" class="row g-3 align-items-end payroll-filter-row">
                <div class="col-12 col-md-6 col-lg">
                    <label for="employee_id" class="form-label mb-1">Employee</label>
                    <select name="employee_id" id="employee_id" class="form-select">
                        <option value="">All employees</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" @if(($filters['employee_id'] ?? '') == $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="period_start" class="form-label mb-1">Period start</label>
                    <input type="date" name="period_start" id="period_start" class="form-control" value="{{ $filters['period_start'] ?? '' }}">
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="period_end" class="form-label mb-1">Period end</label>
                    <input type="date" name="period_end" id="period_end" class="form-control" value="{{ $filters['period_end'] ?? '' }}">
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="status" class="form-label mb-1">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All</option>
                        <option value="Pending" @if(($filters['status'] ?? '') === 'Pending') selected @endif>Pending</option>
                        <option value="Released" @if(($filters['status'] ?? '') === 'Released') selected @endif>Completed</option>
                        <option value="Cancelled" @if(($filters['status'] ?? '') === 'Cancelled') selected @endif>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-lg-end">
                    <button type="submit" class="btn btn-primary w-100 w-lg-auto">Filter</button>
                </div>
            </form>
        </div>

        <div class="container {{ $pageClass }} table-component">

            @php
                $payrollTableData = ($payrolls ?? collect())->map(function ($payroll) {
                    $employeeName = $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : 'Unknown employee';

                    $minWage = '₱ ' . number_format($payroll->min_wage ?? 0, 2);

                    $units = $payroll->hours_worked ?? $payroll->days_worked ?? 0;
                    $unitLabelMap = [
                        'Hourly' => 'hour/s',
                        'Daily' => 'day/s',
                        'Weekly' => 'week/s',
                        'Monthly' => 'month/s',
                        'Piece rate' => 'unit/s',
                    ];
                    $unitLabel = $unitLabelMap[$payroll->wage_type] ?? 'unit/s';
                    $unitsWorked = $units . ' ' . $unitLabel;

                    $grossPay = '₱ ' . number_format($payroll->gross_pay ?? 0, 2);
                    $totalDeductions = '₱ ' . number_format($payroll->total_deductions ?? 0, 2);
                    $netPay = '₱ ' . number_format($payroll->net_pay ?? 0, 2);

                    $statusLabelMap = [
                        'Pending' => 'Pending',
                        'Released' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ];
                    $statusLabel = $statusLabelMap[$payroll->status] ?? ($payroll->status ?? 'Pending');

                    $statusClass = match ($statusLabel) {
                        'Completed' => 'bg-success',
                        'Cancelled' => 'bg-secondary',
                        default => 'bg-warning text-dark',
                    };

                    $actionsHtml = '';

                    if ($statusLabel === 'Pending') {
                        $actionsHtml =
                            '<div class="payroll-actions d-flex align-items-center gap-2">'
                            . '<button type="button" class="btn btn-outline-success btn-sm payroll-action complete" data-id="' . $payroll->id . '">Complete</button>'
                            . '<button type="button" class="btn btn-outline-secondary btn-sm payroll-action cancel" data-id="' . $payroll->id . '">Cancel</button>'
                            . '</div>';
                    }

                    return [
                        '<span class="payroll-employee" data-payroll-id="' . $payroll->id . '">' . e($employeeName) . '</span>',
                        e($payroll->wage_type ?? 'N/A'),
                        e($minWage),
                        e($unitsWorked),
                        e($grossPay),
                        e($totalDeductions),
                        e($netPay),
                        '<span class="badge rounded-pill ' . $statusClass . '">' . e($statusLabel) . '</span>',
                        $actionsHtml,
                    ];
                })->toArray();
            @endphp

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
                    'actions',
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
                    'Actions',
                ],
                'tableData' => $payrollTableData,
                'rawColumns' => ['employee-name', 'status', 'actions'],
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
            ' . view('components.modal-error')->render() . '

            {{--
            ' . view('components.input-field', [
                'inputType' => 'text',
                'inputSrc' => 'payroll',
                'inputVar' => 'employee-name',
                'inputName' => 'employee_name',
                'inputLabel' => 'Name of employee',
                'inputPlaceholder' => 'Employee name',
                'inputInDecrement' => false,
            ])->render() . '
            --}}
            
            {{-- employee name --}}
            ' . view('components.select', [
                'selectType' => 'select2',
                'selectSrc' => 'payroll',
                'selectVar' => 'employee-name',
                'selectName' => 'user_id',
                'selectLabel' => 'Name of employee',
                'selectPlaceholder' => 'Select employee',
                'selectData' => $employeeOptions ?? [],
                'isShort' => false,
            ])->render() .'
            {{-- wage type --}}
            ' . view('components.select', [
                'selectType' => 'normal',
                'selectSrc' => 'payroll',
                'selectVar' => 'wage-type',
                'selectName' => 'wage_type',
                'selectLabel' => 'Type of wage',
                'selectData' => [
                    'Hourly' => 'Hourly',
                    'Daily' => 'Daily',
                    'Weekly' => 'Weekly',
                    'Monthly' => 'Monthly',
                    'Piece rate' => 'Piece rate (per unit/item)',
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
                        'inputStyle' => 'width: 80px;',
                        'inputNumberWithLabel' => true,
                        'inputNumberLabel' => 'unit/s',
                    ])->render() . '

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
                'inputReadonly' => true,
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
                    'Completed' => 'Completed',
                    'Cancelled' => 'Cancelled',
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
                'btnAttribute' => 'data-action="discard"',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'previous',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Go back',
                'hideBtn' => true,
                'btnAttribute' => 'data-action="back"',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'main',
                'buttonVar' => 'next',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Proceed',
                'btnAttribute' => 'data-action="next"',
            ])->render() . '
            ' . view('components.button', [
                'buttonType' => 'main',
                'buttonVar' => 'submit',
                'buttonSrc' => 'payroll',
                'buttonLabel' => 'Submit',
                'isSubmit' => true,
                'hideBtn' => true,
                'btnAttribute' => 'data-action="submit"',
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