<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Attendance Report' }}</title>
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
    <h1>{{ $title ?? 'Attendance Report' }}</h1>
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
        @if(!empty($filters['archived']))
            Including archived records<br>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Employee ID</th>
                <th>Employee</th>
                <th>Date</th>
                <th>Time in</th>
                <th>Time out</th>
                <th class="text-right">Total hours</th>
                <th class="text-right">Overtime</th>
                <th>Status</th>
                <th>OT approved</th>
                <th>Leave approved</th>
                @if($includeArchivedColumn ?? false)
                    <th>Archived</th>
                @endif
            </tr>
        </thead>
        <tbody>
        @forelse($attendances as $index => $attendance)
            @php
                $employeeName = $attendance->user ? ($attendance->user->full_name ?? $attendance->user->username) : 'Unknown employee';
                $employeeId = $attendance->user_id;
                $date = $attendance->date
                    ? $attendance->date->format('Y-m-d')
                    : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : '');
                $timeIn = $attendance->time_in ? $attendance->time_in->format('g:i A') : '';
                $timeOut = $attendance->time_out ? $attendance->time_out->format('g:i A') : '';
                $status = $attendance->status ?? 'Present';
                $otApproved = $attendance->overtime_approved ? 'Yes' : 'No';
                $leaveApproved = $attendance->leave_approved ? 'Yes' : 'No';
            @endphp
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $employeeId }}</td>
                <td>{{ $employeeName }}</td>
                <td class="text-center">{{ $date }}</td>
                <td class="text-center">{{ $timeIn }}</td>
                <td class="text-center">{{ $timeOut }}</td>
                <td class="text-right">{{ number_format((float)($attendance->total_hours ?? 0), 2) }}</td>
                <td class="text-right">{{ number_format((float)($attendance->overtime_hours ?? 0), 2) }}</td>
                <td class="text-center">{{ $status }}</td>
                <td class="text-center">{{ $otApproved }}</td>
                <td class="text-center">{{ $leaveApproved }}</td>
                @if($includeArchivedColumn ?? false)
                    <td class="text-center">{{ $attendance->deleted_at ? 'Yes' : 'No' }}</td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ ($includeArchivedColumn ?? false) ? 12 : 11 }}" class="text-center">
                    No attendance records found for the selected filters.
                </td>
            </tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
