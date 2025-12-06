@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}" data-archived="{{ ($showArchived ?? false) ? '1' : '0' }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-calendar-days"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>View and manage detailed attendance records</p>
                </div>
            </div>
        </div>

        <div class="container {{ $pageClass }} summary mb-3">

            @php
                $summaryDefaults = [
                    'total_hours' => 0,
                    'total_overtime' => 0,
                    'attendance_rate' => 0,
                    'records' => 0,
                    'worked_days' => 0,
                    'absent_days' => 0,
                    'leave_days' => 0,
                    'period_start' => null,
                    'period_end' => null,
                    'period_label' => 'selected period',
                ];
                $summary = array_merge($summaryDefaults, $attendanceSummary ?? []);
                $periodText = $summary['period_label'] ?? 'selected period';
            @endphp

            @include('components.dashboard-count', [
                'countClass' => 'attendance-total-hours',
                'countLabel' => 'Total hours',
                'countSublabel' => 'For ' . $periodText,
                'countIcon' => '<i class="fa-solid fa-clock"></i>',
                'countValue' => number_format($summary['total_hours'], 2),
            ])

            @include('components.dashboard-count', [
                'countClass' => 'attendance-overtime-hours',
                'countLabel' => 'Overtime hours',
                'countSublabel' => 'For ' . $periodText,
                'countIcon' => '<i class="fa-solid fa-business-time"></i>',
                'countValue' => number_format($summary['total_overtime'], 2),
            ])

            @include('components.dashboard-count', [
                'countClass' => 'attendance-rate',
                'countLabel' => 'Attendance rate',
                'countSublabel' => 'For ' . $periodText . ' (' . ($summary['records'] ?? 0) . ' records)',
                'countIcon' => '<i class="fa-solid fa-chart-column"></i>',
                'countValue' => $summary['attendance_rate'] . '%',
            ])

            @include('components.dashboard-count', [
                'countClass' => 'attendance-days',
                'countLabel' => 'Worked / Absent / Leave',
                'countSublabel' => 'For ' . $periodText,
                'countIcon' => '<i class="fa-solid fa-calendar-check"></i>',
                'countValue' => $summary['worked_days'] . ' / ' . $summary['absent_days'] . ' / ' . $summary['leave_days'],
            ])

        </div>

        <div class="container {{ $pageClass }} tab">

            @include('components.search', [
                'searchClass' => 'attendance',
                'searchId' => 'attendance-search',
                'searchValue' => $filters['search'] ?? '',
            ])

            <div class="crud-buttons">

                @include('components.button', [
                    'buttonType' => 'main',
                    'buttonVar' => 'add',
                    'buttonSrc' => 'attendance',
                    'buttonIcon' => '<i class="fa-solid fa-plus"></i>',
                    'buttonLabel' => 'New',
                ])

                @include('components.button', [
                    'buttonType' => 'secondary',
                    'buttonVar' => 'edit',
                    'buttonSrc' => 'attendance',
                    'buttonIcon' => '<i class="fa-solid fa-pen"></i>',
                    'buttonLabel' => 'Edit',
                ])

                @include('components.button', [
                    'buttonType' => 'danger',
                    'buttonVar' => 'delete',
                    'buttonSrc' => 'attendance',
                    'buttonIcon' => '<i class="fa-solid fa-clock-rotate-left"></i>',
                    'buttonLabel' => ($showArchived ?? false) ? 'Back to attendance' : 'View archived',
                ])

                <div class="dropdown">
                    @include('components.button', [
                        'buttonType' => 'main',
                        'buttonVar' => 'more',
                        'buttonSrc' => 'attendance',
                        'buttonIcon' => '<i class="fa-solid fa-caret-down"></i>',
                        'buttonLabel' => 'More actions',
                        'btnAttribute' => 'data-bs-toggle="dropdown" aria-expanded="false"',
                    ])
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button type="button" class="dropdown-item" id="attendance-more-generate-defaults">Generate defaults</button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" id="summary-attendance">
                                <span class="button-label">Summary view</span>
                            </button>
                        </li>
                        <li>
                            <a href="{{ route('attendance.bulk') }}" class="dropdown-item">Bulk sheet</a>
                        </li>
                        <li>
                            <a href="{{ route('attendance.daily', ['date' => $filters['period_end'] ?? ($filters['period_start'] ?? now()->toDateString()), 'employee_id' => $filters['employee_id'] ?? null]) }}" class="dropdown-item">Daily sheet</a>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" id="attendance-more-import">Import CSV</button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item" id="export-attendance">Export detailed CSV</button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item" id="attendance-more-summary-export">Export summary CSV</button>
                        </li>
                    </ul>
                </div>

            </div>

        </div>
        
        @php
            $filters = $filters ?? [];
            $employeeSummaryTableData = $employeeSummaryTableData ?? [];
        @endphp

        <div class="container {{ $pageClass }} mb-1">
            <form method="GET" action="{{ route('attendance') }}" class="row g-3 align-items-end attendance-filter-row">
                <div class="col-12 col-md-6 col-lg">
                    <label for="attendance_filter_employee" class="input-label mb-1">Employee</label>
                    <select name="employee_id" id="attendance_filter_employee" class="select w-100">
                        <option value="">All employees</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" @if(($filters['employee_id'] ?? '') == $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="attendance_filter_period_start" class="input-label mb-1">Period start</label>
                    <input type="date" name="period_start" id="attendance_filter_period_start" class="date-field" value="{{ $filters['period_start'] ?? '' }}">
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="attendance_filter_period_end" class="input-label mb-1">Period end</label>
                    <input type="date" name="period_end" id="attendance_filter_period_end" class="date-field" value="{{ $filters['period_end'] ?? '' }}">
                </div>
                <div class="col-12 col-md-6 col-lg">
                    <label for="attendance_filter_status" class="input-label mb-1">Status</label>
                    <select name="status" id="attendance_filter_status" class="select w-100">
                        <option value="">All</option>
                        <option value="Present" @if(($filters['status'] ?? '') === 'Present') selected @endif>Present</option>
                        <option value="Late" @if(($filters['status'] ?? '') === 'Late') selected @endif>Late</option>
                        <option value="Absent" @if(($filters['status'] ?? '') === 'Absent') selected @endif>Absent</option>
                        <option value="On leave" @if(($filters['status'] ?? '') === 'On leave') selected @endif>On leave</option>
                    </select>
                </div>
                <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-lg-end">
                    <button type="submit" class="button main filter">Filter</button>
                </div>
            </form>
        </div>

        <form id="attendance-generate-defaults-form" method="POST" action="{{ route('attendance.generate-defaults') }}" style="display: none;">
            @csrf
            <input type="hidden" name="period_start" value="{{ $filters['period_start'] ?? '' }}">
            <input type="hidden" name="period_end" value="{{ $filters['period_end'] ?? '' }}">
            <input type="hidden" name="employee_id" value="{{ $filters['employee_id'] ?? '' }}">
        </form>

        <div class="container {{ $pageClass }} mb-3" style="display: none;">
            <form id="attendance-import-form" method="POST" action="{{ route('attendance.import') }}" enctype="multipart/form-data" class="row g-3 align-items-end">
                @csrf
                <div class="col-12 col-md-8">
                    <label for="attendance_import_file" class="form-label mb-1">Import attendance from CSV</label>
                    <input type="file" name="file" id="attendance_import_file" class="form-control" accept=".csv,text/csv" required>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end justify-content-md-end">
                    <button type="submit" class="btn btn-secondary w-100 w-md-auto">Import CSV</button>
                </div>
            </form>
        </div>

        <div class="container {{ $pageClass }} table-component">

            @php
                $isArchivedView = $showArchived ?? false;
                $attendanceTableCols = [
                    'employee-name',
                    'date',
                    'time-in',
                    'time-out',
                    'total-hours',
                    'overtime-hours',
                    'status',
                ];
                $attendanceTableLabels = [
                    'Name of employee',
                    'Date',
                    'Time-in',
                    'Time-out',
                    'Total hours',
                    'Overtime',
                    'Status',
                ];

                if ($isArchivedView) {
                    $attendanceTableCols[] = 'actions';
                    $attendanceTableLabels[] = 'Actions';
                }
            @endphp

            <div class="attendance-table-views">

                <div class="attendance-view attendance-view-detail">

                    @include('components.table', [
                        'tableClass' => 'attendance-table attendance-table-detail' . ($isArchivedView ? ' archived' : ''),
                        'tableCol' => $attendanceTableCols,
                        'tableLabel' => $attendanceTableLabels,
                        'tableData' => $attendanceTableData ?? [],
                        'rawColumns' => $isArchivedView
                            ? ['employee-name', 'time-in', 'time-out', 'status', 'actions']
                            : ['employee-name', 'time-in', 'time-out', 'status'],
                        'sortableColumns' => [
                            'employee-name' => 'name',
                            'date' => 'date',
                        ],
                        'currentSortBy' => $sortBy ?? null,
                        'currentSortDir' => $sortDir ?? 'asc',
                    ])

                </div>

                <div class="attendance-view attendance-view-summary">

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">Summary by employee (for {{ $summary['period_label'] ?? 'selected period' }})</h2>
                    </div>

                    @include('components.table', [
                        'tableClass' => 'attendance-summary-table attendance-table-summary',
                        'tableCol' => [
                            'employee-id',
                            'employee-name',
                            'days-present',
                            'days-late',
                            'days-absent',
                            'days-on-leave',
                            'total-hours',
                            'overtime-hours',
                        ],
                        'tableLabel' => [
                            'Employee ID',
                            'Employee',
                            'Present',
                            'Late',
                            'Absent',
                            'On leave',
                            'Total hours',
                            'Overtime hours',
                        ],
                        'tableData' => $employeeSummaryTableData,
                        'rawColumns' => [],
                    ])

                </div>

            </div>

        </div>

        <div class="container {{ $pageClass }} pagination">

            @include('components.pagination', [
                'paginationClass' => 'attendance',
                'paginator' => $attendances ?? null,   
            ])

        </div>

    </div>

@endsection

@section('modal')

    {{-- Attendance create/update modal --}}
    <div class="modal fade attendance-modal" id="attendanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="attendanceForm" method="POST" action="{{ route('attendance.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="attendance-form-method" value="">

                    <div class="modal-header">
                        <div class="modal-title">
                            Attendance record
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">

                        {{-- error handling --}}
                        @include('components.modal-error')

                        {{-- employee --}}
                        @include('components.select', [
                            'selectType' => 'select2',
                            'selectSrc' => 'attendance',
                            'selectVar' => 'employee',
                            'selectName' => 'user_id',
                            'selectLabel' => 'Employee',
                            'selectPlaceholder' => 'Select employee',
                            'selectData' => $employeeOptions ?? [],
                            'isShort' => false,
                        ])

                        <div class="mb-3 mt-2">
                            <label class="form-label" for="attendance-date">Date</label>
                            <input type="date" name="date" id="attendance-date" class="form-control" required>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label" for="attendance-time-in">Time in</label>
                                <input type="time" name="time_in" id="attendance-time-in" class="form-control">
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="attendance-time-out">Time out</label>
                                <input type="time" name="time_out" id="attendance-time-out" class="form-control">
                            </div>
                        </div>

                        <div class="mt-3">
                            @include('components.select', [
                                'selectType' => 'normal',
                                'selectSrc' => 'attendance',
                                'selectVar' => 'status',
                                'selectName' => 'status',
                                'selectLabel' => 'Status',
                                'selectData' => [
                                    'Present' => 'Present',
                                    'Late' => 'Late',
                                    'Absent' => 'Absent',
                                    'On leave' => 'On leave',
                                ],
                                'isShort' => false,
                            ])
                        </div>

                        <div class="mt-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="attendance-overtime-approved" name="overtime_approved">
                                <label class="form-check-label" for="attendance-overtime-approved">
                                    Overtime approved
                                </label>
                            </div>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" value="1" id="attendance-leave-approved" name="leave_approved">
                                <label class="form-check-label" for="attendance-leave-approved">
                                    Leave approved
                                </label>
                            </div>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- delete / archive confirm modal --}}
    @include('components.confirm', [
        'confirmClass' => 'delete-attendance',
        'confirmModalId' => 'deleteAttendanceModal',
        'confirmType' => 'archive',
        'confirmRoute' => 'attendance.delete',
        'confirmRouteParams' => ['id' => 0],
        'confirmLabel' => 'archive',
        'confirmButtons' =>
            view('components.button', [
                'buttonType' => 'secondary',
                'buttonVar' => 'cancel-delete',
                'buttonSrc' => 'attendance',
                'buttonLabel' => 'Cancel',
                'isModalClose' => true,
            ])->render() .
            view('components.button', [
                'buttonType' => 'danger',
                'buttonVar' => 'confirm-delete',
                'buttonSrc' => 'attendance',
                'buttonLabel' => 'Delete',
                'isSubmit' => false,
            ])
    ])

@endsection