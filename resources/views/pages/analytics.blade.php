@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-chart-line"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Comprehensive analytics for attendance and payroll</p>
                </div>
            </div>
        </div>

        <div class="container {{ $pageClass }} mb-3">
            <form method="GET" action="{{ route('analytics') }}" class="row g-3 align-items-end analytics-filter-row">
                <div class="col-12 col-md-6 col-lg">
                    <label for="analytics_employee" class="input-label mb-1">Employee</label>
                    <select name="employee_id" id="analytics_employee" class="select w-100">
                        <option value="">All employees</option>
                        @foreach (($employeeOptions ?? []) as $id => $name)
                            <option value="{{ $id }}" @if(($filters['employee_id'] ?? '') == $id) selected @endif>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-3 col-lg">
                    <label for="analytics_period_start" class="input-label mb-1">Period start</label>
                    <input type="date" name="period_start" id="analytics_period_start" class="date-field" value="{{ $filters['period_start'] ?? '' }}">
                </div>
                <div class="col-12 col-md-3 col-lg">
                    <label for="analytics_period_end" class="input-label mb-1">Period end</label>
                    <input type="date" name="period_end" id="analytics_period_end" class="date-field" value="{{ $filters['period_end'] ?? '' }}">
                </div>
                <div class="col-12 col-md-6 col-lg-auto d-flex align-items-end justify-content-lg-end">
                    <button type="submit" class="button main filter">Filter</button>
                </div>
            </form>
        </div>

        @php
            $mode = $mode ?? 'combined';
        @endphp

        @if ($mode === 'attendance' || $mode === 'combined')
            @php
                $attendanceSummaryDefaults = [
                    'total_hours' => 0,
                    'total_overtime' => 0,
                    'attendance_rate' => 0,
                    'records' => 0,
                    'worked_days' => 0,
                    'absent_days' => 0,
                    'leave_days' => 0,
                    'employee_count' => 0,
                    'anomaly_count' => 0,
                    'period_label' => ($filters['period_start'] ?? '') && ($filters['period_end'] ?? '')
                        ? (($filters['period_start'] ?? '') . ' to ' . ($filters['period_end'] ?? ''))
                        : 'selected period',
                ];
                $attendanceSummary = array_merge($attendanceSummaryDefaults, $attendanceAnalytics['summary'] ?? []);
                $attendancePeriodText = $attendanceSummary['period_label'] ?? 'selected period';
            @endphp

            <div class="container {{ $pageClass }} cards mt-3">
                <div class="analytics-section-header">
                    <div>
                        <div class="analytics-section-title">Attendance analytics</div>
                        <div class="analytics-section-subtitle">Attendance performance for {{ $attendancePeriodText }}</div>
                    </div>
                </div>
            </div>

            <div class="container {{ $pageClass }} summary mb-3">

                @include('components.dashboard-count', [
                    'countClass' => 'attendance-total-hours',
                    'countLabel' => 'Total hours',
                    'countSublabel' => 'For ' . $attendancePeriodText,
                    'countIcon' => '<i class="fa-solid fa-clock"></i>',
                    'countValue' => number_format($attendanceSummary['total_hours'], 2),
                ])

                @include('components.dashboard-count', [
                    'countClass' => 'attendance-overtime-hours',
                    'countLabel' => 'Overtime hours',
                    'countSublabel' => 'For ' . $attendancePeriodText,
                    'countIcon' => '<i class="fa-solid fa-business-time"></i>',
                    'countValue' => number_format($attendanceSummary['total_overtime'], 2),
                ])

                @include('components.dashboard-count', [
                    'countClass' => 'attendance-rate',
                    'countLabel' => 'Attendance rate',
                    'countSublabel' => 'For ' . $attendancePeriodText . ' (' . ($attendanceSummary['records'] ?? 0) . ' records)',
                    'countIcon' => '<i class="fa-solid fa-chart-column"></i>',
                    'countValue' => $attendanceSummary['attendance_rate'] . '%',
                ])

                @include('components.dashboard-count', [
                    'countClass' => 'attendance-meta',
                    'countLabel' => 'Employees / anomalies',
                    'countSublabel' => 'For ' . $attendancePeriodText,
                    'countIcon' => '<i class="fa-solid fa-people-group"></i>',
                    'countValue' => ($attendanceSummary['employee_count'] ?? 0) . ' employees, ' . ($attendanceSummary['anomaly_count'] ?? 0) . ' anomalies',
                ])

            </div>

            <div class="container {{ $pageClass }} mt-3">

                <div class="dashboard-card analytics-attendance-chart">

                    <div class="dashboard-card-container">
                        <span class="dashboard-card-title">
                            Attendance trend ({{ $attendancePeriodText }})
                        </span>
                    </div>

                    <div class="dashboard-card-chart">
                        <canvas id="analyticsAttendanceTrendChart"></canvas>
                    </div>

                </div>

            </div>

            @php
                $topOvertimeTable = $attendanceAnalytics['topOvertimeTable'] ?? [];
                $topAbsenceTable = $attendanceAnalytics['topAbsenceTable'] ?? [];

                if (empty($topOvertimeTable)) {
                    $topOvertimeTable = [
                        ['No data found', '—', '—', '—'],
                    ];
                }

                if (empty($topAbsenceTable)) {
                    $topAbsenceTable = [
                        ['No data found', '—', '—', '—'],
                    ];
                }
            @endphp

            <div class="container {{ $pageClass }} cards mt-3">

                @include('components.dashboard-card', [
                    'cardClass' => 'attendance-top-overtime',
                    'label' => 'Top overtime (by employee)',
                    'viewAll' => route('attendance'),
                    'tableCol' => [
                        'employee-name',
                        'overtime-hours',
                        'total-hours',
                        'present-days',
                    ],
                    'tableLabel' => [
                        'Name of employee',
                        'Overtime hours',
                        'Total hours',
                        'Worked days',
                    ],
                    'tableData' => $topOvertimeTable,
                ])

                @include('components.dashboard-card', [
                    'cardClass' => 'attendance-top-absence',
                    'label' => 'Top absence and leave (by employee)',
                    'viewAll' => route('attendance'),
                    'tableCol' => [
                        'employee-name',
                        'absent-days',
                        'late-days',
                        'leave-days',
                    ],
                    'tableLabel' => [
                        'Name of employee',
                        'Absent days',
                        'Late days',
                        'Leave days',
                    ],
                    'tableData' => $topAbsenceTable,
                ])

            </div>
        @endif

        @if ($mode === 'payroll' || $mode === 'combined')
            @php
                $payrollSummaryDefaults = [
                    'total_gross' => 0,
                    'total_deductions' => 0,
                    'total_net' => 0,
                    'employee_count' => 0,
                    'payroll_count' => 0,
                    'avg_net_per_employee' => 0,
                    'avg_net_per_payroll' => 0,
                    'status_breakdown' => [
                        'pending' => ['count' => 0, 'net' => 0],
                        'released' => ['count' => 0, 'net' => 0],
                        'cancelled' => ['count' => 0, 'net' => 0],
                    ],
                    'period_label' => ($filters['period_start'] ?? '') && ($filters['period_end'] ?? '')
                        ? (($filters['period_start'] ?? '') . ' to ' . ($filters['period_end'] ?? ''))
                        : 'selected period',
                ];
                $payrollSummary = array_merge($payrollSummaryDefaults, $payrollAnalytics['summary'] ?? []);
                $payrollPeriodText = $payrollSummary['period_label'] ?? 'selected period';
                $statusBreakdown = $payrollSummary['status_breakdown'] ?? $payrollSummaryDefaults['status_breakdown'];
            @endphp

            <div class="container {{ $pageClass }} mt-4">
                <div class="analytics-section-header">
                    <div>
                        <div class="analytics-section-title">Payroll analytics</div>
                        <div class="analytics-section-subtitle">Payroll totals and trends for {{ $payrollPeriodText }}</div>
                    </div>
                </div>
            </div>

            <div class="container {{ $pageClass }} summary mb-3 mt-2">

                @include('components.dashboard-count', [
                    'countClass' => 'payroll-total-gross',
                    'countLabel' => 'Total gross pay',
                    'countSublabel' => 'For ' . $payrollPeriodText,
                    'countIcon' => '<i class="fa-solid fa-money-bills"></i>',
                    'countValue' => '₱ ' . number_format($payrollSummary['total_gross'], 2),
                ])

                @include('components.dashboard-count', [
                    'countClass' => 'payroll-total-net',
                    'countLabel' => 'Total net pay',
                    'countSublabel' => 'For ' . $payrollPeriodText,
                    'countIcon' => '<i class="fa-solid fa-money-bill-trend-up"></i>',
                    'countValue' => '₱ ' . number_format($payrollSummary['total_net'], 2),
                ])

                @include('components.dashboard-count', [
                    'countClass' => 'payroll-employees',
                    'countLabel' => 'Employees paid',
                    'countSublabel' => 'For ' . $payrollPeriodText,
                    'countIcon' => '<i class="fa-solid fa-users"></i>',
                    'countValue' => number_format($payrollSummary['employee_count']),
                ])

                @php
                    $pendingInfo = $statusBreakdown['pending'] ?? ['count' => 0, 'net' => 0];
                    $releasedInfo = $statusBreakdown['released'] ?? ['count' => 0, 'net' => 0];
                    $cancelledInfo = $statusBreakdown['cancelled'] ?? ['count' => 0, 'net' => 0];
                    $avgNetPerEmp = $payrollSummary['avg_net_per_employee'] ?? 0;
                    $avgNetPerPayroll = $payrollSummary['avg_net_per_payroll'] ?? 0;
                @endphp

                @include('components.dashboard-count', [
                    'countClass' => 'payroll-status-breakdown',
                    'countLabel' => 'Status and averages',
                    'countSublabel' => 'Pending ' . ($pendingInfo['count'] ?? 0) . ', Released ' . ($releasedInfo['count'] ?? 0) . ', Cancelled ' . ($cancelledInfo['count'] ?? 0),
                    'countIcon' => '<i class="fa-solid fa-scale-balanced"></i>',
                    'countValue' => '₱ ' . number_format($avgNetPerEmp, 2) . ' / emp' . "\n" . '₱ ' . number_format($avgNetPerPayroll, 2) . ' / payroll',
                ])

            </div>

            <div class="container {{ $pageClass }} mt-3">

                <div class="dashboard-card analytics-payroll-chart">

                    <div class="dashboard-card-container">
                        <span class="dashboard-card-title">
                            Payroll trend (gross vs net)
                        </span>
                    </div>

                    <div class="dashboard-card-chart">
                        @php
                            $hasPayrollChartData = !empty(($payrollAnalytics['chart']['labels'] ?? []));
                        @endphp

                        @if ($hasPayrollChartData)
                            <canvas id="analyticsPayrollChart"></canvas>
                        @else
                            <div class="analytics-chart-empty">
                                No payroll data found for the selected period.
                            </div>
                        @endif
                    </div>

                </div>

            </div>

            @php
                $topNetPayTable = $payrollAnalytics['topNetPayTable'] ?? [];
                $cashAdvanceTableData = $payrollAnalytics['cashAdvanceTableData'] ?? [];

                if (empty($topNetPayTable)) {
                    $topNetPayTable = [
                        ['No data found', '—', '—', '—'],
                    ];
                }

                if (empty($cashAdvanceTableData)) {
                    $cashAdvanceTableData = [
                        ['No data found', '—', '—', '—'],
                    ];
                }
            @endphp

            <div class="container {{ $pageClass }} cards mt-3 mb-3">

                @include('components.dashboard-card', [
                    'cardClass' => 'payroll-top-net',
                    'label' => 'Top net pay (by employee)',
                    'viewAll' => route('payroll'),
                    'tableCol' => [
                        'employee-name',
                        'net-total',
                        'gross-total',
                        'payroll-count',
                    ],
                    'tableLabel' => [
                        'Name of employee',
                        'Net total',
                        'Gross total',
                        'No. of payrolls',
                    ],
                    'tableData' => $topNetPayTable,
                ])

                @include('components.dashboard-card', [
                    'cardClass' => 'payroll-cash-advance',
                    'label' => 'Cash advance balances',
                    'viewAll' => route('cash-advances'),
                    'tableCol' => [
                        'employee-name',
                        'total-advances',
                        'total-repayments',
                        'outstanding',
                    ],
                    'tableLabel' => [
                        'Name of employee',
                        'Total advances',
                        'Total repayments',
                        'Outstanding balance',
                    ],
                    'tableData' => $cashAdvanceTableData,
                ])

            </div>
        @endif

    </div>

@endsection

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mode = @json($mode ?? 'combined');
        const attendanceAnalytics = @json($attendanceAnalytics ?? null);
        const payrollAnalytics = @json($payrollAnalytics ?? null);

        if (window.Chart && (mode === 'attendance' || mode === 'combined') && attendanceAnalytics && attendanceAnalytics.chart && attendanceAnalytics.chart.labels && attendanceAnalytics.chart.labels.length) {
            const attendanceCanvas = document.getElementById('analyticsAttendanceTrendChart');
            if (attendanceCanvas) {
                const ctx = attendanceCanvas.getContext('2d');

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: attendanceAnalytics.chart.labels,
                        datasets: [
                            {
                                label: 'Total hours',
                                data: attendanceAnalytics.chart.totalHours || [],
                                borderColor: '#1F7AE0',
                                backgroundColor: 'rgba(31, 122, 224, 0.18)',
                                tension: 0.3,
                                fill: true,
                                pointRadius: 3,
                            },
                            {
                                label: 'Overtime hours',
                                data: attendanceAnalytics.chart.overtimeHours || [],
                                borderColor: '#F39C12',
                                backgroundColor: 'rgba(243, 156, 18, 0.18)',
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
                                    maxTicksLimit: 10,
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

        if (window.Chart && (mode === 'payroll' || mode === 'combined') && payrollAnalytics && payrollAnalytics.chart && payrollAnalytics.chart.labels && payrollAnalytics.chart.labels.length) {
            const payrollCanvas = document.getElementById('analyticsPayrollChart');
            if (payrollCanvas) {
                const ctx2 = payrollCanvas.getContext('2d');

                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: payrollAnalytics.chart.labels,
                        datasets: [
                            {
                                label: 'Gross pay',
                                data: payrollAnalytics.chart.gross || [],
                                backgroundColor: 'rgba(243, 156, 18, 0.7)',
                                borderColor: '#F39C12',
                                borderWidth: 1,
                            },
                            {
                                label: 'Net pay',
                                data: payrollAnalytics.chart.net || [],
                                backgroundColor: 'rgba(31, 122, 224, 0.8)',
                                borderColor: '#1F7AE0',
                                borderWidth: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
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
                                    maxTicksLimit: 12,
                                },
                            },
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Amount (₱)',
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
