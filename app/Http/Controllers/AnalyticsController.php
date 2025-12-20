<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRequest;
use App\Models\CrewAssignment;
use App\Models\LeaveEntry;
use App\Models\OvertimeEntry;
use App\Models\Payroll;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'employment_type' => $request->input('employment_type'),
        ];

        [$periodStart, $periodEnd] = $this->normalizePeriod($filters['period_start'], $filters['period_end']);

        $filters['period_start'] = $periodStart->toDateString();
        $filters['period_end'] = $periodEnd->toDateString();

        $employeeOptions = $this->buildEmployeeOptions($currentUser, $roleKey);

        $mode = $this->resolveMode($roleKey);

        $attendanceAnalytics = null;
        $payrollAnalytics = null;
        $approvalsAnalytics = null;

        if ($mode === 'attendance' || $mode === 'combined') {
            $attendanceAnalytics = $this->buildAttendanceAnalytics($currentUser, $roleKey, $filters);
        }

        if ($mode === 'payroll' || $mode === 'combined') {
            $payrollAnalytics = $this->buildPayrollAnalytics($filters);
        }

        $approvalsAnalytics = $this->buildApprovalsAnalytics($currentUser, $roleKey, $filters);

        return view('pages.analytics', [
            'title' => 'Analytics',
            'pageClass' => 'analytics',
            'mode' => $mode,
            'roleKey' => $roleKey,
            'filters' => $filters,
            'employeeOptions' => $employeeOptions,
            'attendanceAnalytics' => $attendanceAnalytics,
            'payrollAnalytics' => $payrollAnalytics,
            'approvalsAnalytics' => $approvalsAnalytics,
        ]);
    }

    public function exportPdf(Request $request)
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

        $approvalsAnalytics = null;

        $generatedAt = now();

        $pdf = Pdf::loadView('pdf.analytics', [
            'title' => 'Analytics Report',
            'mode' => $mode,
            'roleKey' => $roleKey,
            'filters' => $filters,
            'employeeOptions' => $employeeOptions,
            'attendanceAnalytics' => $attendanceAnalytics,
            'payrollAnalytics' => $payrollAnalytics,
            'approvalsAnalytics' => $approvalsAnalytics,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'landscape');

        $filename = 'analytics_report_' . $generatedAt->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
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
            ->select(['id', 'full_name', 'username'])
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

    private function buildApprovalsAnalytics(User $currentUser, string $roleKey, array $filters): array
    {
        $start = Carbon::parse($filters['period_start'])->startOfDay();
        $end = Carbon::parse($filters['period_end'])->endOfDay();
        $now = Carbon::now();

        $employeeIdFilter = !empty($filters['employee_id']) ? (int) $filters['employee_id'] : null;
        $employmentTypeFilter = !empty($filters['employment_type']) ? (string) $filters['employment_type'] : null;

        $crewWorkerIds = null;
        if ($roleKey === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');
        }

        $perEmployee = [];
        $oldestEntries = [];

        // Pending leave approvals (multi-stage)
        $leaveQuery = LeaveEntry::select([
            'id',
            'user_id',
            'status',
            'date_start',
            'date_end',
            'supervisor_approved_at',
            'manager_approved_at',
            'hr_approved_at',
            'created_at',
        ])->with(['user:id,full_name,username,employment_type'])
            ->where('status', 'pending')
            ->whereDate('date_start', '<=', $end->toDateString())
            ->whereDate('date_end', '>=', $start->toDateString());

        if ($employeeIdFilter) {
            $leaveQuery->where('user_id', $employeeIdFilter);
        }

        if ($roleKey === 'supervisor') {
            if ($crewWorkerIds && $crewWorkerIds->isNotEmpty()) {
                $leaveQuery->whereIn('user_id', $crewWorkerIds);
            } else {
                $leaveQuery->whereRaw('1 = 0');
            }
        } else {
            $leaveQuery->whereHas('user', function ($q) use ($employmentTypeFilter) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                if ($employmentTypeFilter) {
                    $q->where('employment_type', $employmentTypeFilter);
                }
            });
        }

        $pendingLeaves = $leaveQuery->get();

        $leaveTotal = $pendingLeaves->count();
        $leaveWaitingSupervisor = 0;
        $leaveWaitingManager = 0;
        $leaveWaitingHr = 0;

        foreach ($pendingLeaves as $entry) {
            $user = $entry->user;
            $userId = $entry->user_id;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            if ($userId) {
                if (!isset($perEmployee[$userId])) {
                    $perEmployee[$userId] = [
                        'employee_name' => $employeeName,
                        'employment_type' => $user ? ($user->employment_type ?? \App\Models\User::EMPLOYMENT_TYPE_REGULAR) : null,
                        'pending_leave' => 0,
                        'pending_overtime' => 0,
                        'pending_cash_advance' => 0,
                        'pending_payroll' => 0,
                        'pending_amount' => 0.0,
                    ];
                }
                $perEmployee[$userId]['pending_leave']++;
            }

            $stageLabel = 'Waiting for approval';
            if (!$entry->supervisor_approved_at && !$entry->manager_approved_at && !$entry->hr_approved_at) {
                $leaveWaitingSupervisor++;
                $stageLabel = 'Waiting for Supervisor';
            } elseif ($entry->supervisor_approved_at && !$entry->manager_approved_at && !$entry->hr_approved_at) {
                $leaveWaitingManager++;
                $stageLabel = 'Waiting for Manager';
            } elseif ($entry->manager_approved_at && !$entry->hr_approved_at) {
                $leaveWaitingHr++;
                $stageLabel = 'Waiting for HR';
            }

            $requestedAt = $entry->created_at ?: $entry->date_start ?: $start;
            $ageDays = $requestedAt ? $requestedAt->diffInDays($now) : 0;

            $oldestEntries[] = [
                'type' => 'Leave',
                'employee_name' => $employeeName,
                'requested_at' => $requestedAt,
                'age_days' => $ageDays,
                'stage' => $stageLabel,
            ];
        }

        // Pending overtime approvals
        $otQuery = OvertimeEntry::select([
            'id',
            'user_id',
            'status',
            'date',
            'hours',
            'created_at',
        ])->with(['user:id,full_name,username'])
            ->where('status', 'pending')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString());

        if ($employeeIdFilter) {
            $otQuery->where('user_id', $employeeIdFilter);
        }

        if ($roleKey === 'supervisor') {
            if ($crewWorkerIds && $crewWorkerIds->isNotEmpty()) {
                $otQuery->whereIn('user_id', $crewWorkerIds);
            } else {
                $otQuery->whereRaw('1 = 0');
            }
        } else {
            $otQuery->whereHas('user', function ($q) use ($employmentTypeFilter) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                if ($employmentTypeFilter) {
                    $q->where('employment_type', $employmentTypeFilter);
                }
            });
        }

        $pendingOt = $otQuery->get();

        $otTotal = $pendingOt->count();
        $otTotalHours = (float) $pendingOt->sum('hours');

        foreach ($pendingOt as $entry) {
            $user = $entry->user;
            $userId = $entry->user_id;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            if ($userId) {
                if (!isset($perEmployee[$userId])) {
                    $perEmployee[$userId] = [
                        'employee_name' => $employeeName,
                        'pending_leave' => 0,
                        'pending_overtime' => 0,
                        'pending_cash_advance' => 0,
                        'pending_payroll' => 0,
                        'pending_amount' => 0.0,
                    ];
                }
                $perEmployee[$userId]['pending_overtime']++;
            }

            $requestedAt = $entry->created_at ?: $entry->date ?: $start;
            $ageDays = $requestedAt ? $requestedAt->diffInDays($now) : 0;

            $oldestEntries[] = [
                'type' => 'Overtime',
                'employee_name' => $employeeName,
                'requested_at' => $requestedAt,
                'age_days' => $ageDays,
                'stage' => 'Waiting for approval',
            ];
        }

        // Pending cash advance approvals (by stage)
        $caQuery = CashAdvanceRequest::select([
            'id',
            'user_id',
            'status',
            'amount',
            'created_at',
        ])->with(['user:id,full_name,username,employment_type'])
            ->whereIn('status', ['Pending', 'Supervisor approved', 'Manager approved', 'HR approved'])
            ->whereDate('created_at', '>=', $start->toDateString())
            ->whereDate('created_at', '<=', $end->toDateString());

        if ($employeeIdFilter) {
            $caQuery->where('user_id', $employeeIdFilter);
        }

        if ($roleKey === 'supervisor') {
            if ($crewWorkerIds && $crewWorkerIds->isNotEmpty()) {
                $caQuery->whereIn('user_id', $crewWorkerIds);
            } else {
                $caQuery->whereRaw('1 = 0');
            }
        } else {
            $caQuery->whereHas('user', function ($q) use ($employmentTypeFilter) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                if ($employmentTypeFilter) {
                    $q->where('employment_type', $employmentTypeFilter);
                }
            });
        }

        $pendingCa = $caQuery->get();

        $caTotal = $pendingCa->count();
        $caPending = 0;
        $caSupervisorApproved = 0;
        $caManagerApproved = 0;
        $caHrApproved = 0;
        $caTotalAmount = 0.0;
        $caPartTimeRequests = 0;
        $caPartTimeAmount = 0.0;

        foreach ($pendingCa as $req) {
            $user = $req->user;
            $userId = $req->user_id;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            $amount = (float) $req->amount;
            $caTotalAmount += $amount;

            if ($user && ($user->employment_type ?? null) === User::EMPLOYMENT_TYPE_PART_TIME) {
                $caPartTimeRequests++;
                $caPartTimeAmount += $amount;
            }

            $status = $req->status ?? 'Pending';
            $stageLabel = 'Pending – waiting for Supervisor';

            if ($status === 'Supervisor approved') {
                $caSupervisorApproved++;
                $stageLabel = 'Approved by Supervisor – waiting for Manager';
            } elseif ($status === 'Manager approved') {
                $caManagerApproved++;
                $stageLabel = 'Approved by Manager – waiting for HR';
            } elseif ($status === 'HR approved') {
                $caHrApproved++;
                $stageLabel = 'Approved by HR – waiting for release';
            } else {
                $caPending++;
            }

            if ($userId) {
                if (!isset($perEmployee[$userId])) {
                    $perEmployee[$userId] = [
                        'employee_name' => $employeeName,
                        'pending_leave' => 0,
                        'pending_overtime' => 0,
                        'pending_cash_advance' => 0,
                        'pending_payroll' => 0,
                        'pending_amount' => 0.0,
                    ];
                }
                $perEmployee[$userId]['pending_cash_advance']++;
                $perEmployee[$userId]['pending_amount'] += $amount;
            }

            $requestedAt = $req->created_at ?: $start;
            $ageDays = $requestedAt ? $requestedAt->diffInDays($now) : 0;

            $oldestEntries[] = [
                'type' => 'Cash advance',
                'employee_name' => $employeeName,
                'requested_at' => $requestedAt,
                'age_days' => $ageDays,
                'stage' => $stageLabel,
            ];
        }

        // Pending payroll approvals
        $payrollQuery = Payroll::select([
            'id',
            'user_id',
            'status',
            'period_end',
            'net_pay',
            'admin_approved_at',
            'hr_approved_at',
            'created_at',
        ])->with(['user:id,full_name,username,employment_type'])
            ->where('status', 'Pending')
            ->whereNotNull('period_end')
            ->whereBetween('period_end', [$start->toDateString(), $end->toDateString()]);

        if ($employeeIdFilter) {
            $payrollQuery->where('user_id', $employeeIdFilter);
        }

        if ($roleKey === 'supervisor') {
            if ($crewWorkerIds && $crewWorkerIds->isNotEmpty()) {
                $payrollQuery->whereIn('user_id', $crewWorkerIds);
            } else {
                $payrollQuery->whereRaw('1 = 0');
            }
        } else {
            $payrollQuery->whereHas('user', function ($q) use ($employmentTypeFilter) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                if ($employmentTypeFilter) {
                    $q->where('employment_type', $employmentTypeFilter);
                }
            });
        }

        $pendingPayrolls = $payrollQuery->get();

        $payrollTotal = $pendingPayrolls->count();
        $payrollWaitingAdmin = 0;
        $payrollWaitingHr = 0;
        $payrollPendingNet = 0.0;

        foreach ($pendingPayrolls as $payroll) {
            $user = $payroll->user;
            $userId = $payroll->user_id;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            $net = (float) ($payroll->net_pay ?? 0);
            $payrollPendingNet += $net;

            $stageLabel = 'Waiting for Admin';

            if (!$payroll->admin_approved_at) {
                $payrollWaitingAdmin++;
            } elseif ($payroll->admin_approved_at && !$payroll->hr_approved_at) {
                $payrollWaitingHr++;
                $stageLabel = 'Waiting for HR';
            }

            if ($userId) {
                if (!isset($perEmployee[$userId])) {
                    $perEmployee[$userId] = [
                        'employee_name' => $employeeName,
                        'pending_leave' => 0,
                        'pending_overtime' => 0,
                        'pending_cash_advance' => 0,
                        'pending_payroll' => 0,
                        'pending_amount' => 0.0,
                    ];
                }
                $perEmployee[$userId]['pending_payroll']++;
                $perEmployee[$userId]['pending_amount'] += $net;
            }

            $requestedAt = $payroll->period_end ?: $payroll->created_at ?: $start;
            $ageDays = $requestedAt ? $requestedAt->diffInDays($now) : 0;

            $oldestEntries[] = [
                'type' => 'Payroll',
                'employee_name' => $employeeName,
                'requested_at' => $requestedAt,
                'age_days' => $ageDays,
                'stage' => $stageLabel,
            ];
        }

        usort($oldestEntries, function (array $a, array $b) {
            $aAge = (int) ($a['age_days'] ?? 0);
            $bAge = (int) ($b['age_days'] ?? 0);

            if ($aAge === $bAge) {
                return 0;
            }

            return $aAge < $bAge ? 1 : -1;
        });

        $oldestTable = [];

        foreach (array_slice($oldestEntries, 0, 10) as $entry) {
            $requestedAt = $entry['requested_at'];
            $requestedText = $requestedAt instanceof Carbon ? $requestedAt->format('Y-m-d') : 'N/A';
            $ageText = (int) ($entry['age_days'] ?? 0) . ' days';

            $oldestTable[] = [
                (string) ($entry['type'] ?? ''),
                (string) ($entry['employee_name'] ?? ''),
                $requestedText,
                $ageText,
                (string) ($entry['stage'] ?? ''),
            ];
        }

        $perEmployeeRows = array_values($perEmployee);

        usort($perEmployeeRows, function (array $a, array $b) {
            $aAmount = (float) ($a['pending_amount'] ?? 0.0);
            $bAmount = (float) ($b['pending_amount'] ?? 0.0);

            if (abs($aAmount - $bAmount) > 0.0001) {
                return $aAmount < $bAmount ? 1 : -1;
            }

            $aTotal = (int) ($a['pending_leave'] + $a['pending_overtime'] + $a['pending_cash_advance'] + $a['pending_payroll']);
            $bTotal = (int) ($b['pending_leave'] + $b['pending_overtime'] + $b['pending_cash_advance'] + $b['pending_payroll']);

            if ($aTotal === $bTotal) {
                return strcmp((string) $a['employee_name'], (string) $b['employee_name']);
            }

            return $aTotal < $bTotal ? 1 : -1;
        });

        $byEmployeeTable = [];

        foreach (array_slice($perEmployeeRows, 0, 10) as $row) {
            $empType = $row['employment_type'] ?? null;
            $empTypeLabel = null;
            if ($empType === \App\Models\User::EMPLOYMENT_TYPE_PART_TIME) {
                $empTypeLabel = 'Part-time';
            } elseif ($empType) {
                $empTypeLabel = 'Regular';
            }

            $nameWithType = $row['employee_name'] ?? '';
            if ($empTypeLabel) {
                $nameWithType .= ' (' . $empTypeLabel . ')';
            }

            $byEmployeeTable[] = [
                (string) $nameWithType,
                (int) ($row['pending_leave'] ?? 0),
                (int) ($row['pending_overtime'] ?? 0),
                (int) ($row['pending_cash_advance'] ?? 0),
                (int) ($row['pending_payroll'] ?? 0),
                '₱ ' . number_format((float) ($row['pending_amount'] ?? 0.0), 2),
            ];
        }

        // Part-time paid leave anomalies (any paid leave for part-time employees
        // in this period, pending or approved).
        $ptPaidLeaveQuery = LeaveEntry::whereIn('status', ['pending', 'approved'])
            ->where('is_paid', true)
            ->whereDate('date_start', '<=', $end->toDateString())
            ->whereDate('date_end', '>=', $start->toDateString())
            ->whereHas('user', function ($q) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
                    ->where('employment_type', User::EMPLOYMENT_TYPE_PART_TIME);
            });

        if ($employeeIdFilter) {
            $ptPaidLeaveQuery->where('user_id', $employeeIdFilter);
        }

        if ($roleKey === 'supervisor') {
            if ($crewWorkerIds && $crewWorkerIds->isNotEmpty()) {
                $ptPaidLeaveQuery->whereIn('user_id', $crewWorkerIds);
            } else {
                $ptPaidLeaveQuery->whereRaw('1 = 0');
            }
        }

        $ptPaidLeaveCount = $ptPaidLeaveQuery->count();

        $periodLabel = $filters['period_start'] . ' to ' . $filters['period_end'];

        return [
            'summary' => [
                'period_label' => $periodLabel,
                'leave' => [
                    'total' => $leaveTotal,
                    'waiting_supervisor' => $leaveWaitingSupervisor,
                    'waiting_manager' => $leaveWaitingManager,
                    'waiting_hr' => $leaveWaitingHr,
                    'part_time_paid_count' => $ptPaidLeaveCount,
                ],
                'overtime' => [
                    'total' => $otTotal,
                    'total_hours' => $otTotalHours,
                ],
                'cash_advance' => [
                    'total' => $caTotal,
                    'pending' => $caPending,
                    'supervisor_approved' => $caSupervisorApproved,
                    'manager_approved' => $caManagerApproved,
                    'hr_approved' => $caHrApproved,
                    'total_amount' => $caTotalAmount,
                    'part_time_requests' => $caPartTimeRequests,
                    'part_time_amount' => $caPartTimeAmount,
                ],
                'payroll' => [
                    'total' => $payrollTotal,
                    'waiting_admin' => $payrollWaitingAdmin,
                    'waiting_hr' => $payrollWaitingHr,
                    'pending_net_total' => $payrollPendingNet,
                ],
            ],
            'oldestTable' => $oldestTable,
            'byEmployeeTable' => $byEmployeeTable,
        ];
    }

    private function buildAttendanceAnalytics(User $currentUser, string $roleKey, array $filters): array
    {
        $start = Carbon::parse($filters['period_start'])->startOfDay();
        $end = Carbon::parse($filters['period_end'])->endOfDay();

        $query = Attendance::select([
            'id',
            'user_id',
            'date',
            'time_in',
            'time_out',
            'status',
            'total_hours',
            'overtime_hours',
        ])->with(['user:id,full_name,username,employment_type,deleted_at,role']);
        $employmentTypeFilter = !empty($filters['employment_type']) ? (string) $filters['employment_type'] : null;

        if ($roleKey === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');

            if ($crewWorkerIds->isNotEmpty()) {
                $query->whereIn('user_id', $crewWorkerIds);

                if ($employmentTypeFilter) {
                    $query->whereHas('user', function ($q) use ($employmentTypeFilter) {
                        $q->where('employment_type', $employmentTypeFilter);
                    });
                }
            } else {
                // If supervisor has no crew assignments yet, mirror the attendance index:
                // include all non-admin employees instead of returning empty analytics.
                $query->whereHas('user', function ($q) use ($employmentTypeFilter) {
                    $q->whereNull('deleted_at')
                        ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                    if ($employmentTypeFilter) {
                        $q->where('employment_type', $employmentTypeFilter);
                    }
                });
            }
        } else {
            $query->whereHas('user', function ($q) use ($employmentTypeFilter) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                if ($employmentTypeFilter) {
                    $q->where('employment_type', $employmentTypeFilter);
                }
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
        $awolDays = $attendances->where('status', 'AWOL')->count();
        $absentDays = $attendances->whereIn('status', ['Absent', 'AWOL'])->count();
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

            if ($status === 'AWOL' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                $anomalies[] = 'AWOL but has recorded time/hours on ' . $dateLabel;
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

        $employmentTypeSummary = [
            'regular' => [
                'records' => 0,
                'worked_days' => 0,
                'absent_days' => 0,
                'awol_days' => 0,
                'leave_days' => 0,
            ],
            'part_time' => [
                'records' => 0,
                'worked_days' => 0,
                'absent_days' => 0,
                'awol_days' => 0,
                'leave_days' => 0,
            ],
        ];

        foreach ($attendances as $attendance) {
            $user = $attendance->user;
            if (!$user) {
                continue;
            }

            $employmentType = $user->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;
            if ($employmentType === User::EMPLOYMENT_TYPE_PART_TIME) {
                $typeKey = 'part_time';
            } else {
                $typeKey = 'regular';
            }

            $employmentTypeSummary[$typeKey]['records']++;

            $status = $attendance->status ?? 'Present';

            if (in_array($status, ['Present', 'Late'], true)) {
                $employmentTypeSummary[$typeKey]['worked_days']++;
            }

            if (in_array($status, ['Absent', 'AWOL'], true)) {
                $employmentTypeSummary[$typeKey]['absent_days']++;
            }

            if ($status === 'AWOL') {
                $employmentTypeSummary[$typeKey]['awol_days']++;
            }

            if ($status === 'On leave') {
                $employmentTypeSummary[$typeKey]['leave_days']++;
            }
        }

        $leaveUsage = [
            'paid_days' => 0.0,
            'unpaid_days' => 0.0,
            'employment_type' => [
                'regular' => [
                    'paid_days' => 0.0,
                    'unpaid_days' => 0.0,
                ],
                'part_time' => [
                    'paid_days' => 0.0,
                    'unpaid_days' => 0.0,
                ],
            ],
        ];

        $leaveQuery = LeaveEntry::select([
            'id',
            'user_id',
            'status',
            'date_start',
            'date_end',
            'duration_days',
            'is_paid',
        ])->with(['user:id,employment_type,deleted_at,role'])
            ->where('status', 'approved')
            ->whereDate('date_start', '>=', $start->toDateString())
            ->whereDate('date_end', '<=', $end->toDateString());

        if (!empty($filters['employee_id'])) {
            $leaveQuery->where('user_id', (int) $filters['employee_id']);
        }

        if ($roleKey === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');

            if ($crewWorkerIds->isNotEmpty()) {
                $leaveQuery->whereIn('user_id', $crewWorkerIds);
            } else {
                $leaveQuery->whereHas('user', function ($q) use ($employmentTypeFilter) {
                    $q->whereNull('deleted_at')
                        ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                    if ($employmentTypeFilter) {
                        $q->where('employment_type', $employmentTypeFilter);
                    }
                });
            }
        } else {
            $leaveQuery->whereHas('user', function ($q) use ($employmentTypeFilter) {
                $q->whereNull('deleted_at')
                    ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

                if ($employmentTypeFilter) {
                    $q->where('employment_type', $employmentTypeFilter);
                }
            });
        }

        if (!empty($filters['employment_type'])) {
            $type = (string) $filters['employment_type'];
            $leaveQuery->whereHas('user', function ($q) use ($type) {
                $q->where('employment_type', $type);
            });
        }

        $leaveEntries = $leaveQuery->get();

        foreach ($leaveEntries as $entry) {
            $user = $entry->user;
            if (!$user) {
                continue;
            }

            $days = (float) ($entry->duration_days ?? 0);
            if ($days <= 0) {
                continue;
            }

            $employmentType = $user->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;
            $typeKey = $employmentType === User::EMPLOYMENT_TYPE_PART_TIME ? 'part_time' : 'regular';

            $isPaid = (bool) $entry->is_paid;
            if ($user->isPartTime() && $isPaid) {
                $isPaid = false;
            }

            if ($isPaid) {
                $leaveUsage['paid_days'] += $days;
                $leaveUsage['employment_type'][$typeKey]['paid_days'] += $days;
            } else {
                $leaveUsage['unpaid_days'] += $days;
                $leaveUsage['employment_type'][$typeKey]['unpaid_days'] += $days;
            }
        }

        $periodLabel = $filters['period_start'] . ' to ' . $filters['period_end'];

        $summary = [
            'total_hours' => $totalHours,
            'total_overtime' => $totalOvertime,
            'attendance_rate' => $attendanceRate,
            'records' => $totalRecords,
            'worked_days' => $workedDays,
            'absent_days' => $absentDays,
            'awol_days' => $awolDays,
            'leave_days' => $leaveDays,
            'employee_count' => $employeeCount,
            'anomaly_count' => $anomalyCount,
            'period_label' => $periodLabel,
            'employment_type' => $employmentTypeSummary,
            'leave_usage' => $leaveUsage,
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
            $awolDaysEmp = $records->where('status', 'AWOL')->count();
            $absentDaysEmp = $records->whereIn('status', ['Absent', 'AWOL'])->count();
            $leaveDaysEmp = $records->where('status', 'On leave')->count();

            $totalHoursEmp = (float) $records->sum('total_hours');
            $overtimeHoursEmp = (float) $records->sum('overtime_hours');

            $employeeSummary[] = [
                'employee_name' => $employeeName,
                'present_days' => $presentDaysEmp,
                'late_days' => $lateDaysEmp,
                'absent_days' => $absentDaysEmp,
                'awol_days' => $awolDaysEmp,
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
                $row['awol_days'],
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

        $query = Payroll::select([
            'id',
            'user_id',
            'period_end',
            'gross_pay',
            'total_deductions',
            'net_pay',
            'status',
            'created_at',
        ])->with(['user:id,full_name,username,employment_type'])
            ->whereNotNull('period_end')
            ->whereBetween('period_end', [$start->toDateString(), $end->toDateString()]);

        if (!empty($filters['employee_id'])) {
            $query->where('user_id', (int) $filters['employee_id']);
        }

        $payrolls = $query->get();

        $totalGross = (float) $payrolls->sum('gross_pay');
        $totalDeductions = (float) $payrolls->sum('total_deductions');
        $totalNet = (float) $payrolls->sum('net_pay');

        // Track how much net pay flows to each employment type for this
        // period. This supports analytics and policy checks around CA
        // exposure and benefits by employment type.
        $netByType = [
            'regular' => 0.0,
            'part_time' => 0.0,
            'unknown' => 0.0,
        ];

        foreach ($payrolls as $payroll) {
            $user = $payroll->user;
            $typeKey = 'unknown';

            if ($user) {
                $employmentType = $user->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;
                if ($employmentType === User::EMPLOYMENT_TYPE_PART_TIME) {
                    $typeKey = 'part_time';
                } else {
                    $typeKey = 'regular';
                }
            }

            $netByType[$typeKey] += (float) ($payroll->net_pay ?? 0);
        }

        $employeeIds = $payrolls->pluck('user_id')->filter()->unique();
        $employeeCount = $employeeIds->count();

        $employeeTypes = [];
        foreach ($payrolls as $payroll) {
            $user = $payroll->user;
            if ($user && $user->id) {
                $employeeTypes[$user->id] = $user->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;
            }
        }

        $regularEmployees = 0;
        $partTimeEmployees = 0;
        foreach ($employeeTypes as $type) {
            if ($type === User::EMPLOYMENT_TYPE_PART_TIME) {
                $partTimeEmployees++;
            } else {
                $regularEmployees++;
            }
        }
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
            'employment_type' => [
                'regular' => [
                    'headcount' => $regularEmployees,
                    'total_net' => $netByType['regular'],
                ],
                'part_time' => [
                    'headcount' => $partTimeEmployees,
                    'total_net' => $netByType['part_time'],
                ],
            ],
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
            ->with(['user:id,full_name,username,employment_type']);

        if (!empty($filters['employee_id'])) {
            $balanceQuery->where('user_id', (int) $filters['employee_id']);
        }

        if (!empty($filters['employment_type'])) {
            $type = (string) $filters['employment_type'];
            $balanceQuery->whereHas('user', function ($q) use ($type) {
                $q->where('employment_type', $type);
            });
        }

        $balanceRows = $balanceQuery->get();

        $cashAdvanceExposure = [
            'total_outstanding' => 0.0,
            'employee_count_with_balance' => 0,
            'employment_type' => [
                'regular' => [
                    'headcount' => 0,
                    'total_outstanding' => 0.0,
                ],
                'part_time' => [
                    'headcount' => 0,
                    'total_outstanding' => 0.0,
                ],
            ],
        ];

        $cashAdvanceTableData = $balanceRows->map(function (CashAdvance $row) use (&$cashAdvanceExposure) {
            $user = $row->user;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';
            $employmentType = $user && $user->employment_type
                ? $user->employment_type
                : User::EMPLOYMENT_TYPE_REGULAR;
            $employmentTypeLabel = $employmentType === User::EMPLOYMENT_TYPE_PART_TIME ? 'Part-time' : 'Regular';

            $typeKey = $employmentType === User::EMPLOYMENT_TYPE_PART_TIME ? 'part_time' : 'regular';

            $totalAdvances = (float) ($row->total_advances ?? 0);
            $totalRepayments = (float) ($row->total_repayments ?? 0);
            $outstanding = max(0, $totalAdvances - $totalRepayments);

            if ($outstanding > 0 && isset($cashAdvanceExposure['employment_type'][$typeKey])) {
                $cashAdvanceExposure['total_outstanding'] += $outstanding;
                $cashAdvanceExposure['employee_count_with_balance']++;
                $cashAdvanceExposure['employment_type'][$typeKey]['headcount']++;
                $cashAdvanceExposure['employment_type'][$typeKey]['total_outstanding'] += $outstanding;
            }

            $employeeLabel = $employeeName . ' (' . $employmentTypeLabel . ')';

            return [
                $employeeLabel,
                '₱ ' . number_format($totalAdvances, 2),
                '₱ ' . number_format($totalRepayments, 2),
                '₱ ' . number_format($outstanding, 2),
            ];
        })->toArray();

        $summary['cash_advance'] = $cashAdvanceExposure;

        return [
            'summary' => $summary,
            'chart' => $chart,
            'topNetPayTable' => $topNetPayTable,
            'cashAdvanceTableData' => $cashAdvanceTableData,
        ];
    }
}
