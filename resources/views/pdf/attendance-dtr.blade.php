<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Daily Time Record' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 18px; margin-bottom: 2px; text-align: center; }
        h2 { font-size: 12px; margin: 0; text-align: center; }
        .meta { font-size: 10px; margin: 8px 0 12px; }
        .meta-row { display: flex; justify-content: space-between; }
        .meta-col { width: 48%; }
        .meta-col-generated { text-align: left; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; }
        th { background: #f2f2f2; font-size: 10px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .small { font-size: 9px; }
    </style>
</head>
<body>
    <h1>Daily Time Record</h1>
    <h2>{{ $employee->full_name ?? $employee->username ?? 'Employee' }}</h2>

    <div class="meta">
        <div class="meta-row">
            <div class="meta-col">
                <strong>Employee ID:</strong>
                @if(!empty($employee))
                    RMCS-{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}
                @else
                    N/A
                @endif
            </div>
        </div>
        <div class="meta-row" style="margin-top: 4px;">
            <div class="meta-col">
                <strong>Period:</strong>
                @if(!empty($period_start) && !empty($period_end))
                    {{ $period_start }} to {{ $period_end }}
                @elseif(!empty($period_start))
                    From {{ $period_start }}
                @elseif(!empty($period_end))
                    Up to {{ $period_end }}
                @else
                    All available records
                @endif
            </div>
            <div class="meta-col meta-col-generated">
                <strong>Generated:</strong>
                {{ ($generatedAt ?? now())->format('Y-m-d H:i') }}
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center">Date</th>
                <th class="text-center">Day</th>
                <th class="text-center">Time in</th>
                <th class="text-center">Time out</th>
                <th class="text-right">Total hours</th>
                <th class="text-right">Overtime</th>
                <th class="text-center">Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($dtrRows as $row)
            @php
                /** @var \Carbon\Carbon $date */
                $date = $row['date'];
                /** @var \App\Models\Attendance|null $record */
                $record = $row['record'] ?? null;

                $timeIn = $record && $record->time_in ? $record->time_in->format('g:i A') : '';
                $timeOut = $record && $record->time_out ? $record->time_out->format('g:i A') : '';
                $totalHours = $record ? (float) ($record->total_hours ?? 0) : 0;
                $otHours = $record ? (float) ($record->overtime_hours ?? 0) : 0;
                $status = $record && $record->status ? $record->status : '';
            @endphp
            <tr>
                <td class="text-center">{{ $date->format('Y-m-d') }}</td>
                <td class="text-center">{{ $date->format('D') }}</td>
                <td class="text-center">{{ $timeIn }}</td>
                <td class="text-center">{{ $timeOut }}</td>
                <td class="text-right">{{ $totalHours ? number_format($totalHours, 2) : '' }}</td>
                <td class="text-right">{{ $otHours ? number_format($otHours, 2) : '' }}</td>
                <td class="text-center">{{ $status }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">
                    No attendance records found for this employee and period.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
