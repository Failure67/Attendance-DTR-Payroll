<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\User;
use DB;
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
            'wage_type' => 'required|in:Daily,Hourly,Weekly,Monthly',
            'min_wage' => 'required|numeric|min:0',
            'units_worked' => 'required|numeric|min:0',
            'deductions' => 'nullable|array',
            'deductions.*.name' => 'required_with:deductions|string|max:30',
            'deductions.*.amount' => 'required_with:deductions|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {

            $wage_type = $validated['wage_type'];
            $min_wage = $validated['min_wage'];
            $units_worked = $validated['units_worked'];

            $gross_pay = 0;
            $hours_worked = null;
            $days_worked = null;

            switch ($wage_type) {
                case 'Hourly':
                    $hours_worked = $units_worked;
                    $gross_pay = $min_wage * $hours_worked;
                    break;
                case 'Daily':
                    $days_worked = $units_worked;
                    $gross_pay = $min_wage * $days_worked;
                    break;
                case 'Weekly':
                    $days_worked = $units_worked;
                    $gross_pay = $min_wage * $days_worked;
                    break;
                case 'Monthly':
                    $days_worked = $units_worked;
                    $gross_pay = $min_wage * $days_worked;
                    break;
            }

            $total_deductions = 0;
            if (!empty($validated['deductions'])) {
                $total_deductions = array_sum(array_column($validated['deductions'], 'amount'));
            }

            $net_pay = $gross_pay - $total_deductions;

            $payroll = Payroll::create([
                'user_id' => $validated['user_id'],
                'wage_type' => $wage_type,
                'min_wage' => $min_wage,
                'hours_worked' => $hours_worked,
                'days_worked' => $days_worked,
                'gross_pay' => $gross_pay,
                'total_deductions' => $total_deductions,
                'net_pay' => $net_pay,
                'status' => 'Pending',
            ]);

            if (!empty($validated['deductions'])) {
                foreach ($validated['deductions'] as $deduction) {
                    PayrollDeduction::create([
                        'payroll_id' => $payroll->id,
                        'name' => $deduction['name'],
                        'amount' => $deduction['amount'],
                    ]);
                }
            }

            DB::commit();

            return redirect()->back()->with('success', 'Payroll added successfully.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'An error occured while adding payroll: ' . $e->getMessage()]);
        }
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
