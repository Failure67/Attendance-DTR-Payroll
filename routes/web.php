<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('auth.login.show');
    Route::post('/login', [AuthController::class, 'handleLogin'])->name('auth.login.handle');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('auth.register.show');
    Route::post('/register', [AuthController::class, 'handleRegister'])->name('auth.register.handle');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('auth.forgot-password.show');
    Route::post('/forgot-password', [AuthController::class, 'handleForgotPassword'])->name('auth.forgot-password.handle');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetForm'])->name('auth.reset.show');
    Route::post('/reset-password', [AuthController::class, 'handleReset'])->name('auth.reset.handle');
});

// Superadmin + Admin + worker authenticated routes
Route::middleware('auth:superadmin,admin,web')->group(function () {
    // Global logout (clears both guards)
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // Guard-specific logouts
    Route::post('/logout/admin', [AuthController::class, 'logoutAdmin'])->name('auth.logout.admin');
    Route::post('/logout/worker', [AuthController::class, 'logoutWorker'])->name('auth.logout.worker');

    // profile and settings (accessible to any authenticated user)
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/picture', [\App\Http\Controllers\ProfileController::class, 'uploadPicture'])->name('profile.picture.upload');
    
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'show'])->name('settings.show');
    Route::put('/settings/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])->name('settings.password.update');

    // Back-office dashboard and attendance (Superadmin, Admin, HR/Payroll, etc.)
    Route::middleware(['role:Superadmin,Admin,HR Manager,Accounting,Payroll Officer,Project Manager,Supervisor'])->group(function () {
        Route::get('/dashboard', [AppController::class, 'index'])->name('admin.dashboard');
        Route::get('/', [AppController::class, 'index'])->name('index');

        Route::get('/attendance', [AppController::class, 'viewAttendance'])->name('attendance');
        Route::post('/attendance', [AppController::class, 'storeAttendance'])->name('attendance.store');
        Route::put('/attendance/{id}', [AppController::class, 'updateAttendance'])->name('attendance.update');
        Route::delete('/attendance/{id}', [AppController::class, 'deleteAttendance'])->name('attendance.delete');
        Route::post('/attendance/{attendance}/restore', [AppController::class, 'restoreAttendance'])->name('attendance.restore');
        Route::delete('/attendance', [AppController::class, 'deleteMultipleAttendance'])->name('attendance.delete.multiple');
        Route::get('/attendance/export', [AppController::class, 'exportAttendance'])->name('attendance.export');
        Route::get('/attendance/summary-export', [AppController::class, 'exportAttendanceSummary'])->name('attendance.summary-export');
        Route::get('/attendance/daily', [AppController::class, 'viewAttendanceDaily'])->name('attendance.daily');

        Route::post('/attendance/import', [AppController::class, 'importAttendance'])->name('attendance.import');

        Route::get('/attendance/bulk', [AppController::class, 'viewAttendanceBulk'])->name('attendance.bulk');
        Route::post('/attendance/bulk', [AppController::class, 'storeAttendanceBulk'])->name('attendance.bulk.store');
        Route::post('/attendance/generate-defaults', [AppController::class, 'generateDefaultAttendance'])->name('attendance.generate-defaults');

        // Crew assignments (supervisors and admins)
        Route::get('/crew-assignments', [AppController::class, 'viewCrewAssignments'])->name('crew.assignments');
        Route::post('/crew-assignments', [AppController::class, 'storeCrewAssignments'])->name('crew.assignments.store');
        Route::delete('/crew-assignments/{id}', [AppController::class, 'deleteCrewAssignment'])->name('crew.assignments.delete');
    });

    Route::middleware(['role:Superadmin,Admin,HR Manager,Accounting,Payroll Officer,Project Manager'])->group(function () {
        Route::get('/payroll', [AppController::class, 'viewPayroll'])->name('payroll');
        Route::post('/payroll/create', [AppController::class, 'storePayroll'])->name('payroll.store');
        Route::get('/payroll/export', [AppController::class, 'exportPayroll'])->name('payroll.export');
        Route::get('/payroll/process', [AppController::class, 'viewProcessPayroll'])->name('payroll.process');
        Route::post('/payroll/process', [AppController::class, 'runProcessPayroll'])->name('payroll.process.run');
        Route::get('/payroll/{id}', [AppController::class, 'showPayroll'])->name('payroll.show');
        Route::put('/payroll/{id}', [AppController::class, 'updatePayroll'])->name('payroll.update');
        Route::patch('/payroll/{id}/status', [AppController::class, 'updatePayrollStatus'])->name('payroll.update-status');
        Route::delete('/payroll/{id}', [AppController::class, 'deletePayroll'])->name('payroll.delete');
        Route::delete('/payroll', [AppController::class, 'deleteMultiplePayroll'])->name('payroll.delete.multiple');

        Route::get('/cash-advances', [AppController::class, 'viewCashAdvances'])->name('cash-advances');
        Route::post('/cash-advances', [AppController::class, 'storeCashAdvance'])->name('cash-advances.store');
    });

    Route::middleware(['role:Superadmin'])->group(function () {
        // User management routes
        Route::get('/users', [AppController::class, 'viewUsers'])->name('users');
        Route::post('/users', [AppController::class, 'storeUser'])->name('users.store');
        
        // Archive/restore/delete user routes
        Route::post('/users/{user}/archive', [AppController::class, 'archiveUser'])->name('users.archive');
        Route::post('/users/{user}/restore', [AppController::class, 'restoreUser'])->name('users.restore');
        Route::delete('/users/{user}', [AppController::class, 'deleteUser'])->name('users.delete');
        Route::delete('/users', [AppController::class, 'deleteMultipleUsers'])->name('users.delete.multiple');
    });

    // Worker dashboard + pages (worker-only)
    Route::middleware(['auth:web', 'role:worker'])->group(function () {
        Route::get('/worker', [WorkerController::class, 'overview'])->name('worker.dashboard');
        Route::get('/worker/payroll-history', [WorkerController::class, 'payrollHistory'])->name('worker.payroll-history');
        Route::get('/worker/payroll-history/{id}', [WorkerController::class, 'payslip'])->name('worker.payslip');
        Route::get('/worker/payroll-history/{id}/download', [WorkerController::class, 'downloadPayslip'])->name('worker.payslip.download');
        Route::get('/worker/attendance', [WorkerController::class, 'attendance'])->name('worker.attendance');
    });
});

// require javascript
Route::get('/require', [AppController::class, 'require'])->name('require');

// generate document
Route::get('generate-document', [DocumentController::class, 'generateDocument']);

/*
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
*/
