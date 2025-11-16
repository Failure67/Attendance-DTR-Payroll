@extends('layouts.app')

@section('content')
    
    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }}">

            @include('components.dashboard-count', [
                'countClass' => 'employees-current',
                'countLabel' => 'Employees',
                'countSublabel' => 'As of ' . date('F d, Y'),
                'countIcon' => '<i class="fa-solid fa-users"></i>',
                'countValue' => '0',
            ])

            @include('components.dashboard-count', [
                'countClass' => 'payroll-budget',
                'countLabel' => 'Payroll Budget',
                'countSublabel' => 'As of ' . date('F d, Y'),
                'countIcon' => '<i class="fa-solid fa-money-bills"></i>',
                'countValue' => '0',    
            ])

            @include('components.dashboard-count', [
                'countClass' => 'payroll-due',
                'countLabel' => 'Payroll Due',
                'countIcon' => '<i class="fa-solid fa-money-bill-trend-up"></i>',
                'countValue' => '0',    
            ])

            @include('components.dashboard-count', [
                'countClass' => 'payroll-paid',
                'countLabel' => 'Payroll Paid',
                'countSublabel' => 'As of ' . date('F d, Y'),
                'countIcon' => '<i class="fa-solid fa-money-bill-transfer"></i>',
                'countValue' => '0',    
            ])

        </div>

        <div class="container {{ $pageClass }}">

            @include('components.dashboard-card', [
                'cardClass' => 'recent-attendance',
                'label' => 'Recent Attendance',
                'viewAll' => route('attendance'),
                'tableCol' => [
                    'employee-name',
                    'time-in',
                    'time-out',
                    'date-modified',
                ],
                'tableLabel' => [
                    'Name of employee',
                    'Time-in',
                    'Time-out',
                    'Date modified',
                ],
                'tableData' => [
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                ],
            ])

            @include('components.dashboard-card', [
                'cardClass' => 'recent-payroll',
                'label' => 'Recent Payroll',
                'viewAll' => route('payroll'),
                'tableCol' => [
                    'employee-name',
                    'wage-type',
                    'min-wage',
                    'units-worked',
                    'gross-pay',
                    'deductions',
                    'net-pay',
                ],
                'tableLabel' => [
                    'Name of employee',
                    'Type of wage',
                    'Minimum wage',
                    'Units worked',
                ],
                'tableData' => [
                    ['LAST NAME, FIRST NAME M.I.', 'Weekly', 'P500', '3 weeks'],
                    ['LAST NAME, FIRST NAME M.I.', 'Monthly', 'P600', '5 days'],
                    ['LAST NAME, FIRST NAME M.I.', 'Daily', 'P300', '6 days'],
                    ['LAST NAME, FIRST NAME M.I.', 'Daily', 'P400', '3 days'],
                    ['LAST NAME, FIRST NAME M.I.', 'Monthly', 'P500', '10 days'],
                ],
            ])

        </div>

    </div>

@endsection