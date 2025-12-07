<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncAttendancesToSupabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:attendances-to-supabase {--dry-run : Do not send data, only show what would be synced}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync local attendances table to Supabase cloud database';

    /**
     * Execute the console command.
     */
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
        $totalProcessed = 0;

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
                $totalProcessed += count($payload);
                break;
            }

            $response = Http::withHeaders([
                'apikey' => $serviceKey,
                'Authorization' => 'Bearer '.$serviceKey,
                'Content-Type' => 'application/json',
                'Prefer' => 'return=minimal,resolution=merge-duplicates',
            ])->post($baseUrl.'/rest/v1/attendances_cloud?on_conflict=id', $payload);

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

            $totalProcessed += count($payload);

            if (count($payload) < $batchSize) {
                break;
            }
        }

        $this->info('Supabase attendance sync completed. Total records processed: '.$totalProcessed);

        return self::SUCCESS;
    }
}
