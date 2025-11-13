<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Http\Request;

class AppController extends Controller
{
    // index
    public function index()
    {
        return view('pages.index', [
            'title' => 'Home',
            'pageClass' => 'index',
        ]);
    }

    // attendance
    public function viewAttendance()
    {
        return view('pages.attendance', [
            'title' => 'Attendance',
            'pageClass' => 'attendance',
        ]);
    }

    // payroll
    public function viewPayroll()
    {
        //$payrolls = Payroll::with('user')->latest()->get();
        //$users = User::all();

        /*
        return view('pages.payroll', [
            'title' => 'Payroll',
            'pageClass' => 'payroll',
        ], compact('payrolls', 'users'));
        */

        return view('pages.payroll', [
            'title' => 'Payroll',
            'pageClass' => 'payroll',
        ]);
    }

    public function storePayroll(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'wage_type' => 'required|in:Hourly,Daily,Weekly,Monthly',
            'min_wage' => 'required|numeric|min:0',
            'hours_worked' => 'nullable|numeric|min:0',
            'days_worked' => 'nullable|numeric|min:0',
            'gross_pay' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'net_pay' => 'nullable|numeric|min:0',
        ]);

        $gross_pay = 0;
        switch ($validated['wage_type']) {
            case 'Hourly':
                $gross_pay = $validated['min_wage'] * ($validated['hours_worked'] ?? 0);
                break;
            
            case 'Daily':
            case 'Weekly':
            case 'Monthly':
                $gross_pay = $validated['min_wage'] * ($validated['days-worked'] ?? 0);
                break;
        }

        $total_deductions = 0;
        if (!empty($validated['deductions'])) {
            foreach ($validated['deductions'] as $deduction) {
                $total_deductions += $deduction['amount'];
            }
        }

        $net_pay = $gross_pay - $total_deductions;

        $payroll = Payroll::create([
            'user_id' => $validated['user_id'],
            'wage_type' => $validated['wage_type'],
            'min_wage' => $validated['min_wage'],
            'hours_worked' => $validated['hours_worked'],
            'days_worked' => $validated['days_worked'],
            'gross_pay' => $gross_pay,
            'total_deductions' => $total_deductions,
            'net_pay' => $net_pay,
        ]);

        if (!empty($validated['deductions'])) {
            foreach ($validated['deductions'] as $deduction) {
                $payroll->deductions()->create([
                    'deduction_name' => $deduction['name'],
                    'amount' => $deduction['amount'],
                ]);
            }
        }

        return back()->with('success', 'Payroll record created successfully.');
    }

    // users
    public function viewUsers()
    {
        return view('pages.users', [
            'title' => 'Users',
            'pageClass' => 'users',
        ]);
    }

    // require js
    public function require()
    {
        return view('components.require');
    }
}
