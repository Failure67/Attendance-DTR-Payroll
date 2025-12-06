@extends('layouts.app')

@section('content')
    
    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-house"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Overview of workforce, attendance, and payroll metrics</p>
                </div>
            </div>
        </div>

        <div class="container {{ $pageClass }}">

            @include('components.dashboard-count', [
                'countClass' => 'employees-current',
                'countLabel' => 'Employees',
                'countSublabel' => 'As of ' . date('F d, Y'),
                'countIcon' => '<i class="fa-solid fa-users"></i>',
                'countValue' => number_format($employeesCount ?? 0),
            ])

            @include('components.dashboard-count', [
                'countClass' => 'payroll-budget',
                'countLabel' => 'Payroll Budget',
                'countSublabel' => 'This month',
                'countIcon' => '<i class="fa-solid fa-money-bills"></i>',
                'countValue' => '₱ ' . number_format($payrollBudgetAmount ?? 0, 2),
            ])

            @include('components.dashboard-count', [
                'countClass' => 'payroll-due',
                'countLabel' => 'Payroll Due',
                'countSublabel' => 'Pending (this month)',
                'countIcon' => '<i class="fa-solid fa-money-bill-trend-up"></i>',
                'countValue' => '₱ ' . number_format($payrollDueAmount ?? 0, 2),
            ])

            @include('components.dashboard-count', [
                'countClass' => 'payroll-paid',
                'countLabel' => 'Payroll Paid',
                'countSublabel' => 'Released (this month)',
                'countIcon' => '<i class="fa-solid fa-money-bill-transfer"></i>',
                'countValue' => '₱ ' . number_format($payrollPaidAmount ?? 0, 2),
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

            @php
                $todaySummary = $todayAttendanceSummary ?? [
                    'date_label' => date('F d, Y'),
                    'records' => 0,
                    'present' => 0,
                    'late' => 0,
                    'absent' => 0,
                    'leave' => 0,
                    'anomaly_count' => 0,
                ];

                $todayAttendanceTable = [
                    ['Total records', $todaySummary['records']],
                    ['Present', $todaySummary['present']],
                    ['Late', $todaySummary['late']],
                    ['Absent', $todaySummary['absent']],
                    ['On leave', $todaySummary['leave']],
                    ['Anomalies detected', $todaySummary['anomaly_count']],
                ];
            @endphp

            @include('components.dashboard-card', [
                'cardClass' => 'today-attendance-summary',
                'label' => 'Today\'s attendance (' . ($todaySummary['date_label'] ?? date('F d, Y')) . ')',
                'viewAll' => route('attendance'),
                'tableCol' => [
                    'metric',
                    'value',
                ],
                'tableLabel' => [
                    'Metric',
                    'Value',
                ],
                'tableData' => $todayAttendanceTable,
            ])

            @include('components.dashboard-card', [
                'cardClass' => 'pending-payroll',
                'label' => 'Pending payrolls (this month)',
                'viewAll' => route('payroll', ['status' => 'Pending']),
                'tableCol' => [
                    'employee-name',
                    'net-pay',
                    'period-end',
                ],
                'tableLabel' => [
                    'Name of employee',
                    'Net pay',
                    'Period end',
                ],
                'tableData' => $pendingPayrollTable ?? [],
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