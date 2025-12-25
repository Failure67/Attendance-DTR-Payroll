@extends('layouts.user')

@section('content')

    <div class="wrapper employee employee-leave-requests">

        <div class="container employee header">

            <div class="content info">

                <div class="name">

                    <div class="profile-picture">
                        @if($user->profile_picture && file_exists(public_path('uploads/profiles/' . $user->profile_picture)))
                            <img src="{{ asset('uploads/profiles/' . $user->profile_picture) }}" alt="Profile Picture" width="120">
                        @else
                            <img src="{{ asset('assets/img/defaults/user_image.webp') }}" alt="Profile Picture" width="120">
                        @endif
                    </div>

                    <div class="name-info">

                        <div class="name-container">
                            {{ $user->full_name ?? $user->username }}
                            @php
                                $employmentType = $user->employment_type ?? \App\Models\User::EMPLOYMENT_TYPE_REGULAR;
                                $employmentTypeLabel = $employmentType === \App\Models\User::EMPLOYMENT_TYPE_PART_TIME ? 'Part-time' : 'Regular';
                            @endphp
                            <span class="badge bg-light text-dark ms-2" style="font-size: 0.75rem;">{{ $employmentTypeLabel }}</span>
                        </div>

                        <div class="id-number">
                            <span class="icon">
                                <i class="fa-solid fa-id-badge"></i>
                            </span>
                            <div class="label">
                                RMCS-{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}
                            </div>
                        </div>

                    </div>

                </div>

            </div>

            <div class="content selector">

                <a href="{{ route('worker.dashboard') }}" class="selector-item">
                    Overview
                </a>

                <a href="{{ route('worker.dashboard', ['tab' => 'history']) }}" class="selector-item">
                    Payroll History
                </a>

                <a href="{{ route('worker.dashboard', ['tab' => 'attendance']) }}" class="selector-item">
                    Attendance
                </a>

                @php
                    $requestsSelected = in_array(Route::currentRouteName(), [
                        'worker.cash-advance-requests',
                        'worker.leave-requests',
                        'worker.leave-credits',
                    ], true);
                @endphp

                <div class="selector-dropdown">
                    <a href="#" class="selector-item {{ $requestsSelected ? 'selected' : '' }}" onclick="return false;">
                        Requests
                    </a>
                    <div class="selector-dropdown-menu">
                        <a href="{{ route('worker.cash-advance-requests') }}" class="selector-dropdown-item {{ Route::currentRouteName() === 'worker.cash-advance-requests' ? 'selected' : '' }}">
                            Cash Advance Requests
                        </a>
                        <a href="{{ route('worker.leave-requests') }}" class="selector-dropdown-item {{ Route::currentRouteName() === 'worker.leave-requests' ? 'selected' : '' }}">
                            Leave Requests
                        </a>
                        <a href="{{ route('worker.leave-credits') }}" class="selector-dropdown-item {{ Route::currentRouteName() === 'worker.leave-credits' ? 'selected' : '' }}">
                            Leave Credits
                        </a>
                    </div>
                </div>

            </div>

        </div>

        <div class="container employee">

            <div class="content payroll-breakdown mb-3">
                @php
                    $usage = $leaveUsage ?? null;
                    $yearLabel = $usage['year_label'] ?? now()->format('Y');
                    $paidDays = (float) ($usage['paid_days'] ?? 0);
                    $unpaidDays = (float) ($usage['unpaid_days'] ?? 0);
                    $isPartTime = (bool) ($usage['is_part_time'] ?? false);
                @endphp

                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="title mb-0">New leave request</div>

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

                <form method="POST" action="{{ route('worker.leave-requests.store') }}" class="row g-3 mt-2">
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

                    @if ($errors->any())
                        <div class="col-12">
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @endif

                    @if (session('success'))
                        <div class="col-12">
                            <div class="alert alert-success mb-0">
                                {{ session('success') }}
                            </div>
                        </div>
                    @endif
                </form>
            </div>

            <div class="content payroll-history">
                <div class="title mb-2">Your leave requests</div>

                @php
                    $tableData = $requestTableData ?? [];
                @endphp

                @include('user.components.table', [
                    'tableClass' => 'worker-leave-requests',
                    'tableCol' => [
                        'date-range',
                        'type',
                        'duration',
                        'paidness',
                        'status',
                        'actions',
                    ],
                    'tableLabel' => [
                        'Period',
                        'Type',
                        'Days',
                        'Paid / unpaid',
                        'Status',
                        'Actions',
                    ],
                    'tableData' => $tableData,
                ])

                @if($requests instanceof \Illuminate\Pagination\LengthAwarePaginator || $requests instanceof \Illuminate\Pagination\Paginator)
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $requests->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                @endif
            </div>

        </div>

    </div>

@endsection
