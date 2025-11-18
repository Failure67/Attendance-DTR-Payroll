<?php

use App\Http\Controllers\AppController;
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

// auth
Route::get('/login', [AppController::class, 'login'])->name('login');

// pages
Route::get('/', [AppController::class, 'index'])->name('index');

Route::get('/attendance', [AppController::class, 'viewAttendance'])->name('attendance');


Route::get('/payroll', [AppController::class, 'viewPayroll'])->name('payroll');
Route::post('/payroll/create', [AppController::class, 'storePayroll'])->name('payroll.store');
Route::delete('/payroll/{id}', [AppController::class, ''])->name('payroll.delete');
Route::delete('/payroll', [AppController::class, 'deletePayroll'])->name('payroll.delete.multiple');

Route::get('/users', [AppController::class, 'viewUsers'])->name('users');

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
