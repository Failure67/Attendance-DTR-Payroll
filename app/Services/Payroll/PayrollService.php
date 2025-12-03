<?php

namespace App\Services\Payroll;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\ContributionBracket;
use App\Models\Payroll;
use App\Repositories\PayrollRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(private PayrollRepository $payrollRepository)
    {
    }

    /**
     * Build preview data for the Process payroll page.
     */
    public function buildProcessPreview(string $periodStart, string $periodEnd): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        $attendanceQuery = Attendance::with('user')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('time_in', [$start, $end]);
            });

        $attendances = $attendanceQuery->get();
        $grouped = $attendances->groupBy('user_id');

        $previewRows = [];
        $totalHoursAll = 0;
        $totalOtAll = 0;

        foreach ($grouped as $userId => $rows) {
            $first = $rows->first();
            $user = $first ? $first->user : null;
            $employeeName = $user ? ($user->full_name ?? $user->username) : 'Unknown employee';

            $regularHours = 0;
            $overtimeHours = 0;
            $presentDays = 0;
            $absentDays = 0;
            $leaveDays = 0;
            $employeeAnomalies = [];

            foreach ($rows as $attendance) {
                $total = (float) $attendance->total_hours;
                $ot = (float) $attendance->overtime_hours;
                $regular = max(0, $total - $ot);

                $regularHours += $regular;
                $overtimeHours += $ot;

                $status = $attendance->status ?? 'Present';

                $dateLabel = $attendance->date
                    ? $attendance->date->format('Y-m-d')
                    : ($attendance->time_in ? $attendance->time_in->format('Y-m-d') : 'N/A');

                if (in_array($status, ['Present', 'Late'], true)) {
                    $presentDays++;
                } elseif ($status === 'On leave') {
                    $leaveDays++;
                } elseif ($status === 'Absent') {
                    $absentDays++;
                }

                if ($status === 'Absent' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                    $employeeAnomalies[] = 'Absent but has recorded time/hours on ' . $dateLabel;
                }

                if (in_array($status, ['Present', 'Late'], true) && $total <= 0) {
                    $employeeAnomalies[] = 'Present/late but with 0 hours on ' . $dateLabel;
                }

                if (in_array($status, ['Present', 'Late'], true) && $attendance->time_in && !$attendance->time_out) {
                    $employeeAnomalies[] = 'Missing time-out on ' . $dateLabel;
                }

                if ($status === 'On leave' && ($total > 0 || $attendance->time_in || $attendance->time_out)) {
                    $employeeAnomalies[] = 'On leave but has recorded time/hours on ' . $dateLabel;
                }
            }

            $totalHoursAll += $regularHours + $overtimeHours;
            $totalOtAll += $overtimeHours;

            $lastPayroll = $this->payrollRepository->getLastPayrollForUser((int) $userId);

            $totalAdvances = (float) CashAdvance::where('user_id', $userId)
                ->where('type', 'advance')
                ->sum('amount');

            $totalRepayments = (float) CashAdvance::where('user_id', $userId)
                ->where('type', 'repayment')
                ->sum('amount');

            $caBalance = max(0, $totalAdvances - $totalRepayments);

            $otAlertThreshold = 40;
            if ($overtimeHours > $otAlertThreshold) {
                $employeeAnomalies[] = 'High overtime for period (' . number_format($overtimeHours, 2) . 'h)';
            }

            $previewRows[] = [
                'user_id' => $userId,
                'employee_name' => $employeeName,
                'regular_hours' => $regularHours,
                'overtime_hours' => $overtimeHours,
                'absent_days' => $absentDays,
                'present_days' => $presentDays,
                'leave_days' => $leaveDays,
                'anomalies' => $employeeAnomalies,
                'last_wage_type' => $lastPayroll->wage_type ?? 'Daily',
                'last_min_wage' => (float) ($lastPayroll->min_wage ?? 0),
                'ca_balance' => $caBalance,
            ];
        }

        $previewSummary = [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'employee_count' => count($previewRows),
            'total_hours' => $totalHoursAll,
            'total_ot' => $totalOtAll,
        ];

        return [$start->toDateString(), $end->toDateString(), $previewRows, $previewSummary];
    }

    /**
     * Process payrolls from attendance preview data.
     */
    public function processFromAttendance(array $validated): void
    {
        DB::transaction(function () use ($validated) {
            $periodStart = Carbon::parse($validated['period_start'])->toDateString();
            $periodEnd = Carbon::parse($validated['period_end'])->toDateString();

            foreach ($validated['rows'] as $row) {
                if (empty($row['include'])) {
                    continue;
                }

                $userId = (int) $row['user_id'];
                $wageType = $row['wage_type'];
                $minWage = (float) $row['min_wage'];
                $regularHours = (float) $row['regular_hours'];
                $overtimeHours = (float) $row['overtime_hours'];
                $absentDays = (float) $row['absent_days'];
                $presentDays = (float) $row['present_days'];
                $requestedCaDeduction = isset($row['ca_deduction']) ? (float) $row['ca_deduction'] : 0.0;

                if ($requestedCaDeduction < 0) {
                    $requestedCaDeduction = 0.0;
                }

                $unitsWorked = 0;
                $hoursWorked = null;
                $daysWorked = null;

                switch ($wageType) {
                    case 'Hourly':
                    case 'Piece rate':
                        $unitsWorked = $regularHours + $overtimeHours;
                        $hoursWorked = $unitsWorked;
                        break;
                    case 'Daily':
                    case 'Weekly':
                    case 'Monthly':
                        $unitsWorked = $presentDays;
                        $daysWorked = $unitsWorked;
                        break;
                }

                $grossPay = $minWage * $unitsWorked;

                $sss = ContributionBracket::calculateAmount('SSS', $grossPay);
                $philhealth = ContributionBracket::calculateAmount('PhilHealth', $grossPay);
                $pagibig = ContributionBracket::calculateAmount('Pag-IBIG', $grossPay);

                $totalContrib = $sss + $philhealth + $pagibig;

                $totalAdvances = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'advance')
                    ->sum('amount');

                $totalRepayments = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'repayment')
                    ->sum('amount');

                $outstandingCa = max(0, $totalAdvances - $totalRepayments);

                $maxCaByBalance = $outstandingCa;
                $maxCaByNet = max(0, $grossPay - $totalContrib);

                $caDeduction = min($requestedCaDeduction, $maxCaByBalance, $maxCaByNet);

                if ($caDeduction < 0 || !is_finite($caDeduction)) {
                    $caDeduction = 0.0;
                }

                $totalDeductions = $totalContrib + $caDeduction;
                $netPay = $grossPay - $totalDeductions;

                $payroll = $this->payrollRepository->createPayroll([
                    'user_id' => $userId,
                    'wage_type' => $wageType,
                    'min_wage' => $minWage,
                    'hours_worked' => $hoursWorked,
                    'days_worked' => $daysWorked,
                    'regular_hours' => $regularHours,
                    'overtime_hours' => $overtimeHours,
                    'absent_days' => $absentDays,
                    'gross_pay' => $grossPay,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => $netPay,
                    'status' => 'Pending',
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ]);

                if ($sss > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'SSS', $sss);
                }

                if ($philhealth > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'PhilHealth', $philhealth);
                }

                if ($pagibig > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'Pag-IBIG', $pagibig);
                }

                if ($caDeduction > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'Cash advance', $caDeduction);
                }
            }
        });
    }

    /**
     * Create a manual payroll entry (New payroll form).
     */
    public function createManualPayroll(array $validated): Payroll
    {
        return DB::transaction(function () use ($validated) {
            $wageType = $validated['wage_type'];
            $minWage = $validated['min_wage'];
            $unitsWorked = $validated['units_worked'];

            $grossPay = 0;
            $hoursWorked = null;
            $daysWorked = null;

            switch ($wageType) {
                case 'Hourly':
                    $hoursWorked = $unitsWorked;
                    $grossPay = $minWage * $hoursWorked;
                    break;
                case 'Daily':
                case 'Weekly':
                case 'Monthly':
                    $daysWorked = $unitsWorked;
                    $grossPay = $minWage * $daysWorked;
                    break;
                case 'Piece rate':
                    $hoursWorked = $unitsWorked;
                    $grossPay = $minWage * $unitsWorked;
                    break;
            }

            $totalDeductions = 0;
            if (!empty($validated['deductions'])) {
                $totalDeductions = array_sum(array_column($validated['deductions'], 'amount'));
            }

            $netPay = $grossPay - $totalDeductions;

            $statusUi = $validated['status'] ?? 'Pending';
            $payroll = $this->payrollRepository->createPayroll([
                'user_id' => $validated['user_id'],
                'wage_type' => $wageType,
                'min_wage' => $minWage,
                'hours_worked' => $hoursWorked,
                'days_worked' => $daysWorked,
                'gross_pay' => $grossPay,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
                'status' => 'Pending',
            ]);

            if (!empty($validated['deductions'])) {
                $this->payrollRepository->replaceDeductions($payroll, $validated['deductions']);
            }

            $this->applyStatusAndSyncCashAdvance($payroll, $statusUi);

            return $payroll;
        });
    }

    /**
     * Update an existing manual payroll entry.
     */
    public function updateManualPayroll(Payroll $payroll, array $validated): Payroll
    {
        return DB::transaction(function () use ($payroll, $validated) {
            $wageType = $validated['wage_type'];
            $minWage = $validated['min_wage'];
            $unitsWorked = $validated['units_worked'];

            $grossPay = 0;
            $hoursWorked = null;
            $daysWorked = null;

            switch ($wageType) {
                case 'Hourly':
                    $hoursWorked = $unitsWorked;
                    $grossPay = $minWage * $hoursWorked;
                    break;
                case 'Daily':
                case 'Weekly':
                case 'Monthly':
                    $daysWorked = $unitsWorked;
                    $grossPay = $minWage * $daysWorked;
                    break;
                case 'Piece rate':
                    $hoursWorked = $unitsWorked;
                    $grossPay = $minWage * $unitsWorked;
                    break;
            }

            $totalDeductions = 0;
            if (!empty($validated['deductions'])) {
                $totalDeductions = array_sum(array_column($validated['deductions'], 'amount'));
            }

            $netPay = $grossPay - $totalDeductions;

            $statusUi = $validated['status'] ?? 'Pending';

            $updatedPayroll = $this->payrollRepository->updatePayroll($payroll, [
                'user_id' => $validated['user_id'],
                'wage_type' => $wageType,
                'min_wage' => $minWage,
                'hours_worked' => $hoursWorked,
                'days_worked' => $daysWorked,
                'gross_pay' => $grossPay,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
            ]);

            $this->payrollRepository->replaceDeductions($updatedPayroll, $validated['deductions'] ?? []);

            $this->applyStatusAndSyncCashAdvance($updatedPayroll, $statusUi);

            return $updatedPayroll;
        });
    }

    /**
     * Update payroll status and synchronize cash advance repayments linked to this payroll.
     *
     * UI uses: Pending / Completed / Cancelled.
     * DB uses: Pending / Released / Cancelled (Completed => Released).
     *
     * Cash advance repayments (source = 'payroll') should only exist while
     * the payroll is marked as Released. When moving away from Released,
     * repayments are removed; when moving into Released, repayments are
     * (re)created based on the current "Cash advance" deductions.
     */
    public function updatePayrollStatus(Payroll $payroll, string $statusUi): void
    {
        DB::transaction(function () use ($payroll, $statusUi) {
            $this->applyStatusAndSyncCashAdvance($payroll, $statusUi);
        });
    }

    private function applyStatusAndSyncCashAdvance(Payroll $payroll, string $statusUi): void
    {
        $statusDb = $statusUi === 'Completed' ? 'Released' : $statusUi;

        CashAdvance::where('user_id', $payroll->user_id)
            ->where('source', 'payroll')
            ->where('payroll_id', $payroll->id)
            ->delete();

        if ($statusDb === 'Released') {
            $caDeductionTotal = (float) $payroll->deductions()
                ->where('deduction_name', 'Cash advance')
                ->sum('amount');

            if ($caDeductionTotal > 0) {
                $this->payrollRepository->recordCashAdvanceRepayment(
                    (int) $payroll->user_id,
                    $payroll,
                    $caDeductionTotal,
                );
            }
        }

        $payroll->status = $statusDb;
        $payroll->save();
    }
}
