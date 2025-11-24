<?php

use App\Http\Controllers\AppController;
use App\Http\Controllers\AuthController;
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

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');

    // profile and settings
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/picture', [\App\Http\Controllers\ProfileController::class, 'uploadPicture'])->name('profile.picture.upload');
    
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'show'])->name('settings.show');
    Route::put('/settings/password', [\App\Http\Controllers\SettingsController::class, 'updatePassword'])->name('settings.password.update');

    // pages
    Route::get('/', [AppController::class, 'index'])->name('index');

    Route::get('/attendance', [AppController::class, 'viewAttendance'])->name('attendance');

    Route::get('/payroll', [AppController::class, 'viewPayroll'])->name('payroll');
    Route::post('/payroll/create', [AppController::class, 'storePayroll'])->name('payroll.store');
    Route::delete('/payroll/{id}', [AppController::class, ''])->name('payroll.delete');
    Route::delete('/payroll', [AppController::class, 'deletePayroll'])->name('payroll.delete.multiple');

    Route::get('/users', [AppController::class, 'viewUsers'])->name('users');
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
