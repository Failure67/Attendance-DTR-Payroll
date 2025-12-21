<?php

namespace App\Http\Controllers;

use App\Models\CrewAssignment;
use App\Models\LeaveCreditAccount;
use App\Models\LeaveCreditTransaction;
use App\Models\User;
use App\Services\Leave\LeaveCreditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveCreditController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');

        $employeeOptions = $this->buildEmployeeOptions($currentUser, $roleKey);

        $employeeId = $request->input('employee_id');
        $leaveCode = $request->input('leave_code');

        $selectedEmployee = null;
        $balances = null;
        $transactions = null;
        $transactionTableData = [];

        if (!empty($employeeId)) {
            $employeeId = (int) $employeeId;

            if (!array_key_exists($employeeId, $employeeOptions)) {
                abort(403);
            }

            $selectedEmployee = User::findOrFail($employeeId);

            $service = app(LeaveCreditService::class);
            $service->ensureAccruedUpTo($selectedEmployee, now());

            $balances = [];
            foreach (array_keys((array) config('leave.leave_type_labels', [])) as $code) {
                $balances[$code] = $service->getBalance($selectedEmployee, $code, Carbon::now());
            }

            $accountIds = LeaveCreditAccount::where('user_id', $selectedEmployee->id)->pluck('id');

            $query = LeaveCreditTransaction::with(['actor', 'account'])
                ->whereIn('account_id', $accountIds)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id');

            if (!empty($leaveCode)) {
                $query->whereHas('account', function ($q) use ($leaveCode) {
                    $q->where('leave_code', $leaveCode);
                });
            }

            $transactions = $query->paginate(25)->withQueryString();

            $transactionTableData = $transactions->map(function (LeaveCreditTransaction $tx) {
                $actorName = $tx->actor ? ($tx->actor->full_name ?? $tx->actor->username) : 'System';
                $code = $tx->account ? strtoupper((string) $tx->account->leave_code) : '';

                $amountText = ($tx->direction === 'debit' ? '-' : '+') . number_format((float) $tx->amount, 3) . ' d';
                $remainingText = $tx->direction === 'credit'
                    ? number_format((float) ($tx->remaining_amount ?? 0), 3) . ' d'
                    : '—';

                $occurredAt = $tx->occurred_at ? $tx->occurred_at->format('Y-m-d H:i') : '—';
                $effective = $tx->effective_date ? $tx->effective_date->format('Y-m-d') : '—';
                $expires = $tx->expires_at ? $tx->expires_at->format('Y-m-d') : '—';

                return [
                    $occurredAt,
                    $effective,
                    $code,
                    (string) $tx->type,
                    (string) $tx->direction,
                    $amountText,
                    $remainingText,
                    $expires,
                    (string) $actorName,
                    (string) ($tx->description ?? ''),
                ];
            })->toArray();
        }

        return view('pages.leave-credits', [
            'title' => 'Leave credits',
            'pageClass' => 'leave-credits',
            'employeeOptions' => $employeeOptions,
            'selectedEmployee' => $selectedEmployee,
            'balances' => $balances,
            'transactions' => $transactions,
            'transactionTableData' => $transactionTableData,
            'filters' => [
                'employee_id' => $employeeId,
                'leave_code' => $leaveCode,
            ],
            'leaveTypeLabels' => (array) config('leave.leave_type_labels', []),
        ]);
    }

    public function adjust(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');
        if (!in_array($roleKey, ['admin', 'hr'], true)) {
            abort(403);
        }

        $leaveCodes = array_keys((array) config('leave.leave_type_labels', []));

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'leave_code' => 'required|string|in:' . implode(',', $leaveCodes),
            'direction' => 'required|string|in:credit,debit',
            'amount' => 'required|numeric|min:0.001',
            'effective_date' => 'nullable|date',
            'reason' => 'required|string|max:255',
        ]);

        $employee = User::findOrFail((int) $validated['employee_id']);
        $leaveCode = (string) $validated['leave_code'];
        $direction = (string) $validated['direction'];
        $amount = (float) $validated['amount'];
        $effectiveDate = !empty($validated['effective_date'])
            ? Carbon::parse($validated['effective_date'])->startOfDay()
            : Carbon::now()->startOfDay();

        $service = app(LeaveCreditService::class);

        try {
            if ($direction === 'credit') {
                $service->creditAdjustment($employee, $leaveCode, $amount, $effectiveDate, $currentUser->id, $validated['reason']);
            } else {
                $service->debitAdjustment($employee, $leaveCode, $amount, $effectiveDate, $currentUser->id, $validated['reason']);
            }
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->back()->with('success', 'Leave credit adjustment recorded.');
    }

    public function accrue(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');
        if (!in_array($roleKey, ['admin', 'hr'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
        ]);

        $employee = User::findOrFail((int) $validated['employee_id']);

        $service = app(LeaveCreditService::class);
        $service->ensureAccruedUpTo($employee, now());

        return redirect()->back()->with('success', 'Accrual checked/applied up to today.');
    }

    public function updateEmploymentStartDate(Request $request)
    {
        $currentUser = auth()->user();
        if (!$currentUser) {
            abort(403);
        }

        $roleKey = strtolower($currentUser->role ?? '');
        if (!in_array($roleKey, ['admin', 'hr'], true)) {
            abort(403);
        }

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:users,id',
            'employment_start_date' => 'required|date',
        ]);

        $employee = User::findOrFail((int) $validated['employee_id']);
        $employee->employment_start_date = Carbon::parse($validated['employment_start_date'])->toDateString();
        $employee->save();

        return redirect()->back()->with('success', 'Employment start date updated.');
    }

    public function workerIndex(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        $service = app(LeaveCreditService::class);
        $service->ensureAccruedUpTo($user, now());

        $balances = [];
        foreach (array_keys((array) config('leave.leave_type_labels', [])) as $code) {
            $balances[$code] = $service->getBalance($user, $code, Carbon::now());
        }

        $accountIds = LeaveCreditAccount::where('user_id', $user->id)->pluck('id');

        $query = LeaveCreditTransaction::with(['actor', 'account'])
            ->whereIn('account_id', $accountIds)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        $transactions = $query->paginate(20)->withQueryString();

        $transactionTableData = $transactions->map(function (LeaveCreditTransaction $tx) {
            $actorName = $tx->actor ? ($tx->actor->full_name ?? $tx->actor->username) : 'System';
            $code = $tx->account ? strtoupper((string) $tx->account->leave_code) : '';

            $amountText = ($tx->direction === 'debit' ? '-' : '+') . number_format((float) $tx->amount, 3) . ' d';
            $remainingText = $tx->direction === 'credit'
                ? number_format((float) ($tx->remaining_amount ?? 0), 3) . ' d'
                : '—';

            $occurredAt = $tx->occurred_at ? $tx->occurred_at->format('Y-m-d H:i') : '—';
            $effective = $tx->effective_date ? $tx->effective_date->format('Y-m-d') : '—';
            $expires = $tx->expires_at ? $tx->expires_at->format('Y-m-d') : '—';

            return [
                $occurredAt,
                $effective,
                $code,
                (string) $tx->type,
                $amountText,
                $remainingText,
                $expires,
                (string) $actorName,
                (string) ($tx->description ?? ''),
            ];
        })->toArray();

        return view('user.pages.leave-credits', [
            'title' => 'Leave credits',
            'pageClass' => 'employee-leave-credits',
            'user' => $user,
            'balances' => $balances,
            'transactions' => $transactions,
            'transactionTableData' => $transactionTableData,
            'leaveTypeLabels' => (array) config('leave.leave_type_labels', []),
        ]);
    }

    private function buildEmployeeOptions(User $currentUser, string $roleKey): array
    {
        $query = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin']);

        if ($roleKey === 'supervisor') {
            $crewWorkerIds = CrewAssignment::where('supervisor_id', $currentUser->id)->pluck('worker_id');

            if ($crewWorkerIds->isNotEmpty()) {
                $query->whereIn('id', $crewWorkerIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $employees = $query
            ->select(['id', 'full_name', 'username'])
            ->orderBy('full_name')
            ->orderBy('username')
            ->get();

        return $employees->mapWithKeys(function (User $user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();
    }
}
