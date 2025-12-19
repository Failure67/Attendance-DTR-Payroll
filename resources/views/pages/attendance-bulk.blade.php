@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-table"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Edit attendance for multiple employees on a single date</p>
                </div>
            </div>
        </div>

        <div class="container {{ $pageClass }} mb-3">
            <form method="GET" action="{{ route('attendance.bulk') }}" class="row g-2 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="bulk_attendance_period_start" class="form-label mb-1">Period start</label>
                    <input type="date" name="period_start" id="bulk_attendance_period_start" class="form-control" value="{{ $filters['period_start'] ?? $bulkDate }}">
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="bulk_attendance_period_end" class="form-label mb-1">Period end</label>
                    <input type="date" name="period_end" id="bulk_attendance_period_end" class="form-control" value="{{ $filters['period_end'] ?? '' }}">
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label for="bulk_attendance_employee" class="form-label mb-1">Employee</label>
                    <select name="employee_id" id="bulk_attendance_employee" class="form-control">
                        <option value="">All employees</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" @if(($filters['employee_id'] ?? '') == $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 col-lg-auto ms-lg-auto d-flex align-items-end justify-content-end">
                    <button type="submit" class="button main filter">Load</button>
                </div>
            </form>
        </div>

        <div class="container {{ $pageClass }} table-component mb-3">
            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($errors->has('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    {{ $errors->first('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <form method="POST" action="{{ route('attendance.bulk.store') }}">
                @csrf

                <input type="hidden" name="date" value="{{ $bulkDate }}">

                @include('components.modal-error')

                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="bulk_default_time_in" class="form-label mb-1">Default time in</label>
                        <input type="time" id="bulk_default_time_in" class="form-control" value="{{ config('attendance.default_shift_start', '08:00') }}">
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="bulk_default_time_out" class="form-label mb-1">Default time out</label>
                        <input type="time" id="bulk_default_time_out" class="form-control" value="{{ config('attendance.default_shift_end', '17:00') }}">
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="bulk_default_status" class="form-label mb-1">Default status</label>
                        <select id="bulk_default_status" class="form-control">
                            <option value="">Auto (Present/Late/Absent)</option>
                            <option value="Present">Present</option>
                            <option value="Late">Late</option>
                            <option value="Absent">Absent</option>
                            <option value="On leave">On leave</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-auto ms-lg-auto d-flex align-items-end justify-content-end">
                        <button type="button" id="bulk-apply-to-all" class="button secondary filter">Apply to all rows</button>
                    </div>
                </div>

                @if (!empty($hasLeaveLinkedRows))
                    <div class="mb-2 small text-muted">
                        Some rows are from approved leave requests. Their time and status fields are locked; edit or cancel the leave request instead of changing attendance directly.
                    </div>
                @endif

                @php
                    $bulkTableData = [];

                    if (!empty($rows) && count($rows)) {
                        foreach ($rows as $index => $row) {
                            $isLeaveLinked = !empty($row['is_leave_linked']);
                            $leaveTooltip = $row['leave_tooltip'] ?? null;

                            $badgeHtml = '';
                            if ($isLeaveLinked) {
                                $badgeTitle = $leaveTooltip ?: 'From leave request';
                                $badgeHtml = ' <span class="badge rounded-pill bg-info-subtle text-info leave-linked-badge" title="' . e($badgeTitle) . '">From leave request</span>';
                            }

                            $employeeCell = e($row['name']) .
                                $badgeHtml .
                                '<input type="hidden" name="records[' . $index . '][user_id]" value="' . e($row['user_id']) . '"' . ($isLeaveLinked ? ' data-leave-linked="1"' : '') . '>' .
                                '<input type="hidden" name="records[' . $index . '][attendance_id]" value="' . e($row['attendance_id']) . '">';

                            $timeInClasses = 'form-control form-control-sm' . ($isLeaveLinked ? ' leave-linked-input' : '');
                            $timeOutClasses = 'form-control form-control-sm' . ($isLeaveLinked ? ' leave-linked-input' : '');
                            $statusClasses = 'form-select form-select-sm' . ($isLeaveLinked ? ' leave-linked-input' : '');

                            $timeInExtra = $isLeaveLinked ? ' disabled data-leave-linked="1"' : '';
                            $timeOutExtra = $isLeaveLinked ? ' disabled data-leave-linked="1"' : '';
                            $statusExtra = $isLeaveLinked ? ' disabled data-leave-linked="1"' : '';

                            $timeInInput = '<input type="time" name="records[' . $index . '][time_in]" class="' . $timeInClasses . '" value="' . e($row['time_in']) . '"' . $timeInExtra . '>';

                            $timeOutInput = '<input type="time" name="records[' . $index . '][time_out]" class="' . $timeOutClasses . '" value="' . e($row['time_out']) . '"' . $timeOutExtra . '>';

                            $statusValue = $row['status'] ?? '';

                            $statusSelect = '<select name="records[' . $index . '][status]" class="' . $statusClasses . '"' . $statusExtra . '>'
                                . '<option value="">Auto (Present/Late/Absent)</option>'
                                . '<option value="Present"' . ($statusValue === 'Present' ? ' selected' : '') . '>Present</option>'
                                . '<option value="Late"' . ($statusValue === 'Late' ? ' selected' : '') . '>Late</option>'
                                . '<option value="Absent"' . ($statusValue === 'Absent' ? ' selected' : '') . '>Absent</option>'
                                . '<option value="On leave"' . ($statusValue === 'On leave' ? ' selected' : '') . '>On leave</option>'
                                . '</select>';

                            if ($isLeaveLinked) {
                                $actionButton = '<span class="text-muted small">From leave request</span>' .
                                    '<input type="hidden" name="records[' . $index . '][include]" value="0" data-leave-linked="1">';
                            } else {
                                $actionButton = '<button type="button" class="btn btn-sm btn-outline-primary attendance-include-toggle" data-index="' . $index . '">Include</button>' .
                                    '<input type="hidden" name="records[' . $index . '][include]" value="1">';
                            }

                            $bulkTableData[] = [
                                $employeeCell,
                                $timeInInput,
                                $timeOutInput,
                                $statusSelect,
                                $actionButton,
                            ];
                        }
                    } else {
                        $bulkTableData[] = [
                            '<span class="text-muted">No employees found for bulk attendance.</span>',
                            '',
                            '',
                            '',
                            '',
                        ];
                    }
                @endphp

                @include('components.table', [
                    'tableClass' => 'attendance-bulk-table',
                    'tableCol' => [
                        'employee-name',
                        'time-in',
                        'time-out',
                        'status',
                        'include',
                    ],
                    'tableLabel' => [
                        'Name of employee',
                        'Time in',
                        'Time out',
                        'Status',
                        'Action',
                    ],
                    'tableData' => $bulkTableData,
                    'rawColumns' => ['employee-name', 'time-in', 'time-out', 'status', 'include'],
                ])

                <div class="mt-3 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Save attendance for {{ $bulkDate }}</button>
                </div>

            </form>
        </div>

    </div>

@endsection
