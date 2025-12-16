<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\CashAdvance;
use App\Models\CashAdvanceRequest;
use App\Models\CrewAssignment;
use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\User;
use App\Models\UserCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncAllToSupabase extends Command
{
    protected $signature = 'sync:to-supabase {--dry-run : Do not send data, only show what would be synced}';

    protected $description = 'Sync core tables (users, attendances, payrolls, deductions, cash advances) to Supabase cloud database';

    public function handle(): int
    {
        $enabled = filter_var(env('SUPABASE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            $this->info('Supabase sync is disabled (set SUPABASE_SYNC_ENABLED=true in .env to enable).');

            return self::SUCCESS;
        }

        $baseUrl = rtrim((string) env('SUPABASE_URL', ''), '/');
        $serviceKey = (string) env('SUPABASE_SERVICE_KEY', '');
        $batchSize = (int) env('SUPABASE_SYNC_BATCH_SIZE', 100);

        if ($batchSize <= 0) {
            $batchSize = 100;
        }

        if ($baseUrl === '' || $serviceKey === '') {
            $this->error('SUPABASE_URL or SUPABASE_SERVICE_KEY is not configured.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $total = 0;
        $total += $this->syncUsers($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncAttendances($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncPayrolls($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncPayrollDeductions($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncCashAdvances($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncCashAdvanceRequests($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncCrewAssignments($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncActivityLogs($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncAnnouncements($baseUrl, $serviceKey, $batchSize, $dryRun);
        $total += $this->syncUserCredentials($baseUrl, $serviceKey, $batchSize, $dryRun);

        $this->info('Supabase full sync completed. Total records processed: '.$total);

        return self::SUCCESS;
    }

    protected function httpClient(string $baseUrl, string $serviceKey)
    {
        return Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Content-Type' => 'application/json',
            'Prefer' => 'return=minimal,resolution=merge-duplicates',
        ])->baseUrl($baseUrl.'/rest/v1');
    }

    protected function syncUsers(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
        $processed = 0;

        while (true) {
            $rows = User::withTrashed()
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

            $payload = $rows->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_picture' => $user->profile_picture,
                    'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toDateTimeString() : null,
                    'created_at' => $user->created_at ? $user->created_at->toDateTimeString() : null,
                    'updated_at' => $user->updated_at ? $user->updated_at->toDateTimeString() : null,
                    'deleted_at' => $user->deleted_at ? $user->deleted_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($payload).' users to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('users_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync users to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('users')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncAttendances(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
        $processed = 0;

        while (true) {
            $rows = Attendance::whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNull('synced_to_cloud_at')
                        ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
                })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $payload = $rows->map(function (Attendance $attendance) {
                return [
                    'id' => $attendance->id,
                    'user_id' => $attendance->user_id,
                    'date' => $attendance->date ? $attendance->date->toDateString() : null,
                    'time_in' => $attendance->time_in ? $attendance->time_in->toDateTimeString() : null,
                    'time_out' => $attendance->time_out ? $attendance->time_out->toDateTimeString() : null,
                    'total_hours' => $attendance->total_hours,
                    'overtime_hours' => $attendance->overtime_hours,
                    'status' => $attendance->status,
                    'overtime_approved' => $attendance->overtime_approved,
                    'leave_approved' => $attendance->leave_approved,
                    'created_at' => $attendance->created_at ? $attendance->created_at->toDateTimeString() : null,
                    'updated_at' => $attendance->updated_at ? $attendance->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($payload).' attendances to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('attendances_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync attendances to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('attendances')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncPayrolls(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
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

            $payload = $rows->map(function (Payroll $payroll) {
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
                $this->info('Would sync '.count($payload).' payrolls to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('payrolls_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync payrolls to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('payrolls')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncPayrollDeductions(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
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

            $payload = $rows->map(function (PayrollDeduction $deduction) {
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
                $this->info('Would sync '.count($payload).' payroll deductions to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('payroll_deductions_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync payroll deductions to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('payroll_deductions')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncCashAdvances(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
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

            $payload = $rows->map(function (CashAdvance $cashAdvance) {
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
                $this->info('Would sync '.count($payload).' cash advances to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('cash_advances_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync cash advances to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('cash_advances')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncCashAdvanceRequests(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
        $processed = 0;

        while (true) {
            $rows = CashAdvanceRequest::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $payload = $rows->map(function (CashAdvanceRequest $request) {
                return [
                    'id' => $request->id,
                    'user_id' => $request->user_id,
                    'amount' => $request->amount,
                    'reason' => $request->reason,
                    'status' => $request->status,
                    'hr_approved_at' => $request->hr_approved_at ? $request->hr_approved_at->toDateTimeString() : null,
                    'manager_approved_at' => $request->manager_approved_at ? $request->manager_approved_at->toDateTimeString() : null,
                    'released_at' => $request->released_at ? $request->released_at->toDateTimeString() : null,
                    'rejected_at' => $request->rejected_at ? $request->rejected_at->toDateTimeString() : null,
                    'rejection_reason' => $request->rejection_reason,
                    'created_at' => $request->created_at ? $request->created_at->toDateTimeString() : null,
                    'updated_at' => $request->updated_at ? $request->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($payload).' cash advance requests to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('cash_advance_requests_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync cash advance requests to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('cash_advance_requests')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncCrewAssignments(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
        $processed = 0;

        while (true) {
            $rows = CrewAssignment::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $payload = $rows->map(function (CrewAssignment $assignment) {
                return [
                    'id' => $assignment->id,
                    'supervisor_id' => $assignment->supervisor_id,
                    'worker_id' => $assignment->worker_id,
                    'created_at' => $assignment->created_at ? $assignment->created_at->toDateTimeString() : null,
                    'updated_at' => $assignment->updated_at ? $assignment->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($payload).' crew assignments to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('crew_assignments_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync crew assignments to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('crew_assignments')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncActivityLogs(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
        $processed = 0;

        while (true) {
            $rows = ActivityLog::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $payload = $rows->map(function (ActivityLog $log) {
                return [
                    'id' => $log->id,
                    'user_id' => $log->user_id,
                    'role' => $log->role,
                    'action' => $log->action,
                    'description' => $log->description,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at ? $log->created_at->toDateTimeString() : null,
                    'updated_at' => $log->updated_at ? $log->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($payload).' activity logs to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('activity_logs_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync activity logs to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('activity_logs')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncAnnouncements(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
        $processed = 0;

        while (true) {
            $rows = Announcement::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $payload = $rows->map(function (Announcement $announcement) {
                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'starts_at' => $announcement->starts_at ? $announcement->starts_at->toDateTimeString() : null,
                    'ends_at' => $announcement->ends_at ? $announcement->ends_at->toDateTimeString() : null,
                    'created_at' => $announcement->created_at ? $announcement->created_at->toDateTimeString() : null,
                    'updated_at' => $announcement->updated_at ? $announcement->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($payload).' announcements to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('announcements_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync announcements to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('announcements')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }

    protected function syncUserCredentials(string $baseUrl, string $serviceKey, int $batchSize, bool $dryRun): int
    {
        $client = $this->httpClient($baseUrl, $serviceKey);
        $processed = 0;

        while (true) {
            $rows = UserCredential::where(function ($q) {
                $q->whereNull('synced_to_cloud_at')
                    ->orWhereColumn('updated_at', '>', 'synced_to_cloud_at');
            })
                ->orderBy('id')
                ->limit($batchSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $payload = $rows->map(function (UserCredential $credential) {
                return [
                    'id' => $credential->id,
                    'user_id' => $credential->user_id,
                    'firstname' => $credential->firstname,
                    'middlename' => $credential->middlename,
                    'lastname' => $credential->lastname,
                    'birthdate' => $credential->birthdate,
                    'gender' => $credential->gender,
                    'created_at' => $credential->created_at ? $credential->created_at->toDateTimeString() : null,
                    'updated_at' => $credential->updated_at ? $credential->updated_at->toDateTimeString() : null,
                ];
            })->all();

            if ($dryRun) {
                $this->info('Would sync '.count($payload).' user credentials to Supabase.');
                $processed += count($payload);
                break;
            }

            $response = $client->post('user_credentials_cloud?on_conflict=id', $payload);

            if (! $response->successful()) {
                $this->error('Failed to sync user credentials to Supabase. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $ids = $rows->pluck('id')->all();
            $now = now();

            DB::table('user_credentials')
                ->whereIn('id', $ids)
                ->update(['synced_to_cloud_at' => $now]);

            $processed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        return $processed;
    }
}
