<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRequest;
use App\Models\LeaveEntry;
use App\Models\Payroll;
use App\Models\Announcement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
    public function overview()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $latestPayroll = Payroll::where('user_id', $user->id)
            ->where('status', 'Released')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->first();

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $attendanceBase = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()]);

        // Full-month collection for aggregates
        $monthlyAttendance = (clone $attendanceBase)->get();

        $totalHours = (float) $monthlyAttendance->sum('total_hours');
        $totalOvertime = (float) $monthlyAttendance->sum('overtime_hours');

        $presentDays = $monthlyAttendance->whereIn('status', ['Present', 'Late'])->count();
        $awolDays = $monthlyAttendance->where('status', 'AWOL')->count();
        $leaveDays = $monthlyAttendance->where('status', 'On leave')->count();
        $absentDays = $monthlyAttendance->whereIn('status', ['Absent', 'AWOL'])->count();

        $totalAdvances = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'repayment')
            ->sum('amount');

        $caBalance = max(0, $totalAdvances - $totalRepayments);

        $caConfig = (array) config('payroll.ca', []);
        $caps = (array) ($caConfig['cap'] ?? []);
        $employmentType = $user->employment_type ?? 'regular';
        $typeCap = isset($caps[$employmentType]) ? (float) $caps[$employmentType] : null;

        $salaryBasedLimit = null;
        if ($latestPayroll && $latestPayroll->period_start && $latestPayroll->period_end) {
            $periodStart = $latestPayroll->period_start;
            $periodEnd = $latestPayroll->period_end;
            $daysInPeriod = max(1, $periodStart->diffInDays($periodEnd) + 1);
            $daysPerMonth = (int) config('payroll.days_per_month', 26);

            $netForPeriod = (float) ($latestPayroll->net_pay ?? 0);
            if ($netForPeriod > 0 && $daysInPeriod > 0 && $daysPerMonth > 0) {
                $monthlyApprox = ($netForPeriod / $daysInPeriod) * $daysPerMonth;
                $maxPercent = (float) ($caConfig['max_percent_of_monthly_net'] ?? 0.8);

                if ($monthlyApprox > 0 && $maxPercent > 0) {
                    $salaryBasedLimit = $monthlyApprox * $maxPercent;
                }
            }
        }

        $effectiveLimit = null;
        if ($typeCap !== null && $salaryBasedLimit !== null) {
            $effectiveLimit = min($typeCap, $salaryBasedLimit);
        } elseif ($typeCap !== null) {
            $effectiveLimit = $typeCap;
        } elseif ($salaryBasedLimit !== null) {
            $effectiveLimit = $salaryBasedLimit;
        }

        $allowPartTime = (bool) ($caConfig['allow_part_time'] ?? false);
        if ($employmentType !== 'regular' && !$allowPartTime) {
            $effectiveLimit = null;
            $typeCap = null;
            $salaryBasedLimit = null;
        }

        $caLimit = [
            'type_cap' => $typeCap,
            'salary_based' => $salaryBasedLimit,
            'effective' => $effectiveLimit,
        ];

        $payrollBase = Payroll::where('user_id', $user->id)
            ->where('status', 'Released')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at');

        // Paginated payroll history (5 rows per page) for the Payroll History tab
        $payrolls = (clone $payrollBase)
            ->paginate(5)
            ->appends(['tab' => 'history']);

        // Paginated attendance list for the dashboard (used in the Attendance tab)
        $attendances = (clone $attendanceBase)
            ->orderByDesc('date')
            ->orderByDesc('time_in')
            ->paginate(5)
            ->appends(['tab' => 'attendance']);

        $yearStart = now()->copy()->startOfYear();
        $yearEnd = now()->copy()->endOfYear();

        $leaveEntries = LeaveEntry::where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereDate('date_start', '>=', $yearStart->toDateString())
            ->whereDate('date_end', '<=', $yearEnd->toDateString())
            ->get();

        $leavePaidDays = 0.0;
        $leaveUnpaidDays = 0.0;
        $forceUnpaidLeave = method_exists($user, 'isPartTime') ? $user->isPartTime() : false;

        foreach ($leaveEntries as $entry) {
            $days = (float) ($entry->duration_days ?? 0);
            if ($days <= 0) {
                continue;
            }

            $isPaid = (bool) $entry->is_paid;
            if ($forceUnpaidLeave && $isPaid) {
                $isPaid = false;
            }

            if ($isPaid) {
                $leavePaidDays += $days;
            } else {
                $leaveUnpaidDays += $days;
            }
        }

        $leaveUsage = [
            'year_label' => $yearStart->format('Y'),
            'paid_days' => $leavePaidDays,
            'unpaid_days' => $leaveUnpaidDays,
            'is_part_time' => $forceUnpaidLeave,
        ];

        $pendingLeaveCount = LeaveEntry::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count();

        $pendingCashAdvanceCount = CashAdvanceRequest::where('user_id', $user->id)
            ->whereIn('status', ['Pending', 'Supervisor approved', 'Manager approved', 'HR approved'])
            ->count();

        $today = now()->toDateString();

        $announcements = Announcement::where(function ($q) use ($today) {
                $q->whereNull('starts_at')
                    ->orWhereDate('starts_at', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('ends_at')
                    ->orWhereDate('ends_at', '>=', $today);
            })
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('user.pages.index', [
            'title' => 'Overview',
            'pageClass' => 'employee',
            'user' => $user,
            'latestPayroll' => $latestPayroll,
            'monthHours' => $totalHours,
            'monthOvertime' => $totalOvertime,
            'caBalance' => $caBalance,
            'monthPresentDays' => $presentDays,
            'monthAwolDays' => $awolDays,
            'monthLeaveDays' => $leaveDays,
            'monthAbsentDays' => $absentDays,
            'caLimit' => $caLimit,
            'leaveUsage' => $leaveUsage,
            'pendingLeaveCount' => $pendingLeaveCount,
            'pendingCashAdvanceCount' => $pendingCashAdvanceCount,
            'payrolls' => $payrolls,
            'attendances' => $attendances,
            'announcements' => $announcements,
        ]);
    }

    public function payrollHistory()
    {
        return redirect()->route('worker.dashboard', ['tab' => 'history']);
    }

    public function payslip($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $payroll = Payroll::with(['deductions', 'cashAdvances'])
            ->where('user_id', $user->id)
            ->where('status', 'Released')
            ->findOrFail($id);

        $attendanceSummary = null;

        if ($payroll->period_start && $payroll->period_end) {
            $startDate = $payroll->period_start->toDateString();
            $endDate = $payroll->period_end->toDateString();

            $attendances = Attendance::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $totalHours = (float) $attendances->sum('total_hours');
            $totalOvertime = (float) $attendances->sum('overtime_hours');

            $presentDays = 0;
            $absentDays = 0;
            $leaveDays = 0;

            foreach ($attendances as $attendance) {
                $status = $attendance->status ?? 'Present';
                if (in_array($status, ['Present', 'Late'], true)) {
                    $presentDays++;
                } elseif ($status === 'On leave') {
                    $leaveDays++;
                } elseif ($status === 'Absent') {
                    $absentDays++;
                }
            }

            $attendanceSummary = [
                'total_hours' => $totalHours,
                'total_overtime' => $totalOvertime,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'leave_days' => $leaveDays,
                'period_start' => $startDate,
                'period_end' => $endDate,
            ];
        }

        $caDeductedThisPayroll = $payroll->cashAdvances
            ? (float) $payroll->cashAdvances->where('type', 'repayment')->sum('amount')
            : 0.0;

        return view('worker.payslip', [
            'title' => 'Payslip',
            'pageClass' => 'worker-payslip',
            'user' => $user,
            'payroll' => $payroll,
            'attendanceSummary' => $attendanceSummary,
            'caDeductedThisPayroll' => $caDeductedThisPayroll,
        ]);
    }

    public function downloadPayslip($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $payroll = Payroll::with(['deductions', 'cashAdvances'])
            ->where('user_id', $user->id)
            ->where('status', 'Released')
            ->findOrFail($id);

        $attendanceSummary = null;

        if ($payroll->period_start && $payroll->period_end) {
            $startDate = $payroll->period_start->toDateString();
            $endDate = $payroll->period_end->toDateString();

            $attendances = Attendance::where('user_id', $user->id)
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            $totalHours = (float) $attendances->sum('total_hours');
            $totalOvertime = (float) $attendances->sum('overtime_hours');

            $presentDays = 0;
            $absentDays = 0;
            $leaveDays = 0;

            foreach ($attendances as $attendance) {
                $status = $attendance->status ?? 'Present';
                if (in_array($status, ['Present', 'Late'], true)) {
                    $presentDays++;
                } elseif ($status === 'On leave') {
                    $leaveDays++;
                } elseif ($status === 'Absent') {
                    $absentDays++;
                }
            }

            $attendanceSummary = [
                'total_hours' => $totalHours,
                'total_overtime' => $totalOvertime,
                'present_days' => $presentDays,
                'absent_days' => $absentDays,
                'leave_days' => $leaveDays,
                'period_start' => $startDate,
                'period_end' => $endDate,
            ];
        }

        $caDeductedThisPayroll = $payroll->cashAdvances
            ? (float) $payroll->cashAdvances->where('type', 'repayment')->sum('amount')
            : 0.0;

        $pdf = Pdf::loadView('worker.payslip-pdf', [
            'user' => $user,
            'payroll' => $payroll,
            'attendanceSummary' => $attendanceSummary,
            'caDeductedThisPayroll' => $caDeductedThisPayroll,
        ]);

        $fileName = 'payslip-' . ($user->id ?? 'worker') . '-' . ($payroll->id ?? 'payroll') . '.pdf';

        return $pdf->download($fileName);
    }

    public function attendance()
    {
        return redirect()->route('worker.dashboard', ['tab' => 'attendance']);
    }

    public function announcementsIndex()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Mark announcements as seen for this worker so the header badge disappears
        session(['worker_last_seen_announcement_at' => now()]);

        $announcements = Announcement::orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->paginate(10);

        $tableData = $announcements->map(function (Announcement $a) {
            $startsAt = $a->starts_at ? $a->starts_at->format('Y-m-d') : 'Immediately';
            $endsAt = $a->ends_at ? $a->ends_at->format('Y-m-d') : 'Open';
            $period = $startsAt . ' – ' . $endsAt;

            $bodyPreview = \Illuminate\Support\Str::limit($a->body ?? '', 120);

            return [
                '<div class="announcement-preview" data-title="' . e($a->title) . '" data-body="' . e($a->body ?? '') . '" data-period="' . e($period) . '">' .
                    '<div class="fw-semibold announcement-preview-title">' . e($a->title) . '</div>' .
                    '<div class="small text-muted announcement-preview-body">' . e($bodyPreview) . '</div>' .
                '</div>',
                $period,
            ];
        })->toArray();

        return view('user.pages.announcements-index', [
            'title' => 'Announcements',
            'pageClass' => 'employee-announcements',
            'user' => $user,
            'announcements' => $announcements,
            'announcementTableData' => $tableData,
        ]);
    }
}
