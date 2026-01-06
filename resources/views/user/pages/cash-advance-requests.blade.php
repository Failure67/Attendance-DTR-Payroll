@extends('layouts.user')

@section('content')

    <div class="wrapper employee employee-cash-advance-requests">

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
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div class="title mb-0">New cash advance request</div>

                    <div class="ca-summary-badge d-flex flex-column flex-sm-row align-items-sm-center gap-2">
                        <div class="badge bg-primary-subtle text-primary px-3 py-2">
                            <div class="small text-muted">Current CA balance</div>
                            <div class="fw-semibold">₱ {{ number_format((float) ($caBalance ?? 0), 2) }}</div>
                        </div>
                        <div class="d-flex flex-column flex-sm-row gap-2 small text-muted">
                            <span>Total advances: <strong>₱ {{ number_format((float) ($totalAdvances ?? 0), 2) }}</strong></span>
                            <span>|</span>
                            <span>Total repayments: <strong>₱ {{ number_format((float) ($totalRepayments ?? 0), 2) }}</strong></span>
                        </div>

                        @php
                            $limit = $caLimit ?? null;
                            $effectiveLimit = $limit['effective'] ?? null;
                        @endphp

                        @if(!is_null($effectiveLimit) && $effectiveLimit > 0)
                            <div class="badge bg-success-subtle text-success px-3 py-2">
                                <div class="small text-muted">Estimated request capacity</div>
                                <div class="fw-semibold">₱ {{ number_format($effectiveLimit, 2) }}</div>
                            </div>
                        @elseif(!$user->isRegular() && !(config('payroll.ca.allow_part_time') ?? false))
                            <div class="badge bg-secondary-subtle text-secondary px-3 py-2">
                                <div class="small text-muted">Eligibility</div>
                                <div class="fw-semibold">Cash advances are normally available only to regular employees.</div>
                            </div>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('worker.cash-advance-requests.store') }}" class="row g-3 mt-2">
                    @csrf

                    <div class="col-md-4">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" required
                            @if(!is_null($effectiveLimit) && $effectiveLimit > 0)
                                max="{{ number_format((float) $effectiveLimit, 2, '.', '') }}"
                            @endif
                        >
                    </div>

                    <div class="col-12">
                        <label for="reason" class="form-label">Reason</label>
                        <textarea name="reason" id="reason" class="form-control" rows="3" maxlength="1000" required>{{ old('reason') }}</textarea>
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
                <div class="title mb-2">Your cash advance requests</div>

                @php
                    $tableData = $requestTableData ?? [];
                @endphp

                @include('user.components.table', [
                    'tableClass' => 'worker-cash-advance-requests',
                    'tableCol' => [
                        'created-at',
                        'amount',
                        'status',
                        'actions',
                    ],
                    'tableLabel' => [
                        'Requested on',
                        'Amount',
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

    <div class="modal fade" id="workerCashAdvanceRequestDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cash advance request details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-4">Requested on</dt>
                        <dd class="col-8" id="worker-ca-detail-requested">—</dd>
                        <dt class="col-4">Amount</dt>
                        <dd class="col-8" id="worker-ca-detail-amount">—</dd>
                        <dt class="col-4">Status</dt>
                        <dd class="col-8" id="worker-ca-detail-status">—</dd>
                        <dt class="col-4">Reason</dt>
                        <dd class="col-8" id="worker-ca-detail-reason">—</dd>
                        <dt class="col-4">Timeline</dt>
                        <dd class="col-8" id="worker-ca-detail-timeline">—</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var table = document.querySelector('.worker-cash-advance-requests');
            if (!table) return;

            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                row.addEventListener('click', function (e) {
                    if (e.target.closest('form,button,a')) {
                        return;
                    }

                    var trigger = row.querySelector('.worker-ca-request-row-trigger');
                    if (!trigger) return;

                    var requestedEl = document.getElementById('worker-ca-detail-requested');
                    var amountEl = document.getElementById('worker-ca-detail-amount');
                    var statusEl = document.getElementById('worker-ca-detail-status');
                    var reasonEl = document.getElementById('worker-ca-detail-reason');
                    var timelineEl = document.getElementById('worker-ca-detail-timeline');

                    if (requestedEl) requestedEl.textContent = trigger.getAttribute('data-ca-date') || '—';
                    if (amountEl) amountEl.textContent = trigger.getAttribute('data-ca-amount') || '—';
                    if (statusEl) statusEl.textContent = trigger.getAttribute('data-ca-status') || '—';
                    if (reasonEl) reasonEl.textContent = trigger.getAttribute('data-ca-reason') || '—';
                    if (timelineEl) timelineEl.textContent = trigger.getAttribute('data-ca-timeline') || '—';

                    var modalEl = document.getElementById('workerCashAdvanceRequestDetailsModal');
                    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) return;
                    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modal.show();
                });
            });
        });
    </script>

@endsection
