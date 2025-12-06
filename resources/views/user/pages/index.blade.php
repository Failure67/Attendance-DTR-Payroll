@extends('layouts.user')

@section('content')

    @php
        $activeTab = request('tab');
        if ($activeTab === 'history') {
            $activeTab = 'history';
        } elseif ($activeTab === 'attendance') {
            $activeTab = 'attendance';
        } else {
            $activeTab = 'overview';
        }
    @endphp

    <div class="wrapper {{ $pageClass }}">

        <div class="container {{ $pageClass }} header">

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

                <a href="{{ route('worker.dashboard') }}" class="selector-item {{ $activeTab === 'overview' ? 'selected' : '' }}">
                    Overview
                </a>
                
                <a href="{{ route('worker.dashboard', ['tab' => 'history']) }}" class="selector-item {{ $activeTab === 'history' ? 'selected' : '' }}">
                    Payroll History
                </a>
                
                <a href="{{ route('worker.dashboard', ['tab' => 'attendance']) }}" class="selector-item {{ $activeTab === 'attendance' ? 'selected' : '' }}">
                    Attendance
                </a>

            </div>

        </div>

        <div class="container {{ $pageClass }}" @if($activeTab !== 'overview') style="display: none;" @endif>

            @php
                $latestNet = $latestPayroll ? number_format((float) $latestPayroll->net_pay, 2) : null;
                $latestPeriod = $latestPayroll && $latestPayroll->period_start && $latestPayroll->period_end
                    ? $latestPayroll->period_start->format('Y-m-d') . ' to ' . $latestPayroll->period_end->format('Y-m-d')
                    : null;
            @endphp

            <div class="content cards">

                @include('user.components.count', [
                    'countClass' => 'latest-payment',
                    'countLabel' => 'Latest Payment',
                    'countDesc' => 'As of ' . date('F d, Y'),
                    'countIcon' => '<i class="fa-solid fa-money-bills"></i>',
                    'countValue' => '₱ ' . ($latestNet ?? '0.00'),
                ])
                
                @include('user.components.count', [
                    'countClass' => 'hours-worked',
                    'countLabel' => 'Hours worked',
                    'countDesc' => 'As of ' . date('F Y'),
                    'countIcon' => '<i class="fa-solid fa-clock"></i>',
                    'countValue' => number_format($monthHours ?? 0) . 'h',
                ])

                @include('user.components.count', [
                    'countClass' => 'overtime',
                    'countLabel' => 'Overtime',
                    'countDesc' => 'As of ' . date('F Y'),
                    'countIcon' => '<i class="fa-solid fa-bars-staggered"></i>',
                    'countValue' => number_format($monthOvertime ?? 0) . 'h',
                ])

                @include('user.components.count', [
                    'countClass' => 'ca-balance',
                    'countLabel' => 'CA Balance',
                    'countDesc' => '',
                    'countIcon' => '<i class="fa-solid fa-file-waveform"></i>',
                    'countValue' => '₱ ' . number_format($caBalance ?? 0, 2),
                ])

            </div>

        </div>

        <div class="container {{ $pageClass }}" @if($activeTab !== 'overview') style="display: none;" @endif>

            <div class="content payroll-breakdown">

                <div class="title">
                    Latest payroll breakdown
                </div>
                
                @php
                    $gross = $latestPayroll ? number_format((float) $latestPayroll->gross_pay, 2) : '0.00';
                    $deductions = $latestPayroll ? number_format((float) $latestPayroll->total_deductions, 2) : '0.00';
                    $net = $latestPayroll ? number_format((float) $latestPayroll->net_pay, 2) : '0.00';
                @endphp
                
                <div class="pay-amount">
                    
                    <div class="pay-item">
                        
                        <span class="label">
                            Gross pay
                        </span>
                        
                        <span class="value">
                            ₱ {{ $gross ?? '0.00' }}
                        </span>

                    </div>

                    <div class="pay-item net-pay">

                        <span class="label">
                            Net pay
                        </span>
                        
                        <span class="value">
                            ₱ {{ $net ?? '0.00' }}
                        </span>

                    </div>

                </div>

                <div class="pay-amount">
                    
                    <div class="pay-item">

                        <span class="label">
                            Total deductions
                        </span>
                        
                        <span class="value">
                            -₱ {{ $deductions ?? '0.00' }}
                        </span>

                    </div>

                    <div class="pay-item">

                        <span class="label">
                            Period
                        </span>
                        
                        <span class="value">
                            {{ $latestPeriod ?? 'N/A' }}
                        </span>

                    </div>

                </div>

            </div>

        </div>

        <div class="container {{ $pageClass }} payroll-history" @if($activeTab !== 'history') style="display: none;" @endif>

            <div class="content payroll-history">

                <div class="title">
                    Payroll History
                </div>

                @php
                    $tableData = [];
                    foreach ($payrolls as $payroll) {
                        $period = ($payroll->period_start && $payroll->period_end)
                            ? $payroll->period_start->format('M d, Y') . ' - ' . $payroll->period_end->format('M d, Y')
                            : ($payroll->created_at ? $payroll->created_at->format('M d, Y') : 'N/A');
                        $gross = '₱ ' . number_format((float) ($payroll->gross_pay ?? 0), 2);
                        $deductions = '₱ ' . number_format((float) ($payroll->total_deductions ?? 0), 2);
                        $net = '₱ ' . number_format((float) ($payroll->net_pay ?? 0), 2);
                        $status = $payroll->status ?? 'Pending';
                        
                        $tableData[] = [
                            $period,
                            $gross,
                            $deductions,
                            $net,
                            $status,
                            '<a href="' . route('worker.payslip', $payroll->id) . '" class="btn btn-sm btn-outline-primary">View payslip</a>'
                        ];
                    }
                @endphp

                @include('user.components.table', [
                    'tableClass' => 'payroll-history',
                    'tableCol' => [
                        'date-period',
                        'gross-pay',
                        'deductions',
                        'net-pay',
                        'status',
                        'action',
                    ],
                    'tableLabel' => [
                        'Date period',
                        'Gross pay',
                        'Deductions',
                        'Net pay',
                        'Status',
                        'Action'
                    ],
                    'tableData' => $tableData,
                ])

                @if($payrolls instanceof \Illuminate\Pagination\LengthAwarePaginator || $payrolls instanceof \Illuminate\Pagination\Paginator)
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $payrolls->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                @endif

            </div>

        </div>

        <div class="container {{ $pageClass }} attendance" @if($activeTab !== 'attendance') style="display: none;" @endif>

            <div class="content attendance">

                <div class="title">
                    Recent Attendance
                </div>

                @php
                    $attendanceTableData = [];
                    foreach ($attendances as $attendance) {
                        $date = $attendance->date
                            ? $attendance->date->format('F j, Y')
                            : ($attendance->time_in ? $attendance->time_in->format('F j, Y') : 'N/A');
                        $timeIn = $attendance->time_in ? $attendance->time_in->format('h:i A') : '—';
                        $timeOut = $attendance->time_out ? $attendance->time_out->format('h:i A') : '—';
                        $hours = number_format((float) ($attendance->total_hours ?? 0), 2) . ' hrs';
                        $status = $attendance->status ?? 'Present';
                        
                        $attendanceTableData[] = [
                            $date,
                            $timeIn,
                            $timeOut,
                            $hours,
                            $status,
                        ];
                    }
                @endphp

                @include('user.components.table', [
                    'tableClass' => 'recent-attendance',
                    'tableCol' => [
                        'date',
                        'time-in',
                        'time-out',
                        'hours',
                        'status',    
                    ],
                    'tableLabel' => [
                        'Date',
                        'Time-in',
                        'Time-out',
                        'Hours',
                        'Status',
                    ],
                    'tableData' => $attendanceTableData,
                ])

                @if($attendances instanceof \Illuminate\Pagination\LengthAwarePaginator || $attendances instanceof \Illuminate\Pagination\Paginator)
                    <div class="mt-3 d-flex justify-content-end">
                        {{ $attendances->onEachSide(1)->links('pagination::bootstrap-4') }}
                    </div>
                @endif

            </div>

        </div>
    
    </div>

@endsection