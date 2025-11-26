<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        body {
            margin: 0;
            padding: 24px;
            font-size: 12px;
            color: #0f172a;
        }
        .payslip-wrapper {
            max-width: 800px;
            margin: 0 auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px 24px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .company-name {
            font-size: 18px;
            font-weight: 700;
        }
        .section-title {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 16px 0 6px;
            color: #6b7280;
        }
        .grid {
            width: 100%;
            margin-top: 4px;
        }
        .col {
            width: 50%;
            vertical-align: top;
        }
        .row {
            margin-bottom: 4px;
        }
        .row span:first-child {
            color: #6b7280;
        }
        .row.negative span:last-child {
            color: #b91c1c;
        }
        .row.positive span:last-child {
            color: #15803d;
        }
        .row.highlight {
            margin-top: 4px;
            padding: 6px 10px;
            border-radius: 4px;
            background: #e5e7eb;
            color: #111827;
            font-weight: 700;
        }
        ul {
            margin: 0;
            padding-left: 16px;
        }
        ul li {
            margin-bottom: 2px;
        }
        .footer {
            margin-top: 18px;
            font-size: 10px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $period = ($payroll->period_start && $payroll->period_end)
            ? $payroll->period_start->format('Y-m-d') . ' to ' . $payroll->period_end->format('Y-m-d')
            : ($payroll->created_at ? $payroll->created_at->format('Y-m-d') : 'N/A');
        $gross = number_format((float) ($payroll->gross_pay ?? 0), 2);
        $deductions = number_format((float) ($payroll->total_deductions ?? 0), 2);
        $net = number_format((float) ($payroll->net_pay ?? 0), 2);
        $employeeName = $user->full_name ?? $user->username;
    @endphp

    <div class="payslip-wrapper">
        <div class="header">
            <div>
                <div class="company-name">RMCS Payslip</div>
                <div>Employee: {{ $employeeName }}</div>
                <div>ID: EMP-{{ str_pad($user->id, 4, '0', STR_PAD_LEFT) }}</div>
            </div>
            <div style="text-align: right; font-size: 11px; color: #6b7280;">
                <div>Period: {{ $period }}</div>
                <div>Generated: {{ now()->format('Y-m-d') }}</div>
                <div>Status: {{ $payroll->status ?? 'Pending' }}</div>
            </div>
        </div>

        <table class="grid" cellspacing="0" cellpadding="0">
            <tr>
                <td class="col">
                <div class="section-title">Earnings</div>
                <div class="row">
                    <span>Minimum wage:</span> <span>Php {{ number_format((float) ($payroll->min_wage ?? 0), 2) }}</span>
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
                <div class="row">
                    <span>Units worked:</span> <span>{{ $units }} {{ $unitLabel }}</span>
                </div>
                <div class="row">
                    <span>Gross pay:</span> <span>Php {{ $gross }}</span>
                </div>

                @if($attendanceSummary)
                    <div class="section-title">Attendance summary</div>
                    <div class="row">
                        <span>Total hours:</span> <span>{{ number_format($attendanceSummary['total_hours'] ?? 0, 2) }}h</span>
                    </div>
                    <div class="row">
                        <span>Overtime hours:</span> <span>{{ number_format($attendanceSummary['total_overtime'] ?? 0, 2) }}h</span>
                    </div>
                    <div class="row">
                        <span>Present days:</span> <span>{{ $attendanceSummary['present_days'] ?? 0 }}</span>
                    </div>
                    <div class="row">
                        <span>On leave days:</span> <span>{{ $attendanceSummary['leave_days'] ?? 0 }}</span>
                    </div>
                    <div class="row">
                        <span>Absent days:</span> <span>{{ $attendanceSummary['absent_days'] ?? 0 }}</span>
                    </div>
                @endif
                </td>

                <td class="col">
                <div class="section-title">Deductions</div>
                @php
                    $deductionsList = $payroll->deductions ?? collect();
                @endphp
                @if($deductionsList->isNotEmpty() || $caDeductedThisPayroll > 0)
                    <ul>
                        @foreach($deductionsList as $d)
                            <li>{{ $d->deduction_name }} - Php {{ number_format((float) $d->amount, 2) }}</li>
                        @endforeach
                        @if($caDeductedThisPayroll > 0)
                            <li>Cash advance repayment - Php {{ number_format($caDeductedThisPayroll, 2) }}</li>
                        @endif
                    </ul>
                @else
                    <div class="row">
                        <span>Items:</span> <span>No deductions</span>
                    </div>
                @endif

                <div class="row negative" style="margin-top: 4px;">
                    <span>Total deductions:</span> <span>-Php {{ $deductions }}</span>
                </div>
                <div class="row highlight">
                    <span>Net pay:</span> <span>Php {{ $net }}</span>
                </div>
                </td>
            </tr>
        </table>

        <div class="footer">
            This document is system-generated and does not require a physical signature.
        </div>
    </div>
</body>
</html>
