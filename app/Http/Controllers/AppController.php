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
        /*
        $payrollInfo = DB::table('')
                        ->select('', '')
                        ->orderBy('id')
                        ->get(); */

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
        ], /*[
            'user_id.required' => 'Employee name is required.',
            'user_id.exists' => 'Selected employee does not exist.',
            'wage_type.required' => 'Wage type is required.',
            'wage_type.in' => 'Invalid wage type selected.',
            'min_wage.required' => 'Minimum wage is required.',
            'min_wage.numeric' => 'Minimum wage must be a valid number.',
            'min_wage.min' => 'Minimum wage cannot be negative.',
            'units_worked.required' => 'Units worked is required.',
            'units_worked.numeric' => 'Units worked must be a valid number.',
            'units_worked.min' => 'Units worked cannot be negative.',
            'deductions.*.name.required_with' => 'Deduction name is required.',
            'deductions.*.name.max' => 'Deduction name cannot exceed 30 characters.',
            'deductions.*.amount.required_with' => 'Deduction amount is required.',
            'deductions.*.amount.numeric' => 'Deduction amount must be a valid number.',
            'deductions.*.amount.min' => 'Deduction amount cannot be negative.',
        ]*/);

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

            return redirect()->route('payroll.store')->with('success', 'Payroll added successfully.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withInput()->withErrors(['error' => 'An error occured while adding payroll: ' . $e->getMessage()]);
        }
    }

    public function deletePayroll(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->delete();

        return redirect()->route('payroll')->with('success', 'Payroll successfully deleted.');
    }

    public function deleteMultiplePayroll(Request $request)
    {
        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:payrolls,id',
        ]);

        $payroll = Payroll::whereIn('id', $validated['payroll_ids'])->get();

        $payroll->delete();

        return redirect()->route('payroll')->with('success', 'Selected payrolls successfully deleted.');
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
