<?php

namespace App\Policies;

use App\Models\LeaveEntry;
use App\Models\User;

class LeaveEntryPolicy
{
    protected function isDeveloper(User $user): bool
    {
        return strtolower($user->role ?? '') === 'superadmin';
    }

    /**
     * Determine whether the given user can approve or reject the leave entry.
     *
     * Rules mirror CashAdvancePolicy:
     * - Superadmin cannot approve (acts as developer / auditor only).
     * - No self-approval (requester cannot approve their own leave).
     * - Allowed roles: Supervisor, Manager, HR, Admin.
     */
    public function approve(User $user, LeaveEntry $entry): bool
    {
        if ($this->isDeveloper($user)) {
            return false;
        }

        if ($entry->user_id && $entry->user_id === $user->id) {
            return false;
        }

        $role = strtolower($user->role ?? '');

        return in_array($role, ['supervisor', 'manager', 'hr', 'admin'], true);
    }
}
