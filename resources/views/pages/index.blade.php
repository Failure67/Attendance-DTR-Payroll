@extends('layouts.app')

@section('content')
    
    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <h1>{{ $title }}</h1>

        <div class="container {{ $pageClass }}">

            @include('components.dashboard-card', [
                'cardClass' => 'recent-attendance',
                'label' => 'Recent Attendance',
                'viewAll' => route('attendance'),
                'tableClass' => [
                    'employee-name',
                    'time-in',
                    'time-out',
                    'date-modified',
                ],
                'tableCol' => [
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
                'tableClass' => [
                    'employee-name',
                    'wage-type',
                    'min-wage',
                    'units-worked',
                    'gross-pay',
                    'deductions',
                    'net-pay',
                ],
                'tableCol' => [
                    'Name of employee',
                    'Type of wage',
                    'Minimum wage',
                    'Units worked',
                ],
                'tableData' => [
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                    ['LAST NAME, FIRST NAME M.I.', '08:00 AM', '05:00 PM', 'November 1, 2025'],
                ],
            ])

        </div>

    </div>

@endsection