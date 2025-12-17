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
                'leave_days' => 0,
                'employee_count' => 0,
                'anomaly_count' => 0,
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
                <tr><td>Worked / Absent / Leave days</td><td class="text-right">{{ $attendanceSummary['worked_days'] }} / {{ $attendanceSummary['absent_days'] }} / {{ $attendanceSummary['leave_days'] }}</td></tr>
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
                'period_label' => ($filters['period_start'] ?? '') && ($filters['period_end'] ?? '')
                    ? (($filters['period_start'] ?? '') . ' to ' . ($filters['period_end'] ?? ''))
                    : 'selected period',
            ];
            $payrollSummary = array_merge($payrollSummaryDefaults, $payrollAnalytics['summary'] ?? []);
            $payrollPeriodText = $payrollSummary['period_label'] ?? 'selected period';
            $statusBreakdown = $payrollSummary['status_breakdown'] ?? $payrollSummaryDefaults['status_breakdown'];

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
</body>
</html>
