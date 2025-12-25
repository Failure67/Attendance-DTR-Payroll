<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ApprovalLog;
use App\Models\CrewAssignment;
use App\Models\EmploymentTypeChangeRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmploymentTypeChangeController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');

        $query = EmploymentTypeChangeRequest::with(['user', 'requester', 'manager', 'admin'])
            ->orderByDesc('created_at');

        if ($roleKey === 'hr') {
            $query->where('requested_by_id', $currentUser->id);
        } elseif ($roleKey === 'manager') {
            $query->where('status', EmploymentTypeChangeRequest::STATUS_PENDING);
        } elseif (in_array($roleKey, ['admin', 'superadmin'], true)) {
        } else {
            abort(403);
        }

        $requests = $query->paginate(10)->appends($request->query());

        $userOptions = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->orderBy('full_name')
            ->orderBy('username')
            ->get(['id', 'full_name', 'username']);

        return view('pages.employment-type-requests', [
            'title' => 'Employment type change requests',
            'pageClass' => 'employment-type-requests',
            'requests' => $requests,
            'userOptions' => $userOptions,
            'roleKey' => $roleKey,
        ]);
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');
        if (!in_array($roleKey, ['hr', 'admin'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'to_type' => 'required|in:' . implode(',', [User::EMPLOYMENT_TYPE_REGULAR, User::EMPLOYMENT_TYPE_PART_TIME]),
            'reason' => 'required|string|max:2000',
        ]);

        $targetUser = User::whereNull('deleted_at')->findOrFail($validated['user_id']);

        if ($targetUser->id === $currentUser->id) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['user_id' => 'You cannot initiate an employment type change for yourself.']);
        }

        $fromType = $targetUser->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;
        $toType = $validated['to_type'];

        if ($fromType === $toType) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['to_type' => 'The target employment type is the same as the current type.']);
        }

        DB::beginTransaction();

        try {
            $beforeSnapshot = [
                'user' => [
                    'id' => $targetUser->id,
                    'full_name' => $targetUser->full_name,
                    'username' => $targetUser->username,
                    'email' => $targetUser->email,
                    'role' => $targetUser->role,
                    'employment_type' => $fromType,
                ],
            ];

            $change = EmploymentTypeChangeRequest::create([
                'user_id' => $targetUser->id,
                'requested_by_id' => $currentUser->id,
                'from_type' => $fromType,
                'to_type' => $toType,
                'status' => EmploymentTypeChangeRequest::STATUS_PENDING,
                'reason' => $validated['reason'],
                'before_snapshot' => $beforeSnapshot,
            ]);

            ApprovalLog::create([
                'resource_type' => 'employment_type_change',
                'resource_id' => $change->id,
                'actor_id' => $currentUser->id,
                'actor_role' => $currentUser->role ?? null,
                'action' => 'created',
                'meta' => [
                    'from_type' => $fromType,
                    'to_type' => $toType,
                ],
            ]);

            ActivityLog::create([
                'user_id' => $currentUser->id,
                'role' => $currentUser->role ?? null,
                'action' => 'employment_type_change_created',
                'description' => 'Requested employment type change for ' . ($targetUser->full_name ?? $targetUser->username) . ' from ' . $fromType . ' to ' . $toType,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('employment-type-requests.index')
                ->with('success', 'Employment type change request created.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Unable to create employment type change request: ' . $e->getMessage()]);
        }
    }

    public function managerApprove(Request $request, $id)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');
        if (!in_array($roleKey, ['manager', 'admin'], true)) {
            abort(403);
        }

        $change = EmploymentTypeChangeRequest::with('user', 'requester')->findOrFail($id);

        if (in_array($change->status, [EmploymentTypeChangeRequest::STATUS_APPROVED, EmploymentTypeChangeRequest::STATUS_REJECTED, EmploymentTypeChangeRequest::STATUS_OVERRIDDEN], true)) {
            return redirect()->back()->with('error', 'This request is already finalized.');
        }

        if ($change->user_id === $currentUser->id || $change->requested_by_id === $currentUser->id) {
            return redirect()->back()->with('error', 'You cannot approve an employment type change for yourself or your own request.');
        }

        $targetUser = $change->user;
        if (!$targetUser) {
            return redirect()->back()->with('error', 'Target user no longer exists.');
        }

        // Domain validation: Managers may only approve requests for users
        // under their remit (crew). If a Manager has crew assignments and
        // the target user is not part of that crew, they should not approve
        // the request directly.
        if ($roleKey === 'manager') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');

            if ($crewWorkerIds->isNotEmpty() && !$crewWorkerIds->contains($targetUser->id)) {
                return redirect()->back()->with('error', 'You can only approve employment type changes for employees under your remit. Please coordinate with Admin for other employees.');
            }
        }

        DB::beginTransaction();

        try {
            $fromType = $change->from_type ?? ($targetUser->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR);
            $toType = $change->to_type;

            $targetUser->employment_type = $toType;
            $targetUser->save();

            $afterSnapshot = [
                'user' => [
                    'id' => $targetUser->id,
                    'full_name' => $targetUser->full_name,
                    'username' => $targetUser->username,
                    'email' => $targetUser->email,
                    'role' => $targetUser->role,
                    'employment_type' => $toType,
                ],
            ];

            $change->status = EmploymentTypeChangeRequest::STATUS_APPROVED;
            $change->manager_id = $currentUser->id;
            $change->approved_at = now();
            $change->after_snapshot = $afterSnapshot;
            $change->save();

            ApprovalLog::create([
                'resource_type' => 'employment_type_change',
                'resource_id' => $change->id,
                'actor_id' => $currentUser->id,
                'actor_role' => $currentUser->role ?? null,
                'action' => 'manager_approved',
                'meta' => [
                    'from_type' => $fromType,
                    'to_type' => $toType,
                ],
            ]);

            ActivityLog::create([
                'user_id' => $currentUser->id,
                'role' => $currentUser->role ?? null,
                'action' => 'employment_type_change_approved',
                'description' => 'Approved employment type change for ' . ($targetUser->full_name ?? $targetUser->username) . ' from ' . $fromType . ' to ' . $toType,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('employment-type-requests.index')
                ->with('success', 'Employment type change request approved.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Unable to approve employment type change request: ' . $e->getMessage());
        }
    }

    public function managerReject(Request $request, $id)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');
        if (!in_array($roleKey, ['manager', 'admin'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'manager_reason' => 'required|string|max:2000',
        ]);

        $change = EmploymentTypeChangeRequest::with('user', 'requester')->findOrFail($id);

        if (in_array($change->status, [EmploymentTypeChangeRequest::STATUS_APPROVED, EmploymentTypeChangeRequest::STATUS_REJECTED, EmploymentTypeChangeRequest::STATUS_OVERRIDDEN], true)) {
            return redirect()->back()->with('error', 'This request is already finalized.');
        }

        if ($change->user_id === $currentUser->id || $change->requested_by_id === $currentUser->id) {
            return redirect()->back()->with('error', 'You cannot reject an employment type change for yourself or your own request.');
        }

        $targetUser = $change->user;
        if (!$targetUser) {
            return redirect()->back()->with('error', 'Target user no longer exists.');
        }

        if ($roleKey === 'manager') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');

            if ($crewWorkerIds->isNotEmpty() && !$crewWorkerIds->contains($targetUser->id)) {
                return redirect()->back()->with('error', 'You can only reject employment type changes for employees under your remit. Please coordinate with Admin for other employees.');
            }
        }

        DB::beginTransaction();

        try {
            $change->status = EmploymentTypeChangeRequest::STATUS_REJECTED;
            $change->manager_id = $currentUser->id;
            $change->manager_reason = $validated['manager_reason'];
            $change->rejected_at = now();
            $change->save();

            ApprovalLog::create([
                'resource_type' => 'employment_type_change',
                'resource_id' => $change->id,
                'actor_id' => $currentUser->id,
                'actor_role' => $currentUser->role ?? null,
                'action' => 'manager_rejected',
                'meta' => [
                    'reason' => $validated['manager_reason'],
                ],
            ]);

            ActivityLog::create([
                'user_id' => $currentUser->id,
                'role' => $currentUser->role ?? null,
                'action' => 'employment_type_change_rejected',
                'description' => 'Rejected employment type change for ' . ($targetUser->full_name ?? $targetUser->username) . ' with reason: ' . $validated['manager_reason'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            DB::commit();

            return redirect()->route('employment-type-requests.index')
                ->with('success', 'Employment type change request rejected.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Unable to reject employment type change request: ' . $e->getMessage());
        }
    }

    public function adminOverride(Request $request, $id)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');
        if (!in_array($roleKey, ['admin', 'superadmin'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'admin_reason' => 'required|string|max:2000',
        ]);

        $change = EmploymentTypeChangeRequest::with('user', 'requester')->findOrFail($id);

        DB::beginTransaction();

        try {
            $targetUser = $change->user;
            if ($targetUser) {
                $fromType = $targetUser->employment_type ?? User::EMPLOYMENT_TYPE_REGULAR;
                $toType = $change->to_type;

                $targetUser->employment_type = $toType;
                $targetUser->save();

                $afterSnapshot = [
                    'user' => [
                        'id' => $targetUser->id,
                        'full_name' => $targetUser->full_name,
                        'username' => $targetUser->username,
                        'email' => $targetUser->email,
                        'role' => $targetUser->role,
                        'employment_type' => $toType,
                    ],
                ];

                $change->after_snapshot = $afterSnapshot;
            }

            $change->status = EmploymentTypeChangeRequest::STATUS_OVERRIDDEN;
            $change->admin_id = $currentUser->id;
            $change->admin_reason = $validated['admin_reason'];
            $change->overridden_at = now();
            $change->save();

            ApprovalLog::create([
                'resource_type' => 'employment_type_change',
                'resource_id' => $change->id,
                'actor_id' => $currentUser->id,
                'actor_role' => $currentUser->role ?? null,
                'action' => 'admin_overridden',
                'meta' => [
                    'reason' => $validated['admin_reason'],
                ],
            ]);

            if ($targetUser) {
                ActivityLog::create([
                    'user_id' => $currentUser->id,
                    'role' => $currentUser->role ?? null,
                    'action' => 'employment_type_change_overridden',
                    'description' => 'Overrode employment type change for ' . ($targetUser->full_name ?? $targetUser->username) . ' with reason: ' . $validated['admin_reason'],
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            DB::commit();

            return redirect()->route('employment-type-requests.index')
                ->with('success', 'Employment type change request overridden.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Unable to override employment type change request: ' . $e->getMessage());
        }
    }
}
