<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\CrewAssignment;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * Build all data needed for the main attendance index page.
     *
     * @param  array  $filters
     * @return array
     */
    public function getIndexData(array $filters): array
    {
        $employeeId = $filters['employee_id'] ?? null;
        $status = $filters['status'] ?? null;
        $periodStart = $filters['period_start'] ?? null;
        $periodEnd = $filters['period_end'] ?? null;
        $search = $filters['search'] ?? null;
        $showArchived = !empty($filters['archived']);

        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $baseQuery = $showArchived ? Attendance::onlyTrashed() : Attendance::query();

        if (!empty($employeeId)) {
            $baseQuery->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $baseQuery->where('status', $status);
        }

        if (!empty($search)) {
            $baseQuery->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('username', 'LIKE', '%' . $search . '%');
            });
        }

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

        $attendances = $tableQuery->paginate(10)->appends($filters);

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

        return [
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
                'search' => $search,
            ],
        ];
    }

    /**
     * Build data for the daily attendance sheet view.
     */
    public function getDailyViewData($currentUser, array $filters): array
    {
        $currentRole = strtolower($currentUser->role ?? '');

        $dateInput = $filters['date'] ?? null;
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

        $filterEmployeeId = $filters['employee_id'] ?? null;
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

        return [
            'dailyDate' => $date->toDateString(),
            'dailyTableData' => $dailyTableData,
            'employeeOptions' => $employeeOptions,
            'filters' => [
                'employee_id' => $filterEmployeeId,
                'date' => $date->toDateString(),
            ],
        ];
    }

    /**
     * Build data for the bulk attendance view.
     */
    public function getBulkViewData($currentUser, array $filters): array
    {
        $currentRole = strtolower($currentUser->role ?? '');

        $dateInput = $filters['date'] ?? null;
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

        $filterEmployeeId = $filters['employee_id'] ?? null;
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

        return [
            'bulkDate' => $date->toDateString(),
            'rows' => $rows,
            'employeeOptions' => $employeeOptions,
            'filters' => [
                'employee_id' => $filterEmployeeId,
                'date' => $date->toDateString(),
            ],
        ];
    }

    /**
     * Build data set for detailed attendance export.
     */
    public function getExportAttendanceData(array $filters): array
    {
        $employeeId = $filters['employee_id'] ?? null;
        $status = $filters['status'] ?? null;
        $periodStart = $filters['period_start'] ?? null;
        $periodEnd = $filters['period_end'] ?? null;
        $includeArchived = !empty($filters['archived']);
        $search = $filters['search'] ?? null;

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

        if (!empty($search)) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('username', 'LIKE', '%' . $search . '%');
            });
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

        return [
            'attendances' => $attendances,
            'includeArchivedColumn' => $includeArchived,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];
    }

    /**
     * Build summary rows for attendance summary export.
     */
    public function getExportSummaryRows(array $filters): array
    {
        $employeeId = $filters['employee_id'] ?? null;
        $status = $filters['status'] ?? null;
        $periodStart = $filters['period_start'] ?? null;
        $periodEnd = $filters['period_end'] ?? null;
        $includeArchived = !empty($filters['archived']);
        $search = $filters['search'] ?? null;

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

        if (!empty($search)) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('full_name', 'LIKE', '%' . $search . '%')
                    ->orWhere('username', 'LIKE', '%' . $search . '%');
            });
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

        return $this->buildAttendanceEmployeeSummary($attendances);
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

    /**
     * Store bulk attendance records inside a transaction.
     * Returns the canonical date string used for redirect.
     */
    public function storeAttendanceBulk($currentUser, array $validated): string
    {
        $date = Carbon::parse($validated['date'])->startOfDay();
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
        $records = $validated['records'] ?? [];

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

            return $date->toDateString();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate default attendance records for a period.
     */
    public function generateDefaultAttendance(array $validated): void
    {
        $start = Carbon::parse($validated['period_start'])->startOfDay();
        $end = Carbon::parse($validated['period_end'])->endOfDay();

        $employeeQuery = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if (!empty($validated['employee_id'])) {
            $employeeQuery->where('id', $validated['employee_id']);
        }

        $employees = $employeeQuery->get();

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

                    $calculated = $this->calculateAttendanceMetrics($date, config('attendance.default_shift_start', '08:00'), config('attendance.default_shift_end', '17:00'), null);

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
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function normalizeImportTime(?string $timeStr): ?string
    {
        if ($timeStr === null) {
            return null;
        }

        $timeStr = trim($timeStr);
        if ($timeStr === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $timeStr)) {
            return $timeStr;
        }

        $lower = strtolower($timeStr);

        if (strpos($lower, 'am') !== false || strpos($lower, 'pm') !== false) {
            try {
                return Carbon::createFromFormat('g:i a', $lower)->format('H:i');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    public function calculateAttendanceMetrics(Carbon $date, ?string $timeInStr, ?string $timeOutStr, ?string $statusInput): array
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
}
