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

                <a href="{{ route('worker.cash-advance-requests') }}" class="selector-item selected">
                    Cash Advance Requests
                </a>

            </div>

        </div>

        <div class="container employee">

            <div class="content payroll-breakdown mb-3">
                <div class="title">New cash advance request</div>

                <form method="POST" action="{{ route('worker.cash-advance-requests.store') }}" class="row g-3 mt-2">
                    @csrf

                    <div class="col-md-4">
                        <label for="amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" required>
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

@endsection
