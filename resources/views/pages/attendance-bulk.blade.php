@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} mb-3">
            <form method="GET" action="{{ route('attendance.bulk') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="bulk_attendance_date" class="form-label mb-1">Date</label>
                    <input type="date" name="date" id="bulk_attendance_date" class="form-control" value="{{ $filters['date'] ?? $bulkDate }}">
                </div>
                <div class="col-12 col-md-4">
                    <label for="bulk_attendance_employee" class="form-label mb-1">Employee</label>
                    <select name="employee_id" id="bulk_attendance_employee" class="form-select">
                        <option value="">All employees</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" @if(($filters['employee_id'] ?? '') == $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4 d-flex align-items-end justify-content-md-end">
                    <button type="submit" class="btn btn-primary w-100 w-md-auto">Load</button>
                </div>
            </form>
        </div>

        <div class="container {{ $pageClass }} table-component mb-3">
            <form method="POST" action="{{ route('attendance.bulk.store') }}">
                @csrf

                <input type="hidden" name="date" value="{{ $bulkDate }}">

                @include('components.modal-error')

                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="bulk_default_time_in" class="form-label mb-1">Default time in</label>
                        <input type="time" id="bulk_default_time_in" class="form-control form-control-sm" value="{{ config('attendance.default_shift_start', '08:00') }}">
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="bulk_default_time_out" class="form-label mb-1">Default time out</label>
                        <input type="time" id="bulk_default_time_out" class="form-control form-control-sm" value="{{ config('attendance.default_shift_end', '17:00') }}">
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="bulk_default_status" class="form-label mb-1">Default status</label>
                        <select id="bulk_default_status" class="form-select form-select-sm">
                            <option value="">Auto (Present/Late/Absent)</option>
                            <option value="Present">Present</option>
                            <option value="Late">Late</option>
                            <option value="Absent">Absent</option>
                            <option value="On leave">On leave</option>
                        </select>
                    </div>
                    <div class="col-12 col-lg-3 d-flex align-items-end justify-content-lg-end">
                        <button type="button" id="bulk-apply-to-all" class="btn btn-secondary w-100 w-lg-auto">Apply to all rows</button>
                    </div>
                </div>

                @php
                    $bulkTableData = [];

                    if (!empty($rows) && count($rows)) {
                        foreach ($rows as $index => $row) {
                            $employeeCell = e($row['name']) .
                                '<input type="hidden" name="records[' . $index . '][user_id]" value="' . e($row['user_id']) . '">' .
                                '<input type="hidden" name="records[' . $index . '][attendance_id]" value="' . e($row['attendance_id']) . '">';

                            $timeInInput = '<input type="time" name="records[' . $index . '][time_in]" class="form-control form-control-sm" value="' . e($row['time_in']) . '">';

                            $timeOutInput = '<input type="time" name="records[' . $index . '][time_out]" class="form-control form-control-sm" value="' . e($row['time_out']) . '">';

                            $statusValue = $row['status'] ?? '';

                            $statusSelect = '<select name="records[' . $index . '][status]" class="form-select form-select-sm">'
                                . '<option value="">Auto (Present/Late/Absent)</option>'
                                . '<option value="Present"' . ($statusValue === 'Present' ? ' selected' : '') . '>Present</option>'
                                . '<option value="Late"' . ($statusValue === 'Late' ? ' selected' : '') . '>Late</option>'
                                . '<option value="Absent"' . ($statusValue === 'Absent' ? ' selected' : '') . '>Absent</option>'
                                . '<option value="On leave"' . ($statusValue === 'On leave' ? ' selected' : '') . '>On leave</option>'
                                . '</select>';

                            $bulkTableData[] = [
                                $employeeCell,
                                $timeInInput,
                                $timeOutInput,
                                $statusSelect,
                            ];
                        }
                    } else {
                        $bulkTableData[] = [
                            '<span class="text-muted">No employees found for bulk attendance.</span>',
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
                    ],
                    'tableLabel' => [
                        'Name of employee',
                        'Time in',
                        'Time out',
                        'Status',
                    ],
                    'tableData' => $bulkTableData,
                    'rawColumns' => ['employee-name', 'time-in', 'time-out', 'status'],
                ])

                <div class="mt-3 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Save attendance for {{ $bulkDate }}</button>
                </div>

            </form>
        </div>

    </div>

@endsection
