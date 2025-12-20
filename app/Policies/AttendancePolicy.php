<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\CrewAssignment;
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

        $actorRole = $this->normalizeRole($user->role ?? '');

        if (!in_array($actorRole, ['supervisor', 'manager', 'hr', 'admin'], true)) {
            return false;
        }

        $target = $attendance->user ?: User::find($attendance->user_id);

        if (!$target) {
            // Fallback: legacy behaviour (still no self-approval and no Superadmin)
            return true;
        }

        $targetRole = $this->normalizeRole($target->role ?? '');

        // Do not manage or approve attendance for Admin/Superadmin
        if (in_array($targetRole, ['admin', 'superadmin'], true)) {
            return false;
        }

        // Supervisors: only Managers/Admins can approve their attendance
        if ($targetRole === 'supervisor') {
            return in_array($actorRole, ['manager', 'admin'], true);
        }

        // Managers and HR: only Admin can approve their attendance
        if (in_array($targetRole, ['manager', 'hr'], true)) {
            return $actorRole === 'admin';
        }

        // Regular workers and other staff
        if ($actorRole === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $user->id)->pluck('worker_id');

            return $crewWorkerIds->contains($target->id);
        }

        return in_array($actorRole, ['manager', 'hr', 'admin'], true);
    }

    protected function normalizeRole(?string $role): string
    {
        $role = strtolower(trim((string) $role));

        if ($role === 'project manager') {
            return 'manager';
        }

        return $role;
    }
}
