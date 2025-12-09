@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}" data-archived="{{ ($showArchived ?? false) ? '1' : '0' }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-receipt"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>View, filter, and manage payroll records</p>
                </div>
            </div>
        </div>

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
                $exportPdfUrl = route('payroll.export-pdf') . (count($exportQuery) ? ('?' . http_build_query($exportQuery)) : '');
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
                    'buttonType' => 'danger',
                    'buttonVar' => 'payroll-delete',
                    'buttonSrc' => 'payroll',
                    'buttonIcon' => '<i class="fa-solid fa-clock-rotate-left"></i>',
                    'buttonLabel' => ($showArchived ?? false) ? 'Back to payroll' : 'View archived',
                    'buttonModal' => false,
                ])

                <div class="dropdown">
                    @include('components.button', [
                        'buttonType' => 'main',
                        'buttonVar' => 'more',
                        'buttonSrc' => 'payroll',
                        'buttonIcon' => '<i class="fa-solid fa-caret-down"></i>',
                        'buttonLabel' => 'More actions',
                        'btnAttribute' => 'data-bs-toggle="dropdown" aria-expanded="false"',
                    ])
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button type="button" class="dropdown-item" id="payroll-more-details">View details</button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" id="payroll-more-process" data-url="{{ route('payroll.process') }}">Process from attendance</button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" id="payroll-more-export-csv" data-url="{{ $exportUrl }}">Export as CSV</button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" id="payroll-more-export-pdf" data-url="{{ $exportPdfUrl }}">Export as PDF</button>
                        </li>
                    </ul>
                </div>

            </div>

        </div>

        <div class="container {{ $pageClass }} mb-2 mt-2">
            <form method="GET" action="{{ route('payroll') }}" class="row g-3 align-items-end payroll-filter-row">
                <div class="col-12 col-md-6 col-lg">
                    <label for="employee_id" class="input-label mb-1">Employee</label>
                    <select name="employee_id" id="employee_id" class="select w-100">
                        <option value="">All employees</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" @if(($filters['employee_id'] ?? '') == $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="period_start" class="input-label mb-1">Period start</label>
                    <input type="date" name="period_start" id="period_start" class="date-field" value="{{ $filters['period_start'] ?? '' }}">
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="period_end" class="input-label mb-1">Period end</label>
                    <input type="date" name="period_end" id="period_end" class="date-field" value="{{ $filters['period_end'] ?? '' }}">
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="status" class="input-label mb-1">Status</label>
                    <select name="status" id="status" class="select w-100">
                        <option value="">All</option>
                        <option value="Pending" @if(($filters['status'] ?? '') === 'Pending') selected @endif>Pending</option>
                        <option value="Released" @if(($filters['status'] ?? '') === 'Released') selected @endif>Completed</option>
                        <option value="Cancelled" @if(($filters['status'] ?? '') === 'Cancelled') selected @endif>Cancelled</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-lg-end">
                    <button type="submit" class="button main filter">Filter</button>
                </div>
            </form>
        </div>

        <div class="container {{ $pageClass }} table-component">

            @php
                $isArchivedView = $showArchived ?? false;

                $payrollTableData = ($payrolls ?? collect())->map(function ($payroll) use ($isArchivedView) {
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

                    if ($isArchivedView) {
                        $csrf = csrf_token();

                        $restoreForm = "<form method=\"POST\" action=\"" . route('payroll.restore', ['id' => $payroll->id]) . "\" style=\"display:inline-block;margin-right:4px;\" onsubmit=\"return confirm('Recover this payroll record?');\">"
                            . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                            '<button type="submit" class="btn btn-outline-success btn-sm" title="Recover">'
                            . '<i class="fa-solid fa-rotate-left"></i>' .
                            '</button>' .
                            '</form>';

                        $deleteForm = "<form method=\"POST\" action=\"" . route('payroll.delete', ['id' => $payroll->id]) . "\" style=\"display:inline-block;\" onsubmit=\"return confirm('Permanently delete this payroll record? This cannot be undone.');\">"
                            . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                            '<input type="hidden" name="_method" value="DELETE">'
                            . '<input type="hidden" name="archived" value="1">'
                            . '<button type="submit" class="btn btn-outline-danger btn-sm" title="Delete permanently">'
                            . '<i class="fa-solid fa-trash"></i>' .
                            '</button>' .
                            '</form>';

                        $actionsHtml = '<div class="payroll-archive-actions d-flex align-items-center gap-1">'
                            . $restoreForm
                            . $deleteForm
                            . '</div>';
                    } else {
                        $actions = '<div class="payroll-actions d-flex align-items-center gap-2">';

                        if ($statusLabel === 'Pending') {
                            $actions .=
                                '<button type="button" class="btn btn-outline-success btn-sm payroll-action complete" data-id="' . $payroll->id . '">Complete</button>'
                                . '<button type="button" class="btn btn-outline-secondary btn-sm payroll-action cancel" data-id="' . $payroll->id . '">Cancel</button>';
                        }

                        $actions .= '</div>';
                        $actionsHtml = $actions;
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

        <div class="container {{ $pageClass }} pagination">

            @include('components.pagination', [
                'paginationClass' => 'payroll',
                'paginator' => $payrolls ?? null,
            ])

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
        'confirmType' => 'archive',
        'confirmRoute' => 'payroll.delete',
        'confirmRouteParams' => ['id' => 0],
        'confirmLabel' => 'archive',
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
                'isSubmit' => false,
            ])
    ])

    {{-- Payroll details modal (read-only) --}}
    <div class="modal fade" id="payrollDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Payroll details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="fw-semibold" id="payroll-details-employee">Employee name</div>
                        <div class="text-muted small">Period: <span id="payroll-details-period">N/A</span></div>
                        <div class="text-muted small">Created at: <span id="payroll-details-created-at">N/A</span></div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="fw-semibold">Summary</h6>
                            <ul class="list-unstyled mb-0">
                                <li><span class="text-muted">Wage type:</span> <span id="payroll-details-wage-type">N/A</span></li>
                                <li><span class="text-muted">Minimum wage:</span> <span id="payroll-details-min-wage">₱ 0.00</span></li>
                                <li><span class="text-muted">Units worked:</span> <span id="payroll-details-units-worked">0</span></li>
                                <li><span class="text-muted">Regular hours:</span> <span id="payroll-details-regular-hours">0.00</span></li>
                                <li><span class="text-muted">Overtime hours:</span> <span id="payroll-details-overtime-hours">0.00</span></li>
                                <li><span class="text-muted">Absent days:</span> <span id="payroll-details-absent-days">0.00</span></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-semibold">Amount breakdown</h6>
                            <ul class="list-unstyled mb-0">
                                <li><span class="text-muted">Gross pay:</span> <span id="payroll-details-gross-pay">₱ 0.00</span></li>
                                <li><span class="text-muted">Total deductions:</span> <span id="payroll-details-total-deductions">₱ 0.00</span></li>
                                <li><span class="text-muted">Net pay:</span> <span id="payroll-details-net-pay">₱ 0.00</span></li>
                            </ul>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <h6 class="fw-semibold">Deductions</h6>
                            <ul class="list-unstyled small" id="payroll-details-deductions-list">
                                <li class="text-muted">No manual deductions.</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-semibold">Cash advance repayments</h6>
                            <ul class="list-unstyled small" id="payroll-details-ca-list">
                                <li class="text-muted">No cash advance deductions in this payroll.</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endsection