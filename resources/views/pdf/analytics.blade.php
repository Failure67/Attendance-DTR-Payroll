<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Analytics Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 18px; margin-bottom: 6px; }
        .meta { font-size: 10px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; }
        th { background: #f2f2f2; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .section-separator { margin-top: 12px; margin-bottom: 8px; border-top: 1px solid #ccc; }

        /* Simple bar-chart styles for PDF (no JS) */
        .chart-table th, .chart-table td { border: none; padding: 3px 4px; }
        .chart-bar-container { width: 100%; height: 10px; background: #e5e5e5; }
        .chart-bar { height: 100%; display: block; }
        .chart-bar-total { background: #2f80ed; }
        .chart-bar-ot { background: #f2994a; }
        .chart-bar-gross { background: #2f80ed; }
        .chart-bar-net { background: #27ae60; }
        .chart-value { font-size: 9px; text-align: right; margin-top: 2px; }
        .chart-legend { font-size: 9px; margin-bottom: 4px; }
        .chart-legend span { display: inline-block; margin-right: 8px; }
        .chart-legend-color { width: 8px; height: 8px; display: inline-block; margin-right: 4px; }
        .chart-legend-total { background: #2f80ed; }
        .chart-legend-ot { background: #f2994a; }
        .chart-legend-gross { background: #2f80ed; }
        .chart-legend-net { background: #27ae60; }
    </style>
</head>
<body>
    @php
        $filters = $filters ?? [];
        $mode = $mode ?? 'combined';
        $employeeOptions = $employeeOptions ?? [];

        $employeeLabel = 'All employees';
        if (!empty($filters['employee_id'])) {
            $employeeLabel = $employeeOptions[$filters['employee_id']] ?? ('Employee #' . $filters['employee_id']);
        }
    @endphp

    <h1>{{ $title ?? 'Analytics Report' }}</h1>
    <div class="meta">
        Generated: {{ ($generatedAt ?? now())->format('Y-m-d H:i') }}<br>
        Employee: {{ $employeeLabel }}<br>
        @if(!empty($filters['period_start']) || !empty($filters['period_end']))
            Period:
            @if(!empty($filters['period_start']) && !empty($filters['period_end']))
                {{ $filters['period_start'] }} to {{ $filters['period_end'] }}
            @elseif(!empty($filters['period_start']))
                From {{ $filters['period_start'] }}
            @else
                Up to {{ $filters['period_end'] }}
            @endif
            <br>
        @endif
    </div>

    {{-- Attendance analytics section --}}
    @if(($mode === 'attendance' || $mode === 'combined') && !empty($attendanceAnalytics))
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

            $chart = $attendanceAnalytics['chart'] ?? ['labels' => [], 'totalHours' => [], 'overtimeHours' => []];
            $labels = $chart['labels'] ?? [];
            $totalHoursSeries = $chart['totalHours'] ?? [];
            $overtimeSeries = $chart['overtimeHours'] ?? [];

            $topOvertimeTable = $attendanceAnalytics['topOvertimeTable'] ?? [];
            $topAbsenceTable = $attendanceAnalytics['topAbsenceTable'] ?? [];

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

        <div class="section-separator"></div>
        <h2>Attendance analytics ({{ $attendancePeriodText }})</h2>

        <table>
            <thead>
                <tr>
                    <th>Metric</th>
                    <th class="text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Total hours</td><td class="text-right">{{ number_format($attendanceSummary['total_hours'], 2) }}</td></tr>
                <tr><td>Overtime hours</td><td class="text-right">{{ number_format($attendanceSummary['total_overtime'], 2) }}</td></tr>
                <tr><td>Attendance rate</td><td class="text-right">{{ $attendanceSummary['attendance_rate'] }}% ({{ $attendanceSummary['records'] }} records)</td></tr>
                <tr><td>Worked / Absent (incl AWOL) / Leave days</td><td class="text-right">{{ $attendanceSummary['worked_days'] }} / {{ $attendanceSummary['absent_days'] }} / {{ $attendanceSummary['leave_days'] }}</td></tr>
                <tr><td>AWOL days</td><td class="text-right">{{ $attendanceSummary['awol_days'] }}</td></tr>
                <tr><td>Regular / Part-time AWOL days</td><td class="text-right">{{ $attRegAwol }} / {{ $attPtAwol }}</td></tr>
                <tr><td>Paid leave days (approx)</td><td class="text-right">{{ number_format($leavePaidDays, 3) }}</td></tr>
                <tr><td>Unpaid leave days (approx)</td><td class="text-right">{{ number_format($leaveUnpaidDays, 3) }}</td></tr>
                <tr><td>Employees covered</td><td class="text-right">{{ $attendanceSummary['employee_count'] }}</td></tr>
                <tr><td>Anomalies detected</td><td class="text-right">{{ $attendanceSummary['anomaly_count'] }}</td></tr>
            </tbody>
        </table>

        @if(count($labels))
            @php
                $maxAttendanceTotal = max($totalHoursSeries ?: [0]);
                if ($maxAttendanceTotal <= 0) {
                    $maxAttendanceTotal = 1;
                }
            @endphp
            <h3>Attendance trend (daily)</h3>
            <div class="chart-legend">
                <span><span class="chart-legend-color chart-legend-total"></span>Total hours</span>
                <span><span class="chart-legend-color chart-legend-ot"></span>Overtime hours</span>
            </div>
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Total hours</th>
                        <th>Overtime hours</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($labels as $idx => $label)
                        @php
                            $total = (float)($totalHoursSeries[$idx] ?? 0);
                            $ot = (float)($overtimeSeries[$idx] ?? 0);
                            $totalWidth = max(0, min(100, ($total / $maxAttendanceTotal) * 100));
                            $otWidth = max(0, min(100, ($ot / $maxAttendanceTotal) * 100));
                        @endphp
                        <tr>
                            <td class="text-center">{{ $label }}</td>
                            <td>
                                <div class="chart-bar-container">
                                    <span class="chart-bar chart-bar-total" style="width: {{ $totalWidth }}%;"></span>
                                </div>
                                <div class="chart-value">{{ number_format($total, 2) }}</div>
                            </td>
                            <td>
                                <div class="chart-bar-container">
                                    <span class="chart-bar chart-bar-ot" style="width: {{ $otWidth }}%;"></span>
                                </div>
                                <div class="chart-value">{{ number_format($ot, 2) }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h3>Top overtime (by employee)</h3>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="text-right">Overtime hours</th>
                    <th class="text-right">Total hours</th>
                    <th class="text-right">Worked days</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topOvertimeTable as $row)
                    <tr>
                        <td>{{ $row[0] ?? '' }}</td>
                        <td class="text-right">{{ $row[1] ?? '' }}</td>
                        <td class="text-right">{{ $row[2] ?? '' }}</td>
                        <td class="text-right">{{ $row[3] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3>Top absence and leave (by employee)</h3>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="text-right">Absent days</th>
                    <th class="text-right">Late days</th>
                    <th class="text-right">Leave days</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topAbsenceTable as $row)
                    <tr>
                        <td>{{ $row[0] ?? '' }}</td>
                        <td class="text-right">{{ $row[1] ?? '' }}</td>
                        <td class="text-right">{{ $row[2] ?? '' }}</td>
                        <td class="text-right">{{ $row[3] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    {{-- Payroll analytics section --}}
    @if(($mode === 'payroll' || $mode === 'combined') && !empty($payrollAnalytics))
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
            $caHeadWithBalance = (int) ($caExposure['employee_count_with_balance'] ?? 0);

            $chart = $payrollAnalytics['chart'] ?? ['labels' => [], 'gross' => [], 'net' => []];
            $pLabels = $chart['labels'] ?? [];
            $pGrossSeries = $chart['gross'] ?? [];
            $pNetSeries = $chart['net'] ?? [];

            $topNetPayTable = $payrollAnalytics['topNetPayTable'] ?? [];
            $cashAdvanceTableData = $payrollAnalytics['cashAdvanceTableData'] ?? [];
        @endphp

        <div class="section-separator"></div>
        <h2>Payroll analytics ({{ $payrollPeriodText }})</h2>

        <table>
            <thead>
                <tr>
                    <th>Metric</th>
                    <th class="text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Total gross pay</td><td class="text-right">₱ {{ number_format($payrollSummary['total_gross'], 2) }}</td></tr>
                <tr><td>Total deductions</td><td class="text-right">₱ {{ number_format($payrollSummary['total_deductions'], 2) }}</td></tr>
                <tr><td>Total net pay</td><td class="text-right">₱ {{ number_format($payrollSummary['total_net'], 2) }}</td></tr>
                <tr><td>Employees paid</td><td class="text-right">{{ $payrollSummary['employee_count'] }}</td></tr>
                <tr><td>Regular employees / net</td><td class="text-right">{{ $regHead }} / ₱ {{ number_format($regNet, 2) }}</td></tr>
                <tr><td>Part-time employees / net</td><td class="text-right">{{ $ptHead }} / ₱ {{ number_format($ptNet, 2) }}</td></tr>
                <tr><td>Cash advance outstanding (total)</td><td class="text-right">₱ {{ number_format($caTotalOutstanding, 2) }}</td></tr>
                <tr><td>CA outstanding (Reg / PT)</td><td class="text-right">₱ {{ number_format($caRegOutstanding, 2) }} / ₱ {{ number_format($caPtOutstanding, 2) }}</td></tr>
                <tr><td>Employees with CA balance</td><td class="text-right">{{ $caHeadWithBalance }}</td></tr>
                <tr><td>Payroll records</td><td class="text-right">{{ $payrollSummary['payroll_count'] }}</td></tr>
                <tr><td>Average net per employee</td><td class="text-right">₱ {{ number_format($payrollSummary['avg_net_per_employee'], 2) }}</td></tr>
                <tr><td>Average net per payroll</td><td class="text-right">₱ {{ number_format($payrollSummary['avg_net_per_payroll'], 2) }}</td></tr>
                <tr><td>Pending payrolls / net</td><td class="text-right">{{ $statusBreakdown['pending']['count'] ?? 0 }} / ₱ {{ number_format($statusBreakdown['pending']['net'] ?? 0, 2) }}</td></tr>
                <tr><td>Released payrolls / net</td><td class="text-right">{{ $statusBreakdown['released']['count'] ?? 0 }} / ₱ {{ number_format($statusBreakdown['released']['net'] ?? 0, 2) }}</td></tr>
                <tr><td>Cancelled payrolls / net</td><td class="text-right">{{ $statusBreakdown['cancelled']['count'] ?? 0 }} / ₱ {{ number_format($statusBreakdown['cancelled']['net'] ?? 0, 2) }}</td></tr>
            </tbody>
        </table>

        @if(count($pLabels))
            @php
                $maxPayrollGross = max($pGrossSeries ?: [0]);
                if ($maxPayrollGross <= 0) {
                    $maxPayrollGross = 1;
                }
            @endphp
            <h3>Payroll trend (monthly)</h3>
            <div class="chart-legend">
                <span><span class="chart-legend-color chart-legend-gross"></span>Gross pay</span>
                <span><span class="chart-legend-color chart-legend-net"></span>Net pay</span>
            </div>
            <table class="chart-table">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Gross pay</th>
                        <th>Net pay</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pLabels as $idx => $label)
                        @php
                            $gross = (float)($pGrossSeries[$idx] ?? 0);
                            $net = (float)($pNetSeries[$idx] ?? 0);
                            $grossWidth = max(0, min(100, ($gross / $maxPayrollGross) * 100));
                            $netWidth = max(0, min(100, ($net / $maxPayrollGross) * 100));
                        @endphp
                        <tr>
                            <td class="text-center">{{ $label }}</td>
                            <td>
                                <div class="chart-bar-container">
                                    <span class="chart-bar chart-bar-gross" style="width: {{ $grossWidth }}%;"></span>
                                </div>
                                <div class="chart-value">₱ {{ number_format($gross, 2) }}</div>
                            </td>
                            <td>
                                <div class="chart-bar-container">
                                    <span class="chart-bar chart-bar-net" style="width: {{ $netWidth }}%;"></span>
                                </div>
                                <div class="chart-value">₱ {{ number_format($net, 2) }}</div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <h3>Top net pay (by employee)</h3>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="text-right">Total net pay</th>
                    <th class="text-right">Total gross pay</th>
                    <th class="text-right">Payroll count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($topNetPayTable as $row)
                    <tr>
                        <td>{{ $row[0] ?? '' }}</td>
                        <td class="text-right">{{ $row[1] ?? '' }}</td>
                        <td class="text-right">{{ $row[2] ?? '' }}</td>
                        <td class="text-right">{{ $row[3] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3>Cash advance balances (by employee)</h3>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="text-right">Total advances</th>
                    <th class="text-right">Total repayments</th>
                    <th class="text-right">Outstanding balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cashAdvanceTableData as $row)
                    <tr>
                        <td>{{ $row[0] ?? '' }}</td>
                        <td class="text-right">{{ $row[1] ?? '' }}</td>
                        <td class="text-right">{{ $row[2] ?? '' }}</td>
                        <td class="text-right">{{ $row[3] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center">No data available.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    {{-- Pending approvals & risks section --}}
    @if(!empty($approvalsAnalytics))
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

            $approvalsOldestTable = $approvalsAnalytics['oldestTable'] ?? [];
            $approvalsByEmployeeTable = $approvalsAnalytics['byEmployeeTable'] ?? [];

            $roleKey = strtolower($roleKey ?? '');
            $byEmployeeLabel = $roleKey === 'supervisor'
                ? 'Pending approvals by your crew'
                : 'Pending approvals by employee';
        @endphp

        <div class="section-separator"></div>
        <h2>Pending approvals &amp; risks ({{ $approvalsPeriodText }})</h2>

        <table>
            <thead>
                <tr>
                    <th>Area</th>
                    <th class="text-right">Details</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Leave requests</td>
                    <td class="text-right">
                        {{ $leaveSummary['total'] ?? 0 }} pending
                        (Sup {{ $leaveSummary['waiting_supervisor'] ?? 0 }},
                        Mgr {{ $leaveSummary['waiting_manager'] ?? 0 }},
                        HR {{ $leaveSummary['waiting_hr'] ?? 0 }})
                    </td>
                </tr>
                <tr>
                    <td>Overtime entries</td>
                    <td class="text-right">
                        {{ $otSummary['total'] ?? 0 }} pending /
                        {{ number_format($otSummary['total_hours'] ?? 0, 2) }} hours
                    </td>
                </tr>
                <tr>
                    <td>Cash advance requests</td>
                    <td class="text-right">
                        {{ $caSummary['total'] ?? 0 }} pending
                        (Pending {{ $caSummary['pending'] ?? 0 }},
                        Sup {{ $caSummary['supervisor_approved'] ?? 0 }},
                        Mgr {{ $caSummary['manager_approved'] ?? 0 }},
                        HR {{ $caSummary['hr_approved'] ?? 0 }})
                        / ₱ {{ number_format($caSummary['total_amount'] ?? 0, 2) }}
                    </td>
                </tr>
                <tr>
                    <td>Payroll records</td>
                    <td class="text-right">
                        {{ $prSummary['total'] ?? 0 }} pending
                        (Admin {{ $prSummary['waiting_admin'] ?? 0 }},
                        HR {{ $prSummary['waiting_hr'] ?? 0 }})
                        / ₱ {{ number_format($prSummary['pending_net_total'] ?? 0, 2) }}
                    </td>
                </tr>
            </tbody>
        </table>

        <h3>Oldest pending approvals</h3>
        <table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Employee</th>
                    <th class="text-center">Requested on</th>
                    <th class="text-right">Age</th>
                    <th>Stage</th>
                </tr>
            </thead>
            <tbody>
                @forelse($approvalsOldestTable as $row)
                    <tr>
                        <td>{{ $row[0] ?? '' }}</td>
                        <td>{{ $row[1] ?? '' }}</td>
                        <td class="text-center">{{ $row[2] ?? '' }}</td>
                        <td class="text-right">{{ $row[3] ?? '' }}</td>
                        <td>{{ $row[4] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">No pending approvals found.</td></tr>
                @endforelse
            </tbody>
        </table>

        <h3>{{ $byEmployeeLabel }}</h3>
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th class="text-right">Leave</th>
                    <th class="text-right">Overtime</th>
                    <th class="text-right">Cash advances</th>
                    <th class="text-right">Payroll</th>
                    <th class="text-right">Total pending (₱)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($approvalsByEmployeeTable as $row)
                    <tr>
                        <td>{{ $row[0] ?? '' }}</td>
                        <td class="text-right">{{ $row[1] ?? '' }}</td>
                        <td class="text-right">{{ $row[2] ?? '' }}</td>
                        <td class="text-right">{{ $row[3] ?? '' }}</td>
                        <td class="text-right">{{ $row[4] ?? '' }}</td>
                        <td class="text-right">{{ $row[5] ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center">No pending approvals found.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>
