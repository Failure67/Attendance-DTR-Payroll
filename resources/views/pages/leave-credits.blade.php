@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper leave-credits">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-wallet"></i></span>
                <div class="page-title-text">
                    <h1>Leave Credits</h1>
                    <p>Ledger-based leave credits (transactions in/out)</p>
                </div>
            </div>
        </div>

        <div class="container leave-credits filter mb-3">
            <form method="GET" action="{{ route('leave-credits') }}" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="employee_id" class="form-label">Employee</label>
                    <select name="employee_id" id="employee_id" class="form-select select2">
                        <option value="">Select employee</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" {{ (string)($filters['employee_id'] ?? '') === (string)$id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="leave_code" class="form-label">Leave bucket</label>
                    <select name="leave_code" id="leave_code" class="form-select">
                        <option value="">All</option>
                        @foreach(($leaveTypeLabels ?? []) as $code => $label)
                            <option value="{{ $code }}" {{ (string)($filters['leave_code'] ?? '') === (string)$code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-5 d-flex gap-2">
                    <button type="submit" class="btn btn-primary mt-auto">View</button>
                    <a href="{{ route('leave-credits') }}" class="btn btn-outline-secondary mt-auto">Reset</a>
                </div>
            </form>
        </div>

        @php
            $selectedEmployee = $selectedEmployee ?? null;
            $balances = $balances ?? null;
            $tableData = $transactionTableData ?? [];
            $currentRole = strtolower(auth()->user()->role ?? '');
            $canAdjust = in_array($currentRole, ['admin', 'hr'], true);
        @endphp

        @if($selectedEmployee)
            <div class="container leave-credits mb-3">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <div>
                        <div class="fw-semibold">{{ $selectedEmployee->full_name ?? $selectedEmployee->username }}</div>
                        <div class="small text-muted">Employment type: {{ $selectedEmployee->employment_type ?? \App\Models\User::EMPLOYMENT_TYPE_REGULAR }}</div>
                        <div class="small text-muted">Employment start date: {{ $selectedEmployee->employment_start_date ? $selectedEmployee->employment_start_date->format('Y-m-d') : '—' }}</div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        @foreach(($leaveTypeLabels ?? []) as $code => $label)
                            @php $bal = (float) (($balances[$code] ?? 0) ?? 0); @endphp
                            <div class="badge bg-primary-subtle text-primary px-3 py-2">
                                <div class="small text-muted">{{ $label }} balance</div>
                                <div class="fw-semibold">{{ number_format($bal, 3) }} days</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($canAdjust)
                <div class="container leave-credits mb-3">
                    <div class="card">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">Employment start date</div>

                            <form method="POST" action="{{ route('leave-credits.employment-start-date') }}" class="row g-2 align-items-end mb-3">
                                @csrf
                                <input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}">

                                <div class="col-md-4">
                                    <label class="form-label">Start date</label>
                                    <input type="date" name="employment_start_date" class="form-control" value="{{ $selectedEmployee->employment_start_date ? $selectedEmployee->employment_start_date->format('Y-m-d') : '' }}" required>
                                </div>

                                <div class="col-md-8 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-outline-secondary">Update start date</button>
                                </div>
                            </form>

                            <div class="fw-semibold mb-2">Manual adjustment</div>

                            <form method="POST" action="{{ route('leave-credits.adjust') }}" class="row g-2">
                                @csrf

                                <input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}">

                                <div class="col-md-3">
                                    <label class="form-label">Bucket</label>
                                    <select name="leave_code" class="form-select" required>
                                        @foreach(($leaveTypeLabels ?? []) as $code => $label)
                                            <option value="{{ $code }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Direction</label>
                                    <select name="direction" class="form-select" required>
                                        <option value="credit">Credit</option>
                                        <option value="debit">Debit</option>
                                    </select>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Amount (days)</label>
                                    <input type="number" name="amount" step="0.001" min="0.001" class="form-control" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label">Effective date</label>
                                    <input type="date" name="effective_date" class="form-control">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Reason</label>
                                    <input type="text" name="reason" maxlength="255" class="form-control" required>
                                </div>

                                <div class="col-12 d-flex justify-content-end">
                                    <button type="submit" class="btn btn-outline-primary">Post adjustment</button>
                                </div>

                                @if (session('error'))
                                    <div class="col-12"><div class="alert alert-danger mb-0">{{ session('error') }}</div></div>
                                @endif
                                @if (session('success'))
                                    <div class="col-12"><div class="alert alert-success mb-0">{{ session('success') }}</div></div>
                                @endif
                            </form>

                            <form method="POST" action="{{ route('leave-credits.accrue') }}" class="mt-3">
                                @csrf
                                <input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}">
                                <button type="submit" class="btn btn-outline-secondary">Run accrual up to today</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif

            <div class="container leave-credits table-component">
                @include('components.table', [
                    'tableClass' => 'leave-credits-table',
                    'tableCol' => [
                        'occurred_at',
                        'effective_date',
                        'bucket',
                        'type',
                        'direction',
                        'amount',
                        'remaining',
                        'expires_at',
                        'actor',
                        'description',
                    ],
                    'tableLabel' => [
                        'Occurred',
                        'Effective',
                        'Bucket',
                        'Type',
                        'Direction',
                        'Amount',
                        'Remaining',
                        'Expires',
                        'Actor',
                        'Description',
                    ],
                    'tableData' => $tableData,
                ])

                @if($transactions instanceof \Illuminate\Pagination\LengthAwarePaginator || $transactions instanceof \Illuminate\Pagination\Paginator)
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $transactions->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                @endif
            </div>
        @else
            <div class="container leave-credits">
                <div class="alert alert-info">Select an employee to view balances and ledger transactions.</div>
            </div>
        @endif

    </div>

@endsection
