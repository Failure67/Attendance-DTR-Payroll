<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Payroll;
use Illuminate\Http\Request;

class NewController extends Controller
{
    // === USER === //
    public function user()
    {
        // overview
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $latestPayroll = Payroll::where('user_id', $user->id)
            ->where('status', 'Released')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->first();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $baseAttendance = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        $monthlyAttendance = (clone $baseAttendance)->get();

        $totalHours = (float) $monthlyAttendance->sum('total_hours');
        $totalOvertime = (float) $monthlyAttendance
            ->where('overtime_approved', true)
            ->sum('overtime_hours');

        $daysPresent = $monthlyAttendance->whereIn('status', ['Present', 'Late'])->count();
        $daysAbsent = $monthlyAttendance->whereIn('status', ['Absent', 'AWOL'])->count();
        $daysOnLeave = $monthlyAttendance->where('status', 'On leave')->count();
        $daysAwol = $monthlyAttendance->where('status', 'AWOL')->count();

        $totalCashAdvances = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'repayment')
            ->sum('amount');
        
        $caBalance = max(0, $totalCashAdvances - $totalRepayments);

        $caConfig = (array) config('payroll.ca', []);
        $caps = (array) ($caConfig['cap'] ?? []);
        $employmentType = $user->employment_type ?? 'regular';
        $capType = isset($caps[$employmentType]) ? (float) $caps[$employmentType] : null;

        $salaryBaseLimit = null;
        if ($latestPayroll && $latestPayroll->period_start && $latestPayroll->period_end) {
            $periodStart = $latestPayroll->period_start;
            $periodEnd = $latestPayroll->period_end;
            $daysInPeriod = max(1, $periodStart->diffInDays($periodEnd) + 1);
            $daysPerMonth = (int) config('payroll.days_per_month', 26);

            $netPeriod = (float) ($latestPayroll->net_pay ?? 0);
            if ($netPeriod > 0 && $daysInPeriod > 0 && $daysPerMonth > 0) {
                $monthlyApprox = ($netPeriod / $daysInPeriod) * $daysPerMonth;
                $maxPercent = (float) ($caConfig['max_percent_of_monthly_net'] ?? 0.8);

                if ($monthlyApprox > 0 && $maxPercent > 0) {
                    $salaryBaseLimit = $monthlyApprox * $maxPercent;
                }
            }
        }

        $effectiveLimit = null;

        if ($capType !== null && $salaryBaseLimit !== null) {
            $effectiveLimit = min($capType, $salaryBaseLimit);
        } else if ($capType !== null) {
            $effectiveLimit = $capType;
        } else if ($salaryBaseLimit !== null) {
            $effectiveLimit = $salaryBaseLimit;
        }

        $allowPartTime = (bool) ($caConfig['allow_part_time'] ?? false);
        if ($employmentType !== 'regular' && !$allowPartTime) {
            $effectiveLimit = null;
            $capType = null;
            $salaryBaseLimit = null;
        }

        $caLimit = [
            'type_cap' => $capType,
            'salary_based' => $salaryBaseLimit,
            'effective' => $effectiveLimit,
        ];

        $basePayroll 

    }

    // === ADMIN === //
    public function admin()
    {
        
    }
}
