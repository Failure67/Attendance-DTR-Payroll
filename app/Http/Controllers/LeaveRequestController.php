<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\ApprovalLog;
use App\Models\CrewAssignment;
use App\Models\LeaveEntry;
use App\Models\Payroll;
use App\Models\User;
use App\Services\Leave\LeaveCreditService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $currentRoleKey = strtolower($currentUser->role ?? '');
        $crewWorkerIds = null;

        if ($currentRoleKey === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');
        }

        $employeeQuery = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if ($currentRoleKey === 'supervisor') {
            if ($crewWorkerIds && $crewWorkerIds->isNotEmpty()) {
                $employeeQuery->whereIn('id', $crewWorkerIds);
            } else {
                // If a supervisor has no crew assignments yet, they should not
                // see any employees in the leave-requests listing.
                $employeeQuery->whereRaw('1 = 0');
            }
        }

        $employees = $employeeQuery
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $status = $request->input('status');
        $employeeId = $request->input('employee_id');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');
        $sortBy = $request->input('sort_by', 'date_start');
        $sortDir = $request->input('sort_dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = LeaveEntry::with('user');

        if ($currentRoleKey === 'supervisor') {
            if ($crewWorkerIds && $crewWorkerIds->isNotEmpty()) {
                $query->whereIn('user_id', $crewWorkerIds);
            } else {
                // Mirror the employee options scoping: supervisors with no crew
                // see no leave entries.
                $query->whereRaw('1 = 0');
            }
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($periodStart)) {
            $query->whereDate('date_start', '>=', $periodStart);
        }

        if (!empty($periodEnd)) {
            $query->whereDate('date_end', '<=', $periodEnd);
        }

        $allowedSorts = ['date_start', 'status', 'created_at'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'date_start';
        }

        switch ($sortBy) {
            case 'status':
                $query
                    ->orderBy('status', $sortDir)
                    ->orderByDesc('date_start')
                    ->orderByDesc('created_at');
                break;
            case 'created_at':
                $query
                    ->orderBy('created_at', $sortDir)
                    ->orderBy('date_start', $sortDir);
                break;
            case 'date_start':
            default:
                $query
                    ->orderBy('date_start', $sortDir)
                    ->orderBy('created_at', $sortDir);
                break;
        }

        $requests = $query->paginate(10)->appends($request->query());

        $approvalLogs = ApprovalLog::with('actor')
            ->where('resource_type', 'leave_entry')
            ->whereIn('resource_id', $requests->pluck('id'))
            ->whereIn('action', ['supervisor_approved', 'manager_approved', 'approved'])
            ->get();

        $logsByEntry = [];
        foreach ($approvalLogs as $log) {
            $logsByEntry[$log->resource_id][$log->action] = $log;
        }

        $tableData = $requests->map(function (LeaveEntry $entry) use ($logsByEntry) {
            $user = $entry->user;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';
            $dateRange = $entry->date_start && $entry->date_end
                ? $entry->date_start->format('Y-m-d') . ' to ' . $entry->date_end->format('Y-m-d')
                : 'N/A';

            $typeLabel = $entry->type ?? 'Leave';
            $duration = (float) ($entry->duration_days ?? 0);
            $paidLabel = $entry->is_paid ? 'Paid' : 'Unpaid';
            $createdAtLabel = $entry->created_at ? $entry->created_at->format('Y-m-d H:i') : '—';
            $reasonText = $entry->reason ?? '';
            [$statusLabel, $statusClass] = $this->getStatusLabelAndClass($entry);
            $statusTooltip = '';

            if (!empty($logsByEntry[$entry->id] ?? [])) {
                $stages = $logsByEntry[$entry->id];
                $tooltipParts = [];

                if ($entry->supervisor_approved_at && isset($stages['supervisor_approved'])) {
                    $log = $stages['supervisor_approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'Supervisor: ' . $actorName . ' on ' . $entry->supervisor_approved_at->format('Y-m-d H:i');
                }

                if ($entry->manager_approved_at && isset($stages['manager_approved'])) {
                    $log = $stages['manager_approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'Manager: ' . $actorName . ' on ' . $entry->manager_approved_at->format('Y-m-d H:i');
                }

                if ($entry->hr_approved_at && isset($stages['approved'])) {
                    $log = $stages['approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'HR: ' . $actorName . ' on ' . $entry->hr_approved_at->format('Y-m-d H:i');
                }

                if (!empty($tooltipParts)) {
                    $statusTooltip = implode(' | ', $tooltipParts);
                }
            }

            if ($statusTooltip !== '') {
                $statusHtml = '<span class="badge rounded-pill ' . $statusClass . '" title="' . e($statusTooltip) . '">' . e($statusLabel) . '</span>';
            } else {
                $statusHtml = '<span class="badge rounded-pill ' . $statusClass . '">' . e($statusLabel) . '</span>';
            }

            $employmentType = $user ? ($user->employment_type ?? \App\Models\User::EMPLOYMENT_TYPE_REGULAR) : null;
            $employmentTypeLabel = $employmentType === \App\Models\User::EMPLOYMENT_TYPE_PART_TIME ? 'Part-time' : 'Regular';

            $employeeCellInner = '<span class="leave-request-row-trigger"'
                . ' data-leave-employee="' . e($employeeName) . '"'
                . ' data-leave-employment-type="' . e($employmentTypeLabel) . '"'
                . ' data-leave-period="' . e($dateRange) . '"'
                . ' data-leave-type="' . e($typeLabel) . '"'
                . ' data-leave-paid="' . e($paidLabel) . '"'
                . ' data-leave-status="' . e($statusLabel) . '"'
                . ' data-leave-requested="' . e($createdAtLabel) . '"'
                . ' data-leave-reason="' . e($reasonText) . '"'
                . ' data-leave-approval="' . e($statusTooltip) . '">'
                . e($employeeName) . '</span>';

            $employeeCell = '<div class="d-flex align-items-center justify-content-between">'
                . $employeeCellInner
                . '<span class="badge bg-light text-dark ms-2" style="font-size:0.7rem;">' . e($employmentTypeLabel) . '</span>'
                . '</div>';

            $actions = $this->buildActionsHtml($entry);

            return [
                $employeeCell,
                e($dateRange),
                e($typeLabel),
                $duration,
                e($paidLabel),
                $statusHtml,
                $actions,
            ];
        })->toArray();

        $statusOptions = [
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
        ];

        return view('pages.leave-requests', [
            'title' => 'Leave requests',
            'pageClass' => 'leave-requests',
            'requests' => $requests,
            'requestTableData' => $tableData,
            'employeeOptions' => $employeeOptions,
            'statusOptions' => $statusOptions,
            'filters' => [
                'status' => $status,
                'employee_id' => $employeeId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
        ]);
    }

    public function myIndex()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $requests = LeaveEntry::where('user_id', $user->id)
            ->orderByDesc('date_start')
            ->orderByDesc('created_at')
            ->paginate(10);

        $yearStart = Carbon::now()->startOfYear();
        $yearEnd = Carbon::now()->endOfYear();

        $usageQuery = LeaveEntry::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('date_start', '>=', $yearStart->toDateString())
            ->whereDate('date_end', '<=', $yearEnd->toDateString());

        $entriesForYear = $usageQuery->get();

        $paidDays = 0.0;
        $unpaidDays = 0.0;

        $forceUnpaid = $user->isPartTime();

        foreach ($entriesForYear as $entry) {
            $days = (float) ($entry->duration_days ?? 0);
            if ($days <= 0) {
                continue;
            }

            $isPaid = (bool) $entry->is_paid;
            if ($forceUnpaid && $isPaid) {
                $isPaid = false;
            }

            if ($isPaid) {
                $paidDays += $days;
            } else {
                $unpaidDays += $days;
            }
        }

        $leaveUsage = [
            'year_label' => $yearStart->format('Y'),
            'paid_days' => $paidDays,
            'unpaid_days' => $unpaidDays,
            'is_part_time' => $user->isPartTime(),
        ];

        $approvalLogs = ApprovalLog::with('actor')
            ->where('resource_type', 'leave_entry')
            ->whereIn('resource_id', $requests->pluck('id'))
            ->whereIn('action', ['supervisor_approved', 'manager_approved', 'approved'])
            ->get();

        $logsByEntry = [];
        foreach ($approvalLogs as $log) {
            $logsByEntry[$log->resource_id][$log->action] = $log;
        }

        $tableData = $requests->map(function (LeaveEntry $entry) use ($logsByEntry) {
            $dateRange = $entry->date_start && $entry->date_end
                ? $entry->date_start->format('Y-m-d') . ' to ' . $entry->date_end->format('Y-m-d')
                : 'N/A';

            $typeLabel = $entry->type ?? 'Leave';
            $duration = (float) ($entry->duration_days ?? 0);
            $paidLabel = $entry->is_paid ? 'Paid' : 'Unpaid';
            [$statusLabel, $statusClass] = $this->getStatusLabelAndClass($entry);

            $statusTooltip = '';

            if (!empty($logsByEntry[$entry->id] ?? [])) {
                $stages = $logsByEntry[$entry->id];
                $tooltipParts = [];

                if ($entry->supervisor_approved_at && isset($stages['supervisor_approved'])) {
                    $log = $stages['supervisor_approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'Supervisor: ' . $actorName . ' on ' . $entry->supervisor_approved_at->format('Y-m-d H:i');
                }

                if ($entry->manager_approved_at && isset($stages['manager_approved'])) {
                    $log = $stages['manager_approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'Manager: ' . $actorName . ' on ' . $entry->manager_approved_at->format('Y-m-d H:i');
                }

                if ($entry->hr_approved_at && isset($stages['approved'])) {
                    $log = $stages['approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'HR: ' . $actorName . ' on ' . $entry->hr_approved_at->format('Y-m-d H:i');
                }

                if (!empty($tooltipParts)) {
                    $statusTooltip = implode(' | ', $tooltipParts);
                }
            }

            if ($statusTooltip !== '') {
                $statusCell = '<span class="badge rounded-pill ' . $statusClass . '" title="' . e($statusTooltip) . '">' . e($statusLabel) . '</span>';
            } else {
                $statusCell = '<span class="badge rounded-pill ' . $statusClass . '">' . e($statusLabel) . '</span>';
            }

            $actions = '';
            if (in_array($entry->status ?? 'pending', ['pending'], true) && !$entry->supervisor_approved_at && !$entry->manager_approved_at && !$entry->hr_approved_at) {
                $csrf = csrf_token();
                $actions = '<form method="POST" action="' . route('my.leave-requests.cancel', ['id' => $entry->id]) . '" style="display:inline-block;" data-confirm="Cancel this leave request?">'
                    . '<input type="hidden" name="_token" value="' . $csrf . '">' 
                    . '<button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>'
                    . '</form>';
            } else {
                $actions = '<span class="text-muted">—</span>';
            }

            return [
                e($dateRange),
                e($typeLabel),
                $duration,
                e($paidLabel),
                $statusCell,
                $actions,
            ];
        })->toArray();

        return view('pages.my-leave-requests', [
            'title' => 'My leave requests',
            'pageClass' => 'my-leave-requests',
            'user' => $user,
            'requests' => $requests,
            'requestTableData' => $tableData,
            'leaveUsage' => $leaveUsage,
        ]);
    }

    public function workerIndex()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $requests = LeaveEntry::where('user_id', $user->id)
            ->orderByDesc('date_start')
            ->orderByDesc('created_at')
            ->paginate(10);

        $yearStart = Carbon::now()->startOfYear();
        $yearEnd = Carbon::now()->endOfYear();

        $usageQuery = LeaveEntry::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('date_start', '>=', $yearStart->toDateString())
            ->whereDate('date_end', '<=', $yearEnd->toDateString());

        $entriesForYear = $usageQuery->get();

        $paidDays = 0.0;
        $unpaidDays = 0.0;

        $forceUnpaid = $user->isPartTime();

        foreach ($entriesForYear as $entry) {
            $days = (float) ($entry->duration_days ?? 0);
            if ($days <= 0) {
                continue;
            }

            $isPaid = (bool) $entry->is_paid;
            if ($forceUnpaid && $isPaid) {
                $isPaid = false;
            }

            if ($isPaid) {
                $paidDays += $days;
            } else {
                $unpaidDays += $days;
            }
        }

        $leaveUsage = [
            'year_label' => $yearStart->format('Y'),
            'paid_days' => $paidDays,
            'unpaid_days' => $unpaidDays,
            'is_part_time' => $user->isPartTime(),
        ];

        $approvalLogs = ApprovalLog::with('actor')
            ->where('resource_type', 'leave_entry')
            ->whereIn('resource_id', $requests->pluck('id'))
            ->whereIn('action', ['supervisor_approved', 'manager_approved', 'approved'])
            ->get();

        $logsByEntry = [];
        foreach ($approvalLogs as $log) {
            $logsByEntry[$log->resource_id][$log->action] = $log;
        }

        $tableData = $requests->map(function (LeaveEntry $entry) use ($logsByEntry) {
            $dateRange = $entry->date_start && $entry->date_end
                ? $entry->date_start->format('Y-m-d') . ' to ' . $entry->date_end->format('Y-m-d')
                : 'N/A';

            $typeLabel = $entry->type ?? 'Leave';
            $duration = (float) ($entry->duration_days ?? 0);
            $paidLabel = $entry->is_paid ? 'Paid' : 'Unpaid';
            [$statusLabel] = $this->getStatusLabelAndClass($entry);

            $statusTooltip = '';

            if (!empty($logsByEntry[$entry->id] ?? [])) {
                $stages = $logsByEntry[$entry->id];
                $tooltipParts = [];

                if ($entry->supervisor_approved_at && isset($stages['supervisor_approved'])) {
                    $log = $stages['supervisor_approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'Supervisor: ' . $actorName . ' on ' . $entry->supervisor_approved_at->format('Y-m-d H:i');
                }

                if ($entry->manager_approved_at && isset($stages['manager_approved'])) {
                    $log = $stages['manager_approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'Manager: ' . $actorName . ' on ' . $entry->manager_approved_at->format('Y-m-d H:i');
                }

                if ($entry->hr_approved_at && isset($stages['approved'])) {
                    $log = $stages['approved'];
                    $actorName = $log->actor ? ($log->actor->full_name ?? $log->actor->username) : 'Unknown user';
                    $tooltipParts[] = 'HR: ' . $actorName . ' on ' . $entry->hr_approved_at->format('Y-m-d H:i');
                }

                if (!empty($tooltipParts)) {
                    $statusTooltip = implode(' | ', $tooltipParts);
                }
            }

            if ($statusTooltip !== '') {
                $statusCell = '<span title="' . e($statusTooltip) . '">' . e($statusLabel) . '</span>';
            } else {
                $statusCell = e($statusLabel);
            }
            $actions = '';
            if (in_array($entry->status ?? 'pending', ['pending'], true) && !$entry->supervisor_approved_at && !$entry->manager_approved_at && !$entry->hr_approved_at) {
                $csrf = csrf_token();
                $actions = '<form method="POST" action="' . route('worker.leave-requests.cancel', ['id' => $entry->id]) . '" style="display:inline-block;" data-confirm="Cancel this leave request?">'
                    . '<input type="hidden" name="_token" value="' . $csrf . '">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>'
                    . '</form>';
            } else {
                $actions = '<span class="text-muted">—</span>';
            }

            return [
                e($dateRange),
                e($typeLabel),
                $duration,
                e($paidLabel),
                $statusCell,
                $actions,
            ];
        })->toArray();

        return view('user.pages.leave-requests', [
            'title' => 'Leave requests',
            'pageClass' => 'employee-leave-requests',
            'user' => $user,
            'requests' => $requests,
            'requestTableData' => $tableData,
            'leaveUsage' => $leaveUsage,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'is_paid' => 'required|boolean',
            'date_start' => 'required|date',
            'date_end' => 'required|date|after_or_equal:date_start',
            'duration_days' => 'required|numeric|min:0.125',
            'reason' => 'required|string|max:255',
        ]);

        $isPaid = (bool) $validated['is_paid'];

        // Employment-type rule: by default, only regular employees receive
        // paid leave. Part-time employees' leave is treated as unpaid unless
        // explicitly handled as an administrative exception.
        if ($user->isPartTime() && $isPaid) {
            $isPaid = false;
        }

        $dateStart = Carbon::parse($validated['date_start'])->startOfDay();
        $dateEnd = Carbon::parse($validated['date_end'])->startOfDay();

        // Prevent overlapping leave requests for the same employee.
        // We only consider requests that are still relevant in the workflow
        // (pending or approved). Cancelled / rejected are ignored.
        $hasOverlap = LeaveEntry::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('date_start', '<=', $dateEnd->toDateString())
            ->whereDate('date_end', '>=', $dateStart->toDateString())
            ->exists();

        if ($hasOverlap) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'date_start' => 'You already have a pending or approved leave request that overlaps with this period.',
                ]);
        }

        $entry = LeaveEntry::create([
            'user_id' => $user->id,
            'date_start' => $dateStart->toDateString(),
            'date_end' => $dateEnd->toDateString(),
            'duration_days' => (float) $validated['duration_days'],
            'type' => $validated['type'],
            'is_paid' => $isPaid,
            'status' => 'pending',
            'requested_by_id' => $user->id,
            'reason' => $validated['reason'],
        ]);

        $this->logApproval('leave_entry', $entry->id, 'requested', [
            'type' => $entry->type,
            'is_paid' => (bool) $entry->is_paid,
            'date_start' => $entry->date_start ? $entry->date_start->toDateString() : null,
            'date_end' => $entry->date_end ? $entry->date_end->toDateString() : null,
            'duration_days' => (float) $entry->duration_days,
        ]);

        return redirect()->route('worker.leave-requests')
            ->with('success', 'Your leave request has been submitted for approval.');
    }

    public function myStore(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $validated = $request->validate([
            'type' => 'required|string|max:50',
            'is_paid' => 'required|boolean',
            'date_start' => 'required|date',
            'date_end' => 'required|date|after_or_equal:date_start',
            'duration_days' => 'required|numeric|min:0.125',
            'reason' => 'required|string|max:255',
        ]);

        $isPaid = (bool) $validated['is_paid'];
        if ($user->isPartTime() && $isPaid) {
            $isPaid = false;
        }

        $dateStart = Carbon::parse($validated['date_start'])->startOfDay();
        $dateEnd = Carbon::parse($validated['date_end'])->startOfDay();

        $hasOverlap = LeaveEntry::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('date_start', '<=', $dateEnd->toDateString())
            ->whereDate('date_end', '>=', $dateStart->toDateString())
            ->exists();

        if ($hasOverlap) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'date_start' => 'You already have a pending or approved leave request that overlaps with this period.',
                ]);
        }

        $entry = LeaveEntry::create([
            'user_id' => $user->id,
            'date_start' => $dateStart->toDateString(),
            'date_end' => $dateEnd->toDateString(),
            'duration_days' => (float) $validated['duration_days'],
            'type' => $validated['type'],
            'is_paid' => $isPaid,
            'status' => 'pending',
            'requested_by_id' => $user->id,
            'reason' => $validated['reason'],
        ]);

        $this->logApproval('leave_entry', $entry->id, 'requested', [
            'type' => $entry->type,
            'is_paid' => (bool) $entry->is_paid,
            'date_start' => $entry->date_start ? $entry->date_start->toDateString() : null,
            'date_end' => $entry->date_end ? $entry->date_end->toDateString() : null,
            'duration_days' => (float) $entry->duration_days,
        ]);

        return redirect()->route('my.leave-requests')
            ->with('success', 'Your leave request has been submitted for approval.');
    }

    public function cancel($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $entry = LeaveEntry::where('user_id', $user->id)->findOrFail($id);

        if (!in_array($entry->status, ['pending'], true)) {
            return redirect()->back()->with('error', 'Only pending leave requests can be cancelled.');
        }

        if ($entry->supervisor_approved_at || $entry->manager_approved_at || $entry->hr_approved_at) {
            return redirect()->back()->with('error', 'Leave requests that have already entered the approval process cannot be cancelled.');
        }

        $entry->status = 'cancelled';
        $entry->save();

        $this->logApproval('leave_entry', $entry->id, 'cancelled', []);

        return redirect()->back()->with('success', 'Leave request cancelled.');
    }

    public function supervisorApprove($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'Supervisor'], true)) {
            abort(403, 'Only Supervisor/Admin can approve at supervisor stage.');
        }

        $entry = LeaveEntry::findOrFail($id);

        $this->authorize('approve', $entry);

        if ($entry->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending leave requests can be supervisor-approved.');
        }

        if ($entry->supervisor_approved_at) {
            return redirect()->back()->with('error', 'Leave request is already supervisor-approved.');
        }

        $previousStatus = $entry->status;

        $entry->supervisor_approved_at = now();
        $entry->save();

        $this->logApproval('leave_entry', $entry->id, 'supervisor_approved', [
            'from_status' => $previousStatus,
            'to_status' => $entry->status,
        ]);

        return redirect()->back()->with('success', 'Leave request marked as Supervisor approved.');
    }

    public function managerApprove($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'Manager'], true)) {
            abort(403, 'Only Manager/Admin can approve at manager stage.');
        }

        $entry = LeaveEntry::findOrFail($id);

        $this->authorize('approve', $entry);

        if ($entry->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending leave requests can be manager-approved.');
        }

        if (!$entry->supervisor_approved_at) {
            return redirect()->back()->with('error', 'Only supervisor-approved leave requests can be manager-approved.');
        }

        if ($entry->manager_approved_at) {
            return redirect()->back()->with('error', 'Leave request is already manager-approved.');
        }

        $previousStatus = $entry->status;

        $entry->manager_approved_at = now();
        $entry->save();

        $this->logApproval('leave_entry', $entry->id, 'manager_approved', [
            'from_status' => $previousStatus,
            'to_status' => $entry->status,
        ]);

        return redirect()->back()->with('success', 'Leave request marked as Manager approved.');
    }

    public function approve($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'HR'], true)) {
            abort(403, 'Only HR/Admin can approve at HR stage.');
        }

        $entry = LeaveEntry::findOrFail($id);

        $this->authorize('approve', $entry);

        if ($entry->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending leave requests can be approved.');
        }

        if (!$entry->manager_approved_at) {
            return redirect()->back()->with('error', 'Only manager-approved leave requests can be HR-approved.');
        }

        if ($entry->hr_approved_at) {
            return redirect()->back()->with('success', 'Leave request is already approved.');
        }

        // Guard: do not approve leave that falls inside a Released payroll period
        // for this employee. This mirrors the overtime guard on attendance
        // updates and prevents historical payrolls from becoming inconsistent.
        $leaveStart = $entry->date_start ? $entry->date_start->toDateString() : null;
        $leaveEnd = $entry->date_end ? $entry->date_end->toDateString() : null;

        if ($leaveStart && $leaveEnd) {
            $hasReleasedPayroll = Payroll::where('user_id', $entry->user_id)
                ->where('status', 'Released')
                ->whereDate('period_start', '<=', $leaveEnd)
                ->whereDate('period_end', '>=', $leaveStart)
                ->exists();

            if ($hasReleasedPayroll) {
                return redirect()->back()->with('error', 'Leave cannot be approved because a released payroll already covers one or more days in this request.');
            }
        }

        $employee = $entry->user ?: User::find($entry->user_id);
        if (!$employee) {
            return redirect()->back()->with('error', 'Unable to locate employee for this leave request.');
        }

        try {
            DB::transaction(function () use ($entry, $employee, $user) {
                $leaveCreditTxnId = $entry->leave_credit_transaction_id;
                if ($leaveCreditTxnId === null) {
                    $leaveStartDate = $entry->date_start ? $entry->date_start->copy()->startOfDay() : now()->startOfDay();
                    $days = (float) ($entry->duration_days ?? 0);
                    $isPaid = (bool) ($entry->is_paid ?? false);
                    $type = (string) ($entry->type ?? '');

                    $service = app(LeaveCreditService::class);
                    $txn = $service->debitForLeaveApproval(
                        $employee,
                        (int) $user->id,
                        (int) $entry->id,
                        $type,
                        $isPaid,
                        $days,
                        $leaveStartDate
                    );

                    if ($txn) {
                        $leaveCreditTxnId = $txn->id;
                    }
                }

                $previousStatus = $entry->status;

                $entry->status = 'approved';
                $entry->approved_by_id = $user->id;
                $entry->approved_at = now();
                $entry->hr_approved_at = $entry->approved_at;

                if ($leaveCreditTxnId !== null) {
                    $entry->leave_credit_transaction_id = $leaveCreditTxnId;
                }

                $entry->save();

                $this->logApproval('leave_entry', $entry->id, 'approved', [
                    'from_status' => $previousStatus,
                    'to_status' => $entry->status,
                    'leave_credit_transaction_id' => $leaveCreditTxnId,
                ]);

                $this->applyAttendanceOnApproval($entry);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Leave request approved.');
    }

    public function reject(Request $httpRequest, $id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $entry = LeaveEntry::findOrFail($id);

        $this->authorize('approve', $entry);

        if (!in_array($entry->status, ['pending'], true)) {
            return redirect()->back()->with('error', 'Only pending leave requests can be rejected.');
        }

        $reason = (string) $httpRequest->input('rejection_reason', 'Rejected by ' . ($user->role ?? 'system'));

        $previousStatus = $entry->status;
        $entry->status = 'rejected';
        $entry->reason = $reason;
        $entry->save();

        $this->logApproval('leave_entry', $entry->id, 'rejected', [
            'from_status' => $previousStatus,
            'to_status' => $entry->status,
            'reason' => $reason,
        ]);

        return redirect()->back()->with('success', 'Leave request rejected.');
    }

    protected function getStatusLabelAndClass(LeaveEntry $entry): array
    {
        $status = $entry->status ?? 'pending';

        if ($status === 'approved') {
            return ['Approved', 'bg-success-subtle text-success'];
        }

        if ($status === 'rejected') {
            return ['Rejected', 'bg-danger-subtle text-danger'];
        }

        if ($status === 'cancelled') {
            return ['Cancelled', 'bg-secondary-subtle text-secondary'];
        }

        if ($status === 'pending') {
            if ($entry->manager_approved_at) {
                return ['Manager approved', 'bg-success-subtle text-success'];
            }

            if ($entry->supervisor_approved_at) {
                return ['Supervisor approved', 'bg-success-subtle text-success'];
            }

            return ['Pending', 'bg-warning-subtle text-warning'];
        }

        return [ucfirst($status), 'bg-warning-subtle text-warning'];
    }

    protected function applyAttendanceOnApproval(LeaveEntry $entry): void
    {
        $userId = $entry->user_id;
        if (!$userId || !$entry->date_start || !$entry->date_end) {
            return;
        }

        $start = $entry->date_start->copy()->startOfDay();
        $end = $entry->date_end->copy()->startOfDay();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();

            $attendance = Attendance::where('user_id', $userId)
                ->whereDate('date', $dateString)
                ->first();

            if ($attendance) {
                $attendance->update([
                    'status' => 'On leave',
                    'time_in' => null,
                    'time_out' => null,
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                    'leave_approved' => true,
                ]);
            } else {
                Attendance::create([
                    'user_id' => $userId,
                    'date' => $dateString,
                    'time_in' => null,
                    'time_out' => null,
                    'total_hours' => 0,
                    'overtime_hours' => 0,
                    'status' => 'On leave',
                    'overtime_approved' => false,
                    'leave_approved' => true,
                ]);
            }
        }
    }

    protected function buildActionsHtml(LeaveEntry $entry): string
    {
        $user = auth()->user();
        if (!$user) {
            return '';
        }

        $role = $user->role ?? '';
        $csrf = csrf_token();
        $status = $entry->status ?? 'pending';

        $leftParts = [];
        $rightParts = [];

        $hasSupervisorApproval = (bool) $entry->supervisor_approved_at;
        $hasManagerApproval = (bool) $entry->manager_approved_at;
        $hasHrApproval = (bool) $entry->hr_approved_at;

        $canShowReject = false;

        if ($status === 'pending') {
            if (!$hasSupervisorApproval && !$hasManagerApproval && !$hasHrApproval) {
                if (in_array($role, ['Admin', 'Supervisor'], true)) {
                    $leftParts[] = '<form method="POST" action="' . route('leave-requests.supervisor-approve', ['id' => $entry->id]) . '" style="display:inline-block;margin-right:4px;">'
                        . '<input type="hidden" name="_token" value="' . $csrf . '">'
                        . '<button type="submit" class="btn btn-outline-primary btn-sm">Approve</button>'
                        . '</form>';
                    $canShowReject = true;
                }
            } elseif ($hasSupervisorApproval && !$hasManagerApproval && !$hasHrApproval) {
                if (in_array($role, ['Admin', 'Manager'], true)) {
                    $leftParts[] = '<form method="POST" action="' . route('leave-requests.manager-approve', ['id' => $entry->id]) . '" style="display:inline-block;margin-right:4px;">'
                        . '<input type="hidden" name="_token" value="' . $csrf . '">'
                        . '<button type="submit" class="btn btn-outline-primary btn-sm">Approve</button>'
                        . '</form>';
                    $canShowReject = true;
                }
            } elseif ($hasSupervisorApproval && $hasManagerApproval && !$hasHrApproval) {
                if (in_array($role, ['Admin', 'HR'], true)) {
                    $leftParts[] = '<form method="POST" action="' . route('leave-requests.approve', ['id' => $entry->id]) . '" style="display:inline-block;margin-right:4px;">'
                        . '<input type="hidden" name="_token" value="' . $csrf . '">'
                        . '<button type="submit" class="btn btn-outline-primary btn-sm">Approve</button>'
                        . '</form>';
                    $canShowReject = true;
                }
            }

            if ($canShowReject && in_array($role, ['Admin', 'HR', 'Manager', 'Supervisor'], true)) {
                $rightParts[] = '<form method="POST" action="' . route('leave-requests.reject', ['id' => $entry->id]) . '" style="display:inline-block;" data-confirm="Reject this leave request?">'
                    . '<input type="hidden" name="_token" value="' . $csrf . '">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>'
                    . '</form>';
            }
        }

        if (empty($leftParts) && empty($rightParts)) {
            return '<span class="text-muted">No actions</span>';
        }

        $leftHtml = implode('', $leftParts) ?: '';
        $rightHtml = implode('', $rightParts) ?: '';

        if ($leftHtml && !$rightHtml) {
            return '<div class="leave-actions-single d-flex justify-content-center">' . $leftHtml . '</div>';
        }

        if ($rightHtml && !$leftHtml) {
            return '<div class="leave-actions-single d-flex justify-content-center">' . $rightHtml . '</div>';
        }

        return '<div class="leave-actions d-flex align-items-center">'
            . '<div class="leave-actions-left flex-grow-1 d-flex justify-content-center">' . $leftHtml . '</div>'
            . '<div class="leave-actions-right flex-grow-1 d-flex justify-content-center">' . $rightHtml . '</div>'
            . '</div>';
    }
}
