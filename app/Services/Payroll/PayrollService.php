<?php

namespace App\Services\Payroll;

use App\Models\Attendance;
use App\Models\ApprovalLog;
use App\Models\CashAdvance;
use App\Models\ContributionBracket;
use App\Models\LeaveEntry;
use App\Models\OvertimeEntry;
use App\Models\Payroll;
use App\Models\User;
use App\Repositories\PayrollRepository;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

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
                $ot = $attendance->overtime_approved ? (float) $attendance->overtime_hours : 0.0;
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

            $periodStartDate = Carbon::parse($periodStart);
            $periodEndDate = Carbon::parse($periodEnd);
            $periodDays = max(1, $periodStartDate->diffInDays($periodEndDate) + 1);
            $applySss = $this->shouldApplySssForPeriod($periodStartDate, $periodEndDate);

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
                $leaveDaysAttendance = (float) ($row['leave_days'] ?? 0.0);
                $requestedCaDeduction = isset($row['ca_deduction']) ? (float) $row['ca_deduction'] : 0.0;

                if ($requestedCaDeduction < 0) {
                    $requestedCaDeduction = 0.0;
                }

                $unitsWorked = 0;
                $hoursWorked = null;
                $daysWorked = null;

                $standardDailyHours = (float) config('attendance.standard_daily_hours', 8);
                $hourlyRate = $this->calculateHourlyRate($wageType, $minWage, $standardDailyHours);
                $dailyRate = $this->calculateDailyRate($wageType, $minWage, $standardDailyHours);

                [$paidLeaveDays, $unpaidLeaveDays, $leaveEntryDetails] = $this->calculateLeaveForPeriod(
                    $userId,
                    $periodStartDate,
                    $periodEndDate,
                );

                switch ($wageType) {
                    case 'Hourly':
                    case 'Piece rate':
                        $paidLeaveHours = $paidLeaveDays * $standardDailyHours;
                        $unitsWorked = $regularHours + $overtimeHours + $paidLeaveHours;
                        $hoursWorked = $unitsWorked;
                        break;
                    case 'Daily':
                    case 'Weekly':
                    case 'Monthly':
                        $effectivePaidDays = $presentDays + $paidLeaveDays;
                        $unitsWorked = $effectivePaidDays;
                        $daysWorked = $unitsWorked;
                        break;
                }

                $baseGrossPay = $minWage * $unitsWorked;

                [$otPremiumTotal, $otPremiumDetails] = $this->calculateOvertimePremiumForPeriod(
                    $userId,
                    $periodStartDate,
                    $periodEndDate,
                    $hourlyRate,
                );

                $grossPay = $baseGrossPay + $otPremiumTotal;

                $sss = $applySss ? ContributionBracket::calculateAmount('SSS', $grossPay) : 0.0;
                $philhealth = ContributionBracket::calculateAmount('PhilHealth', $grossPay);
                $pagibig = ContributionBracket::calculateAmount('Pag-IBIG', $grossPay);

                $totalContrib = $sss + $philhealth + $pagibig;
                $incomeTax = $this->calculateSimpleIncomeTax($grossPay, $periodDays);

                $totalAdvances = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'advance')
                    ->sum('amount');

                $totalRepayments = (float) CashAdvance::where('user_id', $userId)
                    ->where('type', 'repayment')
                    ->sum('amount');

                $outstandingCa = max(0, $totalAdvances - $totalRepayments);

                $maxCaByBalance = $outstandingCa;
                $maxCaByNet = max(0, $grossPay - $totalContrib - $incomeTax);

                // Enforce company policy: at least the configured minimum CA
                // deduction per payroll while there is an outstanding balance,
                // subject to available net pay. This reflects the documented
                // "minimum 500 per pay" rule but allows configuration.
                $caConfig = (array) config('payroll.ca', []);
                $minCaDeduction = isset($caConfig['min_deduction'])
                    ? (float) $caConfig['min_deduction']
                    : 500.0;

                $enforcedRequestedCa = $requestedCaDeduction;

                if ($outstandingCa > 0 && $maxCaByNet > 0 && $minCaDeduction > 0) {
                    // Required minimum is the configured amount, but cannot exceed
                    // outstanding balance or the maximum we can deduct without
                    // driving net pay negative.
                    $requiredCa = min($minCaDeduction, $outstandingCa, $maxCaByNet);

                    if ($requiredCa > 0 && $enforcedRequestedCa < $requiredCa) {
                        $enforcedRequestedCa = $requiredCa;
                    }
                }

                $caDeduction = min($enforcedRequestedCa, $maxCaByBalance, $maxCaByNet);

                if ($caDeduction < 0 || !is_finite($caDeduction)) {
                    $caDeduction = 0.0;
                }

                $totalDeductions = $totalContrib + $incomeTax + $caDeduction;
                $netPay = $grossPay - $totalDeductions;

                $snapshot = [
                    'source' => 'attendance_run',
                    'period' => [
                        'start' => $periodStart,
                        'end' => $periodEnd,
                        'days' => $periodDays,
                    ],
                    'inputs' => [
                        'wage_type' => $wageType,
                        'min_wage' => $minWage,
                        'regular_hours' => $regularHours,
                        'overtime_hours' => $overtimeHours,
                        'absent_days' => $absentDays,
                        'present_days' => $presentDays,
                        'leave_days_attendance' => $leaveDaysAttendance,
                        'leave_paid_days_ledger' => $paidLeaveDays,
                        'leave_unpaid_days_ledger' => $unpaidLeaveDays,
                    ],
                    'contributions' => [
                        'sss' => $sss,
                        'philhealth' => $philhealth,
                        'pagibig' => $pagibig,
                        'income_tax' => $incomeTax,
                    ],
                    'cash_advance' => [
                        'outstanding_before' => $outstandingCa,
                        'requested_deduction' => $requestedCaDeduction,
                        'enforced_requested_deduction' => $enforcedRequestedCa,
                        'deduction_applied' => $caDeduction,
                    ],
                    'computed' => [
                        'gross_pay' => $grossPay,
                        'total_deductions' => $totalDeductions,
                        'net_pay' => $netPay,
                    ],
                    'overtime_premium' => [
                        'total' => $otPremiumTotal,
                        'entries' => $otPremiumDetails,
                    ],
                    'leave_ledger' => [
                        'paid_days' => $paidLeaveDays,
                        'unpaid_days' => $unpaidLeaveDays,
                        'entries' => $leaveEntryDetails,
                    ],
                ];

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
                    'snapshot' => $snapshot,
                ]);

                // Link OT premium entries to this payroll and record the premium amount
                foreach ($otPremiumDetails as $detail) {
                    if (!empty($detail['entry_id']) && isset($detail['premium_amount'])) {
                        OvertimeEntry::where('id', (int) $detail['entry_id'])
                            ->whereNull('payroll_id')
                            ->update([
                                'payroll_id' => $payroll->id,
                                'premium_amount' => $detail['premium_amount'],
                            ]);
                    }
                }

                // Link leave ledger entries to this payroll and record the paid amount per entry
                foreach ($leaveEntryDetails as $detail) {
                    if (empty($detail['entry_id'])) {
                        continue;
                    }

                    $durationDays = isset($detail['duration_days']) ? (float) $detail['duration_days'] : 0.0;
                    if ($durationDays <= 0) {
                        continue;
                    }

                    $isPaid = !empty($detail['is_paid']);
                    $paidAmount = $isPaid ? round($dailyRate * $durationDays, 2) : 0.0;

                    LeaveEntry::where('id', (int) $detail['entry_id'])
                        ->whereNull('payroll_id')
                        ->update([
                            'payroll_id' => $payroll->id,
                            'paid_amount' => $paidAmount,
                        ]);
                }

                if ($sss > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'SSS', $sss);
                }

                if ($philhealth > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'PhilHealth', $philhealth);
                }

                if ($pagibig > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'Pag-IBIG', $pagibig);
                }

                if ($incomeTax > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'Income tax', $incomeTax);
                }

                if ($caDeduction > 0) {
                    $this->payrollRepository->addDeduction($payroll, 'Cash advance', $caDeduction);
                }
            }
        });
    }

    /**
     * Check whether a payroll's period still has pending leave or overtime
     * approvals for its employee.
     *
     * Returns [hasAnyPending, hasPendingLeave, hasPendingOvertime].
     */
    public function hasPendingApprovalsForPayroll(Payroll $payroll): array
    {
        $userId = (int) $payroll->user_id;

        if (!$userId || !$payroll->period_start || !$payroll->period_end) {
            return [false, false, false];
        }

        $periodStart = Carbon::parse($payroll->period_start)->startOfDay();
        $periodEnd = Carbon::parse($payroll->period_end)->endOfDay();

        $hasPendingLeave = LeaveEntry::where('user_id', $userId)
            ->where('status', 'pending')
            ->whereDate('date_start', '<=', $periodEnd->toDateString())
            ->whereDate('date_end', '>=', $periodStart->toDateString())
            ->exists();

        $hasPendingOvertime = OvertimeEntry::where('user_id', $userId)
            ->whereIn('status', ['pending', 'pending_supervisor', 'pending_manager'])
            ->whereDate('date', '>=', $periodStart->toDateString())
            ->whereDate('date', '<=', $periodEnd->toDateString())
            ->exists();

        $hasAny = $hasPendingLeave || $hasPendingOvertime;

        return [$hasAny, $hasPendingLeave, $hasPendingOvertime];
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
            $snapshot = [
                'source' => 'manual_entry',
                'inputs' => [
                    'wage_type' => $wageType,
                    'min_wage' => $minWage,
                    'units_worked' => $unitsWorked,
                    'status_ui' => $statusUi,
                ],
                'deductions' => $validated['deductions'] ?? [],
                'computed' => [
                    'gross_pay' => $grossPay,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => $netPay,
                ],
            ];
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
                'snapshot' => $snapshot,
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

            $snapshot = [
                'source' => 'manual_entry',
                'inputs' => [
                    'wage_type' => $wageType,
                    'min_wage' => $minWage,
                    'units_worked' => $unitsWorked,
                    'status_ui' => $statusUi,
                ],
                'deductions' => $validated['deductions'] ?? [],
                'computed' => [
                    'gross_pay' => $grossPay,
                    'total_deductions' => $totalDeductions,
                    'net_pay' => $netPay,
                ],
            ];

            $updatedPayroll = $this->payrollRepository->updatePayroll($payroll, [
                'user_id' => $validated['user_id'],
                'wage_type' => $wageType,
                'min_wage' => $minWage,
                'hours_worked' => $hoursWorked,
                'days_worked' => $daysWorked,
                'gross_pay' => $grossPay,
                'total_deductions' => $totalDeductions,
                'net_pay' => $netPay,
                'snapshot' => $snapshot,
            ]);

            $this->payrollRepository->replaceDeductions($updatedPayroll, $validated['deductions'] ?? []);

            $this->applyStatusAndSyncCashAdvance($updatedPayroll, $statusUi);

            return $updatedPayroll;
        });
    }

    /**
     * Update payroll status and synchronize cash advance repayments linked to this payroll.
     *
     * UI and DB both use: Pending / Released / Cancelled.
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
        $previousStatus = $payroll->status;
        $statusDb = $statusUi;

        // Always remove any existing payroll-linked CA repayments for this
        // payroll before recalculating them based on the current deductions
        // and outstanding balance.
        CashAdvance::where('user_id', $payroll->user_id)
            ->where('source', 'payroll')
            ->where('payroll_id', $payroll->id)
            ->delete();

        if ($statusDb === 'Released') {
            // Recompute outstanding cash advance balance after removing any
            // previous payroll-linked repayments for this payroll.
            $totalAdvancesBefore = (float) CashAdvance::where('user_id', $payroll->user_id)
                ->where('type', 'advance')
                ->sum('amount');

            $totalRepaymentsBefore = (float) CashAdvance::where('user_id', $payroll->user_id)
                ->where('type', 'repayment')
                ->sum('amount');

            $outstandingBefore = max(0, $totalAdvancesBefore - $totalRepaymentsBefore);

            // Total CA deduction on this payroll (from the detailed
            // deductions table). This may be higher than the current
            // outstanding balance if the worker's CA was repaid early via
            // manual ledger entries between preview and release.
            $caDeductionTotal = (float) $payroll->deductions()
                ->where('deduction_name', 'Cash advance')
                ->sum('amount');

            $repaymentAmount = 0.0;
            $repaymentEntry = null;

            if ($caDeductionTotal > 0 && $outstandingBefore > 0) {
                // Never repay more than the outstanding balance.
                $repaymentAmount = min($caDeductionTotal, $outstandingBefore);

                if ($repaymentAmount > 0) {
                    $repaymentEntry = $this->payrollRepository->recordCashAdvanceRepayment(
                        (int) $payroll->user_id,
                        $payroll,
                        $repaymentAmount,
                    );
                }
            }

            $outstandingAfter = max(0, $outstandingBefore - $repaymentAmount);
            $overage = 0.0;

            if ($caDeductionTotal > 0 && $outstandingBefore > 0 && $caDeductionTotal > $outstandingBefore) {
                // Part of the payroll "Cash advance" deduction could not be
                // applied to the CA ledger because the balance was already
                // fully repaid. Track that difference in the audit log so
                // accounting can reconcile any manual adjustments.
                $overage = $caDeductionTotal - $outstandingBefore;
            }

            $actor = Auth::user();

            if ($repaymentEntry && $actor) {
                ApprovalLog::create([
                    'resource_type' => 'cash_advance_ledger',
                    'resource_id' => $repaymentEntry->id,
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role ?? null,
                    'action' => $overage > 0 ? 'payroll_repayment_capped' : 'payroll_repayment_created',
                    'meta' => [
                        'for_user_id' => (int) $payroll->user_id,
                        'payroll_id' => (int) $payroll->id,
                        'from_status' => $previousStatus,
                        'to_status' => $statusDb,
                        'ca_deduction_total' => $caDeductionTotal,
                        'repayment_applied' => $repaymentAmount,
                        'outstanding_before' => $outstandingBefore,
                        'outstanding_after' => $outstandingAfter,
                        'overage_ignored' => $overage,
                    ],
                ]);
            }

            if ($caDeductionTotal > 0 && $outstandingBefore <= 0 && $actor) {
                // There is a "Cash advance" deduction on the payroll but no
                // outstanding CA balance to apply it to. Record this as an
                // audit event tied to the payroll so it is visible in
                // ApprovalLogs, while keeping the CA ledger consistent (no
                // negative balances).
                ApprovalLog::create([
                    'resource_type' => 'payroll',
                    'resource_id' => $payroll->id,
                    'actor_id' => $actor->id,
                    'actor_role' => $actor->role ?? null,
                    'action' => 'payroll_ca_deduction_without_balance',
                    'meta' => [
                        'for_user_id' => (int) $payroll->user_id,
                        'payroll_id' => (int) $payroll->id,
                        'from_status' => $previousStatus,
                        'to_status' => $statusDb,
                        'ca_deduction_total' => $caDeductionTotal,
                        'outstanding_before' => $outstandingBefore,
                    ],
                ]);
            }
        }

        $payroll->status = $statusDb;

        if ($statusDb === 'Released' && !$payroll->released_at) {
            $payroll->released_at = now();
        }

        $payroll->save();
    }

    private function calculateHourlyRate(string $wageType, float $minWage, float $standardDailyHours): float
    {
        if ($minWage <= 0 || $standardDailyHours <= 0) {
            return 0.0;
        }

        $daysPerWeek = (int) config('payroll.days_per_week', 6);
        $daysPerMonth = (int) config('payroll.days_per_month', 26);

        switch ($wageType) {
            case 'Hourly':
            case 'Piece rate':
                return $minWage;
            case 'Daily':
                return $minWage / $standardDailyHours;
            case 'Weekly':
                if ($daysPerWeek <= 0) {
                    return 0.0;
                }

                return ($minWage / $daysPerWeek) / $standardDailyHours;
            case 'Monthly':
                if ($daysPerMonth <= 0) {
                    return 0.0;
                }

                return ($minWage / $daysPerMonth) / $standardDailyHours;
            default:
                return 0.0;
        }
    }

    private function calculateDailyRate(string $wageType, float $minWage, float $standardDailyHours): float
    {
        if ($minWage <= 0 || $standardDailyHours <= 0) {
            return 0.0;
        }

        $daysPerWeek = (int) config('payroll.days_per_week', 6);
        $daysPerMonth = (int) config('payroll.days_per_month', 26);

        switch ($wageType) {
            case 'Hourly':
            case 'Piece rate':
                return $minWage * $standardDailyHours;
            case 'Daily':
                return $minWage;
            case 'Weekly':
                if ($daysPerWeek <= 0) {
                    return 0.0;
                }

                return $minWage / $daysPerWeek;
            case 'Monthly':
                if ($daysPerMonth <= 0) {
                    return 0.0;
                }

                return $minWage / $daysPerMonth;
            default:
                return 0.0;
        }
    }

    /**
     * Calculate overtime premium for the period based on approved ledger entries.
     *
     * Returns an array: [totalPremium, details[]].
     */
    private function calculateOvertimePremiumForPeriod(
        int $userId,
        Carbon $periodStart,
        Carbon $periodEnd,
        float $hourlyRate,
    ): array {
        if ($hourlyRate <= 0) {
            return [0.0, []];
        }

        $defaultMultiplier = (float) config('payroll.overtime_multiplier', 1.30);

        $entries = OvertimeEntry::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereNull('payroll_id')
            ->whereDate('date', '>=', $periodStart->toDateString())
            ->whereDate('date', '<=', $periodEnd->toDateString())
            ->get();

        $totalPremium = 0.0;
        $details = [];

        foreach ($entries as $entry) {
            $hours = (float) $entry->hours;
            if ($hours <= 0) {
                continue;
            }

            $multiplier = (float) ($entry->premium_multiplier ?: $defaultMultiplier);
            $extraFactor = max(0.0, $multiplier - 1.0);
            if ($extraFactor <= 0) {
                continue;
            }

            $premiumAmount = round($hours * $hourlyRate * $extraFactor, 2);
            if ($premiumAmount <= 0) {
                continue;
            }

            $totalPremium += $premiumAmount;

            $details[] = [
                'entry_id' => (int) $entry->id,
                'date' => $entry->date ? $entry->date->toDateString() : null,
                'hours' => $hours,
                'premium_multiplier' => $multiplier,
                'premium_amount' => $premiumAmount,
            ];
        }

        return [$totalPremium, $details];
    }

    /**
     * Aggregate approved leave ledger entries for the period.
     *
     * Returns [paidLeaveDays, unpaidLeaveDays, details[]].
     */
    private function calculateLeaveForPeriod(
        int $userId,
        Carbon $periodStart,
        Carbon $periodEnd,
    ): array {
        $user = User::find($userId);
        $forceUnpaid = $user && $user->isPartTime();

        $entries = LeaveEntry::where('user_id', $userId)
            ->where('status', 'approved')
            ->whereNull('payroll_id')
            ->whereDate('date_start', '>=', $periodStart->toDateString())
            ->whereDate('date_end', '<=', $periodEnd->toDateString())
            ->get();

        $paidLeaveDays = 0.0;
        $unpaidLeaveDays = 0.0;
        $details = [];

        foreach ($entries as $entry) {
            $durationDays = (float) $entry->duration_days;
            if ($durationDays <= 0) {
                continue;
            }

            $isPaid = (bool) $entry->is_paid;

            // Company policy: paid leave applies only to regular employees by
            // default. Part-time employees' leave is treated as unpaid unless
            // explicitly handled as an exception. Enforce that here so payroll
            // calculations stay consistent even if a leave entry was marked
            // paid by mistake for a part-time worker.
            if ($forceUnpaid && $isPaid) {
                $isPaid = false;
            }

            if ($isPaid) {
                $paidLeaveDays += $durationDays;
            } else {
                $unpaidLeaveDays += $durationDays;
            }

            $details[] = [
                'entry_id' => (int) $entry->id,
                'date_start' => $entry->date_start ? $entry->date_start->toDateString() : null,
                'date_end' => $entry->date_end ? $entry->date_end->toDateString() : null,
                'duration_days' => $durationDays,
                'type' => $entry->type,
                'is_paid' => $isPaid,
            ];
        }

        return [$paidLeaveDays, $unpaidLeaveDays, $details];
    }

    /**
     * Determine whether SSS should be applied for the given payroll period.
     *
     * Paper specifies SSS is deducted once per month (25th). To keep
     * implementation simple and robust, apply SSS when the current weekly
     * payroll period covers the 25th day of its month.
     */
    private function shouldApplySssForPeriod(Carbon $periodStart, Carbon $periodEnd): bool
    {
        $monthDate = $periodStart->copy()->day(25);

        return $monthDate->between($periodStart, $periodEnd, true);
    }

    /**
     * Simple income tax: if projected annual income exceeds 200,000,
     * apply a conservative flat 5% rate on this period's gross pay
     * (pro-rated to an annual basis).
     */
    private function calculateSimpleIncomeTax(float $grossPay, int $periodDays): float
    {
        if ($grossPay <= 0 || $periodDays <= 0) {
            return 0.0;
        }

        // Approximate annual income based on this period's gross.
        $daysPerYear = 365;
        $projectedAnnual = ($grossPay / $periodDays) * $daysPerYear;

        if ($projectedAnnual <= 200000) {
            return 0.0;
        }

        $taxRate = 0.05; // 5% flat for simplicity

        return round($grossPay * $taxRate, 2);
    }
}
