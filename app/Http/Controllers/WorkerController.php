<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WorkerController extends Controller
{
    public function overview()
    {
        return view('worker.overview', [
            'title' => 'Overview',
            'pageClass' => 'worker-overview',
        ]);
    }

    public function payrollHistory()
    {
        return view('worker.payroll-history', [
            'title' => 'Payroll History',
            'pageClass' => 'worker-payroll-history',
        ]);
    }

    public function attendance()
    {
        return view('worker.attendance', [
            'title' => 'Attendance',
            'pageClass' => 'worker-attendance',
        ]);
    }
}
