<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CrewAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
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
            'records.*.status' => 'nullable|in:Present,Absent,Late,On leave',
            'records.*.attendance_id' => 'nullable|integer',
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
            'period_end' => 'required|date|after_or_equal:period_start',
            'employee_id' => 'nullable|exists:users,id',
        ]);
        try {
            $this->attendanceService->generateDefaultAttendance($validated);

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
            'status' => 'nullable|in:Present,Absent,Late,On leave',
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        $calculated = $this->attendanceService->calculateAttendanceMetrics(
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

        $redirectParams = $request->query();

        return redirect()->route('attendance', $redirectParams)
            ->with('success', 'Attendance record added successfully.');
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

        $calculated = $this->attendanceService->calculateAttendanceMetrics(
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

        $redirectParams = $request->query();

        return redirect()->route('attendance', $redirectParams)
            ->with('success', 'Attendance record updated successfully.');
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
