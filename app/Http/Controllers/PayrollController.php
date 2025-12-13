<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payroll\RunProcessPayrollRequest;
use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Http\Requests\Payroll\UpdatePayrollRequest;
use App\Models\Payroll;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(private PayrollService $payrollService)
    {
    }

    public function viewPayroll(Request $request)
    {
        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $showArchived = $request->boolean('archived');

        $query = $showArchived
            ? Payroll::onlyTrashed()->with('user')
            : Payroll::with('user');

        $query->orderByDesc('period_end')
            ->orderByDesc('created_at');

        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($periodStart)) {
            $query->whereDate('period_start', '>=', Carbon::parse($periodStart)->toDateString());
        }

        if (!empty($periodEnd)) {
            $query->whereDate('period_end', '<=', Carbon::parse($periodEnd)->toDateString());
        }

        $payrolls = $query->paginate(10)->appends($request->query());

        return view('pages.payroll', [
            'title' => 'Payroll',
            'pageClass' => 'payroll',
            'employeeOptions' => $employeeOptions,
            'payrolls' => $payrolls,
            'filters' => [
                'employee_id' => $employeeId,
                'status' => $status,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            'showArchived' => $showArchived,
        ]);
    }

    public function exportPayroll(Request $request)
    {
        $query = Payroll::with('user')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at');

        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($periodStart)) {
            $query->whereDate('period_start', '>=', Carbon::parse($periodStart)->toDateString());
        }

        if (!empty($periodEnd)) {
            $query->whereDate('period_end', '<=', Carbon::parse($periodEnd)->toDateString());
        }

        $payrolls = $query->limit(1000)->get();

        $filename = 'payroll_export_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($payrolls) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee',
                'Period start',
                'Period end',
                'Wage type',
                'Minimum wage',
                'Units worked',
                'Regular hours',
                'Overtime hours',
                'Absent days',
                'Gross pay',
                'Total deductions',
                'Net pay',
                'Status',
                'Created at',
            ]);

            foreach ($payrolls as $payroll) {
                $employeeName = $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : 'Unknown employee';

                $start = $payroll->period_start ? $payroll->period_start->format('Y-m-d') : '';
                $end = $payroll->period_end ? $payroll->period_end->format('Y-m-d') : '';

                $startForExport = $start !== '' ? "'" . $start : '';
                $endForExport = $end !== '' ? "'" . $end : '';

                $createdAt = $payroll->created_at ? $payroll->created_at->format('Y-m-d H:i:s') : '';
                $createdAtForExport = $createdAt !== '' ? "'" . $createdAt : '';

                $units = $payroll->hours_worked ?? $payroll->days_worked ?? 0;
                $unitLabelMap = [
                    'Hourly' => 'hour/s',
                    'Daily' => 'day/s',
                    'Weekly' => 'week/s',
                    'Monthly' => 'month/s',
                    'Piece rate' => 'unit/s',
                ];
                $unitLabel = $unitLabelMap[$payroll->wage_type] ?? 'unit/s';
                $unitsWorked = $units . ' ' . $unitLabel;

                fputcsv($handle, [
                    $employeeName,
                    $startForExport,
                    $endForExport,
                    $payroll->wage_type ?? '',
                    (float) ($payroll->min_wage ?? 0),
                    $unitsWorked,
                    (float) ($payroll->regular_hours ?? 0),
                    (float) ($payroll->overtime_hours ?? 0),
                    (float) ($payroll->absent_days ?? 0),
                    (float) ($payroll->gross_pay ?? 0),
                    (float) ($payroll->total_deductions ?? 0),
                    (float) ($payroll->net_pay ?? 0),
                    $payroll->status ?? 'Pending',
                    $createdAtForExport,
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPayrollPdf(Request $request)
    {
        $query = Payroll::with('user')
            ->orderByDesc('period_end')
            ->orderByDesc('created_at');

        $employeeId = $request->input('employee_id');
        $status = $request->input('status');
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');

        if (!empty($employeeId)) {
            $query->where('user_id', $employeeId);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($periodStart)) {
            $query->whereDate('period_start', '>=', Carbon::parse($periodStart)->toDateString());
        }

        if (!empty($periodEnd)) {
            $query->whereDate('period_end', '<=', Carbon::parse($periodEnd)->toDateString());
        }

        $payrolls = $query->limit(1000)->get();

        $filters = [
            'employee_id' => $employeeId,
            'status' => $status,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ];

        $generatedAt = now();

        $pdf = Pdf::loadView('pdf.payroll-summary', [
            'title' => 'Payroll Report',
            'payrolls' => $payrolls,
            'filters' => $filters,
            'generatedAt' => $generatedAt,
        ])->setPaper('a4', 'landscape');

        $filename = 'payroll_report_' . $generatedAt->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    public function viewCashAdvances()
    {
        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();
        $showArchived = request()->boolean('archived');

        $cashAdvanceQuery = \App\Models\CashAdvance::with('user', 'payroll');

        if ($showArchived) {
            $cashAdvanceQuery->onlyTrashed();
        }

        $cashAdvances = $cashAdvanceQuery
            ->latest()
            ->limit(200)
            ->get();
        $showArchivedLocal = $showArchived;

        $cashAdvanceTableData = $cashAdvances->map(function ($entry) use ($showArchivedLocal) {
            $employeeName = $entry->user ? ($entry->user->full_name ?? $entry->user->username) : 'Unknown employee';
            $typeLabel = $entry->type === 'repayment' ? 'Repayment' : 'Advance';
            $amount = '₱ ' . number_format((float) $entry->amount, 2);
            $sourceLabel = $entry->source === 'payroll' ? 'Payroll' : 'Manual';
            $payrollRef = $entry->payroll ? ('#' . $entry->payroll->id) : '—';
            $description = $entry->description ?? '—';
            $date = $entry->created_at ? $entry->created_at->format('Y-m-d') : '—';

            $employeeCell = '<span class="cash-advance-entry" data-cash-advance-id="' . $entry->id . '">' . e($employeeName) . '</span>';

            $row = [
                $employeeCell,
                $typeLabel,
                $amount,
                $sourceLabel,
                $payrollRef,
                $description,
                $date,
            ];

            if ($showArchivedLocal) {
                $csrf = csrf_token();

                $restoreForm = "<form method=\"POST\" action=\"" . route('cash-advances.restore', ['id' => $entry->id]) . "\" style=\"display:inline-block;margin-right:4px;\" onsubmit=\"return confirm('Recover this cash advance entry?');\">"
                    . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                    '<button type="submit" class="btn btn-outline-success btn-sm" title="Recover">'
                    . '<i class="fa-solid fa-rotate-left"></i>' .
                    '</button>' .
                    '</form>';

                $deleteForm = "<form method=\"POST\" action=\"" . route('cash-advances.delete', ['id' => $entry->id]) . "\" style=\"display:inline-block;\" onsubmit=\"return confirm('Permanently delete this cash advance entry? This cannot be undone.');\">"
                    . '<input type="hidden" name="_token" value="' . $csrf . '">' .
                    '<input type="hidden" name="_method" value="DELETE">'
                    . '<input type="hidden" name="archived" value="1">'
                    . '<button type="submit" class="btn btn-outline-danger btn-sm" title="Delete permanently">'
                    . '<i class="fa-solid fa-trash"></i>' .
                    '</button>' .
                    '</form>';

                $actionsHtml = '<div class="cash-advances-archive-actions d-flex align-items-center gap-1">'
                    . $restoreForm
                    . $deleteForm
                    . '</div>';

                $row[] = $actionsHtml;
            }

            return $row;
        })->toArray();

        $balanceRows = \App\Models\CashAdvance::select('user_id')
            ->selectRaw("SUM(CASE WHEN type = 'advance' THEN amount ELSE 0 END) AS total_advances")
            ->selectRaw("SUM(CASE WHEN type = 'repayment' THEN amount ELSE 0 END) AS total_repayments")
            ->groupBy('user_id')
            ->with('user')
            ->get();

        $cashAdvanceSummaryTableData = $balanceRows->map(function ($row) {
            $employeeName = $row->user ? ($row->user->full_name ?? $row->user->username) : 'Unknown employee';
            $totalAdvances = (float) ($row->total_advances ?? 0);
            $totalRepayments = (float) ($row->total_repayments ?? 0);
            $outstanding = max(0, $totalAdvances - $totalRepayments);

            return [
                $employeeName,
                '₱ ' . number_format($totalAdvances, 2),
                '₱ ' . number_format($totalRepayments, 2),
                '₱ ' . number_format($outstanding, 2),
            ];
        })->toArray();

        // Cash advance requests (for integration into this page)
        $caRequests = \App\Models\CashAdvanceRequest::with('user')
            ->orderByDesc('created_at')
            ->paginate(10);

        $cashAdvanceRequestsTableData = $caRequests->map(function (\App\Models\CashAdvanceRequest $entry) {
            $employeeName = $entry->user ? ($entry->user->full_name ?? $entry->user->username) : 'Unknown employee';
            $amount = '₱ ' . number_format((float) $entry->amount, 2);
            $status = $entry->status ?? 'Pending';
            $createdAt = $entry->created_at ? $entry->created_at->format('Y-m-d') : '—';

            $actions = app(\App\Http\Controllers\CashAdvanceRequestController::class)->buildActionsHtml($entry);

            return [
                e($employeeName),
                $amount,
                e($status),
                e($createdAt),
                $actions,
            ];
        })->toArray();

        return view('pages.cash-advances', [
            'title' => 'Cash advances',
            'pageClass' => 'cash-advances',
            'cashAdvanceSummaryTableData' => $cashAdvanceSummaryTableData,
            'cashAdvanceTableData' => $cashAdvanceTableData,
            'cashAdvanceRequests' => $caRequests,
            'cashAdvanceRequestsTableData' => $cashAdvanceRequestsTableData,
            'employeeOptions' => $employeeOptions,
            'showArchived' => $showArchived,
        ]);
    }

    public function deleteCashAdvance(Request $request, $id)
    {
        $cashAdvance = \App\Models\CashAdvance::withTrashed()->findOrFail($id);

        $stayOnArchived = $request->boolean('archived');

        if ($cashAdvance->trashed()) {
            $cashAdvance->forceDelete();
            $message = 'Cash advance permanently deleted.';
        } else {
            $cashAdvance->delete();
            $message = 'Cash advance archived successfully.';
        }

        $routeParams = $stayOnArchived ? ['archived' => 1] : [];

        return redirect()->route('cash-advances', $routeParams)->with('success', $message);
    }

    public function restoreCashAdvance($id)
    {
        $cashAdvance = \App\Models\CashAdvance::withTrashed()->findOrFail($id);

        if ($cashAdvance->trashed()) {
            $cashAdvance->restore();
        }

        return redirect()->route('cash-advances', ['archived' => 1])
            ->with('success', 'Cash advance record recovered successfully.');
    }

    public function deleteMultipleCashAdvances(Request $request)
    {
        $validated = $request->validate([
            'cash_advance_ids' => 'required|array',
            'cash_advance_ids.*' => 'exists:cash_advances,id',
        ]);

        $stayOnArchived = $request->boolean('archived');

        $cashAdvances = \App\Models\CashAdvance::withTrashed()
            ->whereIn('id', $validated['cash_advance_ids'])
            ->get();

        foreach ($cashAdvances as $cashAdvance) {
            if ($cashAdvance->trashed()) {
                $cashAdvance->forceDelete();
            } else {
                $cashAdvance->delete();
            }
        }

        $routeParams = $stayOnArchived ? ['archived' => 1] : [];

        return redirect()->route('cash-advances', $routeParams)
            ->with('success', 'Selected cash advance entries processed successfully.');
    }

    public function storeCashAdvance(Request $request)
    {
        $request->merge([
            'amount' => str_replace(',', '', (string) $request->input('amount')),
        ]);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:advance,repayment',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $userId = $validated['user_id'];
        $type = $validated['type'];
        $amount = (float) $validated['amount'];

        if ($type === 'repayment') {
            $totalAdvances = (float) \App\Models\CashAdvance::where('user_id', $userId)
                ->where('type', 'advance')
                ->sum('amount');

            $totalRepayments = (float) \App\Models\CashAdvance::where('user_id', $userId)
                ->where('type', 'repayment')
                ->sum('amount');

            $outstanding = max(0, $totalAdvances - $totalRepayments);

            if ($amount > $outstanding + 0.0001) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'amount' => 'Repayment amount cannot exceed current cash advance balance.',
                    ]);
            }
        }

        \App\Models\CashAdvance::create([
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'description' => $validated['description'] ?? null,
            'source' => 'admin',
            'payroll_id' => null,
        ]);

        return redirect()->route('cash-advances')->with('success', 'Cash advance entry saved successfully.');
    }

    public function showPayroll($id)
    {
        $payroll = Payroll::with('deductions', 'cashAdvances', 'user')->findOrFail($id);

        $cashAdvanceRepayments = $payroll->cashAdvances
            ? $payroll->cashAdvances->where('type', 'repayment')
            : collect();

        return response()->json([
            'id' => $payroll->id,
            'user_id' => $payroll->user_id,
            'employee_name' => $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : null,
            'wage_type' => $payroll->wage_type,
            'min_wage' => (float) ($payroll->min_wage ?? 0),
            'hours_worked' => (float) ($payroll->hours_worked ?? 0),
            'days_worked' => (float) ($payroll->days_worked ?? 0),
            'regular_hours' => (float) ($payroll->regular_hours ?? 0),
            'overtime_hours' => (float) ($payroll->overtime_hours ?? 0),
            'absent_days' => (float) ($payroll->absent_days ?? 0),
            'gross_pay' => (float) ($payroll->gross_pay ?? 0),
            'total_deductions' => (float) ($payroll->total_deductions ?? 0),
            'net_pay' => (float) ($payroll->net_pay ?? 0),
            'status' => $payroll->status,
            'created_at' => $payroll->created_at ? $payroll->created_at->format('Y-m-d H:i:s') : null,
            'period_start' => $payroll->period_start ? $payroll->period_start->format('Y-m-d') : null,
            'period_end' => $payroll->period_end ? $payroll->period_end->format('Y-m-d') : null,
            'deductions' => $payroll->deductions->map(function ($d) {
                return [
                    'name' => $d->deduction_name,
                    'amount' => (float) $d->amount,
                ];
            })->values(),
            'cash_advances' => $cashAdvanceRepayments->map(function ($ca) {
                return [
                    'type' => $ca->type,
                    'amount' => (float) $ca->amount,
                    'description' => $ca->description,
                ];
            })->values(),
        ]);
    }

    public function viewProcessPayroll(Request $request)
    {
        $periodStartInput = $request->input('period_start');
        $periodEndInput = $request->input('period_end');

        $previewRows = [];
        $previewSummary = null;

        if ($request->filled('period_start') || $request->filled('period_end')) {
            $validated = $request->validate([
                'period_start' => 'required|date',
                'period_end' => 'required|date|after_or_equal:period_start',
            ]);

            [$periodStartInput, $periodEndInput, $previewRows, $previewSummary] =
                $this->payrollService->buildProcessPreview($validated['period_start'], $validated['period_end']);
        }

        return view('pages.payroll-process', [
            'title' => 'Process payroll',
            'pageClass' => 'payroll-process',
            'period_start' => $periodStartInput,
            'period_end' => $periodEndInput,
            'previewRows' => $previewRows,
            'previewSummary' => $previewSummary,
        ]);
    }

    public function runProcessPayroll(RunProcessPayrollRequest $request)
    {
        $validated = $request->validated();

        $this->payrollService->processFromAttendance($validated);

        return redirect()->route('payroll')->with('success', 'Payrolls processed from attendance successfully.');
    }

    public function updatePayroll(UpdatePayrollRequest $request, $id)
    {
        $payroll = Payroll::findOrFail($id);
        $validated = $request->validated();

        $this->payrollService->updateManualPayroll($payroll, $validated);

        return redirect()->route('payroll')->with('success', 'Payroll updated successfully.');
    }

    public function storePayroll(StorePayrollRequest $request)
    {
        $validated = $request->validated();

        $this->payrollService->createManualPayroll($validated);

        return redirect()->route('payroll')->with('success', 'Payroll added successfully.');
    }

    public function updatePayrollStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Completed,Cancelled',
        ]);

        $payroll = Payroll::findOrFail($id);

        $this->payrollService->updatePayrollStatus($payroll, $validated['status']);

        return redirect()->route('payroll')->with('success', 'Payroll status updated successfully.');
    }

    public function hrApprove(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $role = (string) ($user->role ?? '');

        // HR stage is restricted to HR, with Superadmin as override (Admin should not act as HR)
        if (!in_array($role, ['Superadmin', 'HR'], true)) {
            abort(403, 'You are not allowed to approve payroll at HR stage.');
        }

        $payroll = Payroll::findOrFail($id);

        if ($payroll->status === 'Cancelled') {
            return redirect()->back()->with('error', 'Cancelled payrolls cannot be approved.');
        }

        if ($payroll->status === 'Released') {
            return redirect()->back()->with('success', 'This payroll is already released.');
        }

        if ($payroll->hr_approved_at) {
            return redirect()->back()->with('success', 'Payroll is already HR approved.');
        }

        $payroll->hr_approved_at = now();
        $payroll->hr_approved_by = $user->id;
        $payroll->save();

        return redirect()->back()->with('success', 'Payroll marked as HR approved.');
    }

    public function adminApprove(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $role = (string) ($user->role ?? '');

        $finalRoles = ['Superadmin', 'Admin', 'Accounting'];
        if (!in_array($role, $finalRoles, true)) {
            abort(403, 'You are not allowed to approve payroll at final stage.');
        }

        $payroll = Payroll::findOrFail($id);

        if ($payroll->status === 'Cancelled') {
            return redirect()->back()->with('error', 'Cancelled payrolls cannot be approved.');
        }

        if ($payroll->status === 'Released') {
            if (!$payroll->admin_approved_at) {
                $payroll->admin_approved_at = now();
                $payroll->admin_approved_by = $user->id;
                $payroll->save();
            }

            return redirect()->back()->with('success', 'This payroll is already released.');
        }

        if (!$payroll->hr_approved_at && $role !== 'Superadmin') {
            return redirect()->back()->with('error', 'HR must approve the payroll before final approval.');
        }

        $payroll->admin_approved_at = now();
        $payroll->admin_approved_by = $user->id;
        $payroll->save();

        $this->payrollService->updatePayrollStatus($payroll, 'Completed');

        return redirect()->back()->with('success', 'Payroll approved and released.');
    }

    public function restorePayroll($id)
    {
        $payroll = Payroll::withTrashed()->findOrFail($id);

        if ($payroll->trashed()) {
            $payroll->restore();
        }

        return redirect()->route('payroll', ['archived' => 1])
            ->with('success', 'Payroll record recovered successfully.');
    }

    public function deletePayroll(Request $request, $id)
    {
        $payroll = Payroll::withTrashed()->findOrFail($id);

        $stayOnArchived = $request->boolean('archived');

        if ($payroll->trashed()) {
            $payroll->forceDelete();
            $message = 'Payroll permanently deleted.';
        } else {
            $payroll->delete();
            $message = 'Payroll archived successfully.';
        }

        $routeParams = $stayOnArchived ? ['archived' => 1] : [];

        return redirect()->route('payroll', $routeParams)->with('success', $message);
    }

    public function deleteMultiplePayroll(Request $request)
    {
        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:payrolls,id',
        ]);

        $stayOnArchived = $request->boolean('archived');

        $payrolls = Payroll::withTrashed()
            ->whereIn('id', $validated['payroll_ids'])
            ->get();

        foreach ($payrolls as $payroll) {
            if ($payroll->trashed()) {
                $payroll->forceDelete();
            } else {
                $payroll->delete();
            }
        }

        $routeParams = $stayOnArchived ? ['archived' => 1] : [];

        return redirect()->route('payroll', $routeParams)
            ->with('success', 'Selected payrolls successfully deleted.');
    }
}
