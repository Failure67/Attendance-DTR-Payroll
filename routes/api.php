<?php

use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// User management routes
// This endpoint is read-only and used by the web UI (Select2) to list employees.
// It does not require Sanctum tokens.
Route::get('/users/list', [UserController::class, 'list'])->name('api.users.list');

Route::post('/sync/users', [SyncController::class, 'syncUsers'])->name('api.sync.users');
Route::post('/sync/attendances', [SyncController::class, 'syncAttendances'])->name('api.sync.attendances');
Route::post('/sync/payrolls', [SyncController::class, 'syncPayrolls'])->name('api.sync.payrolls');
Route::post('/sync/payroll-deductions', [SyncController::class, 'syncPayrollDeductions'])->name('api.sync.payroll-deductions');
Route::post('/sync/cash-advances', [SyncController::class, 'syncCashAdvances'])->name('api.sync.cash-advances');
