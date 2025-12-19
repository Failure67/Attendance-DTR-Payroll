<?php

namespace App\Policies;

use App\Models\Payroll;
use App\Models\User;

class PayrollPolicy
{
    protected function isDeveloper(User $user): bool
    {
        return strtolower($user->role ?? '') === 'superadmin';
    }

    public function approve(User $user, Payroll $payroll): bool
    {
        if ($this->isDeveloper($user)) {
            return false;
        }

        // Disallow self-approval of payroll
        if ($payroll->user_id && $payroll->user_id === $user->id) {
            return false;
        }

        $role = strtolower($user->role ?? '');

        // HR prepares and checks; Admin (owner) is final approver, Accounting can
        // participate in review/approval but never as Superadmin.
        return in_array($role, ['hr', 'admin', 'accounting'], true);
    }
}
