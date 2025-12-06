<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\CrewAssignment;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $roleKey = strtolower($currentUser->role ?? '');

        $filters = [
            'employee_id' => $request->input('employee_id'),
            'period_start' => $request->input('period_start'),
            'period_end' => $request->input('period_end'),
        ];

        [$periodStart, $periodEnd] = $this->normalizePeriod($filters['period_start'], $filters['period_end']);

        $filters['period_start'] = $periodStart->toDateString();
        $filters['period_end'] = $periodEnd->toDateString();

        $employeeOptions = $this->buildEmployeeOptions($currentUser, $roleKey);

        $mode = $this->resolveMode($roleKey);

        $attendanceAnalytics = null;
        $payrollAnalytics = null;

        if ($mode === 'attendance' || $mode === 'combined') {
            $attendanceAnalytics = $this->buildAttendanceAnalytics($currentUser, $roleKey, $filters);
        }

        if ($mode === 'payroll' || $mode === 'combined') {
            $payrollAnalytics = $this->buildPayrollAnalytics($filters);
        }

        return view('pages.analytics', [
            'title' => 'Analytics',
            'pageClass' => 'analytics',
            'mode' => $mode,
            'filters' => $filters,
            'employeeOptions' => $employeeOptions,
            'attendanceAnalytics' => $attendanceAnalytics,
            'payrollAnalytics' => $payrollAnalytics,
        ]);
    }

    private function normalizePeriod(?string $start, ?string $end): array
    {
        $now = Carbon::now()->endOfDay();

        $startDate = null;
        $endDate = null;

        if (!empty($start)) {
            $startDate = Carbon::parse($start)->startOfDay();
        }

        if (!empty($end)) {
            $endDate = Carbon::parse($end)->endOfDay();
        }

        if ($startDate === null && $endDate === null) {
            $endDate = $now;
            $startDate = $now->copy()->subDays(29)->startOfDay();
        } elseif ($startDate === null) {
            $endDate = $endDate ?? $now;
            $startDate = $endDate->copy()->subDays(29)->startOfDay();
        } elseif ($endDate === null) {
            $startDate = $startDate;
            $endDate = $startDate->copy()->addDays(29)->endOfDay();
        }

        if ($startDate->gt($endDate)) {
            $tmp = $startDate;
            $startDate = $endDate;
            $endDate = $tmp;
        }

        return [$startDate, $endDate];
    }

    private function buildEmployeeOptions(User $currentUser, string $roleKey): array
    {
        $query = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if ($roleKey === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');

            if ($crewWorkerIds->isNotEmpty()) {
                $query->whereIn('id', $crewWorkerIds);
            }
            // If supervisor has no crew assignments yet, fall back to all non-admin employees
        }

        $employees = $query
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        return $employees->mapWithKeys(function (User $user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();
    }

    private function resolveMode(string $roleKey): string
    {
        if (in_array($roleKey, ['admin', 'superadmin'], true)) {
            return 'combined';
        }

        if (in_array($roleKey, ['hr', 'accounting', 'project manager'], true)) {
            return 'payroll';
        }

        if ($roleKey === 'supervisor') {
            return 'attendance';
        }

        return 'combined';
    }

    private function buildAttendanceAnalytics(User $currentUser, string $roleKey, array $filters): array
    {
        $start = Carbon::parse($filters['period_start'])->startOfDay();
        $end = Carbon::parse($filters['period_end'])->endOfDay();

        $query = Attendance::with('user');

        if ($roleKey === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');

            if ($crewWorkerIds->isNotEmpty()) {
                $query->whereIn('user_id', $crewWorkerIds);
            } else {
                // If supervisor has no crew assignments yet, mirror the attendance index:
                // include all non-admin employees instead of returning empty analytics.
                $query->whereHas('user', function ($q) {
                    $q->whereNull('deleted_at')
                        ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);
                });
            }
        } else {
            $query->whereHas('user', function ($q) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);
            });
        }

        if (!empty($filters['employee_id'])) {
            $query->where('user_id', (int) $filters['employee_id']);
        }

        $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->orWhereBetween('time_in', [$start, $end]);
        });

        $attendances = $query->get();

        $totalRecords = $attendances->count();
        $workedDays = $attendances->whereIn('status', ['Present', 'Late'])->count();
        $absentDays = $attendances->where('status', 'Absent')->count();
        $leaveDays = $attendances->where('status', 'On leave')->count();
        $totalHours = (float) $attendances->sum('total_hours');
        $totalOvertime = (float) $attendances->sum('overtime_hours');
        $employeeCount = $attendances->pluck('user_id')->unique()->count();

        $anomalies = [];

        foreach ($attendances as $attendance) {
            $status = $attendance->status ?? 'Present';
            $dateLabel = $attendance->date
                ? $attendance->date->format('Y-m-d')
                : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : 'N/A');

            $total = (float) ($attendance->total_hours ?? 0);

            if ($status === 'Absent' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                $anomalies[] = 'Absent but has recorded time/hours on ' . $dateLabel;
            }

            if (in_array($status, ['Present', 'Late'], true) && $total <= 0) {
                $anomalies[] = 'Present/late but with 0 hours on ' . $dateLabel;
            }

            if (in_array($status, ['Present', 'Late'], true) && $attendance->time_in && !$attendance->time_out) {
                $anomalies[] = 'Missing time-out on ' . $dateLabel;
            }

            if ($status === 'On leave' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                $anomalies[] = 'On leave but has recorded time/hours on ' . $dateLabel;
            }
        }

        $anomalyCount = count(array_values(array_unique($anomalies)));

        $attendanceRate = $totalRecords > 0 ? (int) round(($workedDays / $totalRecords) * 100) : 0;

        $periodLabel = $filters['period_start'] . ' to ' . $filters['period_end'];

        $summary = [
            'total_hours' => $totalHours,
            'total_overtime' => $totalOvertime,
            'attendance_rate' => $attendanceRate,
            'records' => $totalRecords,
            'worked_days' => $workedDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'employee_count' => $employeeCount,
            'anomaly_count' => $anomalyCount,
            'period_label' => $periodLabel,
        ];

        $attendanceByDay = [];

        foreach ($attendances as $attendance) {
            $day = $attendance->date
                ? $attendance->date->format('Y-m-d')
                : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : null);

            if (!$day) {
                continue;
            }

            if (!isset($attendanceByDay[$day])) {
                $attendanceByDay[$day] = [
                    'total_hours' => 0.0,
                    'overtime_hours' => 0.0,
                ];
            }

            $attendanceByDay[$day]['total_hours'] += (float) $attendance->total_hours;
            $attendanceByDay[$day]['overtime_hours'] += (float) $attendance->overtime_hours;
        }

        $attendanceLabels = [];
        $attendanceTotalHours = [];
        $attendanceOvertimeHours = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();
            $attendanceLabels[] = $date->format('M d');

            $dayTotals = $attendanceByDay[$key] ?? ['total_hours' => 0.0, 'overtime_hours' => 0.0];
            $attendanceTotalHours[] = round((float) $dayTotals['total_hours'], 2);
            $attendanceOvertimeHours[] = round((float) $dayTotals['overtime_hours'], 2);
        }

        $chart = [
            'labels' => $attendanceLabels,
            'totalHours' => $attendanceTotalHours,
            'overtimeHours' => $attendanceOvertimeHours,
        ];

        $grouped = $attendances->groupBy('user_id');

        $employeeSummary = [];

        foreach ($grouped as $userId => $records) {
            if (!$records->count()) {
                continue;
            }

            $first = $records->first();
            $user = $first ? $first->user : null;

            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            $presentDaysEmp = $records->whereIn('status', ['Present', 'Late'])->count();
            $lateDaysEmp = $records->where('status', 'Late')->count();
            $absentDaysEmp = $records->where('status', 'Absent')->count();
            $leaveDaysEmp = $records->where('status', 'On leave')->count();

            $totalHoursEmp = (float) $records->sum('total_hours');
            $overtimeHoursEmp = (float) $records->sum('overtime_hours');

            $employeeSummary[] = [
                'employee_name' => $employeeName,
                'present_days' => $presentDaysEmp,
                'late_days' => $lateDaysEmp,
                'absent_days' => $absentDaysEmp,
                'leave_days' => $leaveDaysEmp,
                'total_hours' => $totalHoursEmp,
                'overtime_hours' => $overtimeHoursEmp,
            ];
        }

        usort($employeeSummary, function (array $a, array $b) {
            return strcmp((string) $a['employee_name'], (string) $b['employee_name']);
        });

        $topOvertime = collect($employeeSummary)
            ->sortByDesc('overtime_hours')
            ->take(5)
            ->values();

        $topOvertimeTable = $topOvertime->map(function (array $row) {
            return [
                $row['employee_name'],
                number_format($row['overtime_hours'], 2) . ' h',
                number_format($row['total_hours'], 2) . ' h',
                $row['present_days'],
            ];
        })->toArray();

        $topAbsence = collect($employeeSummary)
            ->sortByDesc('absent_days')
            ->take(5)
            ->values();

        $topAbsenceTable = $topAbsence->map(function (array $row) {
            return [
                $row['employee_name'],
                $row['absent_days'],
                $row['late_days'],
                $row['leave_days'],
            ];
        })->toArray();

        return [
            'summary' => $summary,
            'chart' => $chart,
            'topOvertimeTable' => $topOvertimeTable,
            'topAbsenceTable' => $topAbsenceTable,
        ];
    }

    private function buildPayrollAnalytics(array $filters): array
    {
        $start = Carbon::parse($filters['period_start'])->startOfDay();
        $end = Carbon::parse($filters['period_end'])->endOfDay();

        $query = Payroll::with('user')
            ->whereNotNull('period_end')
            ->whereBetween('period_end', [$start->toDateString(), $end->toDateString()]);

        if (!empty($filters['employee_id'])) {
            $query->where('user_id', (int) $filters['employee_id']);
        }

        $payrolls = $query->get();

        $totalGross = (float) $payrolls->sum('gross_pay');
        $totalDeductions = (float) $payrolls->sum('total_deductions');
        $totalNet = (float) $payrolls->sum('net_pay');

        $employeeCount = $payrolls->pluck('user_id')->unique()->count();
        $payrollCount = $payrolls->count();

        $avgNetPerEmployee = $employeeCount > 0 ? $totalNet / $employeeCount : 0.0;
        $avgNetPerPayroll = $payrollCount > 0 ? $totalNet / $payrollCount : 0.0;

        $pending = $payrolls->where('status', 'Pending');
        $released = $payrolls->where('status', 'Released');
        $cancelled = $payrolls->where('status', 'Cancelled');

        $statusBreakdown = [
            'pending' => [
                'count' => $pending->count(),
                'net' => (float) $pending->sum('net_pay'),
            ],
            'released' => [
                'count' => $released->count(),
                'net' => (float) $released->sum('net_pay'),
            ],
            'cancelled' => [
                'count' => $cancelled->count(),
                'net' => (float) $cancelled->sum('net_pay'),
            ],
        ];

        $summary = [
            'total_gross' => $totalGross,
            'total_deductions' => $totalDeductions,
            'total_net' => $totalNet,
            'employee_count' => $employeeCount,
            'payroll_count' => $payrollCount,
            'avg_net_per_employee' => $avgNetPerEmployee,
            'avg_net_per_payroll' => $avgNetPerPayroll,
            'status_breakdown' => $statusBreakdown,
            'period_label' => $filters['period_start'] . ' to ' . $filters['period_end'],
        ];

        $payrollByMonth = [];

        foreach ($payrolls as $payroll) {
            $periodEnd = $payroll->period_end;
            if (!$periodEnd) {
                continue;
            }

            $monthKey = $periodEnd->copy()->startOfMonth()->format('Y-m-01');

            if (!isset($payrollByMonth[$monthKey])) {
                $payrollByMonth[$monthKey] = [
                    'gross' => 0.0,
                    'net' => 0.0,
                ];
            }

            $payrollByMonth[$monthKey]['gross'] += (float) ($payroll->gross_pay ?? 0);
            $payrollByMonth[$monthKey]['net'] += (float) ($payroll->net_pay ?? 0);
        }

        ksort($payrollByMonth);

        $payrollLabels = [];
        $payrollGross = [];
        $payrollNet = [];

        foreach ($payrollByMonth as $monthKey => $totals) {
            $date = Carbon::createFromFormat('Y-m-d', $monthKey);
            $payrollLabels[] = $date->format('M Y');
            $payrollGross[] = round((float) $totals['gross'], 2);
            $payrollNet[] = round((float) $totals['net'], 2);
        }

        $chart = [
            'labels' => $payrollLabels,
            'gross' => $payrollGross,
            'net' => $payrollNet,
        ];

        $groupedByEmployee = $payrolls->groupBy('user_id');

        $employeeTotals = [];

        foreach ($groupedByEmployee as $userId => $records) {
            if (!$records->count()) {
                continue;
            }

            $first = $records->first();
            $user = $first ? $first->user : null;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            $employeeTotals[] = [
                'employee_name' => $employeeName,
                'net_total' => (float) $records->sum('net_pay'),
                'gross_total' => (float) $records->sum('gross_pay'),
                'count' => $records->count(),
            ];
        }

        usort($employeeTotals, function (array $a, array $b) {
            if ($a['net_total'] === $b['net_total']) {
                return 0;
            }
            return $a['net_total'] < $b['net_total'] ? 1 : -1;
        });

        $topNetPayTable = collect($employeeTotals)
            ->take(5)
            ->values()
            ->map(function (array $row) {
                return [
                    $row['employee_name'],
                    '₱ ' . number_format($row['net_total'], 2),
                    '₱ ' . number_format($row['gross_total'], 2),
                    $row['count'],
                ];
            })
            ->toArray();

        $balanceQuery = CashAdvance::select('user_id')
            ->selectRaw("SUM(CASE WHEN type = 'advance' THEN amount ELSE 0 END) AS total_advances")
            ->selectRaw("SUM(CASE WHEN type = 'repayment' THEN amount ELSE 0 END) AS total_repayments")
            ->groupBy('user_id')
            ->with('user');

        if (!empty($filters['employee_id'])) {
            $balanceQuery->where('user_id', (int) $filters['employee_id']);
        }

        $balanceRows = $balanceQuery->get();

        $cashAdvanceTableData = $balanceRows->map(function (CashAdvance $row) {
            $employeeName = $row->user ? ($row->user->full_name ?? $row->user->username) : 'Unknown employee';
            $totalAdvances = (float) ($row->total_advances ?? 0);
            $totalRepayments = (float) ($row->total_repayments ?? 0);
            $outstanding = max(0, $totalAdvances - $totalRepayments);

            return [
                $employeeName,
                '₱ ' . number_format($totalAdvances, 2),
                '₱ ' . number_format($totalRepayments, 2),
                '₱ ' . number_format($outstanding, 2),
            ];
        })->toArray();

        return [
            'summary' => $summary,
            'chart' => $chart,
            'topNetPayTable' => $topNetPayTable,
            'cashAdvanceTableData' => $cashAdvanceTableData,
        ];
    }
}
