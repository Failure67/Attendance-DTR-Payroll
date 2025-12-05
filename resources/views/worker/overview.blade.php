@extends('layouts.app')

@section('styles')
    <style>
        .worker-dashboard {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1.5rem 2rem 2.5rem;
            background: linear-gradient(135deg, #f3f6ff, #e5f0ff);
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.15);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            height: calc(100vh - 220px);
            display: flex;
            flex-direction: column;
        }

        .worker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .worker-profile {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            color: #0f172a;
        }

        .worker-name {
            font-size: 1.4rem;
            font-weight: 700;
        }

        .worker-id {
            font-size: 0.9rem;
            color: #64748b;
        }

        .worker-tabs {
            display: inline-flex;
            gap: 0.5rem;
            padding: 0.25rem;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 999px;
        }

        .worker-tabs .tab {
            padding: 0.4rem 1.2rem;
            border-radius: 999px;
            font-size: 0.9rem;
            text-decoration: none;
            color: #0f172a;
            transition: all 0.15s ease-in-out;
        }

        .worker-tabs .tab:hover {
            background: rgba(59, 130, 246, 0.12);
        }

        .worker-tabs .tab.active {
            background: #2563eb;
            color: #ffffff;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
        }

        .worker-content {
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
            flex: 1;
            overflow-y: auto;
            min-height: 0;
        }

        .worker-cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }

        .worker-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 1rem 1.1rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.12);
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
        }

        .worker-card .label {
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
        }

        .worker-card .value {
            font-size: 1.4rem;
            font-weight: 700;
            color: #0b1120;
        }

        .worker-card .sub {
            font-size: 0.9rem;
            color: #4b5563;
        }

        .worker-payroll-summary {
            background: #ffffff;
            border-radius: 18px;
            padding: 1.3rem 1.5rem 1.6rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .worker-payroll-summary .section-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: #0f172a;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
        }

        .summary-col {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }

        .summary-col .row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            color: #0f172a;
        }

        .summary-col .row span:first-child {
            color: #64748b;
        }

        .summary-col .row.positive span:last-child {
            color: #16a34a;
        }

        .summary-col .row.negative span:last-child {
            color: #dc2626;
        }

        .summary-col .row.highlight {
            background: #e0f2fe;
            padding: 0.55rem 0.75rem;
            border-radius: 12px;
            color: #0b1120;
            font-weight: 700;
        }

        @media (max-width: 1024px) {
            .worker-cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .worker-dashboard {
                margin: 1rem;
                padding: 1.2rem 1.1rem 1.6rem;
                height: auto;
            }

            .worker-content {
                overflow: visible;
            }

            .worker-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .worker-cards {
                grid-template-columns: 1fr;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

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
