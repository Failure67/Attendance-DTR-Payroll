<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\PayrollDeduction;
use App\Models\RemittanceBatch;
use App\Models\RemittanceLineItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemittanceController extends Controller
{
    private function allowedAgencies(): array
    {
        return ['SSS', 'PhilHealth', 'Pag-IBIG'];
    }

    private function membershipColumnForAgency(string $agency): string
    {
        return match ($agency) {
            'SSS' => 'sss_number',
            'PhilHealth' => 'philhealth_number',
            'Pag-IBIG' => 'pagibig_number',
            default => '',
        };
    }

    private function authorizeView(): void
    {
        $roleKey = strtolower(auth()->user()->role ?? '');
        if (!in_array($roleKey, ['admin', 'superadmin', 'hr', 'accounting', 'project manager', 'manager', 'supervisor'], true)) {
            abort(403);
        }
    }

    private function authorizeManage(): void
    {
        $roleKey = strtolower(auth()->user()->role ?? '');
        if (!in_array($roleKey, ['admin', 'superadmin', 'hr', 'accounting'], true)) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->authorizeView();

        $agency = $request->input('agency');
        $status = $request->input('status');
        $month = $request->input('month');

        $query = RemittanceBatch::query()->orderByDesc('period_month')->orderByDesc('id');

        if (!empty($agency)) {
            $query->where('agency', $agency);
        }

        if (!empty($status)) {
            $query->where('status', $status);
        }

        if (!empty($month)) {
            $monthDate = Carbon::parse($month . '-01')->startOfMonth();
            $query->whereDate('period_month', $monthDate->toDateString());
        }

        $batches = $query->paginate(15)->withQueryString();

        $tableData = $batches->map(function (RemittanceBatch $batch) {
            $monthLabel = $batch->period_month ? $batch->period_month->format('Y-m') : '';
            $viewLink = '<a class="btn btn-outline-primary btn-sm" href="' . route('remittances.show', $batch) . '">View</a>';

            return [
                $monthLabel,
                (string) $batch->agency,
                (string) $batch->status,
                number_format((float) $batch->employee_total, 2),
                number_format((float) $batch->employer_total, 2),
                number_format((float) $batch->grand_total, 2),
                $viewLink,
            ];
        })->toArray();

        return view('pages.remittances', [
            'title' => 'Remittances',
            'pageClass' => 'remittances',
            'batches' => $batches,
            'tableData' => $tableData,
            'agencies' => $this->allowedAgencies(),
            'statuses' => ['draft', 'posted', 'paid', 'submitted'],
            'filters' => [
                'agency' => $agency,
                'status' => $status,
                'month' => $month,
            ],
        ]);
    }

    public function show(Request $request, RemittanceBatch $batch)
    {
        $this->authorizeView();

        $items = $batch->items()->orderBy('employee_name')->paginate(50)->withQueryString();

        $itemTableData = $items->map(function (RemittanceLineItem $item) {
            $member = $item->membership_number ?: '—';
            $missing = $item->missing_membership ? 'Yes' : 'No';

            return [
                (string) $item->employee_name,
                (string) $member,
                $missing,
                number_format((float) $item->employee_amount, 2),
                number_format((float) $item->employer_amount, 2),
                number_format((float) $item->total_amount, 2),
            ];
        })->toArray();

        return view('pages.remittance-show', [
            'title' => 'Remittance batch',
            'pageClass' => 'remittance-show',
            'batch' => $batch,
            'items' => $items,
            'itemTableData' => $itemTableData,
        ]);
    }

    public function generate(Request $request)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'agency' => 'required|string|in:' . implode(',', $this->allowedAgencies()),
            'month' => 'required|date_format:Y-m',
        ]);

        $currentUser = auth()->user();

        $agency = (string) $validated['agency'];
        $periodMonth = Carbon::parse($validated['month'] . '-01')->startOfMonth();
        $periodStart = $periodMonth->copy()->startOfMonth()->startOfDay();
        $periodEnd = $periodMonth->copy()->endOfMonth()->endOfDay();

        $membershipColumn = $this->membershipColumnForAgency($agency);
        if ($membershipColumn === '') {
            return redirect()->back()->with('error', 'Unsupported agency.')->withInput();
        }

        return DB::transaction(function () use ($request, $currentUser, $agency, $periodMonth, $periodStart, $periodEnd, $membershipColumn) {
            $batch = RemittanceBatch::query()
                ->where('agency', $agency)
                ->whereDate('period_month', $periodMonth->toDateString())
                ->lockForUpdate()
                ->first();

            if ($batch && $batch->status !== 'draft') {
                return redirect()->back()->with('error', 'Batch already exists and is not in draft status.');
            }

            if (!$batch) {
                $batch = RemittanceBatch::create([
                    'agency' => $agency,
                    'period_month' => $periodMonth->toDateString(),
                    'status' => 'draft',
                    'created_by' => $currentUser ? $currentUser->id : null,
                ]);
            } else {
                $batch->items()->delete();
            }

            $membershipExpr = 'user_credentials.' . $membershipColumn;

            $rows = PayrollDeduction::query()
                ->join('payrolls', 'payrolls.id', '=', 'payroll_deductions.payroll_id')
                ->join('users', 'users.id', '=', 'payrolls.user_id')
                ->leftJoin('user_credentials', 'user_credentials.user_id', '=', 'users.id')
                ->where('payroll_deductions.deduction_name', $agency)
                ->where('payrolls.status', 'Released')
                ->whereDate('payrolls.period_end', '>=', $periodStart->toDateString())
                ->whereDate('payrolls.period_end', '<=', $periodEnd->toDateString())
                ->select([
                    'payrolls.user_id as user_id',
                    'users.full_name as employee_name',
                ])
                ->selectRaw($membershipExpr . ' as membership_number')
                ->selectRaw('SUM(payroll_deductions.amount) as employee_amount')
                ->groupBy('payrolls.user_id', 'users.full_name', DB::raw($membershipExpr))
                ->orderBy('users.full_name')
                ->get();

            if ($rows->isEmpty()) {
                return redirect()->back()->with('error', 'No matching Released payroll deductions found for that month/agency.');
            }

            $employeeTotal = 0.0;

            foreach ($rows as $row) {
                $employeeAmount = round((float) $row->employee_amount, 2);
                $employeeTotal += $employeeAmount;

                $membershipNumber = $row->membership_number;
                $missing = empty($membershipNumber);

                RemittanceLineItem::create([
                    'batch_id' => $batch->id,
                    'user_id' => $row->user_id,
                    'employee_name' => $row->employee_name,
                    'membership_number' => $membershipNumber,
                    'employee_amount' => $employeeAmount,
                    'employer_amount' => 0,
                    'total_amount' => $employeeAmount,
                    'missing_membership' => $missing,
                ]);
            }

            $batch->employee_total = round($employeeTotal, 2);
            $batch->employer_total = 0;
            $batch->grand_total = $batch->employee_total;
            $batch->save();

            if ($currentUser) {
                ActivityLog::create([
                    'user_id' => $currentUser->id,
                    'role' => $currentUser->role ?? null,
                    'action' => 'remittance_batch_generated',
                    'description' => json_encode([
                        'agency' => $agency,
                        'month' => $periodMonth->format('Y-m'),
                        'batch_id' => $batch->id,
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return redirect()->route('remittances.show', $batch)->with('success', 'Remittance batch generated.');
        });
    }

    public function update(Request $request, RemittanceBatch $batch)
    {
        $this->authorizeManage();

        $validated = $request->validate([
            'status' => 'required|string|in:draft,posted,paid,submitted',
            'payment_reference' => 'nullable|string|max:100',
            'proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        $currentUser = auth()->user();

        return DB::transaction(function () use ($request, $batch, $validated, $currentUser) {
            $batch = RemittanceBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            $previousStatus = $batch->status;
            $newStatus = (string) $validated['status'];

            $batch->status = $newStatus;
            $batch->payment_reference = $validated['payment_reference'] ?? null;

            if (in_array($newStatus, ['draft', 'posted'], true)) {
                $batch->paid_at = null;
                $batch->submitted_at = null;
            }

            if ($newStatus === 'paid') {
                if (!$batch->paid_at) {
                    $batch->paid_at = now();
                }
                $batch->submitted_at = null;
            }

            if ($newStatus === 'submitted') {
                if (!$batch->paid_at) {
                    $batch->paid_at = now();
                }
                if (!$batch->submitted_at) {
                    $batch->submitted_at = now();
                }
            }

            if ($request->hasFile('proof')) {
                $uploadPath = public_path('uploads/remittances');
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }

                if ($batch->proof_path && file_exists(public_path($batch->proof_path))) {
                    @unlink(public_path($batch->proof_path));
                }

                $file = $request->file('proof');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
                $file->move($uploadPath, $filename);

                $batch->proof_path = 'uploads/remittances/' . $filename;
            }

            $batch->save();

            if ($currentUser) {
                ActivityLog::create([
                    'user_id' => $currentUser->id,
                    'role' => $currentUser->role ?? null,
                    'action' => 'remittance_batch_updated',
                    'description' => json_encode([
                        'batch_id' => $batch->id,
                        'from_status' => $previousStatus,
                        'to_status' => $newStatus,
                        'payment_reference' => $batch->payment_reference,
                        'has_proof' => !empty($batch->proof_path),
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return redirect()->route('remittances.show', $batch)->with('success', 'Remittance batch updated.');
        });
    }

    public function export(Request $request, RemittanceBatch $batch)
    {
        $this->authorizeView();

        $batch->loadMissing('items');

        $filename = 'remittance_' . strtolower((string) $batch->agency) . '_' . ($batch->period_month ? $batch->period_month->format('Y-m') : 'month')
            . '_' . now()->format('Ymd_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($batch) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Agency',
                'Month',
                'Employee',
                'Membership no.',
                'Employee amount',
                'Employer amount',
                'Total amount',
                'Missing membership',
            ]);

            $monthLabel = $batch->period_month ? $batch->period_month->format('Y-m') : '';

            foreach ($batch->items as $item) {
                fputcsv($handle, [
                    $batch->agency,
                    $monthLabel !== '' ? "'" . $monthLabel : '',
                    $item->employee_name,
                    (string) ($item->membership_number ?? ''),
                    (float) ($item->employee_amount ?? 0),
                    (float) ($item->employer_amount ?? 0),
                    (float) ($item->total_amount ?? 0),
                    $item->missing_membership ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
