<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payroll\RunProcessPayrollRequest;
use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Http\Requests\Payroll\UpdatePayrollRequest;
use App\Models\Payroll;
use App\Models\User;
use App\Services\Payroll\PayrollService;
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

        $payrolls = $query->limit(200)->get();

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

    public function viewCashAdvances()
    {
        $employees = User::whereNull('deleted_at')
            ->whereNotIn('role', ['Admin', 'admin', 'Superadmin', 'superadmin'])
            ->get();

        $employeeOptions = $employees->mapWithKeys(function ($user) {
            return [$user->id => $user->full_name ?? $user->username];
        })->toArray();

        $cashAdvances = \App\Models\CashAdvance::with('user', 'payroll')
            ->latest()
            ->limit(200)
            ->get();

        $cashAdvanceTableData = $cashAdvances->map(function ($entry) {
            $employeeName = $entry->user ? ($entry->user->full_name ?? $entry->user->username) : 'Unknown employee';
            $typeLabel = $entry->type === 'repayment' ? 'Repayment' : 'Advance';
            $amount = '₱ ' . number_format((float) $entry->amount, 2);
            $sourceLabel = $entry->source === 'payroll' ? 'Payroll' : 'Manual';
            $payrollRef = $entry->payroll ? ('#' . $entry->payroll->id) : '—';
            $description = $entry->description ?? '—';
            $date = $entry->created_at ? $entry->created_at->format('Y-m-d') : '—';

            return [
                $employeeName,
                $typeLabel,
                $amount,
                $sourceLabel,
                $payrollRef,
                $description,
                $date,
            ];
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

        return view('pages.cash-advances', [
            'title' => 'Cash advances',
            'pageClass' => 'cash-advances',
            'cashAdvanceSummaryTableData' => $cashAdvanceSummaryTableData,
            'cashAdvanceTableData' => $cashAdvanceTableData,
            'employeeOptions' => $employeeOptions,
        ]);
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
        $payroll = Payroll::with('deductions', 'user')->findOrFail($id);

        return response()->json([
            'id' => $payroll->id,
            'user_id' => $payroll->user_id,
            'employee_name' => $payroll->user ? ($payroll->user->full_name ?? $payroll->user->username) : null,
            'wage_type' => $payroll->wage_type,
            'min_wage' => (float) ($payroll->min_wage ?? 0),
            'hours_worked' => (float) ($payroll->hours_worked ?? 0),
            'days_worked' => (float) ($payroll->days_worked ?? 0),
            'gross_pay' => (float) ($payroll->gross_pay ?? 0),
            'total_deductions' => (float) ($payroll->total_deductions ?? 0),
            'net_pay' => (float) ($payroll->net_pay ?? 0),
            'status' => $payroll->status,
            'deductions' => $payroll->deductions->map(function ($d) {
                return [
                    'name' => $d->deduction_name,
                    'amount' => (float) $d->amount,
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

    public function deletePayroll(Request $request, $id)
    {
        $payroll = Payroll::findOrFail($id);
        $payroll->delete();

        return redirect()->route('payroll')->with('success', 'Payroll successfully deleted.');
    }

    public function deleteMultiplePayroll(Request $request)
    {
        $validated = $request->validate([
            'payroll_ids' => 'required|array',
            'payroll_ids.*' => 'exists:payrolls,id',
        ]);

        $payroll = Payroll::whereIn('id', $validated['payroll_ids'])->get();

        $payroll->each->delete();

        return redirect()->route('payroll')->with('success', 'Selected payrolls successfully deleted.');
    }
}
