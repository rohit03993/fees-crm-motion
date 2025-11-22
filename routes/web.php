<?php

use App\Http\Controllers\BankController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MiscChargeController;
use App\Http\Controllers\DiscountApprovalController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RescheduleApprovalController;
use App\Http\Controllers\RescheduleController;
use App\Http\Controllers\PenaltySettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Show login page directly at root
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return view('auth.login');
});

// Dashboard - accessible to all authenticated users
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

// Staff routes - Staff and Admin can access
Route::middleware(['auth', 'verified', 'staff'])->group(function () {
    // Student management
    Route::resource('students', StudentController::class)->only(['index', 'create', 'store', 'show']);
    Route::patch('students/{student}/basic-info', [StudentController::class, 'updateBasicInfo'])->name('students.basic-info.update');
    Route::post('students/{student}/misc-charges', [StudentController::class, 'storeMiscCharge'])->name('students.misc-charges.store');
    Route::post('students/{student}/penalties', [StudentController::class, 'storePenalty'])->name('students.penalties.store');
    
    // Payment operations
    Route::post('students/{student}/payments', [PaymentController::class, 'store'])->name('students.payments.store');
    Route::post('students/{student}/installments/{installment}/create-remaining', [PaymentController::class, 'createRemainingInstallment'])->name('students.installments.create-remaining');
    
    // Reschedule requests (staff can create, admin approves)
    Route::post('students/{student}/reschedules', [RescheduleController::class, 'store'])->name('students.reschedules.store');
    
    // Discount requests (staff can create, admin approves)
    Route::post('students/{student}/discounts', [DiscountController::class, 'store'])->name('students.discounts.store');
});

// Admin-only routes - Only Admin can access
Route::middleware(['auth', 'verified', 'admin'])->group(function () {
    // Settings management
    Route::get('/settings/penalties', [PenaltySettingsController::class, 'edit'])->name('settings.penalties.edit');
    Route::put('/settings/penalties', [PenaltySettingsController::class, 'update'])->name('settings.penalties.update');
    Route::delete('/settings/clear-students', [PenaltySettingsController::class, 'clearAllStudents'])->name('settings.clear-students');

    // Approval workflows
    Route::get('reschedules', [RescheduleApprovalController::class, 'index'])->name('reschedules.index');
    Route::put('reschedules/{reschedule}', [RescheduleApprovalController::class, 'update'])->name('reschedules.update');

    Route::get('discounts', [DiscountApprovalController::class, 'index'])->name('discounts.index');
    Route::put('discounts/{discount}', [DiscountApprovalController::class, 'update'])->name('discounts.update');

    // Master data management
    Route::resource('banks', BankController::class)->except(['show']);
    Route::resource('courses', CourseController::class)->except(['show']);
    Route::resource('branches', BranchController::class)->except(['show']);
    Route::resource('misc-charges', MiscChargeController::class)->except(['show']);
    Route::get('misc-charges/api/available', [MiscChargeController::class, 'getAvailableCharges'])->name('misc-charges.available');
    
    // Staff management
    Route::resource('users', UserController::class)->except(['show']);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
