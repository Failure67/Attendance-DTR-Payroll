<?php

namespace App\Http\Controllers;

<<<<<<< HEAD
use App\Models\Announcement;
=======
>>>>>>> 5395f652ac77a567497165442a059e59b3366e75
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Payroll;
use Illuminate\Http\Request;

class NewController extends Controller
{
<<<<<<< HEAD
    public function newUser()
    {
        $user = auth()->user();
        if (!$user) abort(403);
=======
    public function index()
    {
        // main
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }
>>>>>>> 5395f652ac77a567497165442a059e59b3366e75

        $latestPayroll = Payroll::where('user_id', $user->id)
            ->where('status', 'Released')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->first();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $baseAttendance = Attendance::where('user_id', $user->id)
<<<<<<< HEAD
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        // Full-month collection for aggregates
=======
            ->whereBetween('date', [$monthStart->toDateString(),
            $monthEnd->toDateString()]);

>>>>>>> 5395f652ac77a567497165442a059e59b3366e75
        $monthlyAttendance = (clone $baseAttendance)->get();

        $totalHours = (float) $monthlyAttendance->sum('total_hours');
        $totalOvertime = (float) $monthlyAttendance->sum('overtime_hours');

<<<<<<< HEAD
        $totalAdvances = (float) CashAdvance::where('user_id', $user->id)
=======
        $daysPresent = $monthlyAttendance->whereIn('status', ['Present', 'Late'])->count();
        $daysAwol = $monthlyAttendance->where('status', 'AWOL')->count();
        $daysLeave = $monthlyAttendance->where('status', 'On leave')->count();
        $daysAbsent = $monthlyAttendance->whereIn('status', ['Absent', 'AWOL'])->count();

        $totalAdvance = (float) CashAdvance::where('user_id', $user->id)
>>>>>>> 5395f652ac77a567497165442a059e59b3366e75
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'repayment')
            ->sum('amount');

<<<<<<< HEAD
        $caBalance = max(0, $totalAdvances - $totalRepayments);

        $payrollBase = Payroll::where('user_id', $user->id)
=======
        $caBalance = max(0, $totalAdvance - $totalRepayments);

        $caConfig = (array) config('payroll.ca', []);
        $caps = (array) ($caConfig['cap'] ?? []);
        $employmentType = $user->employment_type ?? 'regular';
        $capType = isset($caps[$employmentType]) ? (float) $caps[$employmentType] : null;

        $salaryBaseLimit = null;
        if ($latestPayroll && $latestPayroll->period_start && $latestPayroll->period_end) {
            $periodStart = $latestPayroll->period_start;
            $periodEnd = $latestPayroll->period_end;
            $daysPeriod = max(1, $periodStart->diffInDays($periodEnd) + 1);
            $daysPerMonth = (int) config('payroll.days_per_month', 26);

            $netPeriod = (float) ($latestPayroll->net_pay ?? 0);
            if ($netPeriod > 0 && $daysPeriod > 0 && $daysPerMonth > 0) {
                $monthlyApprox = ($netPeriod / $daysPeriod) * $daysPerMonth;
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
            'cap_type' => $capType,
            'salary_based' => $salaryBaseLimit,
            'effective' => $effectiveLimit,
        ];

        $basePayroll = Payroll::where('user_id', $user->id)
>>>>>>> 5395f652ac77a567497165442a059e59b3366e75
            ->where('status', 'Released')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at');

<<<<<<< HEAD
        // Paginated payroll history (5 rows per page) for the Payroll History tab
        $payrolls = (clone $payrollBase)
            ->paginate(5)
            ->appends(['tab' => 'history']);

        // Paginated attendance list for the dashboard (used in the Attendance tab)
        $attendances = (clone $baseAttendance)
            ->orderByDesc('date')
            ->orderByDesc('time_in')
            ->paginate(5)
            ->appends(['tab' => 'attendance']);

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
        // overview 

        // payroll history

        // attendance

        // cash advance request
        return view('pages.new.user', [
            'title' => 'Overview',
            'pageClass' => 'employee',
            'user' => $user,
            'latestPayroll' => $latestPayroll,
            'monthHours' => $totalHours,
            'monthOvertime' => $totalOvertime,
            'caBalance' => $caBalance,
            'payrolls' => $payrolls,
            'attendances' => $attendances,
            'announcements' => $announcements,
        ]);
=======
        
        // overview

        return view('pages.new.index');
>>>>>>> 5395f652ac77a567497165442a059e59b3366e75
    }
}
