<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CrewAssignmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\CashAdvanceRequestController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LeaveCreditController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\EmploymentTypeChangeController;
use App\Http\Controllers\RemittanceController;
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
    Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager,Manager,Supervisor'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
        Route::get('/', [DashboardController::class, 'index'])->name('index');
        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
        Route::get('/analytics/export-pdf', [AnalyticsController::class, 'exportPdf'])->name('analytics.export-pdf');

        Route::get('/leave-credits', [LeaveCreditController::class, 'index'])->name('leave-credits');
        Route::post('/leave-credits/adjust', [LeaveCreditController::class, 'adjust'])->name('leave-credits.adjust');
        Route::post('/leave-credits/accrue', [LeaveCreditController::class, 'accrue'])->name('leave-credits.accrue');
        Route::post('/leave-credits/employment-start-date', [LeaveCreditController::class, 'updateEmploymentStartDate'])->name('leave-credits.employment-start-date');

        Route::get('/attendance', [AttendanceController::class, 'viewAttendance'])->name('attendance');
        Route::post('/attendance', [AttendanceController::class, 'storeAttendance'])->name('attendance.store');
        Route::put('/attendance/{id}', [AttendanceController::class, 'updateAttendance'])->name('attendance.update');
        Route::delete('/attendance/{id}', [AttendanceController::class, 'deleteAttendance'])->name('attendance.delete');
        Route::post('/attendance/{attendance}/restore', [AttendanceController::class, 'restoreAttendance'])->name('attendance.restore');
        Route::delete('/attendance', [AttendanceController::class, 'deleteMultipleAttendance'])->name('attendance.delete.multiple');
        Route::get('/attendance/export', [AttendanceController::class, 'exportAttendance'])->name('attendance.export');
        Route::get('/attendance/export-pdf', [AttendanceController::class, 'exportAttendancePdf'])->name('attendance.export-pdf');
        Route::get('/attendance/dtr-pdf', [AttendanceController::class, 'exportAttendanceDtr'])->name('attendance.dtr-pdf');
        Route::get('/attendance/summary-export', [AttendanceController::class, 'exportAttendanceSummary'])->name('attendance.summary-export');
        Route::get('/attendance/daily', [AttendanceController::class, 'viewAttendanceDaily'])->name('attendance.daily');

        Route::post('/overtime-entries/{id}/supervisor-approve', [AttendanceController::class, 'supervisorApproveOvertime'])->name('overtime-entries.supervisor-approve');
        Route::post('/overtime-entries/{id}/manager-approve', [AttendanceController::class, 'managerApproveOvertime'])->name('overtime-entries.manager-approve');

        Route::post('/attendance/import', [AttendanceController::class, 'importAttendance'])->name('attendance.import');

        Route::get('/attendance/bulk', [AttendanceController::class, 'viewAttendanceBulk'])->name('attendance.bulk');
        Route::post('/attendance/bulk', [AttendanceController::class, 'storeAttendanceBulk'])->name('attendance.bulk.store');
        Route::post('/attendance/generate-defaults', [AttendanceController::class, 'generateDefaultAttendance'])->name('attendance.generate-defaults');

        // Crew assignments (Manager only)
        Route::middleware(['role:Manager'])->group(function () {
            Route::get('/crew-assignments', [CrewAssignmentController::class, 'viewCrewAssignments'])->name('crew.assignments');
            Route::post('/crew-assignments', [CrewAssignmentController::class, 'storeCrewAssignments'])->name('crew.assignments.store');
            Route::delete('/crew-assignments/{id}', [CrewAssignmentController::class, 'deleteCrewAssignment'])->name('crew.assignments.delete');
        });
    });

    // Payroll routes (now including Manager and Supervisor for visibility, with actions still guarded by policies/roles)
    Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager,Manager,Supervisor'])->group(function () {
        Route::get('/payroll', [PayrollController::class, 'viewPayroll'])->name('payroll');
        Route::post('/payroll/create', [PayrollController::class, 'storePayroll'])->name('payroll.store');
        Route::get('/payroll/export', [PayrollController::class, 'exportPayroll'])->name('payroll.export');
        Route::get('/payroll/export-pdf', [PayrollController::class, 'exportPayrollPdf'])->name('payroll.export-pdf');
        Route::get('/payroll/process', [PayrollController::class, 'viewProcessPayroll'])->name('payroll.process');
        Route::post('/payroll/process', [PayrollController::class, 'runProcessPayroll'])->name('payroll.process.run');
        Route::get('/payroll/{id}', [PayrollController::class, 'showPayroll'])->name('payroll.show');
        Route::put('/payroll/{id}', [PayrollController::class, 'updatePayroll'])->name('payroll.update');
        Route::patch('/payroll/{id}/status', [PayrollController::class, 'updatePayrollStatus'])->name('payroll.update-status');
        Route::post('/payroll/{id}/hr-approve', [PayrollController::class, 'hrApprove'])->name('payroll.hr-approve');
        Route::post('/payroll/{id}/admin-approve', [PayrollController::class, 'adminApprove'])->name('payroll.admin-approve');
        Route::post('/payroll/{id}/restore', [PayrollController::class, 'restorePayroll'])->name('payroll.restore');
        Route::delete('/payroll/{id}', [PayrollController::class, 'deletePayroll'])->name('payroll.delete');
        Route::delete('/payroll', [PayrollController::class, 'deleteMultiplePayroll'])->name('payroll.delete.multiple');

        Route::get('/remittances', [RemittanceController::class, 'index'])->name('remittances');
        Route::post('/remittances/generate', [RemittanceController::class, 'generate'])->name('remittances.generate');
        Route::get('/remittances/export-month', [RemittanceController::class, 'exportMonth'])->name('remittances.export-month');
        Route::get('/remittances/{batch}', [RemittanceController::class, 'show'])->name('remittances.show');
        Route::put('/remittances/{batch}', [RemittanceController::class, 'update'])->name('remittances.update');
        Route::get('/remittances/{batch}/export', [RemittanceController::class, 'export'])->name('remittances.export');
    });

    // Cash advance ledger and requests (Supervisor now included)
    Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager,Manager,Supervisor'])->group(function () {
        Route::get('/cash-advances', [PayrollController::class, 'viewCashAdvances'])->name('cash-advances');
        Route::post('/cash-advances', [PayrollController::class, 'storeCashAdvance'])->name('cash-advances.store');
        Route::delete('/cash-advances/{id}', [PayrollController::class, 'deleteCashAdvance'])->name('cash-advances.delete');
        Route::post('/cash-advances/{id}/restore', [PayrollController::class, 'restoreCashAdvance'])->name('cash-advances.restore');
        Route::delete('/cash-advances', [PayrollController::class, 'deleteMultipleCashAdvances'])->name('cash-advances.delete.multiple');
    });

    Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager,Manager,Supervisor'])->group(function () {
        Route::get('/cash-advance-requests', [CashAdvanceRequestController::class, 'index'])->name('cash-advance-requests');
        Route::get('/cash-advance-requests/supervisor', [CashAdvanceRequestController::class, 'supervisorIndex'])->name('cash-advance-requests.supervisor');
        Route::post('/cash-advance-requests/supervisor-store', [CashAdvanceRequestController::class, 'storeFromSupervisor'])->name('cash-advance-requests.store-supervisor');
        Route::post('/cash-advance-requests/{id}/supervisor-approve', [CashAdvanceRequestController::class, 'supervisorApprove'])->name('cash-advance-requests.supervisor-approve');
        Route::post('/cash-advance-requests/{id}/manager-approve', [CashAdvanceRequestController::class, 'managerApprove'])->name('cash-advance-requests.manager-approve');
        Route::post('/cash-advance-requests/{id}/hr-approve', [CashAdvanceRequestController::class, 'hrApprove'])->name('cash-advance-requests.hr-approve');
        Route::post('/cash-advance-requests/{id}/release', [CashAdvanceRequestController::class, 'release'])->name('cash-advance-requests.release');
        Route::post('/cash-advance-requests/{id}/reject', [CashAdvanceRequestController::class, 'reject'])->name('cash-advance-requests.reject');
    });

    Route::middleware(['role:Superadmin,Admin,HR,Accounting,Project Manager,Manager,Supervisor'])->group(function () {
        Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests');
        Route::post('/leave-requests/{id}/supervisor-approve', [LeaveRequestController::class, 'supervisorApprove'])->name('leave-requests.supervisor-approve');
        Route::post('/leave-requests/{id}/manager-approve', [LeaveRequestController::class, 'managerApprove'])->name('leave-requests.manager-approve');
        Route::post('/leave-requests/{id}/approve', [LeaveRequestController::class, 'approve'])->name('leave-requests.approve');
        Route::post('/leave-requests/{id}/reject', [LeaveRequestController::class, 'reject'])->name('leave-requests.reject');
    });

    Route::middleware(['role:HR,Accounting,Project Manager,Manager,Supervisor'])->group(function () {
        Route::get('/my-leave-requests', [LeaveRequestController::class, 'myIndex'])->name('my.leave-requests');
        Route::post('/my-leave-requests', [LeaveRequestController::class, 'myStore'])->name('my.leave-requests.store');
        Route::post('/my-leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel'])->name('my.leave-requests.cancel');
    });

    Route::middleware(['role:Superadmin'])->group(function () {
        Route::get('/backup', [BackupController::class, 'index'])->name('backup');
        Route::post('/backup/create', [BackupController::class, 'createBackup'])->name('backup.create');
        Route::get('/backup/download/{file}', [BackupController::class, 'downloadBackup'])->name('backup.download');
        Route::post('/backup/restore', [BackupController::class, 'restoreBackup'])->name('backup.restore');
        Route::post('/backup/cloud', [BackupController::class, 'runCloudBackup'])->name('backup.cloud');
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

        // Approval logs (Admin & Superadmin only)
        Route::get('/approval-logs', [\App\Http\Controllers\ApprovalLogController::class, 'index'])->name('approval-logs');

        // Employment type change requests (Admin & HR & Manager access via dedicated routes below)
    });

    Route::middleware(['role:Admin,HR,Manager'])->group(function () {
        Route::get('/employment-type-requests', [EmploymentTypeChangeController::class, 'index'])->name('employment-type-requests.index');
        Route::post('/employment-type-requests', [EmploymentTypeChangeController::class, 'store'])->name('employment-type-requests.store');
    });

    Route::middleware(['role:Admin,Manager'])->group(function () {
        Route::post('/employment-type-requests/{id}/approve', [EmploymentTypeChangeController::class, 'managerApprove'])->name('employment-type-requests.approve');
        Route::post('/employment-type-requests/{id}/reject', [EmploymentTypeChangeController::class, 'managerReject'])->name('employment-type-requests.reject');
    });

    Route::middleware(['role:Admin'])->group(function () {
        Route::post('/employment-type-requests/{id}/override', [EmploymentTypeChangeController::class, 'adminOverride'])->name('employment-type-requests.override');
    });

    Route::middleware(['role:Superadmin,Admin'])->group(function () {
        Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('/announcements/{id}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{id}', [AnnouncementController::class, 'destroy'])->name('announcements.delete');
    });

    // Staff read-only announcements page (reached from header icon)
    // Include Manager so header bullhorn works for Manager accounts too.
    Route::middleware(['role:Superadmin,Admin,HR,Supervisor,Manager'])->group(function () {
        Route::get('/staff/announcements', [AnnouncementController::class, 'staffIndex'])->name('staff.announcements');
    });

    // Worker dashboard + pages (worker-only)
    Route::middleware(['auth:web', 'role:worker'])->group(function () {
        Route::get('/home', [WorkerController::class, 'overview'])->name('worker.dashboard');
        Route::get('/worker/payroll-history', [WorkerController::class, 'payrollHistory'])->name('worker.payroll-history');
        Route::get('/worker/payroll-history/{id}', [WorkerController::class, 'payslip'])->name('worker.payslip');
        Route::get('/worker/payroll-history/{id}/download', [WorkerController::class, 'downloadPayslip'])->name('worker.payslip.download');
        Route::get('/worker/attendance', [WorkerController::class, 'attendance'])->name('worker.attendance');
        Route::get('/worker/announcements', [WorkerController::class, 'announcementsIndex'])->name('worker.announcements');
        Route::get('/worker/leave-credits', [LeaveCreditController::class, 'workerIndex'])->name('worker.leave-credits');
        Route::get('/worker/cash-advance-requests', [CashAdvanceRequestController::class, 'workerIndex'])->name('worker.cash-advance-requests');
        Route::post('/worker/cash-advance-requests', [CashAdvanceRequestController::class, 'store'])->name('worker.cash-advance-requests.store');
        Route::post('/worker/cash-advance-requests/{id}/cancel', [CashAdvanceRequestController::class, 'cancel'])->name('worker.cash-advance-requests.cancel');
        Route::get('/worker/leave-requests', [LeaveRequestController::class, 'workerIndex'])->name('worker.leave-requests');
        Route::post('/worker/leave-requests', [LeaveRequestController::class, 'store'])->name('worker.leave-requests.store');
        Route::post('/worker/leave-requests/{id}/cancel', [LeaveRequestController::class, 'cancel'])->name('worker.leave-requests.cancel');
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
