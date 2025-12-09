<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();

        // Attendance chart: last 14 days of total and overtime hours
        $attendanceStart = $now->copy()->subDays(13)->startOfDay();

        $attendanceRecords = Attendance::where(function ($query) use ($attendanceStart, $now) {
                $query->whereBetween('date', [$attendanceStart->toDateString(), $now->toDateString()])
                    ->orWhereBetween('time_in', [$attendanceStart, $now]);
            })
            ->get();

        $attendanceByDay = [];
        foreach ($attendanceRecords as $attendance) {
            $day = $attendance->date
                ? $attendance->date->format('Y-m-d')
                : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : null);

            if (!$day) {
                continue;
            }

            if (!isset($attendanceByDay[$day])) {
                $attendanceByDay[$day] = [
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                ];
            }

            $attendanceByDay[$day]['total_hours'] += (float) $attendance->total_hours;
            $attendanceByDay[$day]['overtime_hours'] += (float) $attendance->overtime_hours;
        }

        $attendanceLabels = [];
        $attendanceTotalHours = [];
        $attendanceOvertimeHours = [];

        for ($date = $attendanceStart->copy(); $date->lte($now); $date->addDay()) {
            $key = $date->toDateString();
            $attendanceLabels[] = $date->format('M d');

            $dayTotals = $attendanceByDay[$key] ?? ['total_hours' => 0, 'overtime_hours' => 0];
            $attendanceTotalHours[] = round((float) $dayTotals['total_hours'], 2);
            $attendanceOvertimeHours[] = round((float) $dayTotals['overtime_hours'], 2);
        }

        $attendanceChart = [
            'labels' => $attendanceLabels,
            'totalHours' => $attendanceTotalHours,
            'overtimeHours' => $attendanceOvertimeHours,
        ];

        // Payroll chart: gross and net pay totals per month for the last 6 months (including current)
        $payrollEnd = $now->copy()->endOfMonth();
        $payrollStart = $now->copy()->subMonths(5)->startOfMonth();

        $payrollRecords = Payroll::whereNotNull('period_end')
            ->whereBetween('period_end', [$payrollStart->toDateString(), $payrollEnd->toDateString()])
            ->get();

        $payrollByMonth = [];
        foreach ($payrollRecords as $payroll) {
            $periodEnd = $payroll->period_end;
            if (!$periodEnd) {
                continue;
            }

            $monthKey = $periodEnd->copy()->startOfMonth()->format('Y-m-01');

            if (!isset($payrollByMonth[$monthKey])) {
                $payrollByMonth[$monthKey] = [
                    'gross' => 0,
                    'net' => 0,
                ];
            }

            $payrollByMonth[$monthKey]['gross'] += (float) ($payroll->gross_pay ?? 0);
            $payrollByMonth[$monthKey]['net'] += (float) ($payroll->net_pay ?? 0);
        }

        $payrollLabels = [];
        $payrollNetPay = [];
        $payrollGrossPay = [];

        for ($date = $payrollStart->copy(); $date->lte($payrollEnd); $date->addMonth()) {
            $key = $date->format('Y-m-01');
            $payrollLabels[] = $date->format('M Y');
            $monthTotals = $payrollByMonth[$key] ?? ['gross' => 0, 'net' => 0];
            $payrollGrossPay[] = round((float) ($monthTotals['gross'] ?? 0), 2);
            $payrollNetPay[] = round((float) ($monthTotals['net'] ?? 0), 2);
        }

        $payrollChart = [
            'labels' => $payrollLabels,
            'grossPay' => $payrollGrossPay,
            'netPay' => $payrollNetPay,
        ];

        // Dashboard stat cards
        $employeesCount = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->count();

        // Define current month window based on period_end
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        $monthlyPayrollQuery = Payroll::whereNotNull('period_end')
            ->whereBetween('period_end', [$monthStart, $monthEnd]);

        // Payroll budget = sum of gross pay for all payrolls in current month
        $payrollBudgetAmount = (float) (clone $monthlyPayrollQuery)->sum('gross_pay');

        // Payroll due = net pay of pending payrolls in current month
        $payrollDueAmount = (float) (clone $monthlyPayrollQuery)
            ->where('status', 'Pending')
            ->sum('net_pay');

        // Payroll paid = net pay of released payrolls in current month
        $payrollPaidAmount = (float) (clone $monthlyPayrollQuery)
            ->where('status', 'Released')
            ->sum('net_pay');

        // Today's attendance summary (for dashboard)
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();

        $todayAttendances = Attendance::where(function ($q) use ($todayStart, $todayEnd) {
                $q->whereBetween('date', [$todayStart->toDateString(), $todayEnd->toDateString()])
                    ->orWhereBetween('time_in', [$todayStart, $todayEnd]);
            })
            ->get();

        $todayRecords = $todayAttendances->count();
        $todayPresent = $todayAttendances->whereIn('status', ['Present', 'Late'])->count();
        $todayLate = $todayAttendances->where('status', 'Late')->count();
        $todayAbsent = $todayAttendances->where('status', 'Absent')->count();
        $todayLeave = $todayAttendances->where('status', 'On leave')->count();

        $todayAnomalies = [];

        foreach ($todayAttendances as $attendance) {
            $status = $attendance->status ?? 'Present';

            $dateLabel = $attendance->date
                ? $attendance->date->format('Y-m-d')
                : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : 'N/A');

            $total = (float) ($attendance->total_hours ?? 0);

            if ($status === 'Absent' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                $todayAnomalies[] = 'Absent but has recorded time/hours on ' . $dateLabel;
            }

            if (in_array($status, ['Present', 'Late'], true) && $total <= 0) {
                $todayAnomalies[] = 'Present/late but with 0 hours on ' . $dateLabel;
            }

            if (in_array($status, ['Present', 'Late'], true) && $attendance->time_in && !$attendance->time_out) {
                $todayAnomalies[] = 'Missing time-out on ' . $dateLabel;
            }

            if ($status === 'On leave' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                $todayAnomalies[] = 'On leave but has recorded time/hours on ' . $dateLabel;
            }
        }

        $todayAnomalies = array_values(array_unique($todayAnomalies));
        $todayAnomalyCount = count($todayAnomalies);

        $todayAttendanceSummary = [
            'date_label' => $todayStart->format('F d, Y'),
            'records' => $todayRecords,
            'present' => $todayPresent,
            'late' => $todayLate,
            'absent' => $todayAbsent,
            'leave' => $todayLeave,
            'anomaly_count' => $todayAnomalyCount,
        ];

        // Pending payrolls (current month) for dashboard card
        $pendingPayrolls = Payroll::with('user')
            ->whereNotNull('period_end')
            ->whereBetween('period_end', [$monthStart, $monthEnd])
            ->where('status', 'Pending')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $pendingPayrollTable = $pendingPayrolls->map(function ($payroll) {
            $employeeName = $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : 'Unknown employee';
            $netPay = '₱ ' . number_format((float) ($payroll->net_pay ?? 0), 2);
            $periodEnd = $payroll->period_end ? $payroll->period_end->format('Y-m-d') : 'N/A';

            return [
                $employeeName,
                $netPay,
                $periodEnd,
            ];
        })->toArray();

        if (empty($pendingPayrollTable)) {
            $pendingPayrollTable = [
                ['No pending payrolls', '—', '—'],
            ];
        }

        return view('pages.index', [
            'title' => 'Home',
            'pageClass' => 'index',
            'attendanceChart' => $attendanceChart,
            'payrollChart' => $payrollChart,
            'employeesCount' => $employeesCount,
            'payrollBudgetAmount' => $payrollBudgetAmount,
            'payrollDueAmount' => $payrollDueAmount,
            'payrollPaidAmount' => $payrollPaidAmount,
            'todayAttendanceSummary' => $todayAttendanceSummary,
            'pendingPayrollTable' => $pendingPayrollTable,
        ]);
    }
}
