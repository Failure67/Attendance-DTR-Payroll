<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrewAssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\UserController;
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
Route::middleware(['auth:superadmin,admin,web', 'log.role.activity'])->group(function () {
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

    // Back-office dashboard and attendance (Superadmin, Admin, HR, etc.)
    Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager,Supervisor'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        Route::get('/attendance', [AttendanceController::class, 'viewAttendance'])->name('attendance');
        Route::post('/attendance', [AttendanceController::class, 'storeAttendance'])->name('attendance.store');
        Route::put('/attendance/{id}', [AttendanceController::class, 'updateAttendance'])->name('attendance.update');
        Route::delete('/attendance/{id}', [AttendanceController::class, 'deleteAttendance'])->name('attendance.delete');
        Route::post('/attendance/{attendance}/restore', [AttendanceController::class, 'restoreAttendance'])->name('attendance.restore');
        Route::delete('/attendance', [AttendanceController::class, 'deleteMultipleAttendance'])->name('attendance.delete.multiple');
        Route::get('/attendance/export', [AttendanceController::class, 'exportAttendance'])->name('attendance.export');
        Route::get('/attendance/summary-export', [AttendanceController::class, 'exportAttendanceSummary'])->name('attendance.summary-export');
        Route::get('/attendance/daily', [AttendanceController::class, 'viewAttendanceDaily'])->name('attendance.daily');

        Route::post('/attendance/import', [AttendanceController::class, 'importAttendance'])->name('attendance.import');

        Route::get('/attendance/bulk', [AttendanceController::class, 'viewAttendanceBulk'])->name('attendance.bulk');
        Route::post('/attendance/bulk', [AttendanceController::class, 'storeAttendanceBulk'])->name('attendance.bulk.store');
        Route::post('/attendance/generate-defaults', [AttendanceController::class, 'generateDefaultAttendance'])->name('attendance.generate-defaults');

        // Crew assignments (Superadmin, Admin, HR, Accounting, Project Manager only)
        Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager'])->group(function () {
            Route::get('/crew-assignments', [CrewAssignmentController::class, 'viewCrewAssignments'])->name('crew.assignments');
            Route::post('/crew-assignments', [CrewAssignmentController::class, 'storeCrewAssignments'])->name('crew.assignments.store');
            Route::delete('/crew-assignments/{id}', [CrewAssignmentController::class, 'deleteCrewAssignment'])->name('crew.assignments.delete');
        });
    });

    Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager'])->group(function () {
        Route::get('/payroll', [PayrollController::class, 'viewPayroll'])->name('payroll');
        Route::post('/payroll/create', [PayrollController::class, 'storePayroll'])->name('payroll.store');
        Route::get('/payroll/export', [PayrollController::class, 'exportPayroll'])->name('payroll.export');
        Route::get('/payroll/process', [PayrollController::class, 'viewProcessPayroll'])->name('payroll.process');
        Route::post('/payroll/process', [PayrollController::class, 'runProcessPayroll'])->name('payroll.process.run');
        Route::get('/payroll/{id}', [PayrollController::class, 'showPayroll'])->name('payroll.show');
        Route::put('/payroll/{id}', [PayrollController::class, 'updatePayroll'])->name('payroll.update');
        Route::patch('/payroll/{id}/status', [PayrollController::class, 'updatePayrollStatus'])->name('payroll.update-status');
        Route::delete('/payroll/{id}', [PayrollController::class, 'deletePayroll'])->name('payroll.delete');
        Route::delete('/payroll', [PayrollController::class, 'deleteMultiplePayroll'])->name('payroll.delete.multiple');

        Route::get('/cash-advances', [PayrollController::class, 'viewCashAdvances'])->name('cash-advances');
        Route::post('/cash-advances', [PayrollController::class, 'storeCashAdvance'])->name('cash-advances.store');
    });

    Route::middleware(['role:Superadmin,Admin'])->group(function () {
        // User management routes
        Route::get('/users', [UserController::class, 'viewUsers'])->name('users');
        Route::post('/users', [UserController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'updateUser'])->name('users.update');
        
        // Archive/restore/delete user routes
        Route::post('/users/{user}/archive', [UserController::class, 'archiveUser'])->name('users.archive');
        Route::post('/users/{user}/restore', [UserController::class, 'restoreUser'])->name('users.restore');
        Route::delete('/users/{user}', [UserController::class, 'deleteUser'])->name('users.delete');
        Route::delete('/users', [UserController::class, 'deleteMultipleUsers'])->name('users.delete.multiple');

        // Activity logs (Admin & Superadmin only)
        Route::get('/activity-logs', [\App\Http\Controllers\ActivityLogController::class, 'index'])->name('activity-logs');
    });

    // Worker dashboard + pages (worker-only)
    Route::middleware(['auth:web', 'role:worker'])->group(function () {
        Route::get('/home', [WorkerController::class, 'overview'])->name('worker.dashboard');
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
