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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('students', StudentController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('students/{student}/payments', [PaymentController::class, 'store'])->name('students.payments.store');
    Route::post('students/{student}/installments/{installment}/create-remaining', [PaymentController::class, 'createRemainingInstallment'])->name('students.installments.create-remaining');
    Route::post('students/{student}/reschedules', [RescheduleController::class, 'store'])->name('students.reschedules.store');
    Route::post('students/{student}/discounts', [DiscountController::class, 'store'])->name('students.discounts.store');

    Route::get('/settings/penalties', [PenaltySettingsController::class, 'edit'])->name('settings.penalties.edit');
    Route::put('/settings/penalties', [PenaltySettingsController::class, 'update'])->name('settings.penalties.update');
    Route::delete('/settings/clear-students', [PenaltySettingsController::class, 'clearAllStudents'])->name('settings.clear-students');

    Route::get('reschedules', [RescheduleApprovalController::class, 'index'])->name('reschedules.index');
    Route::put('reschedules/{reschedule}', [RescheduleApprovalController::class, 'update'])->name('reschedules.update');

    Route::get('discounts', [DiscountApprovalController::class, 'index'])->name('discounts.index');
    Route::put('discounts/{discount}', [DiscountApprovalController::class, 'update'])->name('discounts.update');

    Route::resource('banks', BankController::class)->except(['show']);
    Route::resource('courses', CourseController::class)->except(['show']);
    Route::resource('branches', BranchController::class)->except(['show']);
    Route::resource('misc-charges', MiscChargeController::class)->except(['show']);
    Route::get('misc-charges/api/available', [MiscChargeController::class, 'getAvailableCharges'])->name('misc-charges.available');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
