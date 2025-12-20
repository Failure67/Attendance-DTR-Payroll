@extends('layouts.app')

@section('content')

    @include('partials.menu')

    <div class="wrapper {{ $pageClass }}">

        @php
            $currentFilters = $filters ?? [];
            $exportQuery = array_filter([
                'employee_id' => $currentFilters['employee_id'] ?? null,
                'period_start' => $currentFilters['period_start'] ?? null,
                'period_end' => $currentFilters['period_end'] ?? null,
            ], function ($value) {
                return !is_null($value) && $value !== '';
            });
            $exportPdfUrl = route('analytics.export-pdf') . (count($exportQuery) ? ('?' . http_build_query($exportQuery)) : '');
        @endphp

        <div class="page-header">
            <div class="page-title">
                <span class="page-icon"><i class="fa-solid fa-chart-line"></i></span>
                <div class="page-title-text">
                    <h1>{{ $title }}</h1>
                    <p>Comprehensive analytics for attendance and payroll</p>
                </div>
            </div>
            <div class="page-actions ms-auto">
                <button type="button" class="button secondary filter" id="analytics-export-pdf" onclick="window.location.href='{{ $exportPdfUrl }}';">Export PDF</button>
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
                    'awol_days' => 0,
                    'leave_days' => 0,
                    'employee_count' => 0,
                    'anomaly_count' => 0,
                    'employment_type' => [
                        'regular' => [
                            'awol_days' => 0,
                        ],
                        'part_time' => [
                            'awol_days' => 0,
                        ],
                    ],
                    'leave_usage' => [
                        'paid_days' => 0,
                        'unpaid_days' => 0,
                        'employment_type' => [
                            'regular' => [
                                'paid_days' => 0,
                                'unpaid_days' => 0,
                            ],
                            'part_time' => [
                                'paid_days' => 0,
                                'unpaid_days' => 0,
                            ],
                        ],
                    ],
                    'period_label' => ($filters['period_start'] ?? '') && ($filters['period_end'] ?? '')
                        ? (($filters['period_start'] ?? '') . ' to ' . ($filters['period_end'] ?? ''))
                        : 'selected period',
                ];
                $attendanceSummary = array_merge($attendanceSummaryDefaults, $attendanceAnalytics['summary'] ?? []);
                $attendancePeriodText = $attendanceSummary['period_label'] ?? 'selected period';

                // Build a non-breaking "0 anomalies" phrase so the number stays with the word
                $attendanceEmployeesCount = (int) ($attendanceSummary['employee_count'] ?? 0);
                $attendanceAnomalyCount = (int) ($attendanceSummary['anomaly_count'] ?? 0);
                $nbsp = html_entity_decode('&nbsp;', ENT_QUOTES, 'UTF-8');
                $attendanceEmployeesAnomaliesText = $attendanceEmployeesCount . ' employees, ' . $attendanceAnomalyCount . $nbsp . 'anomalies';

                $attEmpTypeSummary = $attendanceSummary['employment_type'] ?? [
                    'regular' => ['awol_days' => 0],
                    'part_time' => ['awol_days' => 0],
                ];

                $attRegAwol = (int) ($attEmpTypeSummary['regular']['awol_days'] ?? 0);
                $attPtAwol = (int) ($attEmpTypeSummary['part_time']['awol_days'] ?? 0);

                $leaveUsage = $attendanceSummary['leave_usage'] ?? $attendanceSummaryDefaults['leave_usage'];
                $leavePaidDays = (float) ($leaveUsage['paid_days'] ?? 0);
                $leaveUnpaidDays = (float) ($leaveUsage['unpaid_days'] ?? 0);
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
                    'countClass' => 'attendance-awol-days',
                    'countLabel' => 'AWOL days',
                    'countSublabel' => 'For ' . $attendancePeriodText,
                    'countIcon' => '<i class="fa-solid fa-user-xmark"></i>',
                    'countValue' => number_format($attendanceSummary['awol_days'] ?? 0),
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
                        'awol-days',
                        'late-days',
                        'leave-days',
                    ],
                    'tableLabel' => [
                        'Name of employee',
                        'Absent days',
                        'AWOL days',
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
                    'cash_advance' => [
                        'total_outstanding' => 0,
                        'employee_count_with_balance' => 0,
                        'employment_type' => [
                            'regular' => [
                                'headcount' => 0,
                                'total_outstanding' => 0,
                            ],
                            'part_time' => [
                                'headcount' => 0,
                                'total_outstanding' => 0,
                            ],
                        ],
                    ],
                    'period_label' => ($filters['period_start'] ?? '') && ($filters['period_end'] ?? '')
                        ? (($filters['period_start'] ?? '') . ' to ' . ($filters['period_end'] ?? ''))
                        : 'selected period',
                ];
                $payrollSummary = array_merge($payrollSummaryDefaults, $payrollAnalytics['summary'] ?? []);
                $payrollPeriodText = $payrollSummary['period_label'] ?? 'selected period';
                $statusBreakdown = $payrollSummary['status_breakdown'] ?? $payrollSummaryDefaults['status_breakdown'];

                $empTypeSummary = $payrollSummary['employment_type'] ?? [
                    'regular' => ['headcount' => 0, 'total_net' => 0],
                    'part_time' => ['headcount' => 0, 'total_net' => 0],
                ];

                $regHead = (int) ($empTypeSummary['regular']['headcount'] ?? 0);
                $regNet = (float) ($empTypeSummary['regular']['total_net'] ?? 0);
                $ptHead = (int) ($empTypeSummary['part_time']['headcount'] ?? 0);
                $ptNet = (float) ($empTypeSummary['part_time']['total_net'] ?? 0);

                $caExposure = $payrollSummary['cash_advance'] ?? $payrollSummaryDefaults['cash_advance'];
                $caEmpTypeExposure = $caExposure['employment_type'] ?? [
                    'regular' => ['headcount' => 0, 'total_outstanding' => 0],
                    'part_time' => ['headcount' => 0, 'total_outstanding' => 0],
                ];

                $caTotalOutstanding = (float) ($caExposure['total_outstanding'] ?? 0);
                $caRegOutstanding = (float) ($caEmpTypeExposure['regular']['total_outstanding'] ?? 0);
                $caPtOutstanding = (float) ($caEmpTypeExposure['part_time']['total_outstanding'] ?? 0);
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

                @include('components.dashboard-count', [
                    'countClass' => 'payroll-cash-advance-exposure',
                    'countLabel' => 'Cash advance exposure',
                    'countSublabel' => 'Outstanding CA balances for ' . $payrollPeriodText,
                    'countIcon' => '<i class="fa-solid fa-sack-dollar"></i>',
                    'countValue' => 'Total ₱ ' . number_format($caTotalOutstanding, 2) . "\n"
                        . 'Reg ₱ ' . number_format($caRegOutstanding, 2) . ' / PT ₱ ' . number_format($caPtOutstanding, 2),
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

        @php
            $approvalsSummaryDefaults = [
                'period_label' => ($filters['period_start'] ?? '') && ($filters['period_end'] ?? '')
                    ? (($filters['period_start'] ?? '') . ' to ' . ($filters['period_end'] ?? ''))
                    : 'selected period',
                'leave' => [
                    'total' => 0,
                    'waiting_supervisor' => 0,
                    'waiting_manager' => 0,
                    'waiting_hr' => 0,
                ],
                'overtime' => [
                    'total' => 0,
                    'total_hours' => 0,
                ],
                'cash_advance' => [
                    'total' => 0,
                    'pending' => 0,
                    'supervisor_approved' => 0,
                    'manager_approved' => 0,
                    'hr_approved' => 0,
                    'total_amount' => 0,
                ],
                'payroll' => [
                    'total' => 0,
                    'waiting_admin' => 0,
                    'waiting_hr' => 0,
                    'pending_net_total' => 0,
                ],
            ];

            $approvalsSummary = array_merge($approvalsSummaryDefaults, $approvalsAnalytics['summary'] ?? []);
            $approvalsPeriodText = $approvalsSummary['period_label'] ?? 'selected period';

            $leaveSummary = $approvalsSummary['leave'] ?? $approvalsSummaryDefaults['leave'];
            $otSummary = $approvalsSummary['overtime'] ?? $approvalsSummaryDefaults['overtime'];
            $caSummary = $approvalsSummary['cash_advance'] ?? $approvalsSummaryDefaults['cash_advance'];
            $prSummary = $approvalsSummary['payroll'] ?? $approvalsSummaryDefaults['payroll'];
        @endphp

        <div class="container {{ $pageClass }} mt-4">
            <div class="analytics-section-header">
                <div>
                    <div class="analytics-section-title">Approvals &amp; risks</div>
                    <div class="analytics-section-subtitle">Pending approvals and exposure for {{ $approvalsPeriodText }}</div>
                </div>
            </div>
        </div>

        <div class="container {{ $pageClass }} summary mb-3 mt-2">

            @include('components.dashboard-count', [
                'countClass' => 'approvals-leave',
                'countLabel' => 'Pending leave requests',
                'countSublabel' => 'Sup ' . ($leaveSummary['waiting_supervisor'] ?? 0)
                    . ', Mgr ' . ($leaveSummary['waiting_manager'] ?? 0)
                    . ', HR ' . ($leaveSummary['waiting_hr'] ?? 0),
                'countIcon' => '<i class="fa-solid fa-plane-departure"></i>',
                'countValue' => (int) ($leaveSummary['total'] ?? 0),
            ])

            @include('components.dashboard-count', [
                'countClass' => 'approvals-overtime',
                'countLabel' => 'Pending overtime',
                'countSublabel' => 'Requests in queue for ' . $approvalsPeriodText,
                'countIcon' => '<i class="fa-solid fa-business-time"></i>',
                'countValue' => ($otSummary['total'] ?? 0) . ' req / ' . number_format((float) ($otSummary['total_hours'] ?? 0), 2) . ' hrs',
            ])

            @include('components.dashboard-count', [
                'countClass' => 'approvals-cash-advance',
                'countLabel' => 'Pending cash advances',
                'countSublabel' => 'Pending ' . ($caSummary['pending'] ?? 0)
                    . ', Sup ' . ($caSummary['supervisor_approved'] ?? 0)
                    . ', Mgr ' . ($caSummary['manager_approved'] ?? 0)
                    . ', HR ' . ($caSummary['hr_approved'] ?? 0),
                'countIcon' => '<i class="fa-solid fa-file-invoice-dollar"></i>',
                'countValue' => (int) ($caSummary['total'] ?? 0) . ' req / ₱ ' . number_format((float) ($caSummary['total_amount'] ?? 0), 2),
            ])

            @include('components.dashboard-count', [
                'countClass' => 'approvals-payroll',
                'countLabel' => 'Pending payroll records',
                'countSublabel' => 'Admin ' . ($prSummary['waiting_admin'] ?? 0)
                    . ', HR ' . ($prSummary['waiting_hr'] ?? 0),
                'countIcon' => '<i class="fa-solid fa-scale-balanced"></i>',
                'countValue' => (int) ($prSummary['total'] ?? 0) . ' rec / ₱ ' . number_format((float) ($prSummary['pending_net_total'] ?? 0), 2),
            ])

        </div>

        @php
            $approvalsOldestTable = $approvalsAnalytics['oldestTable'] ?? [];
            $approvalsByEmployeeTable = $approvalsAnalytics['byEmployeeTable'] ?? [];

            if (empty($approvalsOldestTable)) {
                $approvalsOldestTable = [
                    ['No data found', '—', '—', '—', '—'],
                ];
            }

            if (empty($approvalsByEmployeeTable)) {
                $approvalsByEmployeeTable = [
                    ['No data found', '—', '—', '—', '—', '—'],
                ];
            }

            $roleKey = $roleKey ?? '';
            $byEmployeeLabel = strtolower($roleKey) === 'supervisor'
                ? 'Pending approvals by your crew'
                : 'Pending approvals by employee';
        @endphp

        <div class="container {{ $pageClass }} cards mt-3 mb-3">

            @include('components.dashboard-card', [
                'cardClass' => 'approvals-oldest',
                'label' => 'Oldest pending approvals',
                'viewAll' => route('leave-requests'),
                'tableCol' => [
                    'approval-type',
                    'employee-name',
                    'requested-on',
                    'age-days',
                    'stage',
                ],
                'tableLabel' => [
                    'Type',
                    'Employee',
                    'Requested on',
                    'Age',
                    'Stage',
                ],
                'tableData' => $approvalsOldestTable,
            ])

            @include('components.dashboard-card', [
                'cardClass' => 'approvals-by-employee',
                'label' => $byEmployeeLabel,
                'viewAll' => route('analytics'),
                'tableCol' => [
                    'employee-name',
                    'pending-leave',
                    'pending-overtime',
                    'pending-cash-advance',
                    'pending-payroll',
                    'pending-amount',
                ],
                'tableLabel' => [
                    'Employee',
                    'Leave',
                    'Overtime',
                    'Cash advances',
                    'Payroll',
                    'Total pending',
                ],
                'tableData' => $approvalsByEmployeeTable,
            ])

        </div>

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
