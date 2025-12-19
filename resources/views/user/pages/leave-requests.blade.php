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

                <a href="{{ route('worker.cash-advance-requests') }}" class="selector-item">
                    Cash Advance Requests
                </a>

                <a href="{{ route('worker.leave-requests') }}" class="selector-item selected">
                    Leave Requests
                </a>

            </div>

        </div>

        <div class="container employee">

            <div class="content payroll-breakdown mb-3">
                <div class="title">New leave request</div>

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
