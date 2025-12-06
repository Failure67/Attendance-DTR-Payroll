@extends('layouts.user')

@section('styles')
    <style>
        .worker-dashboard {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 30px;
            background-color: #d1e1f3;
            border-radius: 12px;
            box-shadow: 0px 0px 4px 0px rgba(0, 0, 0, 0.6);
            border-bottom: 0px solid #0D2C42;
            font-family: 'Inter', sans-serif;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .worker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
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

        .payslip-card {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .payslip-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.25rem;
            gap: 1.5rem;
        }

        .payslip-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #0f172a;
        }

        .payslip-meta {
            font-size: 0.95rem;
            color: #4b5563;
        }

        .payslip-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1.5rem;
        }

        .payslip-section-title {
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .payslip-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.95rem;
            margin-bottom: 0.25rem;
        }

        .payslip-row span:first-child {
            color: #64748b;
        }

        .payslip-row.negative span:last-child {
            color: #dc2626;
        }

        .payslip-row.positive span:last-child {
            color: #16a34a;
        }

        .payslip-row.highlight {
            background: #e0f2fe;
            padding: 0.55rem 0.75rem;
            border-radius: 12px;
            color: #0b1120;
            font-weight: 700;
            margin-top: 0.35rem;
        }

        .payslip-deductions-list {
            margin: 0;
            padding-left: 1rem;
            font-size: 0.9rem;
        }

        .payslip-deductions-list li {
            margin-bottom: 0.15rem;
        }

        .payslip-footer {
            margin-top: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        @media (max-width: 768px) {
            .worker-dashboard {
                margin: 1rem;
                padding: 1.2rem 1.1rem 1.6rem;
            }

            .worker-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .payslip-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endsection

@section('content')
    <div class="wrapper employee">

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

                <a href="{{ route('worker.payroll-history') }}" class="selector-item selected">
                    Payroll History
                </a>

                <a href="{{ route('worker.attendance') }}" class="selector-item">
                    Attendance
                </a>

            </div>

        </div>

        <div class="container employee payroll-history">

            <div class="content payroll-breakdown">

                <div class="title">
                    Payslip
                </div>

                @php
                    $period = ($payroll->period_start && $payroll->period_end)
                        ? $payroll->period_start->format('Y-m-d') . ' to ' . $payroll->period_end->format('Y-m-d')
                        : ($payroll->created_at ? $payroll->created_at->format('Y-m-d') : 'N/A');
                    $gross = number_format((float) ($payroll->gross_pay ?? 0), 2);
                    $deductions = number_format((float) ($payroll->total_deductions ?? 0), 2);
                    $net = number_format((float) ($payroll->net_pay ?? 0), 2);
                @endphp

                <div class="payslip-card">

                    <div class="payslip-header">
                        <div>
                            <div class="payslip-title">Payslip</div>
                            <div class="payslip-meta">Period: {{ $period }}</div>
                            <div class="payslip-meta">Generated on: {{ now()->format('Y-m-d') }}</div>
                        </div>
                        <div class="text-end">
                            <div class="payslip-meta">Status: {{ $payroll->status ?? 'Pending' }}</div>
                            <div class="payslip-meta">Wage type: {{ $payroll->wage_type ?? 'N/A' }}</div>
                        </div>
                    </div>

                    <div class="payslip-grid">
                <div>
                    <div class="payslip-section-title">Earnings</div>
                    <div class="payslip-row">
                        <span>Minimum wage</span><span>₱ {{ number_format((float) ($payroll->min_wage ?? 0), 2) }}</span>
                    </div>
                    @php
                        $units = $payroll->hours_worked ?? $payroll->days_worked ?? 0;
                        $unitLabelMap = [
                            'Hourly' => 'hour/s',
                            'Daily' => 'day/s',
                            'Weekly' => 'week/s',
                            'Monthly' => 'month/s',
                            'Piece rate' => 'unit/s',
                        ];
                        $unitLabel = $unitLabelMap[$payroll->wage_type] ?? 'unit/s';
                    @endphp
                    <div class="payslip-row">
                        <span>Units worked</span><span>{{ $units }} {{ $unitLabel }}</span>
                    </div>
                    <div class="payslip-row">
                        <span>Gross pay</span><span>₱ {{ $gross }}</span>
                    </div>

                    @if($attendanceSummary)
                        <div class="payslip-section-title" style="margin-top: 0.9rem;">Attendance summary</div>
                        <div class="payslip-row">
                            <span>Total hours</span><span>{{ number_format($attendanceSummary['total_hours'] ?? 0, 2) }}h</span>
                        </div>
                        <div class="payslip-row">
                            <span>Overtime hours</span><span>{{ number_format($attendanceSummary['total_overtime'] ?? 0, 2) }}h</span>
                        </div>
                        <div class="payslip-row">
                            <span>Present days</span><span>{{ $attendanceSummary['present_days'] ?? 0 }}</span>
                        </div>
                        <div class="payslip-row">
                            <span>On leave days</span><span>{{ $attendanceSummary['leave_days'] ?? 0 }}</span>
                        </div>
                        <div class="payslip-row">
                            <span>Absent days</span><span>{{ $attendanceSummary['absent_days'] ?? 0 }}</span>
                        </div>
                    @endif
                </div>

                <div>
                    <div class="payslip-section-title">Deductions</div>
                    @php
                        $deductionsList = $payroll->deductions ?? collect();
                    @endphp
                    @if($deductionsList->isNotEmpty() || $caDeductedThisPayroll > 0)
                        <ul class="payslip-deductions-list">
                            @foreach($deductionsList as $d)
                                <li>{{ $d->deduction_name }} - ₱ {{ number_format((float) $d->amount, 2) }}</li>
                            @endforeach
                            @if($caDeductedThisPayroll > 0)
                                <li>Cash advance repayment - ₱ {{ number_format($caDeductedThisPayroll, 2) }}</li>
                            @endif
                        </ul>
                    @else
                        <div class="payslip-row">
                            <span>Items</span><span>No deductions</span>
                        </div>
                    @endif

                    <div class="payslip-row negative" style="margin-top: 0.4rem;">
                        <span>Total deductions</span><span>-₱ {{ $deductions }}</span>
                    </div>
                    <div class="payslip-row highlight">
                        <span>Net pay</span><span>₱ {{ $net }}</span>
                    </div>
                </div>
            </div>

            <div class="payslip-footer">
                <div>
                    For questions about this payslip, please contact HR.
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('worker.payroll-history') }}" class="btn btn-sm btn-outline-secondary">Back to payroll history</a>
                    <a href="{{ route('worker.payslip.download', $payroll->id) }}" class="btn btn-sm btn-primary">Download PDF</a>
                </div>
            </div>

                </div>

            </div>

        </div>

    </div>
@endsection
