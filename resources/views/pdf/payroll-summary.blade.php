<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Payroll Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; }
        th { background: #f2f2f2; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $title ?? 'Payroll Report' }}</h1>
    <div class="meta">
        Generated: {{ ($generatedAt ?? now())->format('Y-m-d H:i') }}<br>
        @if(!empty($filters['period_start']) || !empty($filters['period_end']))
            Period:
            @if(!empty($filters['period_start']) && !empty($filters['period_end']))
                {{ $filters['period_start'] }} to {{ $filters['period_end'] }}
            @elseif(!empty($filters['period_start']))
                From {{ $filters['period_start'] }}
            @else
                Up to {{ $filters['period_end'] }}
            @endif
            <br>
        @endif
        @if(!empty($filters['status']))
            Status: {{ $filters['status'] }}<br>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee</th>
                <th>Period start</th>
                <th>Period end</th>
                <th>Wage type</th>
                <th class="text-right">Min. wage</th>
                <th class="text-right">Units</th>
                <th class="text-right">Gross</th>
                <th class="text-right">Deductions</th>
                <th class="text-right">Net pay</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($payrolls as $index => $payroll)
            @php
                $employeeName = $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : 'Unknown employee';
                $start = $payroll->period_start ? $payroll->period_start->format('Y-m-d') : '';
                $end = $payroll->period_end ? $payroll->period_end->format('Y-m-d') : '';
                $units = $payroll->hours_worked ?? $payroll->days_worked ?? 0;
                $unitLabelMap = [
                    'Hourly' => 'hour/s',
                    'Daily' => 'day/s',
                    'Weekly' => 'week/s',
                    'Monthly' => 'month/s',
                    'Piece rate' => 'unit/s',
                ];
                $unitLabel = $unitLabelMap[$payroll->wage_type] ?? 'unit/s';
                $unitsWorked = $units . ' ' . $unitLabel;
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td>{{ $employeeName }}</td>
                <td class="text-center">{{ $start }}</td>
                <td class="text-center">{{ $end }}</td>
                <td class="text-center">{{ $payroll->wage_type ?? '' }}</td>
                <td class="text-right">{{ number_format((float)($payroll->min_wage ?? 0), 2) }}</td>
                <td class="text-right">{{ $unitsWorked }}</td>
                <td class="text-right">{{ number_format((float)($payroll->gross_pay ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float)($payroll->total_deductions ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float)($payroll->net_pay ?? 0), 2) }}</td>
                <td class="text-center">{{ $payroll->status ?? 'Pending' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="11" class="text-center">No payroll records found for the selected filters.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
