@extends('layouts.app')

@section('content')
    <div class="worker-dashboard overview">
        <div class="worker-header">
            <div class="worker-profile">
                <div class="worker-name">{{ $user->full_name ?? $user->username }}</div>
                <div class="worker-id">ID: EMP-{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</div>
            </div>
            <div class="worker-tabs">
                <a href="{{ route('worker.dashboard') }}" class="tab active">Overview</a>
                <a href="{{ route('worker.payroll-history') }}" class="tab">Payroll History</a>
                <a href="{{ route('worker.attendance') }}" class="tab">Attendance</a>
            </div>
        </div>

        <div class="worker-content">
            <div class="worker-cards">
                @php
                    $latestNet = $latestPayroll ? number_format((float) $latestPayroll->net_pay, 2) : null;
                    $latestPeriod = $latestPayroll && $latestPayroll->period_start && $latestPayroll->period_end
                        ? $latestPayroll->period_start->format('Y-m-d') . ' to ' . $latestPayroll->period_end->format('Y-m-d')
                        : null;
                @endphp

                <div class="worker-card">
                    <div class="label">Latest payment</div>
                    <div class="value">
                        @if ($latestNet)
                            ₱ {{ $latestNet }}
                        @else
                            N/A
                        @endif
                    </div>
                    <div class="sub">
                        @if ($latestPeriod)
                            Period {{ $latestPeriod }}
                        @else
                            No payroll records yet
                        @endif
                    </div>
                </div>

                <div class="worker-card">
                    <div class="label">Hours worked</div>
                    <div class="value">{{ number_format($monthHours ?? 0, 2) }}h</div>
                    <div class="sub">This month</div>
                </div>

                <div class="worker-card">
                    <div class="label">Overtime</div>
                    <div class="value">{{ number_format($monthOvertime ?? 0, 2) }}h</div>
                    <div class="sub">This month</div>
                </div>

                <div class="worker-card">
                    <div class="label">CA balance</div>
                    <div class="value">₱ {{ number_format($caBalance ?? 0, 2) }}</div>
                    <div class="sub">Outstanding cash advance</div>
                </div>
            </div>

            <div class="worker-payroll-summary">
                <div class="section-title">Latest payroll breakdown</div>

                @if ($latestPayroll)
                    @php
                        $gross = number_format((float) $latestPayroll->gross_pay, 2);
                        $deductions = number_format((float) $latestPayroll->total_deductions, 2);
                        $net = number_format((float) $latestPayroll->net_pay, 2);
                    @endphp

                    <div class="summary-grid">
                        <div class="summary-col">
                            <div class="row">
                                <span>Gross pay</span><span>₱ {{ $gross }}</span>
                            </div>
                            <div class="row">
                                <span>CA balance after payroll</span><span>₱ {{ number_format($caBalance ?? 0, 2) }}</span>
                            </div>
                        </div>
                        <div class="summary-col">
                            <div class="row negative">
                                <span>Total deductions</span><span>-₱ {{ $deductions }}</span>
                            </div>
                            <div class="row highlight">
                                <span>Net pay</span><span>₱ {{ $net }}</span>
                            </div>
                            <div class="row">
                                <span>Period</span>
                                <span>{{ $latestPeriod ?? 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="summary-grid">
                        <div class="summary-col">
                            <div class="row">
                                <span>Gross pay</span><span>N/A</span>
                            </div>
                        </div>
                        <div class="summary-col">
                            <div class="row highlight">
                                <span>Net pay</span><span>N/A</span>
                            </div>
                            <div class="row">
                                <span>Period</span><span>No payroll data yet</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
