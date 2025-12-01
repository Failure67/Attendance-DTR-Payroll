<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\Attendance;
use App\Models\CrewAssignment;
use App\Models\User;
use App\Models\CashAdvance;
use App\Models\ContributionBracket;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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

            // Anomaly: Absent but has recorded hours or times
            if ($status === 'Absent' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                $todayAnomalies[] = 'Absent but has recorded time/hours on ' . $dateLabel;
            }

            // Anomaly: Present/Late but zero hours
            if (in_array($status, ['Present', 'Late'], true) && $total <= 0) {
                $todayAnomalies[] = 'Present/late but with 0 hours on ' . $dateLabel;
            }

            // Anomaly: Present/Late with missing time-out
            if (in_array($status, ['Present', 'Late'], true) && $attendance->time_in && !$attendance->time_out) {
                $todayAnomalies[] = 'Missing time-out on ' . $dateLabel;
            }

            // Anomaly: On leave but has recorded hours or times
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
        $showArchived = $request->boolean('archived');

        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');

        if (empty($periodStart) && empty($periodEnd)) {
            $periodStart = now()->subDays(30)->toDateString();
            $periodEnd = now()->toDateString();
        }

        $baseQuery = $showArchived ? Attendance::onlyTrashed() : Attendance::query();

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

        $summaryAttendances = (clone $baseQuery)->with('user')->get();

        $tableQuery = (clone $baseQuery)
            ->with('user')
            ->leftJoin('users', 'attendances.user_id', '=', 'users.id');

        // Normalize and apply sorting
        $allowedSorts = ['name', 'date', 'time_in', 'total_hours', 'overtime_hours', 'status'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }
        $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';

        switch ($sortBy) {
            case 'date':
                $tableQuery
                    ->orderBy('attendances.date', $sortDir)
                    ->orderBy('attendances.time_in', $sortDir === 'asc' ? 'asc' : 'desc')
                    ->orderByRaw('COALESCE(users.full_name, users.username) ASC');
                break;
            case 'time_in':
                $tableQuery
                    ->orderBy('attendances.time_in', $sortDir)
                    ->orderBy('attendances.date', $sortDir === 'asc' ? 'asc' : 'desc')
                    ->orderByRaw('COALESCE(users.full_name, users.username) ASC');
                break;
            case 'total_hours':
                $tableQuery
                    ->orderBy('attendances.total_hours', $sortDir)
                    ->orderBy('attendances.overtime_hours', $sortDir)
                    ->orderByRaw('COALESCE(users.full_name, users.username) ASC');
                break;
            case 'overtime_hours':
                $tableQuery
                    ->orderBy('attendances.overtime_hours', $sortDir)
                    ->orderBy('attendances.total_hours', $sortDir)
                    ->orderByRaw('COALESCE(users.full_name, users.username) ASC');
                break;
            case 'status':
                $tableQuery
                    ->orderBy('attendances.status', $sortDir)
                    ->orderByRaw('COALESCE(users.full_name, users.username) ASC')
                    ->orderByDesc('attendances.date')
                    ->orderByDesc('attendances.time_in');
                break;
            case 'name':
            default:
                $tableQuery
                    ->orderByRaw('COALESCE(users.full_name, users.username) ' . $sortDir)
                    ->orderByDesc('attendances.date')
                    ->orderByDesc('attendances.time_in');
                break;
        }

        $tableQuery->select('attendances.*');

        $attendances = $tableQuery->paginate(10)->appends($request->query());

        $employeeSummaryTableData = $this->buildAttendanceEmployeeSummary($summaryAttendances);

        $attendanceTableData = $attendances->map(function ($attendance) use ($showArchived) {
            $employeeName = $attendance->user ? ($attendance->user->full_name ?? $attendance->user->username) : 'Unknown employee';

            $date = $attendance->date
                ? $attendance->date->format('Y-m-d')
                : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : 'N/A');

            $timeInText = $attendance->time_in ? $attendance->time_in->format('g:i A') : '—';
            $timeOutText = $attendance->time_out ? $attendance->time_out->format('g:i A') : '—';

            $timeIn24 = $attendance->time_in ? $attendance->time_in->format('H:i') : '';
            $timeOut24 = $attendance->time_out ? $attendance->time_out->format('H:i') : '';

            $timeIn = $attendance->time_in
                ? '<span class="attendance-time-in" data-time-24="' . e($timeIn24) . '">' . e($timeInText) . '</span>'
                : '—';

            $timeOut = $attendance->time_out
                ? '<span class="attendance-time-out" data-time-24="' . e($timeOut24) . '">' . e($timeOutText) . '</span>'
                : '—';

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

            $row = [
                $employeeCell,
                e($date),
                $timeIn,
                $timeOut,
                $totalHours,
                $overtimeHours,
                $statusHtml,
            ];

            if ($showArchived) {
                $csrf = csrf_token();

                $restoreForm = "<form method=\"POST\" action=\"" . route('attendance.restore', ['attendance' => $attendance->id]) . "\" style=\"display:inline-block;margin-right:4px;\" onsubmit=\"return confirm('Recover this attendance record?');\">"
                    . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                    '<button type="submit" class="btn btn-outline-success btn-sm" title="Recover">'
                    . '<i class="fa-solid fa-rotate-left"></i>' .
                    '</button>' .
                    '</form>';

                $deleteForm = "<form method=\"POST\" action=\"" . route('attendance.delete', ['id' => $attendance->id]) . "\" style=\"display:inline-block;\" onsubmit=\"return confirm('Permanently delete this attendance record? This cannot be undone.');\">"
                    . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                    '<input type="hidden" name="_method" value="DELETE">'
                    . '<input type="hidden" name="archived" value="1">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm" title="Delete permanently">'
                    . '<i class="fa-solid fa-trash"></i>' .
                    '</button>' .
                    '</form>';

                $actionsHtml = '<div class="attendance-archive-actions d-flex align-items-center gap-1">'
                    . $restoreForm
                    . $deleteForm
                    . '</div>';

                $row[] = $actionsHtml;
            }

            return $row;
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
            'employeeSummaryTableData' => $employeeSummaryTableData,
            'showArchived' => $showArchived,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'filters' => [
                'employee_id' => $employeeId,
                'status' => $status,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
        ]);
    }

    public function viewAttendanceBulk(Request $request)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput)->startOfDay() : now()->startOfDay();

        $employeeQuery = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if ($currentRole === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');
            if ($crewWorkerIds->isNotEmpty()) {
                $employeeQuery->whereIn('id', $crewWorkerIds);
            } else {
                $employeeQuery->whereRaw('1 = 0');
            }
        }

        $filterEmployeeId = $request->input('employee_id');
        if (!empty($filterEmployeeId)) {
            $employeeQuery->where('id', $filterEmployeeId);
        }

        $employees = $employeeQuery
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $attendanceByUser = Attendance::whereDate('date', $date->toDateString())
            ->whereIn('user_id', $employees->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $defaultTimeIn = config('attendance.default_shift_start', '08:00');
        $defaultTimeOut = config('attendance.default_shift_end', '17:00');

        $rows = $employees->map(function ($employee) use ($attendanceByUser, $defaultTimeIn, $defaultTimeOut) {
            $attendance = $attendanceByUser->get($employee->id);

            return [
                'user_id' => $employee->id,
                'attendance_id' => $attendance ? $attendance->id : null,
                'name' => $employee->full_name ?? $employee->username,
                'time_in' => $attendance && $attendance->time_in ? $attendance->time_in->format('H:i') : $defaultTimeIn,
                'time_out' => $attendance && $attendance->time_out ? $attendance->time_out->format('H:i') : $defaultTimeOut,
                'status' => $attendance ? ($attendance->status ?? null) : null,
            ];
        })->values();

        return view('pages.attendance-bulk', [
            'title' => 'Bulk attendance',
            'pageClass' => 'attendance-bulk',
            'bulkDate' => $date->toDateString(),
            'rows' => $rows,
            'employeeOptions' => $employeeOptions,
            'filters' => [
                'employee_id' => $filterEmployeeId,
                'date' => $date->toDateString(),
            ],
        ]);
    }

    public function storeAttendanceBulk(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array',
            'records.*.user_id' => 'required|exists:users,id',
            'records.*.time_in' => 'nullable|date_format:H:i',
            'records.*.time_out' => 'nullable|date_format:H:i',
            'records.*.status' => 'nullable|in:Present,Absent,Late,On leave',
            'records.*.attendance_id' => 'nullable|integer',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $allowedWorkerQuery = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if ($currentRole === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');
            if ($crewWorkerIds->isNotEmpty()) {
                $allowedWorkerQuery->whereIn('id', $crewWorkerIds);
            } else {
                $allowedWorkerQuery->whereRaw('1 = 0');
            }
        }

        $allowedWorkerIds = $allowedWorkerQuery->pluck('id')->all();

        $records = $validated['records'];

        DB::beginTransaction();

        try {
            foreach ($records as $record) {
                $userId = (int) ($record['user_id'] ?? 0);
                if (!$userId || !in_array($userId, $allowedWorkerIds, true)) {
                    continue;
                }

                $timeInStr = $record['time_in'] ?? null;
                $timeOutStr = $record['time_out'] ?? null;
                $statusInput = $record['status'] ?? null;

                if ($timeInStr === null && $timeOutStr === null && $statusInput === null) {
                    continue;
                }

                $calculated = $this->calculateAttendanceMetrics($date, $timeInStr, $timeOutStr, $statusInput);

                $attendanceId = $record['attendance_id'] ?? null;

                if ($attendanceId) {
                    $attendance = Attendance::where('id', $attendanceId)
                        ->where('user_id', $userId)
                        ->first();
                } else {
                    $attendance = Attendance::where('user_id', $userId)
                        ->whereDate('date', $date->toDateString())
                        ->first();
                }

                if ($attendance) {
                    $attendance->update([
                        'date' => $date->format('Y-m-d'),
                        'time_in' => $calculated['time_in'],
                        'time_out' => $calculated['time_out'],
                        'total_hours' => $calculated['total_hours'],
                        'overtime_hours' => $calculated['overtime_hours'],
                        'status' => $calculated['status'],
                    ]);
                } else {
                    Attendance::create([
                        'user_id' => $userId,
                        'date' => $date->format('Y-m-d'),
                        'time_in' => $calculated['time_in'],
                        'time_out' => $calculated['time_out'],
                        'total_hours' => $calculated['total_hours'],
                        'overtime_hours' => $calculated['overtime_hours'],
                        'status' => $calculated['status'],
                        'overtime_approved' => false,
                        'leave_approved' => false,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('attendance.bulk', ['date' => $date->toDateString()])
                ->with('success', 'Bulk attendance saved successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while saving bulk attendance: ' . $e->getMessage()]);
        }
    }

    public function viewAttendanceDaily(Request $request)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $dateInput = $request->input('date');
        $date = $dateInput ? Carbon::parse($dateInput)->startOfDay() : now()->startOfDay();

        $employeeQuery = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if ($currentRole === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');
            if ($crewWorkerIds->isNotEmpty()) {
                $employeeQuery->whereIn('id', $crewWorkerIds);
            } else {
                $employeeQuery->whereRaw('1 = 0');
            }
        }

        $filterEmployeeId = $request->input('employee_id');
        if (!empty($filterEmployeeId)) {
            $employeeQuery->where('id', $filterEmployeeId);
        }

        $employees = $employeeQuery
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $attendanceByUser = Attendance::whereDate('date', $date->toDateString())
            ->whereIn('user_id', $employees->pluck('id'))
            ->get()
            ->keyBy('user_id');

        $dailyTableData = [];

        foreach ($employees as $employee) {
            $attendance = $attendanceByUser->get($employee->id);

            $employeeName = $employee->full_name ?? $employee->username;

            if ($attendance) {
                $timeInText = $attendance->time_in ? $attendance->time_in->format('g:i A') : '—';
                $timeOutText = $attendance->time_out ? $attendance->time_out->format('g:i A') : '—';
                $status = $attendance->status ?? 'Present';
                $totalHours = number_format((float) $attendance->total_hours, 2);
                $overtimeHours = number_format((float) $attendance->overtime_hours, 2);
            } else {
                $timeInText = '—';
                $timeOutText = '—';
                $status = 'No record';
                $totalHours = number_format(0, 2);
                $overtimeHours = number_format(0, 2);
            }

            $dailyTableData[] = [
                e($employeeName),
                e($timeInText),
                e($timeOutText),
                e($status),
                $totalHours,
                $overtimeHours,
            ];
        }

        if (empty($dailyTableData)) {
            $dailyTableData[] = [
                'No employees found for selected date.',
                '',
                '',
                '',
                '',
                '',
            ];
        }

        return view('pages.attendance-daily', [
            'title' => 'Daily attendance sheet',
            'pageClass' => 'attendance-daily',
            'dailyDate' => $date->toDateString(),
            'dailyTableData' => $dailyTableData,
            'employeeOptions' => $employeeOptions,
            'filters' => [
                'employee_id' => $filterEmployeeId,
                'date' => $date->toDateString(),
            ],
        ]);
    }

    public function generateDefaultAttendance(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'employee_id' => 'nullable|exists:users,id',
        ]);

        $start = Carbon::parse($validated['period_start'])->startOfDay();
        $end = Carbon::parse($validated['period_end'])->endOfDay();

        $employeeQuery = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if (!empty($validated['employee_id'])) {
            $employeeQuery->where('id', $validated['employee_id']);
        }

        $employees = $employeeQuery->get();

        $shiftStartStr = config('attendance.default_shift_start', '08:00');
        $shiftEndStr = config('attendance.default_shift_end', '17:00');

        DB::beginTransaction();

        try {
            foreach ($employees as $employee) {
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $existing = Attendance::where('user_id', $employee->id)
                        ->whereDate('date', $date->toDateString())
                        ->first();

                    if ($existing) {
                        continue;
                    }

                    $calculated = $this->calculateAttendanceMetrics($date, $shiftStartStr, $shiftEndStr, null);

                    Attendance::create([
                        'user_id' => $employee->id,
                        'date' => $date->format('Y-m-d'),
                        'time_in' => $calculated['time_in'],
                        'time_out' => $calculated['time_out'],
                        'total_hours' => $calculated['total_hours'],
                        'overtime_hours' => $calculated['overtime_hours'],
                        'status' => $calculated['status'],
                        'overtime_approved' => false,
                        'leave_approved' => false,
                    ]);
                }
            }

            DB::commit();

            return redirect()->route('attendance')
                ->with('success', 'Default attendance generated for selected period.');
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while generating default attendance: ' . $e->getMessage()]);
        }
    }

    public function storeAttendance(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'status' => 'nullable|in:Present,Absent,Late,On leave',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $calculated = $this->calculateAttendanceMetrics(
            $date,
            $validated['time_in'] ?? null,
            $validated['time_out'] ?? null,
            $validated['status'] ?? null
        );

        $overtimeApproved = $request->boolean('overtime_approved') && $calculated['overtime_hours'] > 0;
        $leaveApproved = $request->boolean('leave_approved') && ($calculated['status'] === 'On leave');

        Attendance::create([
            'user_id' => $validated['user_id'],
            'date' => $date->format('Y-m-d'),
            'time_in' => $calculated['time_in'],
            'time_out' => $calculated['time_out'],
            'total_hours' => $calculated['total_hours'],
            'overtime_hours' => $calculated['overtime_hours'],
            'status' => $calculated['status'],
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
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'status' => 'nullable|in:Present,Absent,Late,On leave',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $calculated = $this->calculateAttendanceMetrics(
            $date,
            $validated['time_in'] ?? null,
            $validated['time_out'] ?? null,
            $validated['status'] ?? $attendance->status
        );

        $overtimeApproved = $request->boolean('overtime_approved') && $calculated['overtime_hours'] > 0;
        $leaveApproved = $request->boolean('leave_approved') && ($calculated['status'] === 'On leave');

        $attendance->update([
            'user_id' => $validated['user_id'],
            'date' => $date->format('Y-m-d'),
            'time_in' => $calculated['time_in'],
            'time_out' => $calculated['time_out'],
            'total_hours' => $calculated['total_hours'],
            'overtime_hours' => $calculated['overtime_hours'],
            'status' => $calculated['status'],
            'overtime_approved' => $overtimeApproved,
            'leave_approved' => $leaveApproved,
        ]);

        return redirect()->route('attendance')->with('success', 'Attendance record updated successfully.');
    }

    public function deleteAttendance(Request $request, $id)
    {
        $attendance = Attendance::withTrashed()->findOrFail($id);

        $stayOnArchived = $request->boolean('archived');

        if ($attendance->trashed()) {
            $attendance->forceDelete();
            $message = 'Attendance record permanently deleted.';
        } else {
            $attendance->delete();
            $message = 'Attendance record archived successfully.';
        }

        $routeParams = $stayOnArchived ? ['archived' => 1] : [];

        return redirect()->route('attendance', $routeParams)->with('success', $message);
    }

    public function restoreAttendance($id)
    {
        $attendance = Attendance::withTrashed()->findOrFail($id);
        if ($attendance->trashed()) {
            $attendance->restore();
        }

        return redirect()->route('attendance')->with('success', 'Attendance record recovered successfully.');
    }

    public function deleteMultipleAttendance(Request $request)
    {
        $validated = $request->validate([
            'attendance_ids' => 'required|array',
            'attendance_ids.*' => 'exists:attendances,id',
        ]);

        $stayOnArchived = $request->boolean('archived');

        $attendances = Attendance::withTrashed()->whereIn('id', $validated['attendance_ids'])->get();

        foreach ($attendances as $attendance) {
            if ($attendance->trashed()) {
                $attendance->forceDelete();
            } else {
                $attendance->delete();
            }
        }

        $routeParams = $stayOnArchived ? ['archived' => 1] : [];

        return redirect()->route('attendance', $routeParams)->with('success', 'Selected attendance records processed successfully.');
    }

    public function exportAttendance(Request $request)
    {
        // Use similar filters as the main attendance view
        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');
        $includeArchived = $request->boolean('archived');

        if (empty($periodStart) && empty($periodEnd)) {
            // Default export to last 30 days if no period specified
            $periodStart = now()->subDays(30)->toDateString();
            $periodEnd = now()->toDateString();
        }

        $query = $includeArchived
            ? Attendance::withTrashed()->with('user')
            : Attendance::with('user');

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($periodStart)) {
            $start = Carbon::parse($periodStart)->startOfDay();

            $query->where(function ($q) use ($start) {
                $q->whereDate('date', '>=', $start->toDateString())
                    ->orWhere('time_in', '>=', $start);
            });
        }

        if (!empty($periodEnd)) {
            $end = Carbon::parse($periodEnd)->endOfDay();

            $query->where(function ($q) use ($end) {
                $q->whereDate('date', '<=', $end->toDateString())
                    ->orWhere('time_in', '<=', $end);
            });
        }

        $attendances = $query
            ->orderBy('date')
            ->orderBy('time_in')
            ->orderBy('user_id')
            ->get();

        $filename = 'attendance_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $includeArchivedColumn = $includeArchived;

        $callback = function () use ($attendances, $includeArchivedColumn) {
            $handle = fopen('php://output', 'w');

            $header = [
                'Employee ID',
                'Employee',
                'Date',
                'Time in',
                'Time out',
                'Total hours',
                'Overtime hours',
                'Status',
                'Overtime approved',
                'Leave approved',
            ];

            if ($includeArchivedColumn) {
                $header[] = 'Archived';
            }

            fputcsv($handle, $header);

            foreach ($attendances as $attendance) {
                $employeeName = $attendance->user ? ($attendance->user->full_name ?? $attendance->user->username) : 'Unknown employee';
                $employeeIdOut = $attendance->user_id;

                $date = $attendance->date
                    ? $attendance->date->format('Y-m-d')
                    : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : '');

                // Prefix date with a single quote so Excel treats this as text
                $dateForExport = $date !== '' ? "'" . $date : '';
                $timeIn = $attendance->time_in ? $attendance->time_in->format('g:i A') : '';
                $timeOut = $attendance->time_out ? $attendance->time_out->format('g:i A') : '';

                $row = [
                    $employeeIdOut,
                    $employeeName,
                    $dateForExport,
                    $timeIn,
                    $timeOut,
                    (float) $attendance->total_hours,
                    (float) $attendance->overtime_hours,
                    $attendance->status ?? 'Present',
                    $attendance->overtime_approved ? 'Yes' : 'No',
                    $attendance->leave_approved ? 'Yes' : 'No',
                ];

                if ($includeArchivedColumn) {
                    $row[] = $attendance->deleted_at ? 'Yes' : 'No';
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportAttendanceSummary(Request $request)
    {
        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');
        $includeArchived = $request->boolean('archived');

        if (empty($periodStart) && empty($periodEnd)) {
            $periodStart = now()->subDays(30)->toDateString();
            $periodEnd = now()->toDateString();
        }

        $query = $includeArchived
            ? Attendance::withTrashed()->with('user')
            : Attendance::with('user');

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($periodStart)) {
            $start = Carbon::parse($periodStart)->startOfDay();

            $query->where(function ($q) use ($start) {
                $q->whereDate('date', '>=', $start->toDateString())
                    ->orWhere('time_in', '>=', $start);
            });
        }

        if (!empty($periodEnd)) {
            $end = Carbon::parse($periodEnd)->endOfDay();

            $query->where(function ($q) use ($end) {
                $q->whereDate('date', '<=', $end->toDateString())
                    ->orWhere('time_in', '<=', $end);
            });
        }

        $attendances = $query->get();

        $summaryRows = $this->buildAttendanceEmployeeSummary($attendances);

        $filename = 'attendance_summary_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($summaryRows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee ID',
                'Employee',
                'Days present',
                'Days late',
                'Days absent',
                'Days on leave',
                'Total hours',
                'Overtime hours',
            ]);

            foreach ($summaryRows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    // crew assignments
    public function viewCrewAssignments(Request $request)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $supervisors = User::whereNull('deleted_at')
            ->where('role', 'Supervisor')
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        $canChooseSupervisor = $currentRole !== 'supervisor';

        if ($currentRole === 'supervisor') {
            $selectedSupervisorId = $currentUser->id;
        } else {
            $selectedSupervisorId = $request->input('supervisor_id');
            if (empty($selectedSupervisorId) && $supervisors->isNotEmpty()) {
                $selectedSupervisorId = $supervisors->first()->id;
            }
        }

        $currentSupervisor = null;
        if ($selectedSupervisorId) {
            if ($currentRole === 'supervisor' && $currentUser->id === (int) $selectedSupervisorId) {
                $currentSupervisor = $currentUser;
            } else {
                $currentSupervisor = $supervisors->firstWhere('id', (int) $selectedSupervisorId);
            }
        }

        $crewAssignments = collect();
        $availableWorkers = collect();
        $crewTableData = [];

        if ($selectedSupervisorId) {
            $crewAssignments = CrewAssignment::with('worker')
                ->where('supervisor_id', $selectedSupervisorId)
                ->get();

            $assignedWorkerIds = $crewAssignments->pluck('worker_id');

            $availableWorkers = User::whereNull('deleted_at')
                ->where('role', 'Worker')
                ->whereNotIn('id', $assignedWorkerIds)
                ->orderBy('full_name')
                ->orderBy('username')
                ->get();

            $crewTableData = $crewAssignments->map(function ($assignment) {
                $worker = $assignment->worker;
                $name = $worker ? ($worker->full_name ?? $worker->username) : 'Unknown worker';

                $workerCell = e($name);

                $csrf = csrf_token();
                $deleteForm = "<form method=\"POST\" action=\"" . route('crew.assignments.delete', ['id' => $assignment->id]) . "\" style=\"display:inline-block;\" onsubmit=\"return confirm('Remove this worker from the crew?');\">"
                    . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                    '<input type="hidden" name="_method" value="DELETE">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm" title="Remove from crew">'
                    . '<i class="fa-solid fa-user-minus"></i>' .
                    '</button>' .
                    '</form>';

                return [
                    $workerCell,
                    $deleteForm,
                ];
            })->toArray();
        }

        return view('pages.crew-assignments', [
            'title' => 'Crew assignments',
            'pageClass' => 'crew-assignments',
            'supervisors' => $supervisors,
            'currentSupervisor' => $currentSupervisor,
            'selectedSupervisorId' => $selectedSupervisorId,
            'canChooseSupervisor' => $canChooseSupervisor,
            'availableWorkers' => $availableWorkers,
            'crewTableData' => $crewTableData,
        ]);
    }

    public function storeCrewAssignments(Request $request)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $validated = $request->validate([
            'supervisor_id' => 'required|exists:users,id',
            'worker_ids' => 'required|array',
            'worker_ids.*' => 'exists:users,id',
        ]);

        $supervisorId = (int) $validated['supervisor_id'];

        if ($currentRole === 'supervisor' && $currentUser->id !== $supervisorId) {
            abort(403, 'You are not allowed to modify another supervisor\'s crew.');
        }

        foreach ($validated['worker_ids'] as $workerId) {
            CrewAssignment::firstOrCreate([
                'supervisor_id' => $supervisorId,
                'worker_id' => $workerId,
            ]);
        }

        return redirect()->route('crew.assignments', ['supervisor_id' => $supervisorId])
            ->with('success', 'Crew assignments updated successfully.');
    }

    public function deleteCrewAssignment(Request $request, $id)
    {
        $currentUser = auth()->user();
        $currentRole = strtolower($currentUser->role ?? '');

        $assignment = CrewAssignment::findOrFail($id);

        if ($currentRole === 'supervisor' && $assignment->supervisor_id !== $currentUser->id) {
            abort(403, 'You are not allowed to modify another supervisor\'s crew.');
        }

        $supervisorId = $assignment->supervisor_id;
        $assignment->delete();

        return redirect()->route('crew.assignments', ['supervisor_id' => $supervisorId])
            ->with('success', 'Worker removed from crew successfully.');
    }

    public function importAttendance(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv,txt',
        ]);

        $path = $validated['file']->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return redirect()->back()
                ->withErrors(['file' => 'Unable to read uploaded file.']);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return redirect()->back()
                ->withErrors(['file' => 'Uploaded CSV appears to be empty.']);
        }

        $header = array_map('trim', $header);

        $colIndex = [
            'employee_id' => array_search('employee_id', $header),
            'date' => array_search('date', $header),
            'time_in' => array_search('time_in', $header),
            'time_out' => array_search('time_out', $header),
            'status' => array_search('status', $header),
        ];

        if ($colIndex['employee_id'] === false || $colIndex['date'] === false) {
            fclose($handle);
            return redirect()->back()
                ->withErrors(['file' => 'CSV must contain at least employee_id and date columns.']);
        }

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $employeeIdRaw = $row[$colIndex['employee_id']] ?? null;
                $dateRaw = $row[$colIndex['date']] ?? null;

                if (empty($employeeIdRaw) || empty($dateRaw)) {
                    continue;
                }

                $user = User::find((int) $employeeIdRaw);
                if (!$user || $user->deleted_at !== null) {
                    continue;
                }

                $date = Carbon::parse($dateRaw)->startOfDay();

                $timeInStr = $colIndex['time_in'] !== false ? trim((string) ($row[$colIndex['time_in']] ?? '')) : null;
                $timeOutStr = $colIndex['time_out'] !== false ? trim((string) ($row[$colIndex['time_out']] ?? '')) : null;
                $statusStr = $colIndex['status'] !== false ? trim((string) ($row[$colIndex['status']] ?? '')) : null;

                if ($timeInStr === '') {
                    $timeInStr = null;
                }
                if ($timeOutStr === '') {
                    $timeOutStr = null;
                }
                if ($statusStr === '') {
                    $statusStr = null;
                }

                $calculated = $this->calculateAttendanceMetrics($date, $timeInStr, $timeOutStr, $statusStr);

                $attendance = Attendance::where('user_id', $user->id)
                    ->whereDate('date', $date->toDateString())
                    ->first();

                if ($attendance) {
                    $attendance->update([
                        'date' => $date->format('Y-m-d'),
                        'time_in' => $calculated['time_in'],
                        'time_out' => $calculated['time_out'],
                        'total_hours' => $calculated['total_hours'],
                        'overtime_hours' => $calculated['overtime_hours'],
                        'status' => $calculated['status'],
                    ]);
                } else {
                    Attendance::create([
                        'user_id' => $user->id,
                        'date' => $date->format('Y-m-d'),
                        'time_in' => $calculated['time_in'],
                        'time_out' => $calculated['time_out'],
                        'total_hours' => $calculated['total_hours'],
                        'overtime_hours' => $calculated['overtime_hours'],
                        'status' => $calculated['status'],
                        'overtime_approved' => false,
                        'leave_approved' => false,
                    ]);
                }
            }

            fclose($handle);
            DB::commit();

            return redirect()->route('attendance')
                ->with('success', 'Attendance imported successfully from CSV.');
        } catch (\Exception $e) {
            fclose($handle);
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['file' => 'An error occurred while importing attendance: ' . $e->getMessage()]);
        }
    }

    private function calculateAttendanceMetrics(Carbon $date, ?string $timeInStr, ?string $timeOutStr, ?string $statusInput): array
    {
        $shiftStartStr = config('attendance.default_shift_start', '08:00');
        $shiftEndStr = config('attendance.default_shift_end', '17:00');
        $standardDailyHours = (float) config('attendance.standard_daily_hours', 8);
        $lateGraceMinutes = (int) config('attendance.late_grace_minutes', 15);

        $timeIn = null;
        $timeOut = null;
        $totalHours = 0.0;
        $overtimeHours = 0.0;

        if ($statusInput === 'On leave') {
            return [
                'time_in' => null,
                'time_out' => null,
                'total_hours' => 0.0,
                'overtime_hours' => 0.0,
                'status' => 'On leave',
            ];
        }

        if ($timeInStr) {
            $timeIn = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $timeInStr);

            if ($timeOutStr) {
                $timeOut = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $timeOutStr);

                $minutes = max(0, $timeIn->diffInMinutes($timeOut, false));

                $lunchMinutes = 60;
                if ($minutes >= ($standardDailyHours * 60) + $lunchMinutes) {
                    $minutes -= $lunchMinutes;
                }

                $totalHours = round($minutes / 60, 2);
                $overtimeHours = max(0, $totalHours - $standardDailyHours);
            }
        }

        $status = $statusInput;

        if ($status === null) {
            if (!$timeIn && !$timeOut) {
                $status = 'Absent';
            } else {
                $shiftStart = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d') . ' ' . $shiftStartStr);
                $lateThreshold = $shiftStart->copy()->addMinutes($lateGraceMinutes);

                if ($timeIn && $timeIn->greaterThan($lateThreshold)) {
                    $status = 'Late';
                } else {
                    $status = 'Present';
                }
            }
        }

        return [
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'total_hours' => $totalHours,
            'overtime_hours' => $overtimeHours,
            'status' => $status,
        ];
    }

    private function buildAttendanceEmployeeSummary(Collection $attendances): array
    {
        $grouped = $attendances->groupBy('user_id');

        $rows = [];

        foreach ($grouped as $userId => $records) {
            if (!$records->count()) {
                continue;
            }

            $first = $records->first();
            $user = $first ? $first->user : null;

            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            $presentDays = $records->where('status', 'Present')->count();
            $lateDays = $records->where('status', 'Late')->count();
            $absentDays = $records->where('status', 'Absent')->count();
            $leaveDays = $records->where('status', 'On leave')->count();

            $totalHours = (float) $records->sum('total_hours');
            $overtimeHours = (float) $records->sum('overtime_hours');

            $rows[] = [
                (int) $userId,
                $employeeName,
                $presentDays,
                $lateDays,
                $absentDays,
                $leaveDays,
                number_format($totalHours, 2),
                number_format($overtimeHours, 2),
            ];
        }

        usort($rows, function (array $a, array $b) {
            return strcmp((string) $a[1], (string) $b[1]);
        });

        return $rows;
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
        $currentRole = strtolower(auth()->user()->role ?? '');

        $activeQuery = User::whereNull('deleted_at');
        $archivedQuery = User::onlyTrashed();

        if ($currentRole === 'superadmin') {
            // Superadmin should see Admin, HR, Payroll, Supervisor, Worker, but not other Superadmin accounts
            $users = $activeQuery
                ->whereNotIn('role', ['Superadmin', 'superadmin'])
                ->get();

            $archivedUsers = $archivedQuery
                ->whereNotIn('role', ['Superadmin', 'superadmin'])
                ->get();
        } else {
            // Fallback: hide all admin/superadmin accounts for non-superadmin viewers
            $users = $activeQuery
                ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
                ->get();

            $archivedUsers = $archivedQuery
                ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
                ->get();
        }
        
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
            'role' => 'required|in:Admin,HR Manager,Payroll Officer,Supervisor,Worker',
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
