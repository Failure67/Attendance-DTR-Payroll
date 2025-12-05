<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Payroll;
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
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->first();

        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $monthlyAttendance = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get();

        $totalHours = (float) $monthlyAttendance->sum('total_hours');
        $totalOvertime = (float) $monthlyAttendance->sum('overtime_hours');

        $totalAdvances = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'repayment')
            ->sum('amount');

        $caBalance = max(0, $totalAdvances - $totalRepayments);

        $payrolls = Payroll::where('user_id', $user->id)
        ->orderByDesc('period_end')
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
            'payrolls' => $payrolls,
            'attendances' => $monthlyAttendance,
        ]);
    }

    public function payrollHistory()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $payrolls = Payroll::where('user_id', $user->id)
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return view('user.pages.index', [
            'title' => 'Payroll History',
            'pageClass' => 'worker-payroll-history',
            'user' => $user,
            'payrolls' => $payrolls,
        ]);
    }

    public function payslip($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $payroll = Payroll::with(['deductions', 'cashAdvances'])
            ->where('user_id', $user->id)
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
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $attendances = Attendance::where('user_id', $user->id)
            ->orderByDesc('date')
            ->orderByDesc('time_in')
            ->limit(50)
            ->get();

        return view('user.pages.index', [
            'title' => 'Attendance',
            'pageClass' => 'worker-attendance',
            'user' => $user,
            'attendances' => $attendances,
        ]);
    }
}
