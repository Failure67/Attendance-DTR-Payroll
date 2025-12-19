<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    protected function isDeveloper(User $user): bool
    {
        return strtolower($user->role ?? '') === 'superadmin';
    }

    public function approve(User $user, Attendance $attendance): bool
    {
        if ($this->isDeveloper($user)) {
            return false;
        }

        // Disallow self-approval
        if ($attendance->user_id && $attendance->user_id === $user->id) {
            return false;
        }

        $role = strtolower($user->role ?? '');

        // Supervisor/Manager/HR/Admin can approve attendance
        return in_array($role, ['supervisor', 'manager', 'hr', 'admin'], true);
    }
}
