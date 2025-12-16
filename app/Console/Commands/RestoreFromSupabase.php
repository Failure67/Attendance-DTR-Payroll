<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RestoreFromSupabase extends Command
{
    protected $signature = 'restore:from-supabase
                            {--batch=500 : Number of records to pull per batch}
                            {--tables= : Optional comma-separated list of tables to restore (users, attendances, payrolls, payroll_deductions, cash_advances, cash_advance_requests, crew_assignments, activity_logs, announcements, user_credentials)}';

    protected $description = 'Restore core tables from Supabase cloud mirror back into the local database';

    public function handle(): int
    {
        $enabled = filter_var(env('SUPABASE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN);

        if (! $enabled) {
            $this->error('Supabase sync is disabled (set SUPABASE_SYNC_ENABLED=true in .env to enable).');

            return self::FAILURE;
        }

        $baseUrl = rtrim((string) env('SUPABASE_URL', ''), '/');
        $serviceKey = (string) env('SUPABASE_SERVICE_KEY', '');

        if ($baseUrl === '' || $serviceKey === '') {
            $this->error('SUPABASE_URL or SUPABASE_SERVICE_KEY is not configured.');

            return self::FAILURE;
        }

        $batchSize = (int) $this->option('batch');
        if ($batchSize <= 0) {
            $batchSize = 500;
        }

        $tablesOption = $this->option('tables');
        $allowedTables = [
            'users',
            'attendances',
            'payrolls',
            'payroll_deductions',
            'cash_advances',
            'cash_advance_requests',
            'crew_assignments',
            'activity_logs',
            'announcements',
            'user_credentials',
        ];
        $tablesToRestore = $allowedTables;

        if ($tablesOption) {
            $requested = array_filter(array_map('trim', explode(',', $tablesOption)));
            $tablesToRestore = array_values(array_intersect($allowedTables, $requested));

            if (empty($tablesToRestore)) {
                $this->error('No valid tables specified. Allowed values: '.implode(', ', $allowedTables).'.');

                return self::FAILURE;
            }
        }

        $client = $this->httpClient($baseUrl, $serviceKey);

        $this->warn('This command will merge data from Supabase into your local database using upserts (no deletes).');
        $this->warn('Make sure your local schema is migrated and compatible before running on a fresh or recovered database.');

        $total = 0;

        if (in_array('users', $tablesToRestore, true)) {
            $total += $this->restoreUsers($client, $batchSize);
        }
        if (in_array('attendances', $tablesToRestore, true)) {
            $total += $this->restoreAttendances($client, $batchSize);
        }
        if (in_array('payrolls', $tablesToRestore, true)) {
            $total += $this->restorePayrolls($client, $batchSize);
        }
        if (in_array('payroll_deductions', $tablesToRestore, true)) {
            $total += $this->restorePayrollDeductions($client, $batchSize);
        }
        if (in_array('cash_advances', $tablesToRestore, true)) {
            $total += $this->restoreCashAdvances($client, $batchSize);
        }
        if (in_array('cash_advance_requests', $tablesToRestore, true)) {
            $total += $this->restoreCashAdvanceRequests($client, $batchSize);
        }
        if (in_array('crew_assignments', $tablesToRestore, true)) {
            $total += $this->restoreCrewAssignments($client, $batchSize);
        }
        if (in_array('activity_logs', $tablesToRestore, true)) {
            $total += $this->restoreActivityLogs($client, $batchSize);
        }
        if (in_array('announcements', $tablesToRestore, true)) {
            $total += $this->restoreAnnouncements($client, $batchSize);
        }
        if (in_array('user_credentials', $tablesToRestore, true)) {
            $total += $this->restoreUserCredentials($client, $batchSize);
        }

        $this->info('Restore from Supabase completed. Total records processed: '.$total);

        return self::SUCCESS;
    }

    protected function httpClient(string $baseUrl, string $serviceKey)
    {
        return Http::withHeaders([
            'apikey' => $serviceKey,
            'Authorization' => 'Bearer '.$serviceKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->baseUrl($baseUrl.'/rest/v1');
    }

    protected function restoreUsers($client, int $batchSize): int
    {
        $defaultPasswordHash = bcrypt('password');

        return $this->restoreFromCloudTable(
            $client,
            'users_cloud',
            'users',
            function (array $row) use ($defaultPasswordHash) {
                return [
                    'id' => $row['id'],
                    'username' => $row['username'] ?? null,
                    'full_name' => $row['full_name'] ?? null,
                    'email' => $row['email'] ?? null,
                    'role' => $row['role'] ?? null,
                    'profile_picture' => $row['profile_picture'] ?? null,
                    'password' => $defaultPasswordHash,
                    'email_verified_at' => $this->normalizeDateTime($row['email_verified_at'] ?? null),
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'deleted_at' => $this->normalizeDateTime($row['deleted_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'users'
        );
    }

    protected function restoreAttendances($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'attendances_cloud',
            'attendances',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'] ?? null,
                    'date' => $row['date'] ?? null,
                    'time_in' => $this->normalizeDateTime($row['time_in'] ?? null),
                    'time_out' => $this->normalizeDateTime($row['time_out'] ?? null),
                    'total_hours' => $row['total_hours'] ?? null,
                    'overtime_hours' => $row['overtime_hours'] ?? null,
                    'status' => $row['status'] ?? null,
                    'overtime_approved' => $row['overtime_approved'] ?? null,
                    'leave_approved' => $row['leave_approved'] ?? null,
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'attendances'
        );
    }

    protected function restorePayrolls($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'payrolls_cloud',
            'payrolls',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'] ?? null,
                    'wage_type' => $row['wage_type'] ?? null,
                    'min_wage' => $row['min_wage'] ?? null,
                    'hours_worked' => $row['hours_worked'] ?? null,
                    'days_worked' => $row['days_worked'] ?? null,
                    'regular_hours' => $row['regular_hours'] ?? null,
                    'overtime_hours' => $row['overtime_hours'] ?? null,
                    'absent_days' => $row['absent_days'] ?? null,
                    'gross_pay' => $row['gross_pay'] ?? null,
                    'total_deductions' => $row['total_deductions'] ?? null,
                    'net_pay' => $row['net_pay'] ?? null,
                    'status' => $row['status'] ?? null,
                    'period_start' => $row['period_start'] ?? null,
                    'period_end' => $row['period_end'] ?? null,
                    'released_at' => $this->normalizeDateTime($row['released_at'] ?? null),
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'payrolls'
        );
    }

    protected function restorePayrollDeductions($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'payroll_deductions_cloud',
            'payroll_deductions',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'payroll_id' => $row['payroll_id'] ?? null,
                    'deduction_name' => $row['deduction_name'] ?? null,
                    'amount' => $row['amount'] ?? null,
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'payroll_deductions'
        );
    }

    protected function restoreCashAdvances($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'cash_advances_cloud',
            'cash_advances',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'] ?? null,
                    'type' => $row['type'] ?? null,
                    'amount' => $row['amount'] ?? null,
                    'description' => $row['description'] ?? null,
                    'source' => $row['source'] ?? null,
                    'payroll_id' => $row['payroll_id'] ?? null,
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'cash_advances'
        );
    }

    protected function restoreCashAdvanceRequests($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'cash_advance_requests_cloud',
            'cash_advance_requests',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'] ?? null,
                    'amount' => $row['amount'] ?? null,
                    'reason' => $row['reason'] ?? null,
                    'status' => $row['status'] ?? null,
                    'hr_approved_at' => $this->normalizeDateTime($row['hr_approved_at'] ?? null),
                    'manager_approved_at' => $this->normalizeDateTime($row['manager_approved_at'] ?? null),
                    'released_at' => $this->normalizeDateTime($row['released_at'] ?? null),
                    'rejected_at' => $this->normalizeDateTime($row['rejected_at'] ?? null),
                    'rejection_reason' => $row['rejection_reason'] ?? null,
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'cash_advance_requests'
        );
    }

    protected function restoreCrewAssignments($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'crew_assignments_cloud',
            'crew_assignments',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'supervisor_id' => $row['supervisor_id'] ?? null,
                    'worker_id' => $row['worker_id'] ?? null,
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'crew_assignments'
        );
    }

    protected function restoreActivityLogs($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'activity_logs_cloud',
            'activity_logs',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'] ?? null,
                    'role' => $row['role'] ?? null,
                    'action' => $row['action'] ?? null,
                    'description' => $row['description'] ?? null,
                    'ip_address' => $row['ip_address'] ?? null,
                    'user_agent' => $row['user_agent'] ?? null,
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'activity_logs'
        );
    }

    protected function restoreAnnouncements($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'announcements_cloud',
            'announcements',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'title' => $row['title'] ?? null,
                    'body' => $row['body'] ?? null,
                    'starts_at' => $this->normalizeDateTime($row['starts_at'] ?? null),
                    'ends_at' => $this->normalizeDateTime($row['ends_at'] ?? null),
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'announcements'
        );
    }

    protected function restoreUserCredentials($client, int $batchSize): int
    {
        return $this->restoreFromCloudTable(
            $client,
            'user_credentials_cloud',
            'user_credentials',
            function (array $row) {
                return [
                    'id' => $row['id'],
                    'user_id' => $row['user_id'] ?? null,
                    'firstname' => $row['firstname'] ?? null,
                    'middlename' => $row['middlename'] ?? null,
                    'lastname' => $row['lastname'] ?? null,
                    'birthdate' => $row['birthdate'] ?? null,
                    'gender' => $row['gender'] ?? null,
                    'created_at' => $this->normalizeDateTime($row['created_at'] ?? null),
                    'updated_at' => $this->normalizeDateTime($row['updated_at'] ?? null),
                    'synced_to_cloud_at' => now()->toDateTimeString(),
                ];
            },
            $batchSize,
            'user_credentials'
        );
    }

    protected function restoreFromCloudTable($client, string $cloudTable, string $localTable, callable $transform, int $batchSize, string $label): int
    {
        $this->info('Restoring '.$label.' from Supabase (table: '.$cloudTable.') ...');

        $processed = 0;
        $offset = 0;

        while (true) {
            $response = $client->get($cloudTable, [
                'select' => '*',
                'order' => 'id.asc',
                'limit' => $batchSize,
                'offset' => $offset,
            ]);

            if (! $response->successful()) {
                $this->error('Failed to fetch data from Supabase for table '.$cloudTable.'. HTTP '.$response->status());
                $this->error((string) $response->body());

                break;
            }

            $data = $response->json();

            if (empty($data)) {
                break;
            }

            $rows = [];

            foreach ($data as $row) {
                if (! isset($row['id'])) {
                    continue;
                }

                $rows[] = $transform($row);
            }

            if (empty($rows)) {
                break;
            }

            $updateColumns = array_diff(array_keys($rows[0]), ['id']);

            if ($localTable === 'users') {
                $updateColumns = array_diff($updateColumns, ['password']);
            }

            DB::table($localTable)->upsert($rows, ['id'], $updateColumns);

            $count = count($rows);
            $processed += $count;
            $this->info('  Processed '.$count.' rows (total: '.$processed.')');

            if ($count < $batchSize) {
                break;
            }

            $offset += $batchSize;
        }

        return $processed;
    }

    protected function normalizeDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
