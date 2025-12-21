<?php

namespace App\Policies;

use App\Models\CashAdvanceRequest;
use App\Models\User;

class CashAdvancePolicy
{
    protected function isDeveloper(User $user): bool
    {
        return strtolower($user->role ?? '') === 'superadmin';
    }

    public function approve(User $user, CashAdvanceRequest $request): bool
    {
        if ($this->isDeveloper($user)) {
            return false;
        }

        // Disallow self-approval of a cash advance request
        if ($request->user_id && $request->user_id === $user->id) {
            return false;
        }

        $role = strtolower($user->role ?? '');

        // Supervisor, Manager, HR, and Admin participate in CA approvals
        return in_array($role, ['supervisor', 'manager', 'hr', 'admin'], true);
    }
}
