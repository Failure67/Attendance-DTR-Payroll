<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    protected function ensureValidToken(Request $request): void
    {
        $expected = env('SYNC_API_TOKEN');
        $provided = (string) $request->header('X-Sync-Token', '');

        if (!$expected || !hash_equals($expected, $provided)) {
            abort(401, 'Invalid sync token');
        }
    }

    public function syncUsers(Request $request)
    {
        $this->ensureValidToken($request);

        $items = $request->input('items');
        if (!is_array($items)) {
            return response()->json(['message' => 'Invalid payload. "items" must be an array.'], 422);
        }

        $processed = 0;

        DB::transaction(function () use ($items, &$processed) {
            foreach ($items as $payload) {
                if (!is_array($payload) || !isset($payload['id'])) {
                    continue;
                }

                $id = (int) $payload['id'];
                $attributes = ['id' => $id];

                $values = Arr::only($payload, [
                    'username',
                    'full_name',
                    'email',
                    'password',
                    'profile_picture',
                    'role',
                    'remember_token',
                    'email_verified_at',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]);

                DB::table('users')->updateOrInsert($attributes, $values);
                $processed++;
            }
        });

        return response()->json(['status' => 'ok', 'processed' => $processed]);
    }

    public function syncAttendances(Request $request)
    {
        $this->ensureValidToken($request);

        $items = $request->input('items');
        if (!is_array($items)) {
            return response()->json(['message' => 'Invalid payload. "items" must be an array.'], 422);
        }

        $processed = 0;

        DB::transaction(function () use ($items, &$processed) {
            foreach ($items as $payload) {
                if (!is_array($payload) || !isset($payload['id'])) {
                    continue;
                }

                $id = (int) $payload['id'];
                $attributes = ['id' => $id];

                $values = Arr::only($payload, [
                    'user_id',
                    'time_in',
                    'time_out',
                    'date',
                    'total_hours',
                    'overtime_hours',
                    'status',
                    'overtime_approved',
                    'leave_approved',
                    'created_at',
                    'updated_at',
                    'deleted_at',
                ]);

                DB::table('attendances')->updateOrInsert($attributes, $values);
                $processed++;
            }
        });

        return response()->json(['status' => 'ok', 'processed' => $processed]);
    }

    public function syncPayrolls(Request $request)
    {
        $this->ensureValidToken($request);

        $items = $request->input('items');
        if (!is_array($items)) {
            return response()->json(['message' => 'Invalid payload. "items" must be an array.'], 422);
        }

        $processed = 0;

        DB::transaction(function () use ($items, &$processed) {
            foreach ($items as $payload) {
                if (!is_array($payload) || !isset($payload['id'])) {
                    continue;
                }

                $id = (int) $payload['id'];
                $attributes = ['id' => $id];

                $values = Arr::only($payload, [
                    'user_id',
                    'wage_type',
                    'min_wage',
                    'hours_worked',
                    'days_worked',
                    'regular_hours',
                    'overtime_hours',
                    'absent_days',
                    'gross_pay',
                    'total_deductions',
                    'net_pay',
                    'status',
                    'period_start',
                    'period_end',
                    'released_at',
                    'created_at',
                    'updated_at',
                ]);

                DB::table('payrolls')->updateOrInsert($attributes, $values);
                $processed++;
            }
        });

        return response()->json(['status' => 'ok', 'processed' => $processed]);
    }

    public function syncPayrollDeductions(Request $request)
    {
        $this->ensureValidToken($request);

        $items = $request->input('items');
        if (!is_array($items)) {
            return response()->json(['message' => 'Invalid payload. "items" must be an array.'], 422);
        }

        $processed = 0;

        DB::transaction(function () use ($items, &$processed) {
            foreach ($items as $payload) {
                if (!is_array($payload) || !isset($payload['id'])) {
                    continue;
                }

                $id = (int) $payload['id'];
                $attributes = ['id' => $id];

                $values = Arr::only($payload, [
                    'payroll_id',
                    'deduction_name',
                    'amount',
                    'created_at',
                    'updated_at',
                ]);

                DB::table('payroll_deductions')->updateOrInsert($attributes, $values);
                $processed++;
            }
        });

        return response()->json(['status' => 'ok', 'processed' => $processed]);
    }

    public function syncCashAdvances(Request $request)
    {
        $this->ensureValidToken($request);

        $items = $request->input('items');
        if (!is_array($items)) {
            return response()->json(['message' => 'Invalid payload. "items" must be an array.'], 422);
        }

        $processed = 0;

        DB::transaction(function () use ($items, &$processed) {
            foreach ($items as $payload) {
                if (!is_array($payload) || !isset($payload['id'])) {
                    continue;
                }

                $id = (int) $payload['id'];
                $attributes = ['id' => $id];

                $values = Arr::only($payload, [
                    'user_id',
                    'type',
                    'amount',
                    'description',
                    'source',
                    'payroll_id',
                    'created_at',
                    'updated_at',
                ]);

                DB::table('cash_advances')->updateOrInsert($attributes, $values);
                $processed++;
            }
        });

        return response()->json(['status' => 'ok', 'processed' => $processed]);
    }
}
