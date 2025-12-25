<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Convenience helper for recording an approval or decision in the
     * approval_logs table. Safe to call even when no user is authenticated.
     */
    protected function logApproval(string $resourceType, int $resourceId, string $action, array $meta = []): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        ApprovalLog::create([
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'actor_id' => $user->id,
            'actor_role' => $user->role ?? null,
            'action' => $action,
            'meta' => $meta,
        ]);
    }
}
