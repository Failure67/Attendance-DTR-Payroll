<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\Attendance;
use App\Models\User;
use App\Models\CashAdvance;
use App\Models\ContributionBracket;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class AppController extends Controller
{
    // index
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

        // Payroll chart: net pay totals per month for the last 6 months (including current)
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
                $payrollByMonth[$monthKey] = 0;
            }

            $payrollByMonth[$monthKey] += (float) ($payroll->net_pay ?? 0);
        }

        $payrollLabels = [];
        $payrollNetPay = [];

        for ($date = $payrollStart->copy(); $date->lte($payrollEnd); $date->addMonth()) {
            $key = $date->format('Y-m-01');
            $payrollLabels[] = $date->format('M Y');
            $payrollNetPay[] = round((float) ($payrollByMonth[$key] ?? 0), 2);
        }

        $payrollChart = [
            'labels' => $payrollLabels,
            'netPay' => $payrollNetPay,
        ];

        return view('pages.index', [
            'title' => 'Home',
            'pageClass' => 'index',
            'attendanceChart' => $attendanceChart,
            'payrollChart' => $payrollChart,
        ]);
    }

    // attendance
    public function viewAttendance(Request $request)
    {
        // Employees for filters / form selects
        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        if (empty($periodStart) && empty($periodEnd)) {
            $periodStart = now()->subDays(30)->toDateString();
            $periodEnd = now()->toDateString();
        }

        $baseQuery = Attendance::query();

        if (!empty($employeeId)) {
            $baseQuery->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $baseQuery->where('status', $status);
        }

        // Filtering by date range (either using date column or time_in fallback)
        if (!empty($periodStart)) {
            $start = Carbon::parse($periodStart)->startOfDay();

            $baseQuery->where(function ($q) use ($start) {
                $q->whereDate('date', '>=', $start->toDateString())
                    ->orWhere('time_in', '>=', $start);
            });
        }

        if (!empty($periodEnd)) {
            $end = Carbon::parse($periodEnd)->endOfDay();

            $baseQuery->where(function ($q) use ($end) {
                $q->whereDate('date', '<=', $end->toDateString())
                    ->orWhere('time_in', '<=', $end);
            });
        }

        $summaryAttendances = (clone $baseQuery)->get();

        $tableQuery = $baseQuery->with('user')->orderByDesc('time_in');

        $attendances = $tableQuery->paginate(10)->appends($request->query());

        $attendanceTableData = $attendances->map(function ($attendance) {
            $employeeName = $attendance->user ? ($attendance->user->full_name ?? $attendance->user->username) : 'Unknown employee';

            $date = $attendance->date
                ? $attendance->date->format('Y-m-d')
                : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : 'N/A');

            $timeIn = $attendance->time_in ? $attendance->time_in->format('H:i') : '—';
            $timeOut = $attendance->time_out ? $attendance->time_out->format('H:i') : '—';

            $totalHours = number_format((float) $attendance->total_hours, 2);
            $overtimeHours = number_format((float) $attendance->overtime_hours, 2);

            $status = $attendance->status ?? 'Present';
            $statusClass = match ($status) {
                'Present' => 'bg-success-subtle text-success',
                'Late' => 'bg-warning-subtle text-warning',
                'Absent' => 'bg-danger-subtle text-danger',
                'On leave' => 'bg-secondary-subtle text-secondary',
                default => 'bg-light text-dark',
            };

            $statusBadge = '<span class="badge rounded-pill ' . $statusClass . '">' . e($status) . '</span>';

            // Approval flags for overtime / leave
            $flagBadges = [];
            if ((float) $attendance->overtime_hours > 0) {
                if ($attendance->overtime_approved) {
                    $flagBadges[] = '<span class="badge rounded-pill bg-success-subtle text-success ms-1">OT approved</span>';
                } else {
                    $flagBadges[] = '<span class="badge rounded-pill bg-warning-subtle text-warning ms-1">OT pending</span>';
                }
            }

            if ($status === 'On leave') {
                if ($attendance->leave_approved) {
                    $flagBadges[] = '<span class="badge rounded-pill bg-success-subtle text-success ms-1">Leave approved</span>';
                } else {
                    $flagBadges[] = '<span class="badge rounded-pill bg-warning-subtle text-warning ms-1">Leave pending</span>';
                }
            }

            $statusHtml = $statusBadge . (count($flagBadges) ? ' ' . implode(' ', $flagBadges) : '');

            $employeeCell = '<span class="attendance-employee" data-attendance-id="' . $attendance->id . '" data-user-id="' . $attendance->user_id . '" data-overtime-approved="' . ($attendance->overtime_approved ? '1' : '0') . '" data-leave-approved="' . ($attendance->leave_approved ? '1' : '0') . '">' . e($employeeName) . '</span>';

            return [
                $employeeCell,
                e($date),
                e($timeIn),
                e($timeOut),
                $totalHours,
                $overtimeHours,
                $statusHtml,
            ];
        })->toArray();

        $totalHours = (float) $summaryAttendances->sum('total_hours');
        $totalOvertime = (float) $summaryAttendances->sum('overtime_hours');
        $recordCount = $summaryAttendances->count();
        $workedDays = $summaryAttendances->whereIn('status', ['Present', 'Late'])->count();
        $absentDays = $summaryAttendances->where('status', 'Absent')->count();
        $leaveDays = $summaryAttendances->where('status', 'On leave')->count();
        $attendanceRate = $recordCount > 0 ? round(($workedDays / $recordCount) * 100) : 0;

        $periodLabel = null;
        if (!empty($periodStart) && !empty($periodEnd)) {
            $periodLabel = $periodStart . ' to ' . $periodEnd;
        } elseif (!empty($periodStart)) {
            $periodLabel = 'From ' . $periodStart;
        } elseif (!empty($periodEnd)) {
            $periodLabel = 'Up to ' . $periodEnd;
        } else {
            $periodLabel = 'All time';
        }

        $summary = [
            'total_hours' => $totalHours,
            'total_overtime' => $totalOvertime,
            'attendance_rate' => $attendanceRate,
            'records' => $recordCount,
            'worked_days' => $workedDays,
            'absent_days' => $absentDays,
            'leave_days' => $leaveDays,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'period_label' => $periodLabel,
        ];

        return view('pages.attendance', [
            'title' => 'Attendance',
            'pageClass' => 'attendance',
            'attendanceTableData' => $attendanceTableData,
            'attendanceSummary' => $summary,
            'employeeOptions' => $employeeOptions,
            'attendances' => $attendances,
            'filters' => [
                'employee_id' => $employeeId,
                'status' => $status,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
        ]);
    }

    public function storeAttendance(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'time_in' => 'required_unless:status,Absent,On leave|nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'status' => 'required|in:Present,Absent,Late,On leave',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $timeIn = null;
        $timeOut = null;
        $totalHours = 0;
        $overtimeHours = 0;

        if (!empty($validated['time_in'])) {
            $timeIn = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $validated['time_in']);

            if (!empty($validated['time_out'])) {
                $timeOut = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $validated['time_out']);

                $minutes = max(0, $timeIn->diffInMinutes($timeOut, false));
                $totalHours = round($minutes / 60, 2);
                $standardDailyHours = 8;
                $overtimeHours = max(0, $totalHours - $standardDailyHours);
            }
        }

        $overtimeApproved = $request->boolean('overtime_approved') && $overtimeHours > 0;
        $leaveApproved = $request->boolean('leave_approved') && ($validated['status'] === 'On leave');

        Attendance::create([
            'user_id' => $validated['user_id'],
            'date' => $date->format('Y-m-d'),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'total_hours' => $totalHours,
            'overtime_hours' => $overtimeHours,
            'status' => $validated['status'],
            'overtime_approved' => $overtimeApproved,
            'leave_approved' => $leaveApproved,
        ]);

        return redirect()->route('attendance')->with('success', 'Attendance record added successfully.');
    }

    public function updateAttendance(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'time_in' => 'required_unless:status,Absent,On leave|nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'status' => 'required|in:Present,Absent,Late,On leave',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $timeIn = null;
        $timeOut = null;
        $totalHours = 0;
        $overtimeHours = 0;

        if (!empty($validated['time_in'])) {
            $timeIn = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $validated['time_in']);

            if (!empty($validated['time_out'])) {
                $timeOut = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $validated['time_out']);

                $minutes = max(0, $timeIn->diffInMinutes($timeOut, false));
                $totalHours = round($minutes / 60, 2);
                $standardDailyHours = 8;
                $overtimeHours = max(0, $totalHours - $standardDailyHours);
            }
        }

        $overtimeApproved = $request->boolean('overtime_approved') && $overtimeHours > 0;
        $leaveApproved = $request->boolean('leave_approved') && ($validated['status'] === 'On leave');

        $attendance->update([
            'user_id' => $validated['user_id'],
            'date' => $date->format('Y-m-d'),
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'total_hours' => $totalHours,
            'overtime_hours' => $overtimeHours,
            'status' => $validated['status'],
            'overtime_approved' => $overtimeApproved,
            'leave_approved' => $leaveApproved,
        ]);

        return redirect()->route('attendance')->with('success', 'Attendance record updated successfully.');
    }

    public function deleteAttendance(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);
        $attendance->delete();

        return redirect()->route('attendance')->with('success', 'Attendance record deleted successfully.');
    }

    public function exportAttendance(Request $request)
    {
        $attendances = Attendance::with('user')
            ->orderByDesc('time_in')
            ->limit(1000)
            ->get();

        $filename = 'attendance_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($attendances) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee',
                'Date',
                'Time in',
                'Time out',
                'Total hours',
                'Overtime hours',
                'Status',
            ]);

            foreach ($attendances as $attendance) {
                $employeeName = $attendance->user ? ($attendance->user->full_name ?? $attendance->user->username) : 'Unknown employee';
                $date = $attendance->date
                    ? $attendance->date->format('Y-m-d')
                    : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : '');

                // Prefix with a single quote so Excel treats this as text and avoids ###### due to auto date formatting
                $dateForExport = $date !== '' ? "'" . $date : '';
                $timeIn = $attendance->time_in ? $attendance->time_in->format('H:i') : '';
                $timeOut = $attendance->time_out ? $attendance->time_out->format('H:i') : '';

                fputcsv($handle, [
                    $employeeName,
                    $dateForExport,
                    $timeIn,
                    $timeOut,
                    (float) $attendance->total_hours,
                    (float) $attendance->overtime_hours,
                    $attendance->status ?? 'Present',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // payroll
    public function viewPayroll(Request $request)
    {
        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $query = Payroll::with('user')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at');

        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($periodStart)) {
            $query->whereDate('period_start', '>=', Carbon::parse($periodStart)->toDateString());
        }

        if (!empty($periodEnd)) {
            $query->whereDate('period_end', '<=', Carbon::parse($periodEnd)->toDateString());
        }

        $payrolls = $query->limit(200)->get();

        return view('pages.payroll', [
            'title' => 'Payroll',
            'pageClass' => 'payroll',
            'employeeOptions' => $employeeOptions,
            'payrolls' => $payrolls,
            'filters' => [
                'employee_id' => $employeeId,
                'status' => $status,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
        ]);
    }

    public function exportPayroll(Request $request)
    {
        $query = Payroll::with('user')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at');

        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($periodStart)) {
            $query->whereDate('period_start', '>=', Carbon::parse($periodStart)->toDateString());
        }

        if (!empty($periodEnd)) {
            $query->whereDate('period_end', '<=', Carbon::parse($periodEnd)->toDateString());
        }

        $payrolls = $query->limit(1000)->get();

        $filename = 'payroll_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($payrolls) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee',
                'Period start',
                'Period end',
                'Wage type',
                'Minimum wage',
                'Units worked',
                'Regular hours',
                'Overtime hours',
                'Absent days',
                'Gross pay',
                'Total deductions',
                'Net pay',
                'Status',
                'Created at',
            ]);

            foreach ($payrolls as $payroll) {
                $employeeName = $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : 'Unknown employee';

                $start = $payroll->period_start ? $payroll->period_start->format('Y-m-d') : '';
                $end = $payroll->period_end ? $payroll->period_end->format('Y-m-d') : '';

                $startForExport = $start !== '' ? "'" . $start : '';
                $endForExport = $end !== '' ? "'" . $end : '';

                $createdAt = $payroll->created_at ? $payroll->created_at->format('Y-m-d H:i:s') : '';
                // Prefix with a single quote so Excel treats this as text and avoids ###### due to auto date formatting
                $createdAtForExport = $createdAt !== '' ? "'" . $createdAt : '';

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

                fputcsv($handle, [
                    $employeeName,
                    $startForExport,
                    $endForExport,
                    $payroll->wage_type ?? '',
                    (float) ($payroll->min_wage ?? 0),
                    $unitsWorked,
                    (float) ($payroll->regular_hours ?? 0),
                    (float) ($payroll->overtime_hours ?? 0),
                    (float) ($payroll->absent_days ?? 0),
                    (float) ($payroll->gross_pay ?? 0),
                    (float) ($payroll->total_deductions ?? 0),
                    (float) ($payroll->net_pay ?? 0),
                    $payroll->status ?? 'Pending',
                    $createdAtForExport,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function viewCashAdvances()
    {
        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $cashAdvances = CashAdvance::with('user', 'payroll')
            ->latest()
            ->limit(200)
            ->get();

        $cashAdvanceTableData = $cashAdvances->map(function ($entry) {
            $employeeName = $entry->user ? ($entry->user->full_name ?? $entry->user->username) : 'Unknown employee';
            $typeLabel = $entry->type === 'repayment' ? 'Repayment' : 'Advance';
            $amount = '₱ ' . number_format((float) $entry->amount, 2);
            $sourceLabel = $entry->source === 'payroll' ? 'Payroll' : 'Manual';
            $payrollRef = $entry->payroll ? ('#' . $entry->payroll->id) : '—';
            $description = $entry->description ?? '—';
            $date = $entry->created_at ? $entry->created_at->format('Y-m-d') : '—';

            return [
                $employeeName,
                $typeLabel,
                $amount,
                $sourceLabel,
                $payrollRef,
                $description,
                $date,
            ];
        })->toArray();

        $balanceRows = CashAdvance::select('user_id')
            ->selectRaw("SUM(CASE WHEN type = 'advance' THEN amount ELSE 0 END) AS total_advances")
            ->selectRaw("SUM(CASE WHEN type = 'repayment' THEN amount ELSE 0 END) AS total_repayments")
            ->groupBy('user_id')
            ->with('user')
            ->get();

        $cashAdvanceSummaryTableData = $balanceRows->map(function ($row) {
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

        return view('pages.cash-advances', [
            'title' => 'Cash advances',
            'pageClass' => 'cash-advances',
            'cashAdvanceSummaryTableData' => $cashAdvanceSummaryTableData,
            'cashAdvanceTableData' => $cashAdvanceTableData,
            'employeeOptions' => $employeeOptions,
        ]);
    }

    public function storeCashAdvance(Request $request)
    {
        $request->merge([
            'amount' => str_replace(',', '', (string) $request->input('amount')),
        ]);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:advance,repayment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $userId = $validated['user_id'];
        $type = $validated['type'];
        $amount = (float) $validated['amount'];

        if ($type === 'repayment') {
            $totalAdvances = (float) CashAdvance::where('user_id', $userId)
                ->where('type', 'advance')
                ->sum('amount');

            $totalRepayments = (float) CashAdvance::where('user_id', $userId)
                ->where('type', 'repayment')
                ->sum('amount');

            $outstanding = max(0, $totalAdvances - $totalRepayments);

            if ($amount > $outstanding + 0.0001) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'amount' => 'Repayment amount cannot exceed current cash advance balance.',
                    ]);
            }
        }

        CashAdvance::create([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'description' => $validated['description'] ?? null,
            'source' => 'admin',
            'payroll_id' => null,
        ]);

        return redirect()->route('cash-advances')->with('success', 'Cash advance entry saved successfully.');
    }

    public function showPayroll($id)
    {
        $payroll = Payroll::with('deductions', 'user')->findOrFail($id);

        return response()->json([
            'id' => $payroll->id,
            'user_id' => $payroll->user_id,
            'employee_name' => $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : null,
            'wage_type' => $payroll->wage_type,
            'min_wage' => (float) ($payroll->min_wage ?? 0),
            'hours_worked' => (float) ($payroll->hours_worked ?? 0),
            'days_worked' => (float) ($payroll->days_worked ?? 0),
            'gross_pay' => (float) ($payroll->gross_pay ?? 0),
            'total_deductions' => (float) ($payroll->total_deductions ?? 0),
            'net_pay' => (float) ($payroll->net_pay ?? 0),
            'status' => $payroll->status,
            'deductions' => $payroll->deductions->map(function ($d) {
                return [
                    'name' => $d->deduction_name,
                    'amount' => (float) $d->amount,
                ];
            })->values(),
        ]);
    }

    public function viewProcessPayroll(Request $request)
    {
        $periodStartInput = $request->input('period_start');
        $periodEndInput = $request->input('period_end');

        $previewRows = [];
        $previewSummary = null;

        if ($request->filled('period_start') || $request->filled('period_end')) {
            $validated = $request->validate([
                'period_start' => 'required|date',
                'period_end' => 'required|date|after_or_equal:period_start',
            ]);

            $start = Carbon::parse($validated['period_start'])->startOfDay();
            $end = Carbon::parse($validated['period_end'])->endOfDay();

            $attendanceQuery = Attendance::with('user')
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                        ->orWhereBetween('time_in', [$start, $end]);
                });

            $attendances = $attendanceQuery->get();

            $grouped = $attendances->groupBy('user_id');

            $totalHoursAll = 0;
            $totalOtAll = 0;

            foreach ($grouped as $userId => $rows) {
                $first = $rows->first();
                $user = $first ? $first->user : null;
                $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

                $regularHours = 0;
                $overtimeHours = 0;
                $presentDays = 0;   // Worked days
                $absentDays = 0;
                $leaveDays = 0;
                $employeeAnomalies = [];

                foreach ($rows as $attendance) {
                    $total = (float) $attendance->total_hours;
                    $ot = (float) $attendance->overtime_hours;
                    $regular = max(0, $total - $ot);

                    $regularHours += $regular;
                    $overtimeHours += $ot;

                    $status = $attendance->status ?? 'Present';

                    $dateLabel = $attendance->date
                        ? $attendance->date->format('Y-m-d')
                        : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : 'N/A');

                    if (in_array($status, ['Present', 'Late'], true)) {
                        $presentDays++;
                    } elseif ($status === 'On leave') {
                        $leaveDays++;
                    } elseif ($status === 'Absent') {
                        $absentDays++;
                    }

                    // Anomaly: Absent but has recorded hours or times
                    if ($status === 'Absent' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                        $employeeAnomalies[] = 'Absent but has recorded time/hours on ' . $dateLabel;
                    }

                    // Anomaly: Present/Late but zero hours
                    if (in_array($status, ['Present', 'Late'], true) && $total <= 0) {
                        $employeeAnomalies[] = 'Present/late but with 0 hours on ' . $dateLabel;
                    }

                    // Anomaly: Present/Late with missing time-out
                    if (in_array($status, ['Present', 'Late'], true) && $attendance->time_in && !$attendance->time_out) {
                        $employeeAnomalies[] = 'Missing time-out on ' . $dateLabel;
                    }

                    // Anomaly: On leave but has recorded hours or times
                    if ($status === 'On leave' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                        $employeeAnomalies[] = 'On leave but has recorded time/hours on ' . $dateLabel;
                    }
                }

                $totalHoursAll += $regularHours + $overtimeHours;
                $totalOtAll += $overtimeHours;

                $lastPayroll = Payroll::where('user_id', $userId)->latest()->first();

                $totalAdvances = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'advance')
                    ->sum('amount');

                $totalRepayments = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'repayment')
                    ->sum('amount');

                $caBalance = max(0, $totalAdvances - $totalRepayments);

                // High overtime for the selected period (alert threshold: 40h OT)
                $otAlertThreshold = 40;
                if ($overtimeHours > $otAlertThreshold) {
                    $employeeAnomalies[] = 'High overtime for period (' . number_format($overtimeHours, 2) . 'h)';
                }

                $previewRows[] = [
                    'user_id' => $userId,
                    'employee_name' => $employeeName,
                    'regular_hours' => $regularHours,
                    'overtime_hours' => $overtimeHours,
                    'absent_days' => $absentDays,
                    'present_days' => $presentDays,
                    'leave_days' => $leaveDays,
                    'anomalies' => $employeeAnomalies,
                    'last_wage_type' => $lastPayroll->wage_type ?? 'Daily',
                    'last_min_wage' => (float) ($lastPayroll->min_wage ?? 0),
                    'ca_balance' => $caBalance,
                ];
            }

            $previewSummary = [
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
                'employee_count' => count($previewRows),
                'total_hours' => $totalHoursAll,
                'total_ot' => $totalOtAll,
            ];

            $periodStartInput = $start->toDateString();
            $periodEndInput = $end->toDateString();
        }

        return view('pages.payroll-process', [
            'title' => 'Process payroll',
            'pageClass' => 'payroll-process',
            'period_start' => $periodStartInput,
            'period_end' => $periodEndInput,
            'previewRows' => $previewRows,
            'previewSummary' => $previewSummary,
        ]);
    }

    public function runProcessPayroll(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'rows' => 'required|array',
            'rows.*.user_id' => 'required|exists:users,id',
            'rows.*.wage_type' => 'required|in:Hourly,Daily,Weekly,Monthly,Piece rate',
            'rows.*.min_wage' => 'required|numeric|min:0',
            'rows.*.regular_hours' => 'required|numeric|min:0',
            'rows.*.overtime_hours' => 'required|numeric|min:0',
            'rows.*.absent_days' => 'required|numeric|min:0',
            'rows.*.present_days' => 'required|numeric|min:0',
            'rows.*.include' => 'nullable|boolean',
            'rows.*.ca_deduction' => 'nullable|numeric|min:0',
        ]);

        $periodStart = Carbon::parse($validated['period_start'])->toDateString();
        $periodEnd = Carbon::parse($validated['period_end'])->toDateString();

        DB::beginTransaction();

        try {
            foreach ($validated['rows'] as $row) {
                if (empty($row['include'])) {
                    continue;
                }

                $userId = $row['user_id'];
                $wageType = $row['wage_type'];
                $minWage = (float) $row['min_wage'];
                $regularHours = (float) $row['regular_hours'];
                $overtimeHours = (float) $row['overtime_hours'];
                $absentDays = (float) $row['absent_days'];
                $presentDays = (float) $row['present_days'];
                $requestedCaDeduction = isset($row['ca_deduction']) ? (float) $row['ca_deduction'] : 0.0;

                if ($requestedCaDeduction < 0) {
                    $requestedCaDeduction = 0.0;
                }

                $unitsWorked = 0;
                $hoursWorked = null;
                $daysWorked = null;

                switch ($wageType) {
                    case 'Hourly':
                    case 'Piece rate':
                        $unitsWorked = $regularHours + $overtimeHours;
                        $hoursWorked = $unitsWorked;
                        break;
                    case 'Daily':
                    case 'Weekly':
                    case 'Monthly':
                        $unitsWorked = $presentDays;
                        $daysWorked = $unitsWorked;
                        break;
                }

                $grossPay = $minWage * $unitsWorked;

                $sss = ContributionBracket::calculateAmount('SSS', $grossPay);
                $philhealth = ContributionBracket::calculateAmount('PhilHealth', $grossPay);
                $pagibig = ContributionBracket::calculateAmount('Pag-IBIG', $grossPay);

                $totalContrib = $sss + $philhealth + $pagibig;

                $totalAdvances = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'advance')
                    ->sum('amount');

                $totalRepayments = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'repayment')
                    ->sum('amount');

                $outstandingCa = max(0, $totalAdvances - $totalRepayments);

                $maxCaByBalance = $outstandingCa;
                $maxCaByNet = max(0, $grossPay - $totalContrib);

                $caDeduction = min($requestedCaDeduction, $maxCaByBalance, $maxCaByNet);

                if ($caDeduction < 0 || !is_finite($caDeduction)) {
                    $caDeduction = 0.0;
                }

                $totalDeductions = $totalContrib + $caDeduction;
                $netPay = $grossPay - $totalDeductions;

                $payroll = Payroll::create([
                    'user_id' => $userId,
                    'wage_type' => $wageType,
                    'min_wage' => $minWage,
                    'hours_worked' => $hoursWorked,
                    'days_worked' => $daysWorked,
                    'regular_hours' => $regularHours,
                    'overtime_hours' => $overtimeHours,
                    'absent_days' => $absentDays,
                    'gross_pay' => $grossPay,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => $netPay,
                    'status' => 'Pending',
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ]);

                if ($sss > 0) {
                    PayrollDeduction::create([
                        'payroll_id' => $payroll->id,
                        'deduction_name' => 'SSS',
                        'amount' => $sss,
                    ]);
                }

                if ($philhealth > 0) {
                    PayrollDeduction::create([
                        'payroll_id' => $payroll->id,
                        'deduction_name' => 'PhilHealth',
                        'amount' => $philhealth,
                    ]);
                }

                if ($pagibig > 0) {
                    PayrollDeduction::create([
                        'payroll_id' => $payroll->id,
                        'deduction_name' => 'Pag-IBIG',
                        'amount' => $pagibig,
                    ]);
                }

                if ($caDeduction > 0) {
                    PayrollDeduction::create([
                        'payroll_id' => $payroll->id,
                        'deduction_name' => 'Cash advance',
                        'amount' => $caDeduction,
                    ]);

                    CashAdvance::create([
                        'user_id' => $userId,
                        'type' => 'repayment',
                        'amount' => $caDeduction,
                        'description' => 'Auto-deducted from payroll #' . $payroll->id,
                        'source' => 'payroll',
                        'payroll_id' => $payroll->id,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('payroll')->with('success', 'Payrolls processed from attendance successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->withErrors([
                'error' => 'An error occurred while processing payroll: ' . $e->getMessage(),
            ]);
        }
    }

    public function updatePayroll(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'wage_type' => 'required|in:Hourly,Daily,Weekly,Monthly,Piece rate',
            'min_wage' => 'required|numeric|min:0',
            'units_worked' => 'required|numeric|min:0',
            'status' => 'required|in:Pending,Completed,Cancelled',
            'deductions' => 'nullable|array',
            'deductions.*.name' => 'required_with:deductions|string|max:30',
            'deductions.*.amount' => 'required_with:deductions|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            $wage_type = $validated['wage_type'];
            $min_wage = $validated['min_wage'];
            $units_worked = $validated['units_worked'];

            $gross_pay = 0;
            $hours_worked = null;
            $days_worked = null;

            switch ($wage_type) {
                case 'Hourly':
                    $hours_worked = $units_worked;
                    $gross_pay = $min_wage * $hours_worked;
                    break;
                case 'Daily':
                case 'Weekly':
                case 'Monthly':
                    $days_worked = $units_worked;
                    $gross_pay = $min_wage * $days_worked;
                    break;
                case 'Piece rate':
                    $hours_worked = $units_worked;
                    $gross_pay = $min_wage * $units_worked;
                    break;
            }

            $total_deductions = 0;
            if (!empty($validated['deductions'])) {
                $total_deductions = array_sum(array_column($validated['deductions'], 'amount'));
            }

            $net_pay = $gross_pay - $total_deductions;

            $statusUi = $validated['status'] ?? 'Pending';
            $statusDb = $statusUi === 'Completed' ? 'Released' : $statusUi;

            $payroll->update([
                'user_id' => $validated['user_id'],
                'wage_type' => $wage_type,
                'min_wage' => $min_wage,
                'hours_worked' => $hours_worked,
                'days_worked' => $days_worked,
                'gross_pay' => $gross_pay,
                'total_deductions' => $total_deductions,
                'net_pay' => $net_pay,
                'status' => $statusDb,
            ]);

            // Replace deductions
            $payroll->deductions()->delete();

            if (!empty($validated['deductions'])) {
                foreach ($validated['deductions'] as $deduction) {
                    if (!empty($deduction['name']) && isset($deduction['amount'])) {
                        PayrollDeduction::create([
                            'payroll_id' => $payroll->id,
                            'deduction_name' => $deduction['name'],
                            'amount' => $deduction['amount'],
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('payroll')->with('success', 'Payroll updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'An error occurred while updating payroll: ' . $e->getMessage()]);
        }
    }

    public function storePayroll(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'wage_type' => 'required|in:Hourly,Daily,Weekly,Monthly,Piece rate',
            'min_wage' => 'required|numeric|min:0',
            'units_worked' => 'required|numeric|min:0',
            'status' => 'required|in:Pending,Completed,Cancelled',
            'deductions' => 'nullable|array',
            'deductions.*.name' => 'required_with:deductions|string|max:30',
            'deductions.*.amount' => 'required_with:deductions|numeric|min:0',
        ], /*[
            'user_id.required' => 'Employee name is required.',
            'user_id.exists' => 'Selected employee does not exist.',
            'wage_type.required' => 'Wage type is required.',
            'wage_type.in' => 'Invalid wage type selected.',
            'min_wage.required' => 'Minimum wage is required.',
            'min_wage.numeric' => 'Minimum wage must be a valid number.',
            'min_wage.min' => 'Minimum wage cannot be negative.',
            'units_worked.required' => 'Units worked is required.',
            'units_worked.numeric' => 'Units worked must be a valid number.',
            'units_worked.min' => 'Units worked cannot be negative.',
            'deductions.*.name.required_with' => 'Deduction name is required.',
            'deductions.*.name.max' => 'Deduction name cannot exceed 30 characters.',
            'deductions.*.amount.required_with' => 'Deduction amount is required.',
            'deductions.*.amount.numeric' => 'Deduction amount must be a valid number.',
            'deductions.*.amount.min' => 'Deduction amount cannot be negative.',
        ]*/);

        DB::beginTransaction();

        try {

            $wage_type = $validated['wage_type'];
            $min_wage = $validated['min_wage'];
            $units_worked = $validated['units_worked'];

            $gross_pay = 0;
            $hours_worked = null;
            $days_worked = null;

            switch ($wage_type) {
                case 'Hourly':
                    $hours_worked = $units_worked;
                    $gross_pay = $min_wage * $hours_worked;
                    break;
                case 'Daily':
                    $days_worked = $units_worked;
                    $gross_pay = $min_wage * $days_worked;
                    break;
                case 'Weekly':
                    $days_worked = $units_worked;
                    $gross_pay = $min_wage * $days_worked;
                    break;
                case 'Monthly':
                    $days_worked = $units_worked;
                    $gross_pay = $min_wage * $days_worked;
                    break;
                case 'Piece rate':
                    $hours_worked = $units_worked; // store units in hours_worked for now
                    $gross_pay = $min_wage * $units_worked;
                    break;
            }

            $total_deductions = 0;
            if (!empty($validated['deductions'])) {
                $total_deductions = array_sum(array_column($validated['deductions'], 'amount'));
            }

            $net_pay = $gross_pay - $total_deductions;

            $statusUi = $validated['status'] ?? 'Pending';
            $statusDb = $statusUi === 'Completed' ? 'Released' : $statusUi;

            $payroll = Payroll::create([
                'user_id' => $validated['user_id'],
                'wage_type' => $wage_type,
                'min_wage' => $min_wage,
                'hours_worked' => $hours_worked,
                'days_worked' => $days_worked,
                'gross_pay' => $gross_pay,
                'total_deductions' => $total_deductions,
                'net_pay' => $net_pay,
                'status' => $statusDb,
            ]);

            if (!empty($validated['deductions'])) {
                foreach ($validated['deductions'] as $deduction) {
                    if (!empty($deduction['name']) && isset($deduction['amount'])) {
                        PayrollDeduction::create([
                            'payroll_id' => $payroll->id,
                            'deduction_name' => $deduction['name'],
                            'amount' => $deduction['amount'],
                        ]);
                    }
                }
            }

            DB::commit();

            return redirect()->route('payroll')->with('success', 'Payroll added successfully.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'An error occured while adding payroll: ' . $e->getMessage()]);
        }
    }

    public function updatePayrollStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Completed,Cancelled',
        ]);

        $statusUi = $validated['status'];
        $statusDb = $statusUi === 'Completed' ? 'Released' : $statusUi;

        $payroll = Payroll::findOrFail($id);
        $payroll->status = $statusDb;
        $payroll->save();

        return redirect()->route('payroll')->with('success', 'Payroll status updated successfully.');
    }

    public function deletePayroll(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->delete();

        return redirect()->route('payroll')->with('success', 'Payroll successfully deleted.');
    }

    public function deleteMultiplePayroll(Request $request)
    {
        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:payrolls,id',
        ]);

        $payroll = Payroll::whereIn('id', $validated['payroll_ids'])->get();

        $payroll->delete();

        return redirect()->route('payroll')->with('success', 'Selected payrolls successfully deleted.');
    }

    // users
    public function viewUsers()
    {
        $users = User::whereNull('deleted_at')->get();
        $archivedUsers = User::onlyTrashed()->get();
        
        return view('pages.users', [
            'title' => 'Users',
            'pageClass' => 'users',
            'users' => $users,
            'archivedUsers' => $archivedUsers
        ]);
    }

    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:Admin,HR Manager,Accounting,Payroll Officer,Project Manager,Supervisor,Worker',
            'password' => 'required|string|min:8',
        ]);

        DB::beginTransaction();

        try {
            // Generate unique username
            $baseUsername = strtolower(str_replace(' ', '.', $validated['full_name']));
            $username = $baseUsername;
            $counter = 1;

            // If username already exists, append a number
            while (User::where('username', $username)->exists()) {
                $username = $baseUsername . $counter;
                $counter++;
            }

            $user = User::create([
                'username' => $username,
                'full_name' => $validated['full_name'],
                'email' => $validated['email'],
                'password' => \Hash::make($validated['password']),
                'role' => $validated['role'],
            ]);

            DB::commit();

            return redirect()->route('users')->with('success', 'User added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'An error occurred while adding user: ' . $e->getMessage()]);
        }
    }

    public function archiveUser(User $user)
    {
        $user->delete();
        return response()->json(['success' => true]);
    }

    public function restoreUser($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        return response()->json(['success' => true]);
    }

    public function deleteUser(Request $request, $id)
    {
        $user = User::withTrashed()->findOrFail($id);
        
        // If user is soft deleted, force delete it
        if ($user->trashed()) {
            $user->forceDelete();
        } else {
            $user->delete();
        }

        return response()->json(['success' => true]);
    }

    public function deleteMultipleUsers(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        $users = User::whereIn('id', $validated['user_ids'])->get();
        $users->each->delete();

        return redirect()->route('users')->with('success', 'Selected users successfully deleted.');
    }

    // require js
    public function require()
    {
        return view('components.require');
    }
}
