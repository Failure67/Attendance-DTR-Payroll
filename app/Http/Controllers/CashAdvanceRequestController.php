<?php

namespace App\Http\Controllers;

use App\Models\CashAdvance;
use App\Models\CashAdvanceRequest;
use App\Models\CrewAssignment;
use App\Models\User;
use Illuminate\Http\Request;

class CashAdvanceRequestController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $status = $request->input('status');
        $employeeId = $request->input('employee_id');

        $query = CashAdvanceRequest::with('user')
            ->orderByDesc('created_at');

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        $requests = $query->paginate(10)->appends($request->query());

        $tableData = $requests->map(function (CashAdvanceRequest $entry) {
            $employeeName = $entry->user ? ($entry->user->full_name ?? $entry->user->username) : 'Unknown employee';
            $amount = '₱ ' . number_format((float) $entry->amount, 2);
            $status = $entry->status ?? 'Pending';
            $createdAt = $entry->created_at ? $entry->created_at->format('Y-m-d') : '—';

            $actions = $this->buildActionsHtml($entry);

            return [
                e($employeeName),
                $amount,
                e($status),
                e($createdAt),
                $actions,
            ];
        })->toArray();

        $statusOptions = [
            'Pending' => 'Pending',
            'Supervisor approved' => 'Supervisor approved',
            'Manager approved' => 'Manager approved',
            'HR approved' => 'HR approved',
            'Released' => 'Released',
            'Rejected' => 'Rejected',
            'Cancelled' => 'Cancelled',
        ];

        return view('pages.cash-advance-requests', [
            'title' => 'Cash advance requests',
            'pageClass' => 'cash-advance-requests',
            'requests' => $requests,
            'requestTableData' => $tableData,
            'employeeOptions' => $employeeOptions,
            'statusOptions' => $statusOptions,
            'filters' => [
                'status' => $status,
                'employee_id' => $employeeId,
            ],
        ]);
    }

    public function supervisorIndex(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        if (!in_array($currentUser->role, ['Superadmin', 'Admin', 'Supervisor'], true)) {
            abort(403);
        }
        // Redirect supervisors to the unified Cash advances page, focused on the
        // Requests tab. This replaces the old standalone supervisor
        // cash-advance-requests view so the supervisor now uses the same
        // Cash advances layout that HR previously used.
        return redirect()->route('cash-advances', ['ca_view' => 'requests']);
    }

    public function workerIndex()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $requests = CashAdvanceRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        $totalAdvances = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'repayment')
            ->sum('amount');

        $caBalance = max(0, $totalAdvances - $totalRepayments);

        $tableData = $requests->map(function (CashAdvanceRequest $entry) {
            $date = $entry->created_at ? $entry->created_at->format('Y-m-d') : '—';
            $amount = '₱ ' . number_format((float) $entry->amount, 2);
            $status = $entry->status ?? 'Pending';

            if (in_array($entry->status, ['Pending', 'HR approved'], true)) {
                $csrf = csrf_token();
                $actions = '<form method="POST" action="' . route('worker.cash-advance-requests.cancel', ['id' => $entry->id]) . '" style="display:inline-block;" data-confirm="Cancel this cash advance request?">'
                    . '<input type="hidden" name="_token" value="' . $csrf . '">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm">Cancel</button>'
                    . '</form>';
            } else {
                $actions = '<span class="text-muted">—</span>';
            }

            return [
                e($date),
                $amount,
                e($status),
                $actions,
            ];
        })->toArray();

        return view('user.pages.cash-advance-requests', [
            'title' => 'Cash advance requests',
            'pageClass' => 'employee-cash-advance-requests',
            'user' => $user,
            'caBalance' => $caBalance,
            'requests' => $requests,
            'requestTableData' => $tableData,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Normalize formatted amount (e.g. "1,234.56") before validation
        $rawAmount = (string) $request->input('amount', '');
        if ($rawAmount !== '') {
            $normalizedAmount = preg_replace('/[^0-9.\-]/', '', $rawAmount);
            $request->merge(['amount' => $normalizedAmount]);
        }

        // Do not allow a new cash advance request if there is any outstanding
        // cash advance balance (company policy: no additional CA until fully paid).
        $totalAdvances = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $user->id)
            ->where('type', 'repayment')
            ->sum('amount');

        $outstanding = max(0, $totalAdvances - $totalRepayments);

        if ($outstanding > 0.0001) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'amount' => 'You still have an outstanding cash advance balance. Please settle your existing balance before requesting a new cash advance.',
                ]);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
        ]);

        CashAdvanceRequest::create([
            'user_id' => $user->id,
            'amount' => (float) $validated['amount'],
            'reason' => $validated['reason'],
            'status' => 'Pending',
        ]);

        return redirect()->route('worker.cash-advance-requests')
            ->with('success', 'Your cash advance request has been submitted for approval.');
    }

    public function storeFromSupervisor(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Superadmin', 'Admin', 'Supervisor'], true)) {
            abort(403, 'Only Supervisors or Admins can create requests on behalf of employees.');
        }

        // Normalize formatted amount (e.g. "1,234.56") before validation
        $rawAmount = (string) $request->input('amount', '');
        if ($rawAmount !== '') {
            $normalizedAmount = preg_replace('/[^0-9.\-]/', '', $rawAmount);
            $request->merge(['amount' => $normalizedAmount]);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|max:1000',
        ]);

        // Ensure supervisors only create requests for their own crew members
        if ($user->role === 'Supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $user->id)->pluck('worker_id');

            if ($crewWorkerIds->isEmpty() || !$crewWorkerIds->contains((int) $validated['user_id'])) {
                abort(403, 'You can only create cash advance requests for your assigned workers.');
            }
        }

        // Enforce the same outstanding balance rule as worker-initiated requests
        $totalAdvances = (float) CashAdvance::where('user_id', $validated['user_id'])
            ->where('type', 'advance')
            ->sum('amount');

        $totalRepayments = (float) CashAdvance::where('user_id', $validated['user_id'])
            ->where('type', 'repayment')
            ->sum('amount');

        $outstanding = max(0, $totalAdvances - $totalRepayments);

        if ($outstanding > 0.0001) {
            return redirect()->back()
                ->withInput()
                ->withErrors([
                    'amount' => 'This employee still has an outstanding cash advance balance. Please settle the existing balance before creating a new cash advance request.',
                ]);
        }

        CashAdvanceRequest::create([
            'user_id' => (int) $validated['user_id'],
            'amount' => (float) $validated['amount'],
            'reason' => $validated['reason'],
            'status' => 'Supervisor approved',
            'supervisor_approved_at' => now(),
        ]);

        return redirect()->route('cash-advances', ['ca_view' => 'requests'])
            ->with('success', 'Cash advance request created and marked as Supervisor approved.');
    }

    public function hrApprove($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'HR'], true)) {
            abort(403, 'Only HR/Admin can approve at HR stage.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        // Enforce global CA approval policy (no Superadmin, no self-approval).
        $this->authorize('approve', $request);

        if ($request->status !== 'Manager approved') {
            return redirect()->back()->with('error', 'Only manager-approved requests can be HR-approved.');
        }

        // Auto-release upon HR approval: record the advance and finalize the request
        $now = now();
        \App\Models\CashAdvance::create([
            'user_id' => $request->user_id,
            'type' => 'advance',
            'amount' => $request->amount,
            'description' => 'Cash advance request #' . $request->id . ' released',
            'source' => 'admin',
            'payroll_id' => null,
        ]);

        $previousStatus = $request->status;
        $request->status = 'Released';
        $request->hr_approved_at = $now;
        $request->released_at = $now;
        $request->save();

        $this->logApproval('cash_advance_request', $request->id, 'hr_approved_and_released', [
            'from_status' => $previousStatus,
            'to_status' => $request->status,
            'amount' => (float) $request->amount,
        ]);

        return redirect()->back()->with('success', 'Cash advance request approved by HR and released.');
    }

    public function managerApprove($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'Manager'], true)) {
            abort(403, 'Only Manager/Admin can approve at manager stage.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        // Enforce global CA approval policy (no Superadmin, no self-approval).
        $this->authorize('approve', $request);

        if ($request->status !== 'Supervisor approved') {
            return redirect()->back()->with('error', 'Only supervisor-approved requests can be manager approved.');
        }

        $previousStatus = $request->status;
        $request->status = 'Manager approved';
        $request->manager_approved_at = now();
        $request->save();

        $this->logApproval('cash_advance_request', $request->id, 'manager_approved', [
            'from_status' => $previousStatus,
            'to_status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Cash advance request marked as Manager approved.');
    }

    public function release($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'HR'], true)) {
            abort(403, 'Only HR/Admin can release cash advances.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        if ($request->status === 'Released') {
            return redirect()->back()->with('success', 'Cash advance request already released.');
        }

        if ($request->status !== 'HR approved') {
            return redirect()->back()->with('error', 'Request must be HR approved before release.');
        }

        CashAdvance::create([
            'user_id' => $request->user_id,
            'type' => 'advance',
            'amount' => $request->amount,
            'description' => 'Cash advance request #' . $request->id . ' released',
            'source' => 'admin',
            'payroll_id' => null,
        ]);

        $previousStatus = $request->status;
        $request->status = 'Released';
        $request->released_at = now();
        $request->save();

        $this->logApproval('cash_advance_request', $request->id, 'released', [
            'from_status' => $previousStatus,
            'to_status' => $request->status,
            'amount' => (float) $request->amount,
        ]);

        return redirect()->back()->with('success', 'Cash advance request released and recorded.');
    }

    public function reject(Request $httpRequest, $id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'HR', 'Supervisor'], true)) {
            abort(403, 'You are not allowed to reject cash advance requests.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        if (in_array($request->status, ['Released', 'Cancelled', 'Rejected'], true)) {
            return redirect()->back()->with('error', 'This request is already finalized.');
        }

        $reason = (string) $httpRequest->input('rejection_reason', 'Rejected by ' . ($user->role ?? 'system'));

        $previousStatus = $request->status;
        $request->status = 'Rejected';
        $request->rejected_at = now();
        $request->rejection_reason = $reason;
        $request->save();

        $this->logApproval('cash_advance_request', $request->id, 'rejected', [
            'from_status' => $previousStatus,
            'to_status' => $request->status,
            'reason' => $reason,
        ]);

        return redirect()->back()->with('success', 'Cash advance request rejected.');
    }

    public function cancel($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $request = CashAdvanceRequest::where('user_id', $user->id)->findOrFail($id);

        if (!in_array($request->status, ['Pending', 'HR approved'], true)) {
            return redirect()->back()->with('error', 'Only pending or HR-approved requests can be cancelled.');
        }

        $request->status = 'Cancelled';
        $request->save();

        return redirect()->back()->with('success', 'Cash advance request cancelled.');
    }

    public function supervisorApprove($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Admin', 'Supervisor'], true)) {
            abort(403, 'Only Supervisor/Admin can approve at supervisor stage.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        // Enforce global CA approval policy (no Superadmin, no self-approval).
        $this->authorize('approve', $request);

        if ($request->status !== 'Pending') {
            return redirect()->back()->with('error', 'Only pending requests can be supervisor approved.');
        }

        if ($user->role === 'Supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $user->id)->pluck('worker_id');

            if ($crewWorkerIds->isEmpty() || !$crewWorkerIds->contains($request->user_id)) {
                abort(403, 'You can only approve requests from your assigned workers.');
            }
        }

        $previousStatus = $request->status;
        $request->status = 'Supervisor approved';
        $request->supervisor_approved_at = now();
        $request->save();

        $this->logApproval('cash_advance_request', $request->id, 'supervisor_approved', [
            'from_status' => $previousStatus,
            'to_status' => $request->status,
        ]);

        return redirect()->back()->with('success', 'Cash advance request marked as Supervisor approved.');
    }

    public function buildActionsHtml(CashAdvanceRequest $request): string
    {
        $user = auth()->user();
        if (!$user) {
            return '';
        }

        $role = $user->role ?? '';
        $csrf = csrf_token();

        // Mirror the payroll table's two-slot actions layout so rows stay aligned.
        $leftParts = [];
        $rightParts = [];
        // Track whether this role currently has an approval action available.
        // We will only show the Reject button while an Approve button is also present
        // for this role; once Approve is clicked and the status advances, Reject
        // disappears at that stage (Supervisor, Manager, HR).
        $canShowReject = false;

        // Left slot: main positive actions for each approval stage
        // 1) Supervisor stage: Pending -> Supervisor approved
        if (in_array($role, ['Admin', 'Supervisor'], true) && $request->status === 'Pending') {
            $leftParts[] = '<form method="POST" action="' . route('cash-advance-requests.supervisor-approve', ['id' => $request->id]) . '" style="display:inline-block;margin-right:4px;">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<button type="submit" class="btn btn-outline-primary btn-sm">Approve</button>'
                . '</form>';
            $canShowReject = true;
        }

        // 2) Manager stage: Supervisor approved -> Manager approved
        if (in_array($role, ['Admin', 'Manager'], true) && $request->status === 'Supervisor approved') {
            $leftParts[] = '<form method="POST" action="' . route('cash-advance-requests.manager-approve', ['id' => $request->id]) . '" style="display:inline-block;margin-right:4px;">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<button type="submit" class="btn btn-outline-primary btn-sm">Approve</button>'
                . '</form>';
            $canShowReject = true;
        }

        // 3) HR stage: Manager approved -> Released (single-step release for HR)
        if (in_array($role, ['Admin', 'HR'], true) && $request->status === 'Manager approved') {
            $leftParts[] = '<form method="POST" action="' . route('cash-advance-requests.hr-approve', ['id' => $request->id]) . '" style="display:inline-block;margin-right:4px;" data-confirm="Release this cash advance to the employee?">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<button type="submit" class="btn btn-success btn-sm">Release</button>'
                . '</form>';
            $canShowReject = true;
        }

        // 4) Legacy release stage (HR approved -> Released).
        // This is kept only for older records that might still be in
        // "HR approved" status; new approvals go straight to Released
        // via hrApprove(), so this block should rarely execute.
        if (in_array($role, ['Admin', 'HR'], true) && $request->status === 'HR approved') {
            $leftParts[] = '<form method="POST" action="' . route('cash-advance-requests.release', ['id' => $request->id]) . '" style="display:inline-block;margin-right:4px;" data-confirm="Release this cash advance to the employee?">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<button type="submit" class="btn btn-success btn-sm">Release</button>'
                . '</form>';
        }

        // Right slot: negative action (Reject)
        // Only show Reject while this role still has an Approve action available
        // (i.e. before they approve at their stage). Once approved and the status
        // advances, $canShowReject becomes false and Reject disappears.
        if ($canShowReject && !in_array($request->status, ['Released', 'Rejected', 'Cancelled'], true)) {
            $rightParts[] = '<form method="POST" action="' . route('cash-advance-requests.reject', ['id' => $request->id]) . '" style="display:inline-block;" data-confirm="Reject this cash advance request?">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">'
                . '<button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>'
                . '</form>';
        }

        if (empty($leftParts) && empty($rightParts)) {
            $roleKey = strtolower($role ?? '');
            $status = $request->status ?? '';

            $waitingLabel = null;

            // For Manager and HR views, show which earlier stage is still
            // pending instead of a blank Actions column.
            if (in_array($roleKey, ['manager', 'hr'], true)) {
                if ($status === 'Pending') {
                    $waitingLabel = 'Supervisor pending';
                } elseif ($status === 'Supervisor approved' && $roleKey === 'hr') {
                    $waitingLabel = 'Manager pending';
                }
            }

            if ($waitingLabel !== null) {
                return '<span class="text-muted">' . e($waitingLabel) . '</span>';
            }

            return '<span class="text-muted">No actions</span>';
        }

        $leftHtml = implode('', $leftParts) ?: '';
        $rightHtml = implode('', $rightParts) ?: '';

        // When we only have a single cluster of actions (all on left or all on right),
        // render a single centered container so the button(s) align perfectly under
        // the Actions header. Only use the two-slot grid when both sides are present.
        if ($leftHtml && !$rightHtml) {
            return '<div class="cash-advance-actions-single d-flex justify-content-center">' . $leftHtml . '</div>';
        }

        if ($rightHtml && !$leftHtml) {
            return '<div class="cash-advance-actions-single d-flex justify-content-center">' . $rightHtml . '</div>';
        }

        return '<div class="cash-advance-actions d-flex align-items-center">'
            . '<div class="cash-advance-actions-left flex-grow-1 d-flex justify-content-center">' . $leftHtml . '</div>'
            . '<div class="cash-advance-actions-right flex-grow-1 d-flex justify-content-center">' . $rightHtml . '</div>'
            . '</div>';
    }
}
