<?php

namespace App\Repositories;

use App\Models\CashAdvance;
use App\Models\Payroll;
use App\Models\PayrollDeduction;

class PayrollRepository
{
    public function createPayroll(array $data): Payroll
    {
        return Payroll::create($data);
    }

    public function updatePayroll(Payroll $payroll, array $data): Payroll
    {
        $payroll->update($data);

        return $payroll;
    }

    public function addDeduction(Payroll $payroll, string $name, float $amount): PayrollDeduction
    {
        return PayrollDeduction::create([
            'payroll_id' => $payroll->id,
            'deduction_name' => $name,
            'amount' => $amount,
        ]);
    }

    public function replaceDeductions(Payroll $payroll, array $deductions): void
    {
        $payroll->deductions()->delete();

        foreach ($deductions as $deduction) {
            if (!empty($deduction['name']) && isset($deduction['amount'])) {
                PayrollDeduction::create([
                    'payroll_id' => $payroll->id,
                    'deduction_name' => $deduction['name'],
                    'amount' => $deduction['amount'],
                ]);
            }
        }
    }

    public function recordCashAdvanceRepayment(int $userId, Payroll $payroll, float $amount): CashAdvance
    {
        return CashAdvance::create([
            'user_id' => $userId,
            'type' => 'repayment',
            'amount' => $amount,
            'description' => 'Auto-deducted from payroll #' . $payroll->id,
            'source' => 'payroll',
            'payroll_id' => $payroll->id,
        ]);
    }

    public function getLastPayrollForUser(int $userId): ?Payroll
    {
        return Payroll::where('user_id', $userId)->latest()->first();
    }
}
