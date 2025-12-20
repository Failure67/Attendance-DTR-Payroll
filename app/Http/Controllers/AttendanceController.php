<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CrewAssignment;
use App\Models\LeaveEntry;
use App\Models\OvertimeEntry;
use App\Models\Payroll;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendanceService)
    {
    }

    public function viewAttendance(Request $request)
    {
        $data = $this->attendanceService->getIndexData($request->query());

        return view('pages.attendance', array_merge([
            'title' => 'Attendance',
            'pageClass' => 'attendance',
        ], $data));
    }

    public function viewAttendanceBulk(Request $request)
    {
        $currentUser = auth()->user();

        $data = $this->attendanceService->getBulkViewData($currentUser, $request->query());

        return view('pages.attendance-bulk', array_merge([
            'title' => 'Bulk attendance',
            'pageClass' => 'attendance-bulk',
        ], $data));
    }

    public function storeAttendanceBulk(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'records' => 'required|array',
            'records.*.user_id' => 'required|exists:users,id',
            'records.*.time_in' => 'nullable|date_format:H:i',
            'records.*.time_out' => 'nullable|date_format:H:i',
            'records.*.status' => 'nullable|in:Present,Absent,Late,On leave,AWOL',
            'records.*.attendance_id' => 'nullable|integer',
            'records.*.include' => 'nullable|in:0,1',
        ]);
        $currentUser = auth()->user();

        try {
            $dateString = $this->attendanceService->storeAttendanceBulk($currentUser, $validated);

            return redirect()->route('attendance.bulk', ['date' => $dateString])
                ->with('success', 'Bulk attendance saved successfully.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An error occurred while saving bulk attendance: ' . $e->getMessage()]);
        }
    }

    public function viewAttendanceDaily(Request $request)
    {
        $currentUser = auth()->user();

        $data = $this->attendanceService->getDailyViewData($currentUser, $request->query());

        return view('pages.attendance-daily', array_merge([
            'title' => 'Daily attendance sheet',
            'pageClass' => 'attendance-daily',
        ], $data));
    }

    public function generateDefaultAttendance(Request $request)
    {
        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'employee_id' => 'nullable|exists:users,id',
        ]);
        $currentUser = auth()->user();
        
        if (empty($validated['period_end'])) {
            $validated['period_end'] = $validated['period_start'];
        }
        try {
            $this->attendanceService->generateDefaultAttendance($currentUser, $validated);

            return redirect()->route('attendance', [
                    'period_start' => $validated['period_start'],
                    'period_end' => $validated['period_end'],
                    'employee_id' => $validated['employee_id'] ?? null,
                ])
                ->with('success', 'Default attendance generated for selected period.');
        } catch (\Exception $e) {
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
            'status' => 'nullable|in:Present,Absent,Late,On leave,AWOL',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $currentUser = auth()->user();
        $targetUser = User::findOrFail($validated['user_id']);

        if (!$currentUser || !$this->canEditAttendanceFor($currentUser, $targetUser)) {
            abort(403);
        }

        $calculated = $this->attendanceService->calculateAttendanceMetrics(
            $date,
            $validated['time_in'] ?? null,
            $validated['time_out'] ?? null,
            $validated['status'] ?? null
        );

        $dateString = $date->toDateString();

        $linkedLeave = LeaveEntry::where('user_id', $validated['user_id'])
            ->where('status', 'approved')
            ->whereDate('date_start', '<=', $dateString)
            ->whereDate('date_end', '>=', $dateString)
            ->first();

        if ($linkedLeave) {
            $newStatus = $calculated['status'];
            $leaveApproved = $request->boolean('leave_approved') && ($newStatus === 'On leave');

            if ($newStatus !== 'On leave' || !$leaveApproved) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'status' => 'This attendance date is covered by an approved leave request from ' . $linkedLeave->date_start->toDateString() . ' to ' . $linkedLeave->date_end->toDateString() . '. Edit or cancel the leave request instead of changing attendance directly.',
                    ]);
            }
        }

        $overtimeApproved = $request->boolean('overtime_approved') && $calculated['overtime_hours'] > 0;
        $leaveApproved = $request->boolean('leave_approved') && ($calculated['status'] === 'On leave');

        if ($overtimeApproved || $leaveApproved) {
            // Enforce global attendance approval policy (no Superadmin, no self-approval).
            $tempAttendance = new Attendance([
                'user_id' => $validated['user_id'],
            ]);

            $this->authorize('approve', $tempAttendance);
        }

        $attendance = Attendance::create([
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

        if ($attendance->status === 'AWOL') {
            $this->logApproval('attendance', $attendance->id, 'status_awol_set', [
                'date' => $attendance->date ? $attendance->date->toDateString() : $date->toDateString(),
                'previous_status' => null,
                'new_status' => 'AWOL',
            ]);
        }

        if ($overtimeApproved) {
            $multiplier = (float) config('payroll.overtime_multiplier', 1.30);
            $currentUser = auth()->user();

            OvertimeEntry::updateOrCreate(
                ['attendance_id' => $attendance->id],
                [
                    'user_id' => $attendance->user_id,
                    'date' => $attendance->date ? $attendance->date->toDateString() : $date->toDateString(),
                    'hours' => $attendance->overtime_hours,
                    'premium_multiplier' => $multiplier,
                    'status' => 'approved',
                    'requested_by_id' => $attendance->user_id,
                    'approved_by_id' => $currentUser ? $currentUser->id : null,
                    'approved_at' => now(),
                ]
            );

            $this->logApproval('attendance', $attendance->id, 'overtime_approved', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
                'overtime_hours' => (float) $attendance->overtime_hours,
            ]);
        }

        if ($leaveApproved) {
            $this->logApproval('attendance', $attendance->id, 'leave_approved', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
            ]);
        }

        $redirectParams = $request->query();

        return redirect()->route('attendance', $redirectParams)
            ->with('success', 'Attendance record added successfully.');
    }

    public function updateAttendance(Request $request, $id)
    {
        $attendance = Attendance::findOrFail($id);

        $previousStatus = (string) $attendance->status;

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'status' => 'nullable|in:Present,Absent,Late,On leave,AWOL',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $currentUser = auth()->user();
        $targetUser = User::findOrFail($validated['user_id']);

        if (!$currentUser || !$this->canEditAttendanceFor($currentUser, $targetUser)) {
            abort(403);
        }

        $calculated = $this->attendanceService->calculateAttendanceMetrics(
            $date,
            $validated['time_in'] ?? null,
            $validated['time_out'] ?? null,
            $validated['status'] ?? $attendance->status
        );

        $dateString = $date->toDateString();
        $newStatus = $calculated['status'];

        $linkedLeave = LeaveEntry::where('user_id', $attendance->user_id)
            ->where('status', 'approved')
            ->whereDate('date_start', '<=', $dateString)
            ->whereDate('date_end', '>=', $dateString)
            ->first();

        if ($linkedLeave) {
            $newLeaveApproved = $request->boolean('leave_approved') && ($newStatus === 'On leave');

            if ($newStatus !== 'On leave' || !$newLeaveApproved) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'status' => 'This attendance record is linked to an approved leave request from ' . $linkedLeave->date_start->toDateString() . ' to ' . $linkedLeave->date_end->toDateString() . '. Edit or cancel the leave request instead of changing this attendance directly.',
                    ]);
            }
        }

        $overtimeApproved = $request->boolean('overtime_approved') && $calculated['overtime_hours'] > 0;
        $leaveApproved = $request->boolean('leave_approved') && ($calculated['status'] === 'On leave');
        $previousOvertimeApproved = (bool) $attendance->overtime_approved;
        $previousLeaveApproved = (bool) $attendance->leave_approved;

        if ($overtimeApproved !== $previousOvertimeApproved) {
            $dateString = $date->toDateString();

            $hasReleasedPayroll = Payroll::where('user_id', $attendance->user_id)
                ->where('status', 'Released')
                ->whereDate('period_start', '<=', $dateString)
                ->whereDate('period_end', '>=', $dateString)
                ->exists();

            if ($hasReleasedPayroll) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'overtime_approved' => 'Overtime approval cannot be changed because a released payroll already exists for this date.',
                    ]);
            }
        }

        if ($overtimeApproved || $leaveApproved) {
            // Enforce global attendance approval policy (no Superadmin, no self-approval).
            $this->authorize('approve', $attendance);
        }

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

        $attendance->refresh();

        $newStatusAfterUpdate = (string) $attendance->status;

        if ($previousStatus !== 'AWOL' && $newStatusAfterUpdate === 'AWOL') {
            $this->logApproval('attendance', $attendance->id, 'status_awol_set', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
                'previous_status' => $previousStatus,
                'new_status' => $newStatusAfterUpdate,
            ]);
        } elseif ($previousStatus === 'AWOL' && $newStatusAfterUpdate !== 'AWOL') {
            $this->logApproval('attendance', $attendance->id, 'status_awol_cleared', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
                'previous_status' => $previousStatus,
                'new_status' => $newStatusAfterUpdate,
            ]);
        }

        if (!$previousOvertimeApproved && $attendance->overtime_approved) {
            $this->logApproval('attendance', $attendance->id, 'overtime_approved', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
                'overtime_hours' => (float) $attendance->overtime_hours,
            ]);
        } elseif ($previousOvertimeApproved && !$attendance->overtime_approved) {
            $this->logApproval('attendance', $attendance->id, 'overtime_unapproved', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
            ]);
        }

        $currentUser = auth()->user();
        $multiplier = (float) config('payroll.overtime_multiplier', 1.30);

        if ($attendance->overtime_approved) {
            OvertimeEntry::updateOrCreate(
                ['attendance_id' => $attendance->id],
                [
                    'user_id' => $attendance->user_id,
                    'date' => $attendance->date ? $attendance->date->toDateString() : $date->toDateString(),
                    'hours' => $attendance->overtime_hours,
                    'premium_multiplier' => $multiplier,
                    'status' => 'approved',
                    'requested_by_id' => $attendance->user_id,
                    'approved_by_id' => $currentUser ? $currentUser->id : null,
                    'approved_at' => now(),
                ]
            );
        } elseif ($previousOvertimeApproved && !$attendance->overtime_approved) {
            $entry = OvertimeEntry::where('attendance_id', $attendance->id)
                ->latest('id')
                ->first();

            if ($entry) {
                $entry->status = 'cancelled';
                $entry->approved_by_id = $currentUser ? $currentUser->id : null;
                $entry->approved_at = now();
                $entry->save();
            }
        }

        if (!$previousLeaveApproved && $attendance->leave_approved) {
            $this->logApproval('attendance', $attendance->id, 'leave_approved', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
            ]);
        } elseif ($previousLeaveApproved && !$attendance->leave_approved) {
            $this->logApproval('attendance', $attendance->id, 'leave_unapproved', [
                'date' => $attendance->date ? $attendance->date->toDateString() : null,
            ]);
        }

        $redirectParams = $request->query();

        return redirect()->route('attendance', $redirectParams)
            ->with('success', 'Attendance record updated successfully.');
    }

    protected function canEditAttendanceFor(User $actor, User $target): bool
    {
        $actorRole = $this->normalizeRole($actor->role ?? '');
        $targetRole = $this->normalizeRole($target->role ?? '');

        // Superadmin can adjust attendance for diagnostics but cannot approve.
        if ($actorRole === 'superadmin') {
            return true;
        }

        // Do not manage attendance for Admin/Superadmin via this workflow.
        if (in_array($targetRole, ['admin', 'superadmin'], true)) {
            return false;
        }

        // Supervisors: only Managers/Admins can record their attendance.
        if ($targetRole === 'supervisor') {
            return in_array($actorRole, ['manager', 'admin'], true);
        }

        // Managers and HR: only Admin can record their attendance.
        if (in_array($targetRole, ['manager', 'hr'], true)) {
            return $actorRole === 'admin';
        }

        // Regular workers and other staff.
        if ($actorRole === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $actor->id)->pluck('worker_id');

            return $crewWorkerIds->contains($target->id);
        }

        return in_array($actorRole, ['manager', 'hr', 'admin'], true);
    }

    protected function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        if ($role === 'project manager') {
            return 'manager';
        }

        return $role;
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
        $exportData = $this->attendanceService->getExportAttendanceData($request->query());

        $attendances = $exportData['attendances'];
        $includeArchivedColumn = $exportData['includeArchivedColumn'];

        $filename = 'attendance_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

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

    public function exportAttendancePdf(Request $request)
    {
        $exportData = $this->attendanceService->getExportAttendanceData($request->query());

        $attendances = $exportData['attendances'];
        $includeArchivedColumn = $exportData['includeArchivedColumn'];
        $periodStart = $exportData['period_start'] ?? null;
        $periodEnd = $exportData['period_end'] ?? null;

        $filters = [
            'employee_id' => $request->query('employee_id'),
            'status' => $request->query('status'),
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'archived' => $request->query('archived'),
            'search' => $request->query('search'),
        ];

        $generatedAt = now();

        $pdf = Pdf::loadView('pdf.attendance-detailed', [
            'title' => 'Attendance Report',
            'attendances' => $attendances,
            'includeArchivedColumn' => $includeArchivedColumn,
            'filters' => $filters,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'landscape');

        $filename = 'attendance_report_' . $generatedAt->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportAttendanceDtr(Request $request)
    {
        $employeeId = $request->query('employee_id');

        if (empty($employeeId)) {
            return redirect()->route('attendance', $request->query())
                ->withErrors(['error' => 'Please select an employee before exporting a DTR report.']);
        }

        $exportData = $this->attendanceService->getExportAttendanceData($request->query());

        /** @var \Illuminate\Support\Collection $attendances */
        $attendances = $exportData['attendances'];
        $periodStart = $exportData['period_start'] ?? null;
        $periodEnd = $exportData['period_end'] ?? null;

        // Restrict to the selected employee and index by date string for easy lookup
        $employeeAttendances = $attendances
            ->where('user_id', (int) $employeeId)
            ->sortBy(function ($attendance) {
                /** @var \App\Models\Attendance $attendance */
                if ($attendance->date) {
                    return $attendance->date->toDateString();
                }

                if ($attendance->time_in) {
                    return $attendance->time_in->toDateString();
                }

                return '';
            });

        $employee = $employeeAttendances->first()?->user ?? User::find($employeeId);

        // Build continuous day rows between period_start and period_end (if provided)
        $dtrRows = [];

        if ($periodStart && $periodEnd) {
            $start = Carbon::parse($periodStart)->startOfDay();
            $end = Carbon::parse($periodEnd)->startOfDay();

            // Group records by date string for quick lookup
            $byDate = $employeeAttendances->groupBy(function ($attendance) {
                /** @var \App\Models\Attendance $attendance */
                if ($attendance->date) {
                    return $attendance->date->toDateString();
                }

                if ($attendance->time_in) {
                    return $attendance->time_in->toDateString();
                }

                return null;
            });

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateKey = $date->toDateString();
                $record = null;

                if ($byDate->has($dateKey)) {
                    // In practice there should only be one record per day; take the first
                    $record = $byDate->get($dateKey)->first();
                }

                $dtrRows[] = [
                    'date' => $date->copy(),
                    'record' => $record,
                ];
            }
        } else {
            // Fallback: just use whatever records exist for the employee in the export set
            foreach ($employeeAttendances as $attendance) {
                /** @var \App\Models\Attendance $attendance */
                $date = $attendance->date
                    ? $attendance->date->copy()
                    : ($attendance->time_in ? $attendance->time_in->copy() : null);

                if ($date === null) {
                    continue;
                }

                $dtrRows[] = [
                    'date' => $date->startOfDay(),
                    'record' => $attendance,
                ];
            }

            // Ensure stable ordering by date
            usort($dtrRows, function ($a, $b) {
                /** @var \Carbon\Carbon $dateA */
                /** @var \Carbon\Carbon $dateB */
                $dateA = $a['date'];
                $dateB = $b['date'];

                if ($dateA->eq($dateB)) {
                    return 0;
                }

                return $dateA->lt($dateB) ? -1 : 1;
            });
        }

        $generatedAt = now();

        $pdf = Pdf::loadView('pdf.attendance-dtr', [
            'title' => 'Daily Time Record',
            'employee' => $employee,
            'dtrRows' => $dtrRows,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'portrait');

        $employeeName = $employee ? ($employee->full_name ?? $employee->username ?? ('employee_' . $employee->id)) : 'employee';
        $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $employeeName);

        $filenameParts = ['dtr', $safeName];
        if ($periodStart) {
            $filenameParts[] = str_replace('-', '', $periodStart);
        }
        if ($periodEnd && $periodEnd !== $periodStart) {
            $filenameParts[] = str_replace('-', '', $periodEnd);
        }

        $filename = implode('_', $filenameParts) . '_' . $generatedAt->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function exportAttendanceSummary(Request $request)
    {
        $summaryRows = $this->attendanceService->getExportSummaryRows($request->query());
        
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
                'Days AWOL',
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

        $normalizedHeader = array_map(function ($value) {
            $value = trim((string) $value);
            $value = strtolower($value);
            $value = str_replace([' ', '-'], '_', $value);
            return $value;
        }, $header);

        $colIndex = [
            'employee_id' => array_search('employee_id', $normalizedHeader),
            'date' => array_search('date', $normalizedHeader),
            'time_in' => array_search('time_in', $normalizedHeader),
            'time_out' => array_search('time_out', $normalizedHeader),
            'status' => array_search('status', $normalizedHeader),
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

                $employeeIdRaw = trim((string) $employeeIdRaw);
                $dateRaw = trim((string) $dateRaw);

                if ($dateRaw !== '') {
                    $dateRaw = ltrim($dateRaw, '\\"');
                }

                if ($employeeIdRaw === '' || $dateRaw === '') {
                    continue;
                }

                $user = User::find((int) $employeeIdRaw);
                if (!$user || $user->deleted_at !== null) {
                    continue;
                }

                try {
                    $date = Carbon::parse($dateRaw)->startOfDay();
                } catch (\Exception $e) {
                    continue;
                }

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

                $timeInStr = $this->attendanceService->normalizeImportTime($timeInStr);
                $timeOutStr = $this->attendanceService->normalizeImportTime($timeOutStr);

                $calculated = $this->attendanceService->calculateAttendanceMetrics($date, $timeInStr, $timeOutStr, $statusStr);

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

}
