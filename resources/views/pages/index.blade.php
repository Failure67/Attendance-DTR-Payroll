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

        <div class="container {{ $pageClass }} mt-3">

            <div class="dashboard-card index-attendance-chart">

                <div class="dashboard-card-container">
                    <span class="dashboard-card-title">
                        Attendance (last 14 days)
                    </span>
                </div>

                <div class="dashboard-card-chart">
                    <canvas id="attendanceTrendChart"></canvas>
                </div>

            </div>

            <div class="dashboard-card index-payroll-chart">

                <div class="dashboard-card-container">
                    <span class="dashboard-card-title">
                        Payroll net pay (last 6 months)
                    </span>
                </div>

                <div class="dashboard-card-chart">
                    <canvas id="payrollTrendChart"></canvas>
                </div>

            </div>

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

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const attendanceChartData = @json($attendanceChart ?? null);
        const payrollChartData = @json($payrollChart ?? null);

        if (window.Chart && attendanceChartData && attendanceChartData.labels && attendanceChartData.labels.length) {
            const attendanceCanvas = document.getElementById('attendanceTrendChart');
            if (attendanceCanvas) {
                const ctx = attendanceCanvas.getContext('2d');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: attendanceChartData.labels,
                        datasets: [
                            {
                                label: 'Total hours',
                                data: attendanceChartData.totalHours || [],
                                borderColor: '#0d6efd',
                                backgroundColor: 'rgba(13, 110, 253, 0.15)',
                                tension: 0.3,
                                fill: true,
                                pointRadius: 3,
                            },
                            {
                                label: 'Overtime hours',
                                data: attendanceChartData.overtimeHours || [],
                                borderColor: '#fd7e14',
                                backgroundColor: 'rgba(253, 126, 20, 0.15)',
                                tension: 0.3,
                                fill: true,
                                pointRadius: 3,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: true,
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            },
                        },
                        scales: {
                            x: {
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: 7,
                                },
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Hours',
                                },
                            },
                        },
                    },
                });
            }
        }

        if (window.Chart && payrollChartData && payrollChartData.labels && payrollChartData.labels.length) {
            const payrollCanvas = document.getElementById('payrollTrendChart');
            if (payrollCanvas) {
                const ctx2 = payrollCanvas.getContext('2d');

                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: payrollChartData.labels,
                        datasets: [
                            {
                                label: 'Net pay',
                                data: payrollChartData.netPay || [],
                                backgroundColor: 'rgba(25, 135, 84, 0.7)',
                                borderColor: '#198754',
                                borderWidth: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        const value = context.parsed.y ?? 0;
                                        return '₱ ' + value.toLocaleString(undefined, {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2,
                                        });
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                ticks: {
                                    autoSkip: true,
                                    maxTicksLimit: 6,
                                },
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Net pay (₱)',
                                },
                            },
                        },
                    },
                });
            }
        }
    });
</script>
@endsection