@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper my-leave-requests">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-calendar-plus"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Submit and track your leave requests</p>
                </div>
            </div>
        </div>

        @if (session('success') || session('error') || $errors->any())
            <div class="container my-leave-requests mb-3">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger mb-0">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <div class="container my-leave-requests mb-3">
            <div class="card">
                <div class="card-body">
                    @php
                        $usage = $leaveUsage ?? null;
                        $yearLabel = $usage['year_label'] ?? now()->format('Y');
                        $paidDays = (float) ($usage['paid_days'] ?? 0);
                        $unpaidDays = (float) ($usage['unpaid_days'] ?? 0);
                        $isPartTime = (bool) ($usage['is_part_time'] ?? false);
                    @endphp

                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                        <div class="fw-semibold">New leave request</div>

                        <div class="d-flex flex-wrap gap-2 align-items-stretch">
                            <div class="badge bg-primary-subtle text-primary px-3 py-2">
                                <div class="small text-muted">Paid leave used ({{ $yearLabel }})</div>
                                <div class="fw-semibold">{{ number_format($paidDays, 3) }} days</div>
                            </div>

                            <div class="badge bg-secondary-subtle text-secondary px-3 py-2">
                                <div class="small text-muted">Unpaid leave used ({{ $yearLabel }})</div>
                                <div class="fw-semibold">{{ number_format($unpaidDays, 3) }} days</div>
                            </div>

                            @if($isPartTime)
                                <div class="badge bg-warning-subtle text-warning px-3 py-2">
                                    <div class="small text-muted">Employment type policy</div>
                                    <div class="fw-semibold">Part-time leave is treated as unpaid by default.</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('my.leave-requests.store') }}" class="row g-3 mt-2">
                        @csrf

                        <div class="col-md-4">
                            <label for="type" class="form-label">Leave type</label>
                            <select name="type" id="type" class="form-select" required>
                                <option value="">Select type</option>
                                <option value="vacation" {{ old('type') === 'vacation' ? 'selected' : '' }}>Vacation</option>
                                <option value="sick" {{ old('type') === 'sick' ? 'selected' : '' }}>Sick</option>
                                <option value="emergency" {{ old('type') === 'emergency' ? 'selected' : '' }}>Emergency</option>
                                <option value="pto" {{ old('type') === 'pto' ? 'selected' : '' }}>Paid time off (PTO)</option>
                                <option value="lwop" {{ old('type') === 'lwop' ? 'selected' : '' }}>Leave without pay (LWOP)</option>
                                <option value="awol" {{ old('type') === 'awol' ? 'selected' : '' }}>AWOL</option>
                                <option value="unpaid" {{ old('type') === 'unpaid' ? 'selected' : '' }}>Unpaid leave</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="is_paid" class="form-label">Paid?</label>
                            <select name="is_paid" id="is_paid" class="form-select" required>
                                <option value="1" {{ old('is_paid', '1') === '1' ? 'selected' : '' }}>Paid</option>
                                <option value="0" {{ old('is_paid') === '0' ? 'selected' : '' }}>Unpaid</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label for="duration_days" class="form-label">Duration (days)</label>
                            <input type="number" step="0.125" min="0.125" name="duration_days" id="duration_days" class="form-control" value="{{ old('duration_days', '1.0') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label for="date_start" class="form-label">Start date</label>
                            <input type="date" name="date_start" id="date_start" class="form-control" value="{{ old('date_start') }}" required>
                        </div>

                        <div class="col-md-4">
                            <label for="date_end" class="form-label">End date</label>
                            <input type="date" name="date_end" id="date_end" class="form-control" value="{{ old('date_end') }}" required>
                        </div>

                        <div class="col-12">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" maxlength="255" required>{{ old('reason') }}</textarea>
                        </div>

                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">Submit request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="container my-leave-requests table-component">
            <div class="card">
                <div class="card-body">
                    <div class="fw-semibold mb-2">Your leave requests</div>

                    @php
                        $tableData = $requestTableData ?? [];
                    @endphp

                    @include('components.table', [
                        'tableClass' => 'my-leave-requests-table',
                        'tableCol' => ['date-range', 'type', 'duration', 'paidness', 'status', 'actions'],
                        'tableLabel' => ['Period', 'Type', 'Days', 'Paid / unpaid', 'Status', 'Actions'],
                        'tableData' => $tableData,
                        'rawColumns' => ['status', 'actions'],
                    ])

                    @if($requests instanceof \Illuminate\Pagination\LengthAwarePaginator || $requests instanceof \Illuminate\Pagination\Paginator)
                        <div class="mt-3 d-flex justify-content-end">
                            {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

    </div>

@endsection
