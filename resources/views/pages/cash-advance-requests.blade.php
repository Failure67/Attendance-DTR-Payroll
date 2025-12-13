@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper cash-advance-requests">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <div class="page-title-text">
                    <h1>Cash Advance Requests</h1>
                    <p>Review and process employee cash advance requests</p>
                </div>
            </div>
        </div>

        <div class="container cash-advance-requests filter mb-3">
            <form method="GET" action="{{ route('cash-advance-requests') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="employee_id" class="form-label">Employee</label>
                    <select name="employee_id" id="employee_id" class="form-select select2">
                        <option value="">All employees</option>
                        @foreach ($employeeOptions as $id => $name)
                            <option value="{{ $id }}" {{ (string)($filters['employee_id'] ?? '') === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary mt-auto">Apply filters</button>
                    <a href="{{ route('cash-advance-requests') }}" class="btn btn-outline-secondary mt-auto">Reset</a>
                </div>
            </form>
        </div>

        <div class="container cash-advance-requests table-component">

            @php
                $tableData = $requestTableData ?? [];
            @endphp

            @include('components.table', [
                'tableClass' => 'cash-advance-requests-table',
                'tableCol' => [
                    'employee-name',
                    'amount',
                    'status',
                    'created-at',
                    'actions',
                ],
                'tableLabel' => [
                    'Employee',
                    'Amount',
                    'Status',
                    'Requested on',
                    'Actions',
                ],
                'tableData' => $tableData,
                'rawColumns' => ['actions'],
            ])

            @if($requests instanceof \Illuminate\Pagination\LengthAwarePaginator || $requests instanceof \Illuminate\Pagination\Paginator)
                <div class="mt-3 d-flex justify-content-end">
                    {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }}
                </div>
            @endif

        </div>

    </div>

@endsection
