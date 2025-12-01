@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }} mb-3">
            <form method="GET" action="{{ route('attendance.daily') }}" class="row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="daily_attendance_date" class="form-label mb-1">Date</label>
                    <input type="date" name="date" id="daily_attendance_date" class="form-control" value="{{ $filters['date'] ?? $dailyDate }}">
                </div>
                <div class="col-12 col-md-4">
                    <label for="daily_attendance_employee" class="form-label mb-1">Employee</label>
                    <select name="employee_id" id="daily_attendance_employee" class="form-select">
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
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div>
                    <strong>Date:</strong> {{ $dailyDate }}
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
                </div>
            </div>

            @php
                $dailyTableData = $dailyTableData ?? [];
            @endphp

            @include('components.table', [
                'tableClass' => 'attendance-daily-table',
                'tableCol' => [
                    'employee-name',
                    'time-in',
                    'time-out',
                    'status',
                    'total-hours',
                    'overtime-hours',
                ],
                'tableLabel' => [
                    'Name of employee',
                    'Time-in',
                    'Time-out',
                    'Status',
                    'Total hours',
                    'Overtime',
                ],
                'tableData' => $dailyTableData,
                'rawColumns' => [],
            ])

        </div>

    </div>

@endsection
