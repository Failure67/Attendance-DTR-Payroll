<?php

namespace App\Http\Controllers;

use App\Models\CashAdvance;
use App\Models\CashAdvanceRequest;
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
                $actions = '<form method="POST" action="' . route('worker.cash-advance-requests.cancel', ['id' => $entry->id]) . '" style="display:inline-block;" onsubmit="return confirm(\'Cancel this cash advance request?\');">'
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

    public function hrApprove($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Superadmin', 'Admin', 'HR'], true)) {
            abort(403, 'Only HR/Admin can approve at HR stage.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        if ($request->status !== 'Pending') {
            return redirect()->back()->with('error', 'Only pending requests can be approved.');
        }

        // Record the HR approval timestamp, then immediately release the
        // request so that a single Approve action behaves like
        // "Approve & release" (similar to payroll).
        $request->status = 'HR approved';
        $request->hr_approved_at = now();
        $request->save();

        // Delegate to the release logic, which will create the actual
        // CashAdvance entry and set the status to Released while keeping
        // the user on the same page via redirect()->back().
        return $this->release($id);
    }

    public function managerApprove($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Superadmin', 'Admin', 'Supervisor'], true)) {
            abort(403, 'Only Supervisor/Admin can approve at manager stage.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        if (!in_array($request->status, ['Pending', 'HR approved'], true)) {
            return redirect()->back()->with('error', 'Only pending or HR approved requests can be manager approved.');
        }

        $request->status = 'Manager approved';
        $request->manager_approved_at = now();
        $request->save();

        return redirect()->back()->with('success', 'Cash advance request marked as Manager approved.');
    }

    public function release($id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Superadmin', 'Admin', 'HR'], true)) {
            abort(403, 'Only HR/Admin can release cash advances.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        if ($request->status === 'Released') {
            return redirect()->back()->with('success', 'Cash advance request already released.');
        }

        if (!in_array($request->status, ['HR approved', 'Manager approved'], true)) {
            return redirect()->back()->with('error', 'Request must be approved before release.');
        }

        CashAdvance::create([
            'user_id' => $request->user_id,
            'type' => 'advance',
            'amount' => $request->amount,
            'description' => 'Cash advance request #' . $request->id . ' released',
            'source' => 'admin',
            'payroll_id' => null,
        ]);

        $request->status = 'Released';
        $request->released_at = now();
        $request->save();

        return redirect()->back()->with('success', 'Cash advance request released and recorded.');
    }

    public function reject(Request $httpRequest, $id)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        if (!in_array($user->role, ['Superadmin', 'Admin', 'HR', 'Supervisor'], true)) {
            abort(403, 'You are not allowed to reject cash advance requests.');
        }

        $request = CashAdvanceRequest::findOrFail($id);

        if (in_array($request->status, ['Released', 'Cancelled', 'Rejected'], true)) {
            return redirect()->back()->with('error', 'This request is already finalized.');
        }

        $reason = (string) $httpRequest->input('rejection_reason', 'Rejected by ' . ($user->role ?? 'system'));

        $request->status = 'Rejected';
        $request->rejected_at = now();
        $request->rejection_reason = $reason;
        $request->save();

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

        // Left slot: main positive action (Approve / Release)
        if (in_array($role, ['Superadmin', 'Admin', 'HR'], true) && $request->status === 'Pending') {
            $leftParts[] = '<form method="POST" action="' . route('cash-advance-requests.hr-approve', ['id' => $request->id]) . '" style="display:inline-block;margin-right:4px;">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">' 
                . '<button type="submit" class="btn btn-outline-primary btn-sm">Approve</button>'
                . '</form>';
        }

        if (in_array($role, ['Superadmin', 'Admin', 'HR'], true) && $request->status === 'HR approved') {
            $leftParts[] = '<form method="POST" action="' . route('cash-advance-requests.release', ['id' => $request->id]) . '" style="display:inline-block;margin-right:4px;" onsubmit="return confirm(\'Release this cash advance to the employee?\');">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">' 
                . '<button type="submit" class="btn btn-success btn-sm">Release</button>'
                . '</form>';
        }

        // Right slot: negative action (Reject)
        if (in_array($role, ['Superadmin', 'Admin', 'HR', 'Supervisor'], true) && !in_array($request->status, ['Released', 'Rejected', 'Cancelled'], true)) {
            $rightParts[] = '<form method="POST" action="' . route('cash-advance-requests.reject', ['id' => $request->id]) . '" style="display:inline-block;" onsubmit="return confirm(\'Reject this cash advance request?\');">'
                . '<input type="hidden" name="_token" value="' . $csrf . '">' 
                . '<button type="submit" class="btn btn-outline-danger btn-sm">Reject</button>'
                . '</form>';
        }

        if (empty($leftParts) && empty($rightParts)) {
            return '<span class="text-muted">No actions</span>';
        }

        $leftHtml = implode('', $leftParts) ?: '&nbsp;';
        $rightHtml = implode('', $rightParts) ?: '&nbsp;';

        return '<div class="cash-advance-actions d-flex align-items-center">'
            . '<div class="cash-advance-actions-left flex-grow-1 d-flex justify-content-center">' . $leftHtml . '</div>'
            . '<div class="cash-advance-actions-right flex-grow-1 d-flex justify-content-center">' . $rightHtml . '</div>'
            . '</div>';
    }
}
