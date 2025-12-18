<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Payroll;
use Illuminate\Http\Request;

class NewController extends Controller
{
    public function newUser()
    {
        $user = auth()->user();
        if (!$user) abort(403);

        $latestPayroll = Payroll::where('user_id', $user->id)
            ->where('status', 'Released')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at')
            ->first();

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $baseAttendance = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()]);

        // Full-month collection for aggregates
        $monthlyAttendance = (clone $baseAttendance)->get();

        $totalHours = (float) $monthlyAttendance->sum('total_hours');
        $totalOvertime = (float) $monthlyAttendance->sum('overtime_hours');

        $totalAdvances = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'repayment')
            ->sum('amount');

        $caBalance = max(0, $totalAdvances - $totalRepayments);

        $payrollBase = Payroll::where('user_id', $user->id)
            ->where('status', 'Released')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at');

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
    }
}
