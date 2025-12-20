<?php

namespace App\Services\Leave;

use App\Models\LeaveCreditAccount;
use App\Models\LeaveCreditAllocation;
use App\Models\LeaveCreditTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class LeaveCreditService
{
    public function ensureAccounts(User $user): void
    {
        foreach ($this->supportedLeaveCodes() as $code) {
            $this->getOrCreateAccount($user->id, $code);
        }
    }

    protected function employmentStartDate(User $user): Carbon
    {
        $value = $user->employment_start_date;
        if ($value) {
            return Carbon::parse($value)->startOfDay();
        }

        if ($user->created_at) {
            return Carbon::parse($user->created_at)->startOfDay();
        }

        return Carbon::now()->startOfDay();
    }

    public function ensureAccruedUpTo(User $user, Carbon $asOfDate): void
    {
        $this->ensureAccounts($user);

        if (!$user->isRegular()) {
            return;
        }

        $employmentStartDate = $this->employmentStartDate($user);
        $asOfDate = $asOfDate->copy()->startOfDay();

        if ($employmentStartDate->gt($asOfDate)) {
            return;
        }

        $startYear = (int) $employmentStartDate->format('Y');
        $endYear = (int) $asOfDate->format('Y');

        if (($endYear - $startYear) > 20) {
            $startYear = $endYear - 20;
        }

        for ($year = $startYear; $year <= $endYear; $year++) {
            $throughMonth = ($year === $endYear) ? (int) $asOfDate->format('n') : 12;

            foreach ($this->supportedLeaveCodes() as $code) {
                $this->ensureCarryover($user, $code, $year, $employmentStartDate);
                $this->ensureMonthlyAccruals($user, $code, $year, $throughMonth, $employmentStartDate);
            }
        }
    }

    public function getBalance(User $user, string $leaveCode, ?Carbon $asOfDate = null): float
    {
        $asOfDate = $asOfDate ? $asOfDate->copy()->startOfDay() : Carbon::now()->startOfDay();
        $account = $this->getOrCreateAccount($user->id, $leaveCode);

        $sum = LeaveCreditTransaction::query()
            ->where('account_id', $account->id)
            ->where('direction', 'credit')
            ->whereDate('effective_date', '<=', $asOfDate->toDateString())
            ->where(function ($q) use ($asOfDate) {
                $q->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', $asOfDate->toDateString());
            })
            ->sum('remaining_amount');

        return (float) $sum;
    }

    public function debitForLeaveApproval(User $employee, int $actorId, int $leaveEntryId, string $requestType, bool $isPaid, float $days, Carbon $leaveStartDate): ?LeaveCreditTransaction
    {
        if (!$isPaid || $days <= 0) {
            return null;
        }

        if (!$employee->isRegular()) {
            throw new \RuntimeException('This employee is not eligible for paid leave credits.');
        }

        $leaveCode = $this->resolveLeaveCodeForRequest($requestType);

        $this->ensureAccruedUpTo($employee, $leaveStartDate);

        return $this->debit(
            $employee,
            $leaveCode,
            $days,
            $leaveStartDate,
            $actorId,
            'leave_debit',
            'leave_entry',
            $leaveEntryId,
            'Leave deduction for approved request #' . $leaveEntryId
        );
    }

    public function creditAdjustment(User $employee, string $leaveCode, float $days, Carbon $effectiveDate, int $actorId, string $reason): LeaveCreditTransaction
    {
        $this->ensureAccounts($employee);

        $account = $this->getOrCreateAccount($employee->id, $leaveCode);

        $amount = $this->normalizeAmount($days);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Adjustment amount must be greater than 0.');
        }

        $type = 'manual_credit';

        return LeaveCreditTransaction::create([
            'account_id' => $account->id,
            'direction' => 'credit',
            'amount' => $amount,
            'remaining_amount' => $amount,
            'occurred_at' => now(),
            'effective_date' => $effectiveDate->toDateString(),
            'type' => $type,
            'actor_id' => $actorId,
            'reference_type' => 'manual_adjustment',
            'reference_id' => null,
            'description' => $reason,
            'expires_at' => null,
            'meta' => null,
        ]);
    }

    public function debitAdjustment(User $employee, string $leaveCode, float $days, Carbon $effectiveDate, int $actorId, string $reason): LeaveCreditTransaction
    {
        $this->ensureAccounts($employee);

        return $this->debit(
            $employee,
            $leaveCode,
            $days,
            $effectiveDate,
            $actorId,
            'manual_debit',
            'manual_adjustment',
            null,
            $reason
        );
    }

    protected function debit(User $employee, string $leaveCode, float $days, Carbon $effectiveDate, int $actorId, string $type, ?string $referenceType, ?int $referenceId, ?string $description): LeaveCreditTransaction
    {
        $amount = $this->normalizeAmount($days);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be greater than 0.');
        }

        $effectiveDate = $effectiveDate->copy()->startOfDay();

        return DB::transaction(function () use ($employee, $leaveCode, $amount, $effectiveDate, $actorId, $type, $referenceType, $referenceId, $description) {
            $account = $this->getOrCreateAccount($employee->id, $leaveCode);

            if ($referenceType && $referenceId) {
                $existing = LeaveCreditTransaction::query()
                    ->where('account_id', $account->id)
                    ->where('direction', 'debit')
                    ->where('type', $type)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $available = $this->getBalance($employee, $leaveCode, $effectiveDate);
            if ($available + 0.0005 < $amount) {
                throw new \RuntimeException('Insufficient ' . strtoupper($leaveCode) . ' credits. Available: ' . number_format($available, 3) . ' days. Required: ' . number_format($amount, 3) . ' days.');
            }

            $debit = LeaveCreditTransaction::create([
                'account_id' => $account->id,
                'direction' => 'debit',
                'amount' => $amount,
                'remaining_amount' => null,
                'occurred_at' => now(),
                'effective_date' => $effectiveDate->toDateString(),
                'type' => $type,
                'actor_id' => $actorId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description,
                'expires_at' => null,
                'meta' => null,
            ]);

            $needed = $amount;

            $credits = LeaveCreditTransaction::query()
                ->where('account_id', $account->id)
                ->where('direction', 'credit')
                ->where('remaining_amount', '>', 0)
                ->whereDate('effective_date', '<=', $effectiveDate->toDateString())
                ->where(function ($q) use ($effectiveDate) {
                    $q->whereNull('expires_at')
                        ->orWhereDate('expires_at', '>=', $effectiveDate->toDateString());
                })
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('effective_date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($credits as $credit) {
                if ($needed <= 0) {
                    break;
                }

                $remaining = (float) ($credit->remaining_amount ?? 0);
                if ($remaining <= 0) {
                    continue;
                }

                $consume = min($remaining, $needed);

                LeaveCreditAllocation::create([
                    'debit_transaction_id' => $debit->id,
                    'credit_transaction_id' => $credit->id,
                    'amount' => $consume,
                ]);

                $credit->remaining_amount = $this->normalizeAmount($remaining - $consume);
                $credit->save();

                $needed = $this->normalizeAmount($needed - $consume);
            }

            if ($needed > 0.0005) {
                throw new \RuntimeException('Ledger debit allocation failed (insufficient credits after lock).');
            }

            return $debit;
        });
    }

    protected function ensureCarryover(User $user, string $leaveCode, int $year, Carbon $employmentStartDate): void
    {
        if (!$user->isRegular()) {
            return;
        }

        $carryMax = (float) Arr::get(config('leave.carryover_max', []), $leaveCode, 0.0);
        if ($carryMax <= 0) {
            return;
        }

        $priorYear = $year - 1;
        if ($priorYear < 1970) {
            return;
        }

        // Not employed yet for the prior year period.
        if ($employmentStartDate->gt(Carbon::create($priorYear, 12, 31)->startOfDay())) {
            return;
        }

        $account = $this->getOrCreateAccount($user->id, $leaveCode);

        $referenceType = 'carryover';
        $referenceId = $year;

        $exists = LeaveCreditTransaction::query()
            ->where('account_id', $account->id)
            ->where('type', 'carryover')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->exists();

        if ($exists) {
            return;
        }

        $dec31 = Carbon::create($priorYear, 12, 31)->startOfDay();
        $balance = $this->getBalance($user, $leaveCode, $dec31);
        $carryAmount = min($carryMax, $balance);

        $carryAmount = $this->normalizeAmount($carryAmount);
        if ($carryAmount <= 0) {
            return;
        }

        $effectiveDate = Carbon::create($year, 1, 1)->startOfDay();
        $expiresAt = Carbon::create($year, 12, 31)->startOfDay();

        LeaveCreditTransaction::create([
            'account_id' => $account->id,
            'direction' => 'credit',
            'amount' => $carryAmount,
            'remaining_amount' => $carryAmount,
            'occurred_at' => now(),
            'effective_date' => $effectiveDate->toDateString(),
            'type' => 'carryover',
            'actor_id' => null,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => 'Carry-over from ' . $priorYear,
            'expires_at' => $expiresAt->toDateString(),
            'meta' => null,
        ]);
    }

    protected function ensureMonthlyAccruals(User $user, string $leaveCode, int $year, int $throughMonth, Carbon $employmentStartDate): void
    {
        if (!$user->isRegular()) {
            return;
        }

        $annual = $this->annualEntitlement($user, $leaveCode);
        if ($annual <= 0) {
            return;
        }

        $throughMonth = max(1, min(12, $throughMonth));

        $account = $this->getOrCreateAccount($user->id, $leaveCode);

        $accrualStart = $employmentStartDate->copy()->startOfMonth();
        if ((int) $employmentStartDate->format('j') !== 1) {
            $accrualStart->addMonth();
        }

        for ($month = 1; $month <= $throughMonth; $month++) {
            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            if ($monthStart->lt($accrualStart)) {
                continue;
            }

            $ref = (int) ($year . str_pad((string) $month, 2, '0', STR_PAD_LEFT));

            $exists = LeaveCreditTransaction::query()
                ->where('account_id', $account->id)
                ->where('type', 'monthly_accrual')
                ->where('reference_type', 'accrual')
                ->where('reference_id', $ref)
                ->exists();

            if ($exists) {
                continue;
            }

            $monthlyBase = $this->normalizeAmount(round($annual / 12, $this->roundingScale()));
            $amount = $monthlyBase;

            if ($month === 12) {
                $amount = $this->normalizeAmount($annual - ($monthlyBase * 11));
            }

            if ($amount <= 0) {
                continue;
            }

            $effectiveDate = Carbon::create($year, $month, 1)->startOfDay();
            $expiresAt = Carbon::create($year, 12, 31)->startOfDay();

            LeaveCreditTransaction::create([
                'account_id' => $account->id,
                'direction' => 'credit',
                'amount' => $amount,
                'remaining_amount' => $amount,
                'occurred_at' => now(),
                'effective_date' => $effectiveDate->toDateString(),
                'type' => 'monthly_accrual',
                'actor_id' => null,
                'reference_type' => 'accrual',
                'reference_id' => $ref,
                'description' => strtoupper($leaveCode) . ' accrual for ' . $effectiveDate->format('F Y'),
                'expires_at' => $expiresAt->toDateString(),
                'meta' => null,
            ]);
        }
    }

    protected function resolveLeaveCodeForRequest(string $requestType): string
    {
        $map = (array) config('leave.request_type_map', []);
        $mapped = (string) Arr::get($map, $requestType, '');
        if ($mapped !== '') {
            return $mapped;
        }

        return (string) config('leave.default_paid_bucket', 'vl');
    }

    protected function annualEntitlement(User $user, string $leaveCode): float
    {
        $type = $user->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;

        $ent = (float) Arr::get(config('leave.employment_entitlements', []), $type . '.' . $leaveCode, 0.0);

        return $this->normalizeAmount($ent);
    }

    protected function supportedLeaveCodes(): array
    {
        return array_keys((array) config('leave.leave_type_labels', ['vl' => 'Vacation Leave', 'sl' => 'Sick Leave']));
    }

    protected function getOrCreateAccount(int $userId, string $leaveCode): LeaveCreditAccount
    {
        return LeaveCreditAccount::firstOrCreate([
            'user_id' => $userId,
            'leave_code' => $leaveCode,
        ]);
    }

    protected function roundingScale(): int
    {
        $scale = (int) config('leave.accrual_rounding_scale', 3);

        return $scale > 0 ? $scale : 3;
    }

    protected function normalizeAmount(float $value): float
    {
        return (float) number_format($value, 3, '.', '');
    }
}
