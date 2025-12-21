@extends('layouts.user')

@section('content')

    <div class="wrapper employee employee-leave-credits">

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

                <a href="{{ route('worker.leave-requests') }}" class="selector-item">
                    Leave Requests
                </a>

                <a href="{{ route('worker.leave-credits') }}" class="selector-item selected">
                    Leave Credits
                </a>

            </div>

        </div>

        @php
            $balances = $balances ?? [];
            $tableData = $transactionTableData ?? [];
        @endphp

        <div class="container employee">

            <div class="content payroll-breakdown mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="title mb-0">Your leave credits</div>

                    <div class="d-flex flex-wrap gap-2">
                        @foreach(($leaveTypeLabels ?? []) as $code => $label)
                            @php $bal = (float) ($balances[$code] ?? 0); @endphp
                            <div class="badge bg-primary-subtle text-primary px-3 py-2">
                                <div class="small text-muted">{{ $label }} balance</div>
                                <div class="fw-semibold">{{ number_format($bal, 3) }} days</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if($user->isPartTime())
                    <div class="alert alert-warning mb-0">Part-time employees do not accrue paid leave credits by default. Leave requests are treated as unpaid unless an administrative exception is recorded.</div>
                @endif
            </div>

            <div class="content payroll-history">
                <div class="title mb-2">Ledger transactions</div>

                @include('user.components.table', [
                    'tableClass' => 'worker-leave-credits-table',
                    'tableCol' => [
                        'occurred_at',
                        'effective_date',
                        'bucket',
                        'type',
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

        </div>

    </div>

@endsection
