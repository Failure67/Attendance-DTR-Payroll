<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncPushToCloud extends Command
{
    protected $signature = 'sync:push-to-cloud {--dry-run : Do not send data, only show what would be synced}';

    protected $description = 'Push local changes to the cloud read-only instance';

    public function handle(): int
    {
        $enabled = filter_var(env('SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            $this->info('Sync is disabled (set SYNC_ENABLED=true in .env to enable).');

            return self::SUCCESS;
        }

        $baseUrl = rtrim((string) env('SYNC_API_URL', ''), '/');
        $token = (string) env('SYNC_API_TOKEN', '');
        $batchSize = (int) env('SYNC_BATCH_SIZE', 100);

        if ($batchSize <= 0) {
            $batchSize = 100;
        }

        if ($baseUrl === '' || $token === '') {
            $this->error('SYNC_API_URL or SYNC_API_TOKEN is not configured.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $total = 0;
        $total += $this->syncUsers($baseUrl, $token, $batchSize, $dryRun);
        $total += $this->syncAttendances($baseUrl, $token, $batchSize, $dryRun);
        $total += $this->syncPayrolls($baseUrl, $token, $batchSize, $dryRun);
        $total += $this->syncPayrollDeductions($baseUrl, $token, $batchSize, $dryRun);
        $total += $this->syncCashAdvances($baseUrl, $token, $batchSize, $dryRun);

        $this->info('Sync completed. Total records processed: '.$total);

        return self::SUCCESS;
    }

    protected function syncUsers(string $baseUrl, string $token, int $batchSize, bool $dryRun): int
    {
        $processed = 0;

        while (true) {
            $users = User::withTrashed()
                ->where(function ($q) {
                    $q->whereNull('synced_to_cloud_at')
                        ->orWhere(function ($q2) {
                            $q2->whereColumn('updated_at', '>', 'synced_to_cloud_at')
                                ->orWhereColumn('deleted_at', '>', 'synced_to_cloud_at');
                        });
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($users->isEmpty()) {
                break;
            }

            $items = $users->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'profile_picture' => $user->profile_picture,
                    'role' => $user->role,
                    'remember_token' => $user->remember_token,
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toDateTimeString() : null,
                    'created_at' => $user->created_at ? $user->created_at->toDateTimeString() : null,
                    'updated_at' => $user->updated_at ? $user->updated_at->toDateTimeString() : null,
                    'deleted_at' => $user->deleted_at ? $user->deleted_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($items).' users.');
                $processed += count($items);
                break;
            }

            $response = Http::withHeaders([
                'X-Sync-Token' => $token,
            ])->post($baseUrl.'/sync/users', [
                'items' => $items,
            ]);

            if (!$response->successful()) {
                $this->error('Failed to sync users batch. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $users->pluck('id')->all();
            $now = now();

            DB::table('users')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($items);

            if (count($items) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncAttendances(string $baseUrl, string $token, int $batchSize, bool $dryRun): int
    {
        $processed = 0;

        while (true) {
            $rows = Attendance::withTrashed()
                ->where(function ($q) {
                    $q->whereNull('synced_to_cloud_at')
                        ->orWhere(function ($q2) {
                            $q2->whereColumn('updated_at', '>', 'synced_to_cloud_at')
                                ->orWhereColumn('deleted_at', '>', 'synced_to_cloud_at');
                        });
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $items = $rows->map(function (Attendance $attendance) {
                return [
                    'id' => $attendance->id,
                    'user_id' => $attendance->user_id,
                    'time_in' => $attendance->time_in ? $attendance->time_in->toDateTimeString() : null,
                    'time_out' => $attendance->time_out ? $attendance->time_out->toDateTimeString() : null,
                    'date' => $attendance->date ? $attendance->date->toDateString() : null,
                    'total_hours' => $attendance->total_hours,
                    'overtime_hours' => $attendance->overtime_hours,
                    'status' => $attendance->status,
                    'overtime_approved' => $attendance->overtime_approved,
                    'leave_approved' => $attendance->leave_approved,
                    'created_at' => $attendance->created_at ? $attendance->created_at->toDateTimeString() : null,
                    'updated_at' => $attendance->updated_at ? $attendance->updated_at->toDateTimeString() : null,
                    'deleted_at' => $attendance->deleted_at ? $attendance->deleted_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($items).' attendances.');
                $processed += count($items);
                break;
            }

            $response = Http::withHeaders([
                'X-Sync-Token' => $token,
            ])->post($baseUrl.'/sync/attendances', [
                'items' => $items,
            ]);

            if (!$response->successful()) {
                $this->error('Failed to sync attendances batch. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('attendances')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($items);

            if (count($items) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncPayrolls(string $baseUrl, string $token, int $batchSize, bool $dryRun): int
    {
        $processed = 0;

        while (true) {
            $rows = Payroll::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $items = $rows->map(function (Payroll $payroll) {
                return [
                    'id' => $payroll->id,
                    'user_id' => $payroll->user_id,
                    'wage_type' => $payroll->wage_type,
                    'min_wage' => $payroll->min_wage,
                    'hours_worked' => $payroll->hours_worked,
                    'days_worked' => $payroll->days_worked,
                    'regular_hours' => $payroll->regular_hours,
                    'overtime_hours' => $payroll->overtime_hours,
                    'absent_days' => $payroll->absent_days,
                    'gross_pay' => $payroll->gross_pay,
                    'total_deductions' => $payroll->total_deductions,
                    'net_pay' => $payroll->net_pay,
                    'status' => $payroll->status,
                    'period_start' => $payroll->period_start ? $payroll->period_start->toDateString() : null,
                    'period_end' => $payroll->period_end ? $payroll->period_end->toDateString() : null,
                    'released_at' => $payroll->released_at ? $payroll->released_at->toDateTimeString() : null,
                    'created_at' => $payroll->created_at ? $payroll->created_at->toDateTimeString() : null,
                    'updated_at' => $payroll->updated_at ? $payroll->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($items).' payrolls.');
                $processed += count($items);
                break;
            }

            $response = Http::withHeaders([
                'X-Sync-Token' => $token,
            ])->post($baseUrl.'/sync/payrolls', [
                'items' => $items,
            ]);

            if (!$response->successful()) {
                $this->error('Failed to sync payrolls batch. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('payrolls')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($items);

            if (count($items) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncPayrollDeductions(string $baseUrl, string $token, int $batchSize, bool $dryRun): int
    {
        $processed = 0;

        while (true) {
            $rows = PayrollDeduction::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $items = $rows->map(function (PayrollDeduction $deduction) {
                return [
                    'id' => $deduction->id,
                    'payroll_id' => $deduction->payroll_id,
                    'deduction_name' => $deduction->deduction_name,
                    'amount' => $deduction->amount,
                    'created_at' => $deduction->created_at ? $deduction->created_at->toDateTimeString() : null,
                    'updated_at' => $deduction->updated_at ? $deduction->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($items).' payroll deductions.');
                $processed += count($items);
                break;
            }

            $response = Http::withHeaders([
                'X-Sync-Token' => $token,
            ])->post($baseUrl.'/sync/payroll-deductions', [
                'items' => $items,
            ]);

            if (!$response->successful()) {
                $this->error('Failed to sync payroll deductions batch. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('payroll_deductions')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($items);

            if (count($items) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncCashAdvances(string $baseUrl, string $token, int $batchSize, bool $dryRun): int
    {
        $processed = 0;

        while (true) {
            $rows = CashAdvance::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $items = $rows->map(function (CashAdvance $cashAdvance) {
                return [
                    'id' => $cashAdvance->id,
                    'user_id' => $cashAdvance->user_id,
                    'type' => $cashAdvance->type,
                    'amount' => $cashAdvance->amount,
                    'description' => $cashAdvance->description,
                    'source' => $cashAdvance->source,
                    'payroll_id' => $cashAdvance->payroll_id,
                    'created_at' => $cashAdvance->created_at ? $cashAdvance->created_at->toDateTimeString() : null,
                    'updated_at' => $cashAdvance->updated_at ? $cashAdvance->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($items).' cash advances.');
                $processed += count($items);
                break;
            }

            $response = Http::withHeaders([
                'X-Sync-Token' => $token,
            ])->post($baseUrl.'/sync/cash-advances', [
                'items' => $items,
            ]);

            if (!$response->successful()) {
                $this->error('Failed to sync cash advances batch. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('cash_advances')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($items);

            if (count($items) < $batchSize) {
                break;
            }
        }

        return $processed;
    }
}
