@php
    use Illuminate\Support\Facades\Storage;
    use Illuminate\Support\Str;
    $outstandingInstallments = collect(optional($student->fee)->installments)
        ->filter(fn ($installment) => $installment->amount > $installment->paid_amount)
        ->sortBy('due_date')
        ->values();
    $nextInstallment = $outstandingInstallments->first();
    // Calculate penalties: Late fees from penalties table + GST penalties from misc_charges
    // Get all penalties (for display in penalty ledger)
    $allPenalties = $student->penalties;
    
    // Separate manual penalties from auto-generated late fee penalties
    $lateFeePenalties = $allPenalties->filter(function($penalty) {
        return $penalty->penalty_type === 'auto';
    });
    $manualPenalties = $allPenalties->filter(function($penalty) {
        return $penalty->penalty_type === 'manual';
    });
    
    $gstPenalties = $student->miscCharges()->where('label', 'like', 'GST Penalty%')->get();
    $lateFeePenaltyTotal = $lateFeePenalties->sum('penalty_amount');
    $manualPenaltyTotal = $manualPenalties->sum('penalty_amount');
    $gstPenaltyTotal = $gstPenalties->sum('amount');
    $penaltyTotal = $lateFeePenaltyTotal + $manualPenaltyTotal + $gstPenaltyTotal;
    
    // Calculate UNPAID penalties (for total unpaid calculation)
    // Late fee penalties: exclude those with status 'paid' or 'waived'
    $unpaidLateFeePenalties = $lateFeePenalties->filter(function($penalty) {
        return !in_array($penalty->status, ['paid', 'waived']);
    });
    $unpaidLateFeePenaltyTotal = $unpaidLateFeePenalties->sum('penalty_amount');
    
    // Manual penalties: exclude those with status 'paid' or 'waived'
    $unpaidManualPenalties = $manualPenalties->filter(function($penalty) {
        return !in_array($penalty->status, ['paid', 'waived']);
    });
    $unpaidManualPenaltyTotal = $unpaidManualPenalties->sum('penalty_amount');
    
    // GST penalties: exclude those with status 'paid' or 'cancelled'
    $unpaidGstPenalties = $gstPenalties->filter(function($penalty) {
        return !in_array($penalty->status, ['paid', 'cancelled']);
    });
    $unpaidGstPenaltyTotal = $unpaidGstPenalties->sum('amount');
    
    $unpaidPenaltyTotal = $unpaidLateFeePenaltyTotal + $unpaidManualPenaltyTotal + $unpaidGstPenaltyTotal;
    $recentReminders = $student->reminders->take(8);
    // Only show unpaid installments for reschedule (status != 'paid' or amount > paid_amount)
    $reschedulableInstallments = collect(optional($student->fee)->installments)
        ->filter(fn ($installment) => $installment->status !== 'paid' && $installment->amount > $installment->paid_amount)
        ->sortBy('due_date')
        ->values();
    
    // Get unpaid miscellaneous charges for this student
    // Priority: Show student-specific charges first, then course-level charges that don't have a student-specific unpaid instance
    // EXCLUDE penalties (GST Penalty and Late Fee penalties) from miscellaneous charges
    $unpaidMiscCharges = collect();
    
    // Get all student-specific unpaid charges (EXCLUDE penalties)
    $studentCharges = $student->miscCharges()
        ->where('student_id', $student->id)
        ->where('status', '!=', 'paid')
        ->where('status', '!=', 'cancelled')
        ->where('label', 'not like', 'GST Penalty%') // Exclude GST penalties
        ->orderBy('due_date')
        ->get();
    
    $unpaidMiscCharges = $unpaidMiscCharges->merge($studentCharges);
    
    // Get student-specific charge labels (to avoid duplicates)
    $studentChargeLabels = $studentCharges->pluck('label')->unique()->toArray();
    
    // Get course-level charges that haven't been paid AND don't have a student-specific unpaid instance
    // EXCLUDE penalties (GST Penalty and Late Fee penalties) from miscellaneous charges
    $courseLevelCharges = \App\Models\MiscCharge::whereNull('student_id')
        ->where('course_id', $student->course_id)
        ->where('status', '!=', 'paid')
        ->where('status', '!=', 'cancelled')
        ->where('label', 'not like', 'GST Penalty%') // Exclude GST penalties
        ->orderBy('due_date')
        ->get()
        ->filter(function($charge) use ($student, $studentChargeLabels) {
            // Exclude if there's already a student-specific unpaid charge with the same label
            if (in_array($charge->label, $studentChargeLabels)) {
                return false;
            }
            
            // Also check if this course-level charge has already been paid
            // by checking if there's a paid student-specific charge with the same label
            $paidExists = \App\Models\MiscCharge::where('student_id', $student->id)
                ->where('course_id', $charge->course_id)
                ->where('label', $charge->label)
                ->where('status', 'paid')
                ->exists();
            return !$paidExists;
        });
    
    $unpaidMiscCharges = $unpaidMiscCharges->merge($courseLevelCharges)->sortBy('due_date')->values();
    
    // Get credit balance (NEW FEATURE - for overpayments)
    $creditBalance = optional($student->fee)->credit_balance ?? 0;
    $recentReschedules = $student->reschedules->take(6);
    $cashAllowance = optional($student->fee)->cash_allowance ?? 0;
    $onlineAllowance = optional($student->fee)->online_allowance ?? 0;
    
    // Calculate payments by mode
    // Separate tuition payments from miscellaneous payments
    // Only tuition payments are bound by cash/online allowance restrictions
    // EXCLUDE payments for GST penalties - they should only appear in penalty ledger
    $tuitionPayments = $student->payments->whereNotNull('installment_id');
    
    // Get GST penalty misc charge IDs to exclude from misc payments
    $gstPenaltyChargeIds = $student->miscCharges()->where('label', 'like', 'GST Penalty%')->pluck('id')->toArray();
    
    // Misc payments exclude GST penalty payments
    $miscPayments = $student->payments->whereNotNull('misc_charge_id')
        ->filter(function($payment) use ($gstPenaltyChargeIds) {
            return !in_array($payment->misc_charge_id, $gstPenaltyChargeIds);
        });
    
    // Calculate cash/online payments for TUITION ONLY (misc payments are not bound by allowances)
    $totalCashPayments = $tuitionPayments->where('payment_mode', 'cash')->sum('amount_received');
    $totalOnlinePayments = $tuitionPayments->whereIn('payment_mode', ['upi', 'bank_transfer', 'cheque'])->sum('amount_received');
    
    // Separate amounts for tuition vs misc
    $tuitionPaymentsAmount = $tuitionPayments->sum('amount_received');
    $miscPaymentsAmount = $miscPayments->sum('amount_received');
    $tuitionPaymentsCount = $tuitionPayments->count();
    $miscPaymentsCount = $miscPayments->count();
    
    // Calculate unpaid amounts
    // Tuition unpaid = total program fee - tuition paid
    $tuitionUnpaid = max(0, $totalProgramFee - $tuitionPaymentsAmount);
    
    // Misc unpaid = total misc charges - misc paid
    // Get all misc charges for this student (both paid and unpaid)
    // EXCLUDE penalties (GST Penalty and Late Fee penalties) from miscellaneous charges
    $allMiscCharges = $student->miscCharges()->where('label', 'not like', 'GST Penalty%')->get();
    $totalMiscChargesAmount = $allMiscCharges->sum('amount');
    $miscUnpaid = max(0, $totalMiscChargesAmount - $miscPaymentsAmount);
    
    // Total paid and unpaid
    // Note: UNPAID penalties are included in total unpaid as they are part of what needs to be paid
    $totalPaid = $tuitionPaymentsAmount + $miscPaymentsAmount;
    $totalUnpaid = $tuitionUnpaid + $miscUnpaid + $unpaidPenaltyTotal;
    
    $recentDiscounts = $student->discounts->take(8);
    $approvedDiscountTotal = $student->discounts->where('status', 'approved')->sum('amount');
    
    // Calculate original program fee (before discounts)
    // The current totalProgramFee is after discounts are applied, so add back approved discounts
    $originalProgramFee = $totalProgramFee + $approvedDiscountTotal;
    
    // Calculate original promised allowances (before discounts)
    // Discounts only reduce online_allowance, so original = current + approved discounts
    $originalCashAllowance = $cashAllowance; // Cash allowance is not affected by discounts
    $originalOnlineAllowance = $onlineAllowance + $approvedDiscountTotal; // Add back approved discounts to get original
    
    // Calculate remaining allowances (current limit - TUITION payments made only)
    // Miscellaneous payments are NOT counted towards allowance limits
    $remainingCashAllowance = max(0, $cashAllowance - $totalCashPayments);
    $remainingOnlineAllowance = max(0, $onlineAllowance - $totalOnlinePayments);
    $outstandingBalance = $outstandingInstallments->sum(fn ($installment) => max($installment->amount - $installment->paid_amount, 0));
    $nextInstallment = $outstandingInstallments->first();
    $pendingReschedulesCount = $student->reschedules->where('status', 'pending')->count();
    $pendingDiscountsCount = $student->discounts->where('status', 'pending')->count();
    
    // Calculate installments added after student creation
    // Compare current installment count with original installment_count
    $originalInstallmentCount = optional($student->fee)->installment_count ?? 0;
    $currentInstallmentCount = optional($student->fee)->installments->count() ?? 0;
    $addedInstallmentsCount = max(0, $currentInstallmentCount - $originalInstallmentCount);
    
    // Count approved discounts (discounts that were given)
    $approvedDiscountsCount = $student->discounts->where('status', 'approved')->count();
    
    // Total payments (tuition + misc)
    $totalPaymentsCount = $student->payments->count();
    $totalPaymentsAmount = $student->payments->sum('amount_received');
    $paymentsTotal = $totalPaymentsAmount; // Use totalPaymentsAmount instead of non-existent 'amount' field
    $installmentPaidTotal = optional($student->fee)->installments->sum('paid_amount') ?? 0;
    $plannedCollections = $cashAllowance + $onlineAllowance;
    $collectionGoal = max(1, ($totalProgramFee + $miscTotal) - $approvedDiscountTotal);
    $collectionProgress = min(100, round(($paymentsTotal / $collectionGoal) * 100));
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $student->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">UID: {{ $student->student_uid }}</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="button" onclick="openAddMiscChargeModal()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Miscellaneous
                </button>
                <button type="button" onclick="openAddPenaltyModal()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Penalty
                </button>
                @can('update', $student)
                    <button type="button" onclick="openEditBasicInfoModal()" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Edit Basic Info
                    </button>
                @endcan
                <a href="{{ route('students.index') }}" class="text-sm text-indigo-600 hover:text-indigo-500 font-semibold">← Back to list</a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if(session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">Please fix the following errors:</h3>
                            <div class="mt-2 text-sm text-red-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-500 via-violet-500 to-sky-500 p-6 text-white shadow-xl">
                <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.45),_transparent_60%)]"></div>
                <div class="relative z-10 flex flex-col gap-6">
                    <!-- Top Section: Photos and Guardian Info -->
                    <div class="flex flex-col md:flex-row items-start gap-6 pb-4 border-b border-white/20">
                        <!-- Student Photo - Passport Size (Portrait) -->
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-24 h-32 rounded-lg shadow-xl border-4 border-white/40 overflow-hidden relative bg-white">
                                @if($student->student_photo)
                                    <img src="{{ url(Storage::url($student->student_photo)) }}" alt="Student Photo" class="w-full h-full object-cover object-center m-0 p-0 block" />
                                @else
                                    <div class="w-full h-full flex items-center justify-center bg-gray-100">
                                        <svg class="w-12 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                @endif
                            </div>
                            <p class="text-xs text-center text-indigo-100/90 font-semibold">Student</p>
                            @if($student->name)
                                <p class="text-xs text-center text-white font-medium">{{ $student->name }}</p>
                            @endif
                        </div>
                        
                        <!-- Guardian 1 & 2 Photos and Info -->
                        <div class="flex-1 flex flex-col md:flex-row gap-6">
                            <!-- Guardian 1 -->
                            @if($student->guardian_1_name)
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-24 h-32 rounded-lg shadow-xl border-4 border-white/40 overflow-hidden relative bg-white">
                                        @if($student->guardian_1_photo)
                                            <img src="{{ url(Storage::url($student->guardian_1_photo)) }}" alt="Guardian 1" class="w-full h-full object-cover object-center m-0 p-0 block" />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-gray-100">
                                                <svg class="w-12 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-xs text-center text-indigo-100/90 font-semibold">Guardian 1</p>
                                    <div class="text-center">
                                        <p class="text-sm font-semibold text-white">{{ $student->guardian_1_name }}</p>
                                        <p class="text-xs text-indigo-100/80 mt-0.5">{{ $student->guardian_1_relation }}</p>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Guardian 2 -->
                            @if($student->guardian_2_name)
                                <div class="flex flex-col items-center gap-2">
                                    <div class="w-24 h-32 rounded-lg shadow-xl border-4 border-white/40 overflow-hidden relative bg-white">
                                        @if($student->guardian_2_photo)
                                            <img src="{{ url(Storage::url($student->guardian_2_photo)) }}" alt="Guardian 2" class="w-full h-full object-cover object-center m-0 p-0 block" />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center bg-gray-100">
                                                <svg class="w-12 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                </svg>
                                            </div>
                                        @endif
                                    </div>
                                    <p class="text-xs text-center text-indigo-100/90 font-semibold">Guardian 2</p>
                                    <div class="text-center">
                                        <p class="text-sm font-semibold text-white">{{ $student->guardian_2_name }}</p>
                                        <p class="text-xs text-indigo-100/80 mt-0.5">{{ $student->guardian_2_relation }}</p>
                                    </div>
                                </div>
                            @endif
                            
                            <!-- Student Profile Details - Next to Guardian 2 -->
                            <div class="flex-1 space-y-3">
                                <div>
                                    <p class="text-xs uppercase tracking-[0.35em] text-indigo-100/80">Student Profile</p>
                                    <h1 class="text-2xl font-semibold text-white">{{ $student->name }}</h1>
                                    <p class="text-sm text-indigo-100/80">UID: {{ $student->student_uid }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2 text-xs text-indigo-100/90">
                                    @if($student->course)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 font-medium">
                                            <span class="h-2 w-2 rounded-full bg-white/70"></span>{{ $student->course->name }}
                                        </span>
                                    @endif
                                    @if($student->branch)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 font-medium">
                                            <span class="h-2 w-2 rounded-full bg-white/70"></span>{{ $student->branch->name }}
                                        </span>
                                    @endif
                                    <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 font-medium">
                                        <span class="h-2 w-2 rounded-full bg-white/70"></span>Joined {{ $student->admission_date->format('d M Y') }}
                                    </span>
                                    @if($student->guardian_1_whatsapp)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 font-medium">
                                            <span class="h-2 w-2 rounded-full bg-white/70"></span>{{ $student->guardian_1_whatsapp }}
                                        </span>
                                    @endif
                                    @if($student->guardian_2_whatsapp)
                                        <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 font-medium">
                                            <span class="h-2 w-2 rounded-full bg-white/70"></span>{{ $student->guardian_2_whatsapp }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Financial Section - Restructured for Better Visibility -->
                    <div class="space-y-4">
                        <!-- Total Paid and Total Unpaid - Big Prominent Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Total Paid -->
                            <div class="rounded-2xl bg-emerald-500/20 border-2 border-emerald-300/40 p-6 backdrop-blur">
                                <p class="text-xs uppercase tracking-wide text-emerald-100/90 font-semibold mb-2">Total Paid</p>
                                <p class="text-4xl font-bold text-white mb-2">₹{{ number_format($totalPaid, 2) }}</p>
                                <p class="text-sm text-emerald-100/80">{{ $totalPaymentsCount }} receipt{{ $totalPaymentsCount === 1 ? '' : 's' }}</p>
                            </div>
                            
                            <!-- Total Unpaid -->
                            <div class="rounded-2xl bg-amber-500/20 border-2 border-amber-300/40 p-6 backdrop-blur">
                                <p class="text-xs uppercase tracking-wide text-amber-100/90 font-semibold mb-2">Total Unpaid</p>
                                <p class="text-4xl font-bold text-white mb-2">₹{{ number_format($totalUnpaid, 2) }}</p>
                                <p class="text-sm text-amber-100/80">Pending</p>
                            </div>
                        </div>
                        
                        <!-- Tuition Fees and Miscellaneous Charges - Side by Side -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Tuition Fees - Wider Section -->
                            <div class="md:col-span-2 rounded-2xl bg-white/15 border border-white/20 p-5 backdrop-blur">
                                <p class="text-xs uppercase tracking-wide text-indigo-100/90 font-semibold mb-4">Tuition Fees</p>
                                <div class="grid grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <p class="text-xs text-indigo-100/80 mb-1">Paid</p>
                                        <p class="text-2xl font-bold text-white">₹{{ number_format($tuitionPaymentsAmount, 2) }}</p>
                                        <p class="text-xs text-indigo-100/70 mt-1">{{ $tuitionPaymentsCount }} payment{{ $tuitionPaymentsCount === 1 ? '' : 's' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-indigo-100/80 mb-1">Unpaid</p>
                                        <p class="text-2xl font-bold text-white">₹{{ number_format($tuitionUnpaid, 2) }}</p>
                                        <p class="text-xs text-indigo-100/60 italic mt-1">Bound by allowances</p>
                                    </div>
                                </div>
                                
                                <!-- Cash and Online Allowance -->
                                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-white/20">
                                    <div>
                                        <p class="text-xs text-indigo-100/80 mb-1">Cash Allowance</p>
                                        <p class="text-lg font-bold text-white">₹{{ number_format($cashAllowance, 2) }}</p>
                                        <p class="text-xs text-indigo-100/70 mt-1">Remaining: ₹{{ number_format($remainingCashAllowance, 2) }}</p>
                                        @if($totalCashPayments > 0)
                                            <p class="text-xs text-indigo-100/60 mt-0.5">Used: ₹{{ number_format($totalCashPayments, 2) }}</p>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="text-xs text-indigo-100/80 mb-1">Online Allowance</p>
                                        <p class="text-lg font-bold text-white">₹{{ number_format($onlineAllowance, 2) }}</p>
                                        <p class="text-xs text-indigo-100/70 mt-1">Remaining: ₹{{ number_format($remainingOnlineAllowance, 2) }}</p>
                                        @if($totalOnlinePayments > 0)
                                            <p class="text-xs text-indigo-100/60 mt-0.5">Used: ₹{{ number_format($totalOnlinePayments, 2) }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Miscellaneous Charges -->
                            <div class="rounded-2xl bg-white/15 border border-white/20 p-5 backdrop-blur">
                                <p class="text-xs uppercase tracking-wide text-indigo-100/90 font-semibold mb-4">Miscellaneous Charges</p>
                                <div class="space-y-4">
                                    <div>
                                        <p class="text-xs text-indigo-100/80 mb-1">Paid</p>
                                        <p class="text-2xl font-bold text-white">₹{{ number_format($miscPaymentsAmount, 2) }}</p>
                                        <p class="text-xs text-indigo-100/70 mt-1">{{ $miscPaymentsCount }} payment{{ $miscPaymentsCount === 1 ? '' : 's' }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-indigo-100/80 mb-1">Unpaid</p>
                                        <p class="text-2xl font-bold text-white">₹{{ number_format($miscUnpaid, 2) }}</p>
                                        <p class="text-xs text-indigo-100/60 italic mt-1">Not bound by allowances</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Penalties Section - Always Visible -->
                        <div class="rounded-2xl bg-red-500/20 border-2 border-red-300/40 p-5 backdrop-blur">
                            <p class="text-xs uppercase tracking-wide text-red-100/90 font-semibold mb-4">Penalties Accrued</p>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <p class="text-xs text-red-100/80 mb-1">Total Penalties</p>
                                    <p class="text-2xl font-bold text-white">₹{{ number_format($penaltyTotal, 2) }}</p>
                                    @if($penaltyTotal == 0)
                                        <p class="text-xs text-red-100/70 mt-1">No penalties yet</p>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs text-red-100/80 mb-1">Late Fee Penalties</p>
                                    <p class="text-xl font-bold text-white">₹{{ number_format($lateFeePenaltyTotal, 2) }}</p>
                                    @if($lateFeePenalties->count() > 0)
                                        <p class="text-xs text-red-100/70 mt-1">{{ $lateFeePenalties->count() }} penalty{{ $lateFeePenalties->count() === 1 ? '' : 'ies' }}</p>
                                    @else
                                        <p class="text-xs text-red-100/70 mt-1">No late fees</p>
                                    @endif
                                </div>
                                <div>
                                    <p class="text-xs text-red-100/80 mb-1">GST Penalties</p>
                                    <p class="text-xl font-bold text-white">₹{{ number_format($gstPenaltyTotal, 2) }}</p>
                                    @if($gstPenalties->count() > 0)
                                        <p class="text-xs text-red-100/70 mt-1">{{ $gstPenalties->count() }} penalty{{ $gstPenalties->count() === 1 ? '' : 'ies' }}</p>
                                    @else
                                        <p class="text-xs text-red-100/70 mt-1">No GST penalties</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <!-- Credit Balance and Next Installment -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Credit Balance -->
                            @if($creditBalance > 0)
                            <div class="rounded-2xl bg-emerald-500/20 border border-emerald-300/30 px-4 py-3 text-sm text-emerald-50">
                                <p class="text-xs uppercase tracking-wide text-emerald-100/90 font-semibold">Credit Balance Available</p>
                                <p class="mt-1 text-lg font-semibold text-white">₹{{ number_format($creditBalance, 2) }}</p>
                                <p class="text-xs text-emerald-100/70 mt-1">This credit can be applied to future payments (tuition or miscellaneous)</p>
                            </div>
                            @endif
                            
                            <!-- Next Installment -->
                            @if($nextInstallment)
                                <div class="rounded-2xl bg-white/15 px-4 py-3 text-sm text-indigo-50">
                                    <p class="text-xs uppercase tracking-wide text-indigo-100/70">Next installment due</p>
                                    <p class="mt-1 text-base font-semibold text-white">{{ $nextInstallment->due_date->format('d M Y') }}</p>
                                    <p class="text-xs text-indigo-100/70">Outstanding ₹{{ number_format(max($nextInstallment->amount - $nextInstallment->paid_amount, 0), 2) }} &middot; #{{ $nextInstallment->installment_number }}</p>
                                </div>
                            @else
                                <div class="rounded-2xl bg-white/15 px-4 py-3 text-sm text-indigo-50">
                                    <p class="text-xs uppercase tracking-wide text-indigo-100/70">All installments settled</p>
                                    <p class="mt-1 text-base font-semibold text-white">Great work!</p>
                                    <p class="text-xs text-indigo-100/70">No pending schedule right now.</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <div class="mt-8 grid gap-6 items-start lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">

                    <!-- 1. Installment Schedule -->
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Installment Schedule</h3>
                            <span class="text-xs text-gray-500">Total ₹{{ number_format($installmentsTotal, 2) }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">#</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse(optional($student->fee)->installments ?? [] as $installment)
                                        @php
                                            $originalAmount = $installment->original_amount ?? $installment->amount;
                                            $remainingAmount = max(0, $installment->amount - $installment->paid_amount);
                                            $hasRemaining = $remainingAmount > 0.01 && $installment->status === 'partially_paid';
                                            $showOriginal = $originalAmount != $installment->amount && $installment->status === 'partially_paid';
                                            // Get approved discounts for this installment
                                            $installmentDiscounts = $student->discounts()
                                                ->where('installment_id', $installment->id)
                                                ->where('status', 'approved')
                                                ->get();
                                            $totalDiscountAmount = $installmentDiscounts->sum('amount');
                                            $hasDiscount = $totalDiscountAmount > 0;
                                        @endphp
                                        <tr class="bg-white {{ $hasRemaining ? 'bg-amber-50/30' : '' }}">
                                            <td class="px-4 py-3 font-semibold text-gray-700">{{ $installment->installment_number }}</td>
                                            <td class="px-4 py-3 text-gray-700">{{ \Carbon\Carbon::parse($installment->due_date)->format('d M Y') }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <div class="space-y-1">
                                                    @if($showOriginal)
                                                        <div class="text-xs text-gray-500 line-through">Original: ₹{{ number_format($originalAmount, 2) }}</div>
                                                    @endif
                                                    <div class="text-gray-900 font-semibold">₹{{ number_format($installment->amount, 2) }}</div>
                                                    @if($hasDiscount)
                                                        <div class="text-xs text-emerald-600 font-medium">Discount: -₹{{ number_format($totalDiscountAmount, 2) }}</div>
                                                    @endif
                                                    @if($installment->paid_amount > 0)
                                                        <div class="text-xs text-emerald-600">Paid: ₹{{ number_format($installment->paid_amount, 2) }}</div>
                                                    @endif
                                                    @if($hasRemaining)
                                                        <div class="text-xs font-semibold text-amber-600">Remaining: ₹{{ number_format($remainingAmount, 2) }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ match($installment->status) {
                                                        'paid' => 'bg-emerald-50 text-emerald-700',
                                                        'overdue' => 'bg-red-50 text-red-700',
                                                        'partially_paid' => 'bg-amber-50 text-amber-700',
                                                        'rescheduled' => 'bg-blue-50 text-blue-700',
                                                        default => 'bg-slate-100 text-slate-700'
                                                    } }}">
                                                    {{ Str::headline($installment->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($hasRemaining)
                                                    <button 
                                                        type="button"
                                                        onclick="openCreateRemainingModal({{ $installment->id }}, {{ $remainingAmount }})"
                                                        class="inline-flex items-center gap-1.5 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600"
                                                    >
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                        </svg>
                                                        Create Installment
                                                    </button>
                                                @else
                                                    <span class="text-xs text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No installments configured.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 2. Miscellaneous Charges -->
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Miscellaneous Charges</h3>
                            <span class="text-xs text-gray-500">Total ₹{{ number_format($miscTotal, 2) }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Item</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Due Date</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($student->miscCharges()->where('label', 'not like', 'GST Penalty%')->get() as $charge)
                                        <tr class="bg-white">
                                            <td class="px-4 py-3 text-gray-700">{{ $charge->label }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-semibold">₹{{ number_format($charge->amount, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $charge->due_date ? \Carbon\Carbon::parse($charge->due_date)->format('d M Y') : '—' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    {{ match($charge->status) {
                                                        'paid' => 'bg-emerald-50 text-emerald-700',
                                                        'cancelled' => 'bg-slate-100 text-slate-600',
                                                        default => 'bg-amber-50 text-amber-700'
                                                    } }}">
                                                    {{ Str::headline($charge->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No miscellaneous charges added.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 3. Payment History -->
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Payment History</h3>
                            <span class="text-xs text-gray-500">Payments recorded: {{ $student->payments->count() }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Date</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Mode</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">For</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Reference</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($student->payments as $payment)
                                        <tr class="bg-white">
                                            <td class="px-4 py-3 text-gray-700">{{ $payment->payment_date->format('d M Y') }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-semibold">₹{{ number_format($payment->amount_received, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-700 capitalize">{{ str_replace('_', ' ', $payment->payment_mode) }}</td>
                                            <td class="px-4 py-3 text-gray-600">
                                                @if($payment->installment)
                                                    <span class="text-indigo-600 font-medium">Tuition:</span> #{{ $payment->installment->installment_number }} &mdash; due {{ $payment->installment->due_date->format('d M Y') }}
                                                @elseif($payment->miscCharge)
                                                    <span class="text-purple-600 font-medium">Misc:</span> {{ $payment->miscCharge->label }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <div class="space-y-1">
                                                    @if($payment->voucher_number)
                                                        <div class="text-sm font-medium">Voucher: {{ $payment->voucher_number }}</div>
                                                    @endif
                                                    @if($payment->employee_name)
                                                        <div class="text-xs text-gray-500">Employee: {{ $payment->employee_name }}</div>
                                                    @endif
                                                    @if($payment->transaction_id)
                                                        <div class="text-sm font-medium mt-1">
                                                            @if($payment->payment_mode === 'cheque')
                                                                Cheque: {{ $payment->transaction_id }}
                                                            @else
                                                                UTR: {{ $payment->transaction_id }}
                                                            @endif
                                                        </div>
                                                    @endif
                                                    @if($payment->bank)
                                                        <div class="text-xs text-gray-500">Bank: {{ $payment->bank->name }}</div>
                                                    @elseif($payment->deposited_to && $payment->payment_mode === 'cheque')
                                                        <div class="text-xs text-gray-500">Deposited To: {{ $payment->deposited_to }}</div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No payments recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 4. Penalty Ledger -->
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div class="border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="text-lg font-semibold text-gray-900">Penalty Ledger</h3>
                                <span class="text-xs text-gray-500">Total ₹{{ number_format($penaltyTotal, 2) }}</span>
                            </div>
                            @if($penaltyTotal > 0)
                                <div class="flex gap-4 text-xs text-gray-600">
                                    <span>Late Fees: ₹{{ number_format($lateFeePenaltyTotal, 2) }}</span>
                                    <span>Manual Penalties: ₹{{ number_format($manualPenaltyTotal, 2) }}</span>
                                    <span>GST Penalties: ₹{{ number_format($gstPenaltyTotal, 2) }}</span>
                                </div>
                            @endif
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Date</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Type</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Details</th>
                                        <th class="px-4 py-3 text-right font-medium text-gray-500 uppercase tracking-wide">Amount (₹)</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Days Late</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @php
                                        // Combine late fee penalties, manual penalties, and GST penalties, sorted by date
                                        $allPenalties = collect();
                                        
                                        // Add late fee penalties
                                        foreach ($lateFeePenalties as $penalty) {
                                            $allPenalties->push([
                                                'type' => 'late_fee',
                                                'date' => $penalty->applied_date,
                                                'amount' => $penalty->penalty_amount,
                                                'days_delayed' => $penalty->days_delayed,
                                                'status' => $penalty->status,
                                                'installment' => $penalty->installment,
                                                'penalty_rate' => $penalty->penalty_rate,
                                                'penalty' => $penalty,
                                            ]);
                                        }
                                        
                                        // Add manual penalties
                                        foreach ($manualPenalties as $penalty) {
                                            $allPenalties->push([
                                                'type' => 'manual',
                                                'date' => $penalty->applied_date,
                                                'amount' => $penalty->penalty_amount,
                                                'days_delayed' => null, // Manual penalties don't have days delayed
                                                'status' => $penalty->status,
                                                'installment' => null, // Manual penalties are not tied to installments
                                                'penalty_rate' => null, // Manual penalties don't have a rate
                                                'penalty_type_name' => $penalty->remarks, // The type name stored in remarks
                                                'penalty' => $penalty,
                                            ]);
                                        }
                                        
                                        // Add GST penalties
                                        foreach ($gstPenalties as $gstPenalty) {
                                            // Extract excess amount from label (e.g., "GST Penalty on Online Overage (Excess ₹1000.00 + 18% GST = ₹180.00)")
                                            // Try to extract the excess amount (first ₹ amount in parentheses)
                                            preg_match('/Excess ₹([\d,]+\.?\d*)/', $gstPenalty->label, $matches);
                                            $excessAmount = $matches[1] ?? '0.00';
                                            // Clean up any commas
                                            $excessAmount = str_replace(',', '', $excessAmount);
                                            
                                            $allPenalties->push([
                                                'type' => 'gst',
                                                'date' => $gstPenalty->due_date ?? $gstPenalty->created_at?->toDateString(),
                                                'amount' => $gstPenalty->amount, // This is now ONLY the GST amount
                                                'days_delayed' => null, // GST penalties don't have days delayed
                                                'status' => $gstPenalty->status,
                                                'excess_amount' => $excessAmount, // The excess tuition amount
                                                'gst_charge' => $gstPenalty,
                                            ]);
                                        }
                                        
                                        // Sort by date descending
                                        $allPenalties = $allPenalties->sortByDesc('date')->values();
                                    @endphp
                                    
                                    @forelse($allPenalties as $penaltyItem)
                                        <tr class="bg-white">
                                            <td class="px-4 py-3 text-gray-700">
                                                {{ \Carbon\Carbon::parse($penaltyItem['date'])->format('d M Y') }}
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($penaltyItem['type'] === 'late_fee')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-amber-100 text-amber-800">
                                                        Late Fee
                                                    </span>
                                                @elseif($penaltyItem['type'] === 'manual')
                                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-indigo-100 text-indigo-800">
                                                        {{ $penaltyItem['penalty_type_name'] ?? 'Manual Penalty' }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-purple-100 text-purple-800">
                                                        GST Penalty
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                @if($penaltyItem['type'] === 'late_fee')
                                                    <div>
                                                        Installment #{{ $penaltyItem['installment']?->installment_number ?? '—' }}
                                                        @if($penaltyItem['installment']?->due_date)
                                                            <div class="text-xs text-gray-400">Due {{ $penaltyItem['installment']->due_date->format('d M Y') }}</div>
                                                        @endif
                                                        <div class="text-xs text-gray-400 mt-0.5">Rate: {{ number_format($penaltyItem['penalty_rate'], 2) }}% per day</div>
                                                    </div>
                                                @elseif($penaltyItem['type'] === 'manual')
                                                    <div class="text-sm">
                                                        Manually Added Penalty
                                                    </div>
                                                    <div class="text-xs text-gray-400 mt-0.5">
                                                        Applied on {{ \Carbon\Carbon::parse($penaltyItem['date'])->format('d M Y') }}
                                                    </div>
                                                @else
                                                    <div class="text-sm">
                                                        GST Penalty on Online Overage
                                                    </div>
                                                    <div class="text-xs text-gray-400 mt-0.5 space-y-0.5">
                                                        <div>Excess Tuition: ₹{{ number_format(str_replace(',', '', $penaltyItem['excess_amount']), 2) }}</div>
                                                        <div>GST Penalty: ₹{{ number_format($penaltyItem['amount'], 2) }}</div>
                                                        <div class="text-xs text-gray-500 italic">(Excess amount is counted as tuition fees)</div>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right text-gray-900 font-semibold">₹{{ number_format($penaltyItem['amount'], 2) }}</td>
                                            <td class="px-4 py-3 text-gray-600">
                                                @if($penaltyItem['type'] === 'late_fee' && $penaltyItem['days_delayed'] !== null)
                                                    {{ $penaltyItem['days_delayed'] }} days
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($penaltyItem['type'] === 'late_fee' || $penaltyItem['type'] === 'manual')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $penaltyItem['status'] === 'recorded' ? 'bg-amber-50 text-amber-700' : ($penaltyItem['status'] === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($penaltyItem['status'] === 'waived' ? 'bg-gray-50 text-gray-700' : 'bg-slate-100 text-slate-700')) }}">
                                                        {{ Str::headline($penaltyItem['status']) }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $penaltyItem['status'] === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($penaltyItem['status'] === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                                        {{ Str::headline($penaltyItem['status']) }}
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="px-4 py-6 text-center text-sm text-gray-500">No penalties applied.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 5. Reschedule History -->
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Reschedule History</h3>
                            <span class="text-xs text-gray-500">Total requests: {{ $student->reschedules->count() }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Installment</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Old → New</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($recentReschedules as $reschedule)
                                        <tr class="bg-white">
                                            <td class="px-4 py-3 text-gray-700">
                                                #{{ $reschedule->installment?->installment_number ?? '—' }}
                                                <div class="text-xs text-gray-400">Attempt {{ $reschedule->attempt_number }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{ $reschedule->old_due_date->format('d M Y') }}
                                                <span class="text-xs text-gray-400">→</span>
                                                <span class="text-indigo-600">{{ $reschedule->new_due_date->format('d M Y') }}</span>
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @class([
                                                        'bg-indigo-50 text-indigo-700' => $reschedule->status === 'pending',
                                                        'bg-emerald-50 text-emerald-700' => $reschedule->status === 'approved',
                                                        'bg-red-50 text-red-700' => $reschedule->status === 'rejected',
                                                    ])">
                                                    {{ ucfirst($reschedule->status) }}
                                                </span>
                                                <div class="text-xs text-gray-400 mt-1">
                                                    {{ $reschedule->created_at->format('d M Y') }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <p class="text-xs text-gray-500">{{ Str::limit($reschedule->reason, 80) }}</p>
                                                @if($reschedule->decision_notes)
                                                    <div class="text-xs text-gray-400 mt-1">Admin: {{ Str::limit($reschedule->decision_notes, 80) }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No reschedule activity yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- 6. Reminder Log -->
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Reminder Log</h3>
                            <span class="text-xs text-gray-500">Recent reminders</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Scheduled</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Installment</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Channel</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Sent</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($recentReminders as $reminder)
                                        <tr class="bg-white">
                                            <td class="px-4 py-3 text-gray-700">{{ $reminder->scheduled_for->format('d M Y') }}</td>
                                            <td class="px-4 py-3 text-gray-600">#{{ $reminder->installment?->installment_number ?? '—' }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ Str::headline($reminder->channel) }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $reminder->status === 'queued' ? 'bg-indigo-50 text-indigo-700' : ($reminder->status === 'sent' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600') }}">
                                                    {{ Str::headline($reminder->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{ $reminder->sent_at ? $reminder->sent_at->format('d M Y H:i') : '—' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-4 py-6 text-center text-sm text-gray-500">No reminders queued yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Discount History -->
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-6 py-4">
                            <h3 class="text-lg font-semibold text-gray-900">Discount History</h3>
                            <span class="text-xs text-gray-500">Approved total ₹{{ number_format($approvedDiscountTotal, 2) }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Amount</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Reason</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Status</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wide">Updated</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @forelse($recentDiscounts as $discount)
                                        <tr class="bg-white">
                                            <td class="px-4 py-3 text-gray-700 font-semibold">₹{{ number_format($discount->amount, 2) }}</td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <p class="text-sm">{{ Str::limit($discount->reason, 120) }}</p>
                                                @if($discount->document_path)
                                                    <a href="{{ Storage::disk('public')->url($discount->document_path) }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-500 mt-1 inline-block">View document</a>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    @class([
                                                        'bg-indigo-50 text-indigo-700' => $discount->status === 'pending',
                                                        'bg-emerald-50 text-emerald-700' => $discount->status === 'approved',
                                                        'bg-red-50 text-red-700' => $discount->status === 'rejected',
                                                    ])">
                                                    {{ ucfirst($discount->status) }}
                                                </span>
                                                @if($discount->decision_notes)
                                                    <div class="text-xs text-gray-400 mt-1">Admin: {{ Str::limit($discount->decision_notes, 80) }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                {{ $discount->updated_at->format('d M Y H:i') }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-500">No discount requests yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <!-- Modal for Creating Remaining Installment -->
                    <div id="create-remaining-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="closeCreateRemainingModal()"></div>
                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                <form id="create-remaining-form" method="POST" action="">
                                    @csrf
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 sm:mx-0 sm:h-10 sm:w-10">
                                                <svg class="h-6 w-6 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                            </div>
                                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                                    Create New Installment for Remaining Amount
                                                </h3>
                                                <div class="mt-4">
                                                    <p class="text-sm text-gray-500">
                                                        Remaining amount: <span id="modal-remaining-amount" class="font-semibold text-indigo-600"></span>
                                                    </p>
                                                    <p class="text-sm text-gray-500 mt-2">
                                                        This will create a new installment in the schedule for the remaining amount. The original installment will remain marked as "Partially Paid".
                                                    </p>
                                                </div>
                                                <div class="mt-4">
                                                    <label for="remaining-due-date" class="block text-sm font-medium text-gray-700">Due Date *</label>
                                                    <input 
                                                        type="date" 
                                                        id="remaining-due-date" 
                                                        name="due_date" 
                                                        required
                                                        min="{{ now()->toDateString() }}"
                                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                    >
                                                    <p class="mt-1 text-xs text-gray-500">Select the date when the remaining amount should be due</p>
                                                    @error('due_date')
                                                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button 
                                            type="submit"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm"
                                        >
                                            Create Installment
                                        </button>
                                        <button 
                                            type="button"
                                            onclick="closeCreateRemainingModal()"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <script>
                        function openCreateRemainingModal(installmentId, remainingAmount) {
                            const modal = document.getElementById('create-remaining-modal');
                            const form = document.getElementById('create-remaining-form');
                            const amountSpan = document.getElementById('modal-remaining-amount');
                            const dueDateInput = document.getElementById('remaining-due-date');
                            
                            // Set the form action URL
                            form.action = '{{ route("students.installments.create-remaining", ["student" => $student->id, "installment" => ":installment"]) }}'.replace(':installment', installmentId);
                            
                            // Set the remaining amount
                            amountSpan.textContent = '₹' + remainingAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            
                            // Set a suggested date (30 days from today)
                            const suggestedDate = new Date();
                            suggestedDate.setDate(suggestedDate.getDate() + 30);
                            dueDateInput.value = suggestedDate.toISOString().split('T')[0];
                            
                            // Show the modal
                            modal.classList.remove('hidden');
                            document.body.style.overflow = 'hidden';
                        }
                        
                        function closeCreateRemainingModal() {
                            const modal = document.getElementById('create-remaining-modal');
                            modal.classList.add('hidden');
                            document.body.style.overflow = 'auto';
                        }
                        
                        // Close modal on Escape key
                        document.addEventListener('keydown', function(event) {
                            if (event.key === 'Escape') {
                                closeCreateRemainingModal();
                            }
                        });
                    </script>

                </div>

                <div class="space-y-6">
                    <section class="rounded-2xl border border-slate-100 bg-white shadow-sm p-6 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-gray-900">Status &amp; Alerts</h3>
                            <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-600">Live</span>
                        </div>
                        <dl class="space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Installments added</dt>
                                <dd class="font-semibold text-gray-900">{{ $addedInstallmentsCount }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Discounts given</dt>
                                <dd class="font-semibold text-gray-900">{{ $approvedDiscountsCount }}</dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Payments recorded</dt>
                                <dd class="font-semibold text-gray-900">{{ $totalPaymentsCount }}</dd>
                            </div>
                        </dl>
                        @if($outstandingInstallments->isNotEmpty())
                            <div class="space-y-2">
                                <p class="text-xs uppercase tracking-wide text-gray-500">Upcoming instalments</p>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    @foreach($outstandingInstallments->take(3) as $installment)
                                        <li class="flex items-start justify-between rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                                            <div>
                                                <p class="font-semibold text-slate-900">#{{ $installment->installment_number }}</p>
                                                <p class="text-xs text-slate-600">Due {{ $installment->due_date->format('d M Y') }}</p>
                                            </div>
                                            <span class="text-sm font-semibold text-indigo-600">₹{{ number_format(max($installment->amount - $installment->paid_amount, 0), 2) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-xs text-emerald-700">
                                All instalments are cleared. Great job!
                            </div>
                        @endif
                    </section>

                    <!-- Accordion Container -->
                    <div class="space-y-3">
                        <!-- Financial Snapshot Accordion - Hidden as per user request -->
                        {{-- <div class="accordion-item rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                            <button type="button" class="accordion-header w-full px-6 py-4 flex items-center justify-between text-left hover:bg-slate-50 transition-colors duration-150" data-accordion="financial-snapshot">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Financial Snapshot</h3>
                                    <p class="text-sm text-gray-500 mt-0.5">View financial overview and totals</p>
                                </div>
                                <svg class="accordion-chevron h-5 w-5 text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="accordion-content hidden px-6 pb-6">
                                <div class="grid gap-3 sm:grid-cols-2 pt-4">
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-lg font-semibold text-slate-900">₹{{ number_format($totalProgramFee, 2) }}</p>
                                        @if($approvedDiscountTotal > 0)
                                            <p class="mt-1 text-xs text-emerald-600">Discount applied: -₹{{ number_format($approvedDiscountTotal, 2) }}</p>
                                        @endif
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-lg font-semibold text-slate-900">₹{{ number_format($originalCashAllowance, 2) }}</p>
                                        <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                                            <p class="text-xs text-slate-700 font-medium">
                                                ₹{{ number_format($remainingCashAllowance, 2) }}
                                            </p>
                                            @if($totalCashPayments > 0)
                                                <p class="text-xs text-slate-500">
                                                    <span class="font-medium">Used:</span> ₹{{ number_format($totalCashPayments, 2) }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-lg font-semibold text-slate-900">₹{{ number_format($originalOnlineAllowance, 2) }}</p>
                                        @if($approvedDiscountTotal > 0)
                                            <p class="mt-0.5 text-xs text-amber-600 italic">
                                                After discount: ₹{{ number_format($onlineAllowance, 2) }}
                                            </p>
                                        @endif
                                        <div class="mt-2 space-y-1 border-t border-slate-200 pt-2">
                                            <p class="text-xs text-slate-700 font-medium">
                                                ₹{{ number_format($remainingOnlineAllowance, 2) }}
                                            </p>
                                            @if($totalOnlinePayments > 0)
                                                <p class="text-xs text-slate-500">
                                                    <span class="font-medium">Used:</span> ₹{{ number_format($totalOnlinePayments, 2) }}
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-lg font-semibold text-slate-900">₹{{ number_format($penaltyTotal, 2) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Installments Planned</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-900">₹{{ number_format($installmentsTotal, 2) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="text-xs uppercase tracking-wide text-slate-500">Misc Charges</p>
                                        <p class="mt-1 text-lg font-semibold text-slate-900">₹{{ number_format($miscTotal, 2) }}</p>
                                    </div>
                                    <div class="rounded-xl bg-indigo-50 p-4 sm:col-span-2">
                                        <p class="text-xs uppercase tracking-wide text-indigo-500">Total Planned Collections</p>
                                        <p class="mt-1 text-xl font-semibold text-indigo-600">₹{{ number_format($installmentsTotal + $miscTotal, 2) }}</p>
                                        <p class="text-xs text-indigo-500">Installments + miscellaneous charges</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-4">Payments, penalties, reminders, reschedule, and discount requests are tracked here. Upcoming modules will add discount ledger adjustments, audit logs, and exports.</p>
                            </div>
                        </div> --}}

                        <!-- Record Payment Accordion -->
                        <div class="accordion-item rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                            <button type="button" class="accordion-header w-full px-6 py-4 flex items-center justify-between text-left hover:bg-slate-50 transition-colors duration-150" data-accordion="record-payment">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Record Payment</h3>
                                    <p class="text-sm text-gray-500 mt-0.5">Capture a payment and update outstanding installments</p>
                                </div>
                                <svg class="accordion-chevron h-5 w-5 text-gray-400 transform transition-transform duration-200 {{ ($errors->has('payment_type') || $errors->has('amount_received') || $errors->has('payment_date') || $errors->has('payment_mode') || $errors->has('installment_id') || $errors->has('misc_charge_id') || $errors->has('penalty_type') || $errors->has('penalty_id') || $errors->has('gst_penalty_charge_id') || $errors->has('voucher_number') || $errors->has('employee_name') || session('error')) ? 'rotate-180' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="accordion-content px-6 pb-6 {{ ($errors->has('payment_type') || $errors->has('amount_received') || $errors->has('payment_date') || $errors->has('payment_mode') || $errors->has('installment_id') || $errors->has('misc_charge_id') || $errors->has('penalty_type') || $errors->has('penalty_id') || $errors->has('gst_penalty_charge_id') || $errors->has('voucher_number') || $errors->has('employee_name') || session('error')) ? '' : 'hidden' }}">
                                <div class="pt-4">
                                    @if(session('error'))
                                        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                                            {{ session('error') }}
                                        </div>
                                    @endif
                                    
                                    @if($errors->any())
                                        <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3">
                                            <div class="font-medium text-red-800 mb-1">Please fix the following errors:</div>
                                            <ul class="list-disc list-inside text-sm text-red-700">
                                                @foreach($errors->all() as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('students.payments.store', $student) }}" class="space-y-4" id="payment-form">
                                        @csrf

                                        <!-- Step 0: Payment Type Selection (Always Visible) -->
                                        <div class="rounded-lg border-2 border-indigo-200 bg-indigo-50 p-4">
                                            <div>
                                                <x-input-label for="payment_type" value="Payment Type *" class="text-base font-semibold text-indigo-900" />
                                                <select id="payment_type" name="payment_type" class="mt-2 block w-full rounded-lg border-indigo-300 bg-white text-base font-medium focus:border-indigo-500 focus:ring-indigo-500" required>
                                                    <option value="">-- Select payment type --</option>
                                                    <option value="tuition" @selected(old('payment_type') === 'tuition')>Tuition Fees</option>
                                                    <option value="miscellaneous" @selected(old('payment_type') === 'miscellaneous')>Miscellaneous Fees</option>
                                                    <option value="penalty" @selected(old('payment_type') === 'penalty')>Penalty</option>
                                                </select>
                                                <x-input-error :messages="$errors->get('payment_type')" class="mt-2" />
                                                <p class="mt-2 text-sm text-indigo-700">Select whether this payment is for tuition fees, miscellaneous charges, or penalties.</p>
                                            </div>
                                        </div>

                                        <!-- Step 1: Select Installment (Hidden until payment type is selected) -->
                                        <div id="tuition-payment-section" class="hidden rounded-lg border-2 border-indigo-200 bg-indigo-50 p-4">
                                            <div>
                                                <x-input-label for="installment_id" value="Select Installment *" class="text-base font-semibold text-indigo-900" />
                                                <select id="installment_id" name="installment_id" class="mt-2 block w-full rounded-lg border-indigo-300 bg-white text-base font-medium focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">-- Select an installment --</option>
                                                    @foreach($outstandingInstallments as $installment)
                                                        <option value="{{ $installment->id }}" 
                                                            data-installment-amount="{{ $installment->amount }}"
                                                            data-paid-amount="{{ $installment->paid_amount }}"
                                                            @selected(old('installment_id') == $installment->id)>
                                                            #{{ $installment->installment_number }} &mdash; Due {{ $installment->due_date->format('d M Y') }} &middot; Outstanding ₹{{ number_format($installment->amount - $installment->paid_amount, 2) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('installment_id')" class="mt-2" />
                                                <p class="mt-2 text-sm text-indigo-700">Please select the installment number first to proceed with payment recording.</p>
                                            </div>
                                        </div>

                                        <!-- Step 1: Select Miscellaneous Charge (Hidden until payment type is selected) -->
                                        <div id="misc-payment-section" class="hidden rounded-lg border-2 border-indigo-200 bg-indigo-50 p-4">
                                            <div>
                                                <x-input-label for="misc_charge_id" value="Select Miscellaneous Charge *" class="text-base font-semibold text-indigo-900" />
                                                <select id="misc_charge_id" name="misc_charge_id" class="mt-2 block w-full rounded-lg border-indigo-300 bg-white text-base font-medium focus:border-indigo-500 focus:ring-indigo-500">
                                                    <option value="">-- Select a charge --</option>
                                                    @foreach($unpaidMiscCharges as $charge)
                                                        <option value="{{ $charge->id }}" 
                                                            data-charge-amount="{{ $charge->amount }}"
                                                            @selected(old('misc_charge_id') == $charge->id)>
                                                            {{ $charge->label }} &mdash; Due {{ $charge->due_date ? \Carbon\Carbon::parse($charge->due_date)->format('d M Y') : 'No due date' }} &middot; ₹{{ number_format($charge->amount, 2) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('misc_charge_id')" class="mt-2" />
                                                <p class="mt-2 text-sm text-indigo-700">Full payment required for miscellaneous charges (no partial payments).</p>
                                            </div>
                                        </div>

                                        <!-- Step 1: Select Penalty (Hidden until payment type is selected) -->
                                        <div id="penalty-payment-section" class="hidden rounded-lg border-2 border-red-200 bg-red-50 p-4">
                                            <div>
                                                <x-input-label for="penalty_type" value="Penalty Type *" class="text-base font-semibold text-red-900" />
                                                <select id="penalty_type" name="penalty_type" class="mt-2 block w-full rounded-lg border-red-300 bg-white text-base font-medium focus:border-red-500 focus:ring-red-500">
                                                    <option value="">-- Select penalty type --</option>
                                                    <option value="late_fee" @selected(old('penalty_type') === 'late_fee')>Late Fee Penalty</option>
                                                    <option value="gst" @selected(old('penalty_type') === 'gst')>GST Penalty</option>
                                                    <option value="manual" @selected(old('penalty_type') === 'manual')>Manually Added Penalty</option>
                                                </select>
                                                <x-input-error :messages="$errors->get('penalty_type')" class="mt-2" />
                                                <p class="mt-2 text-sm text-red-700">Select the type of penalty you want to pay.</p>
                                            </div>
                                        </div>
                                            
                                            <!-- Late Fee Penalty Selection -->
                                            <div id="late-fee-penalty-section" class="mt-4 hidden">
                                                <x-input-label for="penalty_id" value="Select Late Fee Penalty *" class="text-base font-semibold text-red-900" />
                                                <select id="penalty_id" name="penalty_id" class="mt-2 block w-full rounded-lg border-red-300 bg-white text-base font-medium focus:border-red-500 focus:ring-red-500">
                                                    <option value="">-- Select a penalty --</option>
                                                    @foreach($unpaidLateFeePenalties as $penalty)
                                                        <option value="{{ $penalty->id }}" 
                                                            data-penalty-amount="{{ $penalty->penalty_amount }}"
                                                            @selected(old('penalty_id') == $penalty->id)>
                                                            Late Fee &mdash; Installment #{{ $penalty->installment->installment_number ?? 'N/A' }} &mdash; Applied {{ $penalty->applied_date->format('d M Y') }} &middot; ₹{{ number_format($penalty->penalty_amount, 2) }} ({{ $penalty->days_delayed }} days late)
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('penalty_id')" class="mt-2" />
                                                <p class="mt-2 text-sm text-red-700">Select the late fee penalty you want to pay.</p>
                                            </div>
                                            
                                            <!-- GST Penalty Selection -->
                                            <div id="gst-penalty-section" class="mt-4 hidden">
                                                <x-input-label for="gst_penalty_charge_id" value="Select GST Penalty *" class="text-base font-semibold text-red-900" />
                                                <select id="gst_penalty_charge_id" name="gst_penalty_charge_id" class="mt-2 block w-full rounded-lg border-red-300 bg-white text-base font-medium focus:border-red-500 focus:ring-red-500">
                                                    <option value="">-- Select a GST penalty --</option>
                                                    @foreach($unpaidGstPenalties as $gstPenalty)
                                                        <option value="{{ $gstPenalty->id }}" 
                                                            data-penalty-amount="{{ $gstPenalty->amount }}"
                                                            @selected(old('gst_penalty_charge_id') == $gstPenalty->id)>
                                                            {{ $gstPenalty->label }} &mdash; Due {{ $gstPenalty->due_date ? \Carbon\Carbon::parse($gstPenalty->due_date)->format('d M Y') : 'No due date' }} &middot; ₹{{ number_format($gstPenalty->amount, 2) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('gst_penalty_charge_id')" class="mt-2" />
                                                <p class="mt-2 text-sm text-red-700">Select the GST penalty you want to pay.</p>
                                            </div>
                                            
                                            <!-- Manual Penalty Selection -->
                                            <div id="manual-penalty-section" class="mt-4 hidden">
                                                <x-input-label for="manual_penalty_id" value="Select Manually Added Penalty *" class="text-base font-semibold text-red-900" />
                                                <select id="manual_penalty_id" name="penalty_id" class="mt-2 block w-full rounded-lg border-red-300 bg-white text-base font-medium focus:border-red-500 focus:ring-red-500">
                                                    <option value="">-- Select a penalty --</option>
                                                    @foreach($unpaidManualPenalties as $penalty)
                                                        <option value="{{ $penalty->id }}" 
                                                            data-penalty-amount="{{ $penalty->penalty_amount }}"
                                                            @selected(old('penalty_id') == $penalty->id)>
                                                            [{{ $penalty->remarks }}] &mdash; Applied {{ $penalty->applied_date->format('d M Y') }} &middot; ₹{{ number_format($penalty->penalty_amount, 2) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('penalty_id')" class="mt-2" />
                                                <p class="mt-2 text-sm text-red-700">Select the manually added penalty you want to pay.</p>
                                            </div>
                                        </div>

                                        <!-- Step 2: Payment Details (Hidden until installment or misc charge is selected) -->
                                        <div id="payment-details-section" class="space-y-4 hidden">
                                            <div class="border-t border-gray-200 pt-4">
                                                <h4 class="text-sm font-semibold text-gray-700 mb-4">Payment Details</h4>
                                            </div>

                                            <!-- Credit Balance Option (NEW FEATURE) -->
                                            @if($creditBalance > 0)
                                            <div class="rounded-lg border-2 border-emerald-200 bg-emerald-50 p-4">
                                                <div class="flex items-start">
                                                    <input type="checkbox" id="use_credit_balance" name="use_credit_balance" value="1" class="mt-1 h-4 w-4 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500" {{ old('use_credit_balance') ? 'checked' : '' }}>
                                                    <div class="ml-3 flex-1">
                                                        <label for="use_credit_balance" class="text-sm font-semibold text-emerald-900 cursor-pointer">Use Credit Balance</label>
                                                        <p class="mt-1 text-sm text-emerald-700">
                                                            Available credit: <strong>₹{{ number_format($creditBalance, 2) }}</strong>
                                                        </p>
                                                        <p class="mt-1 text-xs text-emerald-600">
                                                            If checked, credit balance will be applied to cover this payment. Only the amount needed will be used from your credit balance.
                                                        </p>
                                                        <div id="credit-preview" class="mt-2 hidden text-sm text-emerald-800 bg-emerald-100/50 rounded-lg p-3 border border-emerald-200">
                                                            <p class="font-semibold mb-2">Credit Application Preview:</p>
                                                            <div class="space-y-1">
                                                                <p><strong>Payment amount needed:</strong> ₹<span id="payment-amount-needed">0.00</span></p>
                                                                <p><strong>Credit to use:</strong> ₹<span id="credit-to-use">0.00</span> <span class="text-xs text-emerald-600">(from available ₹{{ number_format($creditBalance, 2) }})</span></p>
                                                                <p class="font-semibold"><strong>Remaining payment to record:</strong> ₹<span id="remaining-payment-amount">0.00</span></p>
                                                                <p class="mt-2 text-xs text-emerald-700 italic">
                                                                    <span id="credit-remaining-after">₹{{ number_format($creditBalance, 2) }}</span> credit will remain available after this payment.
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            @endif

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label for="amount_received" value="Amount Received (₹) *" />
                                                    <x-text-input id="amount_received" name="amount_received" type="number" step="0.01" min="0" class="mt-1 block w-full" value="{{ old('amount_received') }}" required />
                                                    <x-input-error :messages="$errors->get('amount_received')" class="mt-2" />
                                                    @if($creditBalance > 0)
                                                        <p class="mt-1 text-xs text-gray-500">Enter the payment amount (can be ₹0.00 if credit fully covers it). Credit balance will be applied if checked above.</p>
                                                    @else
                                                        <p class="mt-1 text-xs text-gray-500">Enter the payment amount received.</p>
                                                    @endif
                                                </div>
                                                <div>
                                                    <x-input-label for="payment_date" value="Payment Date *" />
                                                    <x-text-input id="payment_date" name="payment_date" type="date" class="mt-1 block w-full" value="{{ old('payment_date', now()->toDateString()) }}" required />
                                                    <x-input-error :messages="$errors->get('payment_date')" class="mt-2" />
                                                </div>
                                                <div>
                                                    <x-input-label for="payment_mode" value="Payment Mode *" />
                                                    <select id="payment_mode" name="payment_mode" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
                                                        @foreach(['cash', 'upi', 'bank_transfer', 'cheque'] as $mode)
                                                            <option value="{{ $mode }}" @selected(old('payment_mode') === $mode)>{{ Str::headline(str_replace('_', ' ', $mode)) }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('payment_mode')" class="mt-2" />
                                                </div>
                                            </div>

                                            <!-- GST Penalty Warning (shown only for online payments that exceed allowance) -->
                                            @php
                                                $onlineAllowance = optional($student->fee)->online_allowance ?? 0;
                                                // Only count TUITION payments for online allowance check (misc payments not bound by limits)
                                                $currentOnlineTotal = $student->payments()
                                                    ->whereNotNull('installment_id') // Only tuition payments
                                                    ->whereNull('misc_charge_id') // Exclude misc payments
                                                    ->whereNotIn('payment_mode', ['cash'])
                                                    ->sum('amount_received');
                                            @endphp
                                            @if($onlineAllowance > 0)
                                                <div id="gst-penalty-warning" class="hidden rounded-lg border border-amber-300 bg-amber-50 p-4">
                                                    <div class="flex items-start">
                                                        <svg class="h-5 w-5 text-amber-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                        </svg>
                                                        <div class="ml-3 flex-1">
                                                            <h4 class="text-sm font-semibold text-amber-900">GST Penalty Warning</h4>
                                                            <div class="mt-2 text-sm text-amber-700">
                                                                <p>This payment will exceed the online allowance of ₹<span id="online-allowance-amount">{{ number_format($onlineAllowance, 2) }}</span>.</p>
                                                                <p class="mt-1">Excess amount: ₹<span id="excess-amount">0.00</span></p>
                                                                <p class="mt-1 text-sm">The excess amount (₹<span id="excess-base">0.00</span>) will be counted as <strong>tuition fees</strong>.</p>
                                                                <p class="mt-1 font-semibold">GST Penalty: ₹<span id="penalty-amount">0.00</span> (<span id="gst-rate-display">{{ \App\Models\Setting::getValue('penalty.gst_percentage', config('fees.gst_percentage', 18.0)) }}</span>% GST on excess) will be added as a separate charge.</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Overpayment Notification (NEW FEATURE) -->
                                            <div id="overpayment-notification" class="hidden rounded-lg border-2 border-blue-200 bg-blue-50 p-4">
                                                <div class="flex items-start">
                                                    <svg class="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <div class="ml-3 flex-1">
                                                        <h4 class="text-sm font-semibold text-blue-900">Overpayment Detected</h4>
                                                        <div class="mt-2 text-sm text-blue-700">
                                                            <p>This payment exceeds the outstanding balance by <strong>₹<span id="overpayment-amount">0.00</span></strong>.</p>
                                                            <p class="mt-1">The excess amount will be automatically stored as <strong>credit balance</strong> and can be used for future payments.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Remaining Installment Option (shown when partial payment is made - only for tuition payments) -->
                                            <div id="remaining-installment-section" class="hidden rounded-lg border-2 border-indigo-300 bg-indigo-50 p-4 shadow-sm">
                                                <div class="flex items-start gap-3">
                                                    <input type="checkbox" name="create_remaining_installment" value="1" id="create_remaining_installment" class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('create_remaining_installment'))>
                                                    <div class="flex-1">
                                                        <label for="create_remaining_installment" class="text-sm font-semibold text-indigo-900 cursor-pointer flex items-center gap-2">
                                                            <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                            </svg>
                                                            Create new installment for remaining amount
                                                        </label>
                                                        <p class="mt-2 text-sm text-gray-700">
                                                            <span class="font-medium">Remaining amount:</span> <span id="remaining-amount" class="font-bold text-indigo-700 text-lg">₹0.00</span>
                                                        </p>
                                                        <p class="mt-1 text-xs text-gray-600">The remaining amount will be added as a new installment in the schedule.</p>
                                                        <div id="remaining-installment-date-field" class="mt-4 hidden">
                                                            <x-input-label for="remaining_installment_due_date" value="Due Date for Remaining Installment *" />
                                                            <x-text-input id="remaining_installment_due_date" name="remaining_installment_due_date" type="date" class="mt-1 block w-full" value="{{ old('remaining_installment_due_date') }}" min="{{ now()->toDateString() }}" required />
                                                            <x-input-error :messages="$errors->get('remaining_installment_due_date')" class="mt-2" />
                                                            <p class="mt-1 text-xs text-gray-500">Select the date when the remaining amount should be due</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Payment Details Fields (Dynamic based on payment mode) -->
                                            
                                            <!-- Common Fields (Available for all payment modes) -->
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label for="voucher_number" value="Voucher Number *" />
                                                    <x-text-input id="voucher_number" name="voucher_number" type="text" class="mt-1 block w-full" value="{{ old('voucher_number') }}" placeholder="Enter voucher number" required />
                                                    <x-input-error :messages="$errors->get('voucher_number')" class="mt-2" />
                                                    <p class="mt-1 text-xs text-gray-500">Voucher number issued when payment was received</p>
                                                </div>
                                                <div>
                                                    <x-input-label for="employee_name" value="Employee Name *" />
                                                    <x-text-input id="employee_name" name="employee_name" type="text" class="mt-1 block w-full" value="{{ old('employee_name') }}" placeholder="Name of employee who received payment" required />
                                                    <x-input-error :messages="$errors->get('employee_name')" class="mt-2" />
                                                    <p class="mt-1 text-xs text-gray-500">Employee who received the payment</p>
                                                </div>
                                            </div>

                                            <!-- Cash Mode Fields -->
                                            <div id="cash-fields" class="hidden">
                                                <!-- No additional fields needed for cash mode -->
                                            </div>

                                            <!-- Online Mode Fields (UPI, Bank Transfer) -->
                                            <div id="online-fields" class="hidden grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label for="transaction_id_online" value="UTR / Transaction ID *" />
                                                    <x-text-input id="transaction_id_online" type="text" class="mt-1 block w-full" value="{{ old('transaction_id') }}" placeholder="Enter UTR or transaction ID" />
                                                    <x-input-error :messages="$errors->get('transaction_id')" class="mt-2" />
                                                    <p class="mt-1 text-xs text-gray-500">Unique Transaction Reference (UTR) number</p>
                                                </div>
                                                <div>
                                                    <x-input-label for="bank_id_online" value="Bank *" />
                                                    <select id="bank_id_online" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">Select Bank</option>
                                                        @foreach($banks as $bank)
                                                            <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>{{ $bank->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('bank_id')" class="mt-2" />
                                                    <p class="mt-1 text-xs text-gray-500">Bank where payment was received</p>
                                                </div>
                                            </div>

                                            <!-- Cheque Mode Fields -->
                                            <div id="cheque-fields" class="hidden grid grid-cols-1 sm:grid-cols-3 gap-4">
                                                <div>
                                                    <x-input-label for="transaction_id_cheque" value="Cheque Number *" />
                                                    <x-text-input id="transaction_id_cheque" type="text" class="mt-1 block w-full" value="{{ old('transaction_id') }}" placeholder="Enter cheque number" />
                                                    <x-input-error :messages="$errors->get('transaction_id')" class="mt-2" />
                                                    <p class="mt-1 text-xs text-gray-500">Cheque number from the cheque</p>
                                                </div>
                                                <div>
                                                    <x-input-label for="bank_id_cheque" value="Bank of Cheque *" />
                                                    <select id="bank_id_cheque" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">Select Bank</option>
                                                        @foreach($banks as $bank)
                                                            <option value="{{ $bank->id }}" @selected(old('bank_id') == $bank->id)>{{ $bank->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('bank_id')" class="mt-2" />
                                                    <p class="mt-1 text-xs text-gray-500">Bank from which cheque was issued</p>
                                                </div>
                                                <div>
                                                    <x-input-label for="deposited_to_cheque" value="Deposited To Bank *" />
                                                    <x-text-input id="deposited_to_cheque" type="text" class="mt-1 block w-full" value="{{ old('deposited_to') }}" placeholder="Bank where cheque was deposited" />
                                                    <x-input-error :messages="$errors->get('deposited_to')" class="mt-2" />
                                                    <p class="mt-1 text-xs text-gray-500">Bank where cheque was deposited</p>
                                                </div>
                                            </div>

                                            <!-- Hidden fields that will be submitted (values synced from visible fields for mode-specific fields) -->
                                            <input type="hidden" id="transaction_id" name="transaction_id" value="{{ old('transaction_id') }}" />
                                            <input type="hidden" id="bank_id" name="bank_id" value="{{ old('bank_id') }}" />
                                            <input type="hidden" id="deposited_to" name="deposited_to" value="{{ old('deposited_to') }}" />

                                            <div>
                                                <x-input-label for="remarks" value="Remarks" />
                                                <textarea id="remarks" name="remarks" rows="3" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Optional notes">{{ old('remarks') }}</textarea>
                                                <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                                            </div>

                                            <div class="flex items-center justify-end">
                                                <x-primary-button>Save Payment</x-primary-button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Request Reschedule Accordion -->
                        <div class="accordion-item rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                            <button type="button" class="accordion-header w-full px-6 py-4 flex items-center justify-between text-left hover:bg-slate-50 transition-colors duration-150" data-accordion="request-reschedule">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Request Reschedule</h3>
                                    <p class="text-sm text-gray-500 mt-0.5">Propose a new due date for an installment</p>
                                </div>
                                <svg class="accordion-chevron h-5 w-5 text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div class="accordion-content hidden px-6 pb-6">
                                <div class="pt-4">
                                    @if($reschedulableInstallments->isEmpty())
                                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                                            All installments are paid. No reschedule requests available.
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('students.reschedules.store', $student) }}" class="space-y-4">
                                            @csrf
                                            <div class="space-y-2">
                                                <x-input-label for="reschedule_installment_id" value="Select Installment *" />
                                                <select id="reschedule_installment_id" name="installment_id" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" required>
                                                    <option value="">Choose installment</option>
                                                    @foreach($reschedulableInstallments as $installment)
                                                        @php
                                                            $outstandingAmount = max($installment->amount - $installment->paid_amount, 0);
                                                        @endphp
                                                        <option value="{{ $installment->id }}" @selected(old('installment_id') == $installment->id)>
                                                            #{{ $installment->installment_number }} &middot; Due {{ $installment->due_date->format('d M Y') }} &middot; ₹{{ number_format($outstandingAmount, 2) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('installment_id')" class="mt-2" />
                                            </div>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label for="new_due_date" value="Proposed Due Date *" />
                                                    <x-text-input id="new_due_date" name="new_due_date" type="date" class="mt-1 block w-full" value="{{ old('new_due_date') }}" required />
                                                    <x-input-error :messages="$errors->get('new_due_date')" class="mt-2" />
                                                </div>
                                                <div class="text-xs text-gray-500 sm:pt-6">
                                                    Choose a future date that works for the guardian. Admin will review before finalizing.
                                                </div>
                                            </div>

                                            <div>
                                                <x-input-label for="reason" value="Reason *" />
                                                <textarea id="reason" name="reason" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500" placeholder="Explain why this reschedule is needed" required>{{ old('reason') }}</textarea>
                                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                            </div>

                                            <div class="flex items-center justify-end">
                                                <x-primary-button>Submit Request</x-primary-button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Request Discount Accordion -->
                        <div class="accordion-item rounded-2xl border border-slate-100 bg-white shadow-sm overflow-hidden">
                            <button type="button" class="accordion-header w-full px-6 py-4 flex items-center justify-between text-left hover:bg-slate-50 transition-colors duration-150" data-accordion="request-discount">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900">Request Discount</h3>
                                    <p class="text-sm text-gray-500 mt-0.5">Submit a discount request for admin review</p>
                                </div>
                                <svg class="accordion-chevron h-5 w-5 text-gray-400 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            @php
                                $hasDiscountErrors = $errors->has('installment_id') || $errors->has('amount') || $errors->has('reason') || $errors->has('document');
                                // Get unpaid installments for discount selection (same as reschedulable)
                                $discountableInstallments = $reschedulableInstallments;
                            @endphp
                            <div class="accordion-content px-6 pb-6 {{ $hasDiscountErrors ? '' : 'hidden' }}" id="discount-accordion-content">
                                <div class="pt-4">
                                    @if($hasDiscountErrors)
                                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3">
                                            <p class="text-sm font-medium text-red-800">⚠ Please fix the errors below to submit your discount request.</p>
                                        </div>
                                    @endif
                                    
                                    <form method="POST" action="{{ route('students.discounts.store', $student) }}" enctype="multipart/form-data" class="space-y-4" id="discount-form">
                                        @csrf
                                        
                                        <!-- Step 1: Select Installment -->
                                        <div class="rounded-lg border-2 border-indigo-200 bg-indigo-50 p-4">
                                            <x-input-label for="discount_installment_id" value="Select Installment *" class="text-base font-semibold text-indigo-900" />
                                            <select id="discount_installment_id" name="installment_id" class="mt-2 block w-full rounded-lg border-indigo-300 bg-white text-base font-medium focus:border-indigo-500 focus:ring-indigo-500 {{ $errors->has('installment_id') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : '' }}" required>
                                                <option value="">-- Select an installment --</option>
                                                @foreach($discountableInstallments as $installment)
                                                    @php
                                                        $unpaidAmount = $installment->amount - $installment->paid_amount;
                                                        // Calculate existing approved discounts on this installment
                                                        $existingDiscounts = $student->discounts()
                                                            ->where('installment_id', $installment->id)
                                                            ->where('status', 'approved')
                                                            ->sum('amount');
                                                        $availableForDiscount = $unpaidAmount - $existingDiscounts;
                                                    @endphp
                                                    <option value="{{ $installment->id }}" 
                                                        data-installment-amount="{{ $installment->amount }}"
                                                        data-paid-amount="{{ $installment->paid_amount }}"
                                                        data-unpaid-amount="{{ $unpaidAmount }}"
                                                        data-existing-discounts="{{ $existingDiscounts }}"
                                                        data-available-for-discount="{{ $availableForDiscount }}"
                                                        @selected(old('installment_id') == $installment->id)>
                                                        Installment #{{ $installment->installment_number }} &mdash; Due {{ $installment->due_date->format('d M Y') }} &middot; Amount: ₹{{ number_format($installment->amount, 2) }} &middot; Outstanding: ₹{{ number_format($unpaidAmount, 2) }}
                                                        @if($existingDiscounts > 0)
                                                            (Discounts: ₹{{ number_format($existingDiscounts, 2) }})
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                            <x-input-error :messages="$errors->get('installment_id')" class="mt-2" />
                                            <p class="mt-2 text-sm text-indigo-700">Select the installment for which you want to request a discount.</p>
                                        </div>

                                        <!-- Step 2: Discount Amount (shown after installment selection) -->
                                        <div id="discount-amount-section" class="hidden rounded-lg border-2 border-emerald-200 bg-emerald-50 p-4">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <x-input-label for="discount_amount" value="Discount Amount (₹) *" class="text-base font-semibold text-emerald-900" />
                                                    <x-text-input id="discount_amount" name="amount" type="number" min="0.01" step="0.01" class="mt-2 block w-full rounded-lg border-emerald-300 bg-white text-base font-medium focus:border-emerald-500 focus:ring-emerald-500 {{ $errors->has('amount') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : '' }}" value="{{ old('amount') }}" required />
                                                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                                    <p class="mt-2 text-sm text-emerald-700">
                                                        Installment amount: ₹<span id="selected-installment-amount">0.00</span><br>
                                                        Outstanding: ₹<span id="selected-unpaid-amount">0.00</span><br>
                                                        <span id="existing-discounts-display" class="hidden">Existing discounts: ₹<span id="selected-existing-discounts">0.00</span><br></span>
                                                        Maximum discount allowed: ₹<span id="max-discount-amount" class="font-semibold">0.00</span>
                                                    </p>
                                                </div>
                                                <div>
                                                    <x-input-label for="discount_document" value="Supporting Document" class="text-base font-semibold text-emerald-900" />
                                                    <input id="discount_document" name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-2 block w-full text-sm text-gray-600 border border-emerald-300 rounded-lg p-2 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100" />
                                                    <p class="mt-2 text-sm text-emerald-700">Optional. Max size 2MB. Formats: PDF, JPG, JPEG, PNG</p>
                                                    <x-input-error :messages="$errors->get('document')" class="mt-2" />
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Step 3: Reason (shown after installment selection) -->
                                        <div id="discount-reason-section" class="hidden">
                                            <div>
                                                <x-input-label for="discount_reason" value="Reason *" />
                                                <textarea id="discount_reason" name="reason" rows="4" class="mt-1 block w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 {{ $errors->has('reason') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : '' }}" placeholder="Explain why this discount is requested for the selected installment (minimum 10 characters required)" required minlength="10" maxlength="500">{{ old('reason') }}</textarea>
                                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                                <p class="mt-1 text-xs">
                                                    <span id="reason-char-count" class="font-medium">{{ strlen(old('reason', '')) }}</span>/500 characters 
                                                    <span id="reason-min-warning" class="text-red-600 {{ strlen(old('reason', '')) >= 10 ? 'hidden' : '' }}">(minimum 10 characters required)</span>
                                                    <span id="reason-ok" class="text-green-600 {{ strlen(old('reason', '')) >= 10 ? '' : 'hidden' }}">✓</span>
                                                </p>
                                            </div>
                                        </div>

                                        <script>
                                            // Installment selection handler for discount form
                                            (function() {
                                                const installmentSelect = document.getElementById('discount_installment_id');
                                                const amountSection = document.getElementById('discount-amount-section');
                                                const reasonSection = document.getElementById('discount-reason-section');
                                                const amountInput = document.getElementById('discount_amount');
                                                const maxAmountSpan = document.getElementById('max-discount-amount');
                                                const installmentAmountSpan = document.getElementById('selected-installment-amount');
                                                const unpaidAmountSpan = document.getElementById('selected-unpaid-amount');
                                                const existingDiscountsSpan = document.getElementById('selected-existing-discounts');
                                                const existingDiscountsDisplay = document.getElementById('existing-discounts-display');

                                                if (installmentSelect && amountSection && reasonSection) {
                                                    function handleInstallmentChange() {
                                                        const selectedOption = installmentSelect.options[installmentSelect.selectedIndex];
                                                        
                                                        if (selectedOption.value) {
                                                            const installmentAmount = parseFloat(selectedOption.dataset.installmentAmount || 0);
                                                            const paidAmount = parseFloat(selectedOption.dataset.paidAmount || 0);
                                                            const unpaidAmount = parseFloat(selectedOption.dataset.unpaidAmount || 0);
                                                            const existingDiscounts = parseFloat(selectedOption.dataset.existingDiscounts || 0);
                                                            const availableForDiscount = parseFloat(selectedOption.dataset.availableForDiscount || 0);

                                                            // Update display
                                                            installmentAmountSpan.textContent = installmentAmount.toFixed(2);
                                                            unpaidAmountSpan.textContent = unpaidAmount.toFixed(2);
                                                            if (existingDiscounts > 0 && existingDiscountsSpan && existingDiscountsDisplay) {
                                                                existingDiscountsSpan.textContent = existingDiscounts.toFixed(2);
                                                                existingDiscountsDisplay.classList.remove('hidden');
                                                            } else if (existingDiscountsDisplay) {
                                                                existingDiscountsDisplay.classList.add('hidden');
                                                            }
                                                            maxAmountSpan.textContent = availableForDiscount.toFixed(2);

                                                            // Set max attribute on amount input
                                                            amountInput.setAttribute('max', availableForDiscount.toFixed(2));

                                                            // Show amount and reason sections
                                                            amountSection.classList.remove('hidden');
                                                            reasonSection.classList.remove('hidden');
                                                        } else {
                                                            // Hide sections if no installment selected
                                                            amountSection.classList.add('hidden');
                                                            reasonSection.classList.add('hidden');
                                                            amountInput.value = '';
                                                        }
                                                    }

                                                    installmentSelect.addEventListener('change', handleInstallmentChange);
                                                    
                                                    // Initialize on page load if old value exists
                                                    if (installmentSelect.value) {
                                                        handleInstallmentChange();
                                                    }
                                                }
                                            })();

                                            // Character count for reason field
                                            (function() {
                                                const reasonField = document.getElementById('discount_reason');
                                                const charCount = document.getElementById('reason-char-count');
                                                const minWarning = document.getElementById('reason-min-warning');
                                                const okIndicator = document.getElementById('reason-ok');
                                                
                                                if (reasonField && charCount && minWarning && okIndicator) {
                                                    function updateCharCount() {
                                                        const length = reasonField.value.length;
                                                        charCount.textContent = length;
                                                        
                                                        if (length < 10) {
                                                            charCount.classList.add('text-red-600');
                                                            charCount.classList.remove('text-gray-500');
                                                            minWarning.classList.remove('hidden');
                                                            okIndicator.classList.add('hidden');
                                                            reasonField.classList.add('border-red-300');
                                                            reasonField.classList.remove('border-green-300');
                                                        } else {
                                                            charCount.classList.remove('text-red-600');
                                                            charCount.classList.add('text-gray-500');
                                                            minWarning.classList.add('hidden');
                                                            okIndicator.classList.remove('hidden');
                                                            reasonField.classList.remove('border-red-300');
                                                            reasonField.classList.add('border-green-300');
                                                        }
                                                    }
                                                    
                                                    reasonField.addEventListener('input', updateCharCount);
                                                    reasonField.addEventListener('change', updateCharCount);
                                                    
                                                    // Initialize on page load
                                                    updateCharCount();
                                                }
                                            })();
                                        </script>

                                        <div class="flex items-center justify-end">
                                            <x-primary-button>Submit Discount Request</x-primary-button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        // Accordion functionality - Simple and robust
                        document.addEventListener('DOMContentLoaded', function() {
                            console.log('Initializing accordion...');
                            
                            // Wait a bit to ensure all DOM is ready
                            setTimeout(function() {
                                const accordionHeaders = document.querySelectorAll('.accordion-header');
                                console.log('Found', accordionHeaders.length, 'accordion headers');
                                
                                if (accordionHeaders.length === 0) {
                                    console.error('No accordion headers found!');
                                    return;
                                }
                                
                                // First, set up initial state (open if has errors)
                                accordionHeaders.forEach(function(header) {
                                    const targetContent = header.nextElementSibling;
                                    const chevron = header.querySelector('.accordion-chevron');
                                    
                                    if (targetContent && !targetContent.classList.contains('hidden')) {
                                        if (chevron) {
                                            chevron.classList.add('rotate-180');
                                        }
                                    }
                                });
                                
                                // Add click listeners
                                accordionHeaders.forEach(function(header) {
                                    header.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        e.stopImmediatePropagation();
                                        
                                        console.log('Accordion clicked');
                                        
                                        const targetContent = this.nextElementSibling;
                                        const chevron = this.querySelector('.accordion-chevron');
                                        
                                        if (!targetContent) {
                                            console.error('No content found for accordion');
                                            return;
                                        }
                                        
                                        if (!chevron) {
                                            console.error('No chevron found for accordion');
                                            return;
                                        }
                                        
                                        const isOpen = !targetContent.classList.contains('hidden');
                                        
                                        // Close all other accordions
                                        accordionHeaders.forEach(function(h) {
                                            if (h === header) return; // Skip clicked one
                                            
                                            const content = h.nextElementSibling;
                                            const chev = h.querySelector('.accordion-chevron');
                                            
                                            if (content && chev) {
                                                content.classList.add('hidden');
                                                chev.classList.remove('rotate-180');
                                            }
                                        });
                                        
                                        // Toggle clicked accordion
                                        if (isOpen) {
                                            targetContent.classList.add('hidden');
                                            chevron.classList.remove('rotate-180');
                                            console.log('Accordion closed');
                                        } else {
                                            targetContent.classList.remove('hidden');
                                            chevron.classList.add('rotate-180');
                                            console.log('Accordion opened');
                                        }
                                    }, false);
                                    
                                    console.log('Added click listener to accordion');
                                });
                                
                                console.log('Accordion initialized successfully!');
                            }, 200);
                        });

                        // Payment form JavaScript
                        document.addEventListener('DOMContentLoaded', function() {
                                // Payment type selection handler
                                const paymentTypeSelect = document.getElementById('payment_type');
                                const tuitionSection = document.getElementById('tuition-payment-section');
                                const miscSection = document.getElementById('misc-payment-section');
                                const installmentSelect = document.getElementById('installment_id');
                                const miscChargeSelect = document.getElementById('misc_charge_id');
                                const paymentDetailsSection = document.getElementById('payment-details-section');
                                const amountReceived = document.getElementById('amount_received');
                                
                                // Handle payment type change
                                function handlePaymentTypeChange() {
                                    const paymentType = paymentTypeSelect?.value;
                                    const remainingSection = document.getElementById('remaining-installment-section');
                                    const remainingDateInput = document.getElementById('remaining_installment_due_date');
                                    const createRemainingCheckbox = document.getElementById('create_remaining_installment');
                                    
                                    const penaltySection = document.getElementById('penalty-payment-section');
                                    const penaltyTypeSelect = document.getElementById('penalty_type');
                                    const lateFeeSection = document.getElementById('late-fee-penalty-section');
                                    const gstPenaltySection = document.getElementById('gst-penalty-section');
                                    const penaltyIdSelect = document.getElementById('penalty_id');
                                    const gstPenaltyChargeIdSelect = document.getElementById('gst_penalty_charge_id');
                                    
                                    // Get misc charge select fresh each time (in case it was hidden before)
                                    const miscChargeSelectEl = document.getElementById('misc_charge_id');
                                    
                                    // Hide all sections initially
                                    if (tuitionSection) tuitionSection.classList.add('hidden');
                                    if (miscSection) miscSection.classList.add('hidden');
                                    if (penaltySection) penaltySection.classList.add('hidden');
                                    if (paymentDetailsSection) paymentDetailsSection.classList.add('hidden');
                                    
                                    // Clear selections
                                    if (installmentSelect) {
                                        installmentSelect.value = '';
                                        installmentSelect.required = false;
                                    }
                                    if (miscChargeSelectEl) {
                                        miscChargeSelectEl.value = '';
                                        miscChargeSelectEl.required = false;
                                    }
                                    if (penaltyTypeSelect) {
                                        penaltyTypeSelect.value = '';
                                        penaltyTypeSelect.required = false;
                                    }
                                    if (penaltyIdSelect) {
                                        penaltyIdSelect.value = '';
                                        penaltyIdSelect.required = false;
                                    }
                                    if (gstPenaltyChargeIdSelect) {
                                        gstPenaltyChargeIdSelect.value = '';
                                        gstPenaltyChargeIdSelect.required = false;
                                    }
                                    if (lateFeeSection) lateFeeSection.classList.add('hidden');
                                    if (gstPenaltySection) gstPenaltySection.classList.add('hidden');
                                    
                                    if (paymentType === 'tuition') {
                                        // Show tuition section
                                        if (tuitionSection) tuitionSection.classList.remove('hidden');
                                        if (installmentSelect) installmentSelect.required = true;
                                        // Re-enable remaining date field for tuition (controlled by checkbox)
                                        if (remainingDateInput) {
                                            remainingDateInput.disabled = false;
                                        }
                                    } else if (paymentType === 'miscellaneous') {
                                        // Show miscellaneous section
                                        if (miscSection) {
                                            miscSection.classList.remove('hidden');
                                            console.log('Miscellaneous section shown');
                                        } else {
                                            console.error('Misc section element not found!');
                                        }
                                        // Ensure misc charge select exists and attach listener if needed
                                        if (miscChargeSelectEl) {
                                            miscChargeSelectEl.required = true;
                                            console.log('Misc charge select found, options:', miscChargeSelectEl.options.length);
                                            // Re-attach listener in case it wasn't attached before
                                            if (!miscChargeSelectEl.hasAttribute('data-listener-added')) {
                                                miscChargeSelectEl.addEventListener('change', function() {
                                                    console.log('Misc charge changed:', miscChargeSelectEl.value);
                                                    togglePaymentDetailsSection();
                                                });
                                                miscChargeSelectEl.setAttribute('data-listener-added', 'true');
                                            }
                                        } else {
                                            console.error('Misc charge select element not found!');
                                        }
                                        // Hide and disable remaining installment section for misc charges
                                        if (remainingSection) {
                                            remainingSection.classList.add('hidden');
                                        }
                                        if (remainingDateInput) {
                                            remainingDateInput.removeAttribute('required');
                                            remainingDateInput.disabled = true; // Disable to prevent validation
                                            remainingDateInput.value = ''; // Clear value
                                        }
                                        if (createRemainingCheckbox) {
                                            createRemainingCheckbox.checked = false;
                                        }
                                        
                                        // Ensure the accordion content is visible (open the accordion if closed)
                                        // The miscSection is inside the accordion-content, so we need to make sure the parent is visible
                                        if (miscSection) {
                                            const accordionContent = miscSection.closest('.accordion-content');
                                            if (accordionContent) {
                                                // Remove hidden class from accordion content if it's there
                                                accordionContent.classList.remove('hidden');
                                                // Find the accordion header (button) to update chevron
                                                const accordionHeader = accordionContent.previousElementSibling;
                                                if (accordionHeader && accordionHeader.classList.contains('accordion-header')) {
                                                    const chevron = accordionHeader.querySelector('.accordion-chevron');
                                                    if (chevron) {
                                                        chevron.classList.add('rotate-180');
                                                    }
                                                }
                                                console.log('Accordion opened for miscellaneous charges');
                                            }
                                        }
                                    } else if (paymentType === 'penalty') {
                                        // Show penalty section
                                        if (penaltySection) {
                                            penaltySection.classList.remove('hidden');
                                            console.log('Penalty section shown');
                                        } else {
                                            console.error('Penalty section element not found!');
                                        }
                                        if (penaltyTypeSelect) {
                                            penaltyTypeSelect.required = true;
                                        }
                                        // Hide and disable remaining installment section for penalties
                                        if (remainingSection) {
                                            remainingSection.classList.add('hidden');
                                        }
                                        if (remainingDateInput) {
                                            remainingDateInput.removeAttribute('required');
                                            remainingDateInput.disabled = true; // Disable to prevent validation
                                            remainingDateInput.value = ''; // Clear value
                                        }
                                        if (createRemainingCheckbox) {
                                            createRemainingCheckbox.checked = false;
                                        }
                                    }
                                }
                                
                                // Handle installment selection
                                function togglePaymentDetailsSection() {
                                    const paymentDetailsSectionEl = document.getElementById('payment-details-section');
                                    if (!paymentDetailsSectionEl) return;
                                    
                                    // Get elements fresh each time (in case they were hidden/shown)
                                    const installmentSelectEl = document.getElementById('installment_id');
                                    const miscChargeSelectEl = document.getElementById('misc_charge_id');
                                    const penaltyIdSelectEl = document.getElementById('penalty_id');
                                    const manualPenaltyIdSelectEl = document.getElementById('manual_penalty_id');
                                    const gstPenaltyChargeIdSelectEl = document.getElementById('gst_penalty_charge_id');
                                    const amountReceivedEl = document.getElementById('amount_received');
                                    
                                    const selectedInstallment = installmentSelectEl?.value;
                                    const selectedMiscCharge = miscChargeSelectEl?.value;
                                    const selectedPenalty = penaltyIdSelectEl?.value;
                                    const selectedManualPenalty = manualPenaltyIdSelectEl?.value;
                                    const selectedGstPenalty = gstPenaltyChargeIdSelectEl?.value;
                                    
                                    if ((selectedInstallment && selectedInstallment !== '') || 
                                        (selectedMiscCharge && selectedMiscCharge !== '') ||
                                        (selectedPenalty && selectedPenalty !== '') ||
                                        (selectedManualPenalty && selectedManualPenalty !== '') ||
                                        (selectedGstPenalty && selectedGstPenalty !== '')) {
                                        // Show payment details section
                                        paymentDetailsSectionEl.classList.remove('hidden');
                                        
                                        // Auto-populate amount for misc charges (full payment only)
                                        const remainingSection = document.getElementById('remaining-installment-section');
                                        const remainingDateInput = document.getElementById('remaining_installment_due_date');
                                        const createRemainingCheckbox = document.getElementById('create_remaining_installment');
                                        
                                        if (selectedMiscCharge && miscChargeSelectEl) {
                                            const selectedOption = miscChargeSelectEl.options[miscChargeSelectEl.selectedIndex];
                                            const chargeAmount = parseFloat(selectedOption?.dataset.chargeAmount || 0);
                                            if (chargeAmount > 0 && amountReceivedEl) {
                                                // Check if credit balance is being used
                                                const useCreditCheckbox = document.getElementById('use_credit_balance');
                                                const useCredit = useCreditCheckbox?.checked || false;
                                                let creditBalance = 0;
                                                
                                                // Get credit balance from the page (if available)
                                                @if($creditBalance > 0)
                                                creditBalance = {{ number_format($creditBalance, 2, '.', '') }};
                                                @endif
                                                
                                                // Calculate remaining amount after credit
                                                let remainingAmount = chargeAmount;
                                                if (useCredit && creditBalance > 0) {
                                                    const creditToUse = Math.min(creditBalance, chargeAmount);
                                                    remainingAmount = Math.max(0, chargeAmount - creditToUse);
                                                }
                                                
                                                // Set the amount field to remaining amount (0 if fully covered)
                                                amountReceivedEl.value = remainingAmount.toFixed(2);
                                                amountReceivedEl.readOnly = false; // Allow editing, but show calculated amount
                                                
                                                // If fully covered by credit, make it clear
                                                if (remainingAmount === 0 && useCredit) {
                                                    amountReceivedEl.classList.add('bg-emerald-50', 'border-emerald-300');
                                                    amountReceivedEl.placeholder = 'Credit fully covers this charge';
                                                } else {
                                                    amountReceivedEl.classList.remove('bg-emerald-50', 'border-emerald-300');
                                                    amountReceivedEl.placeholder = '';
                                                }
                                            }
                                            // Hide remaining installment section for misc charges (full payment only)
                                            if (remainingSection) {
                                                remainingSection.classList.add('hidden');
                                            }
                                            // Remove required attribute from remaining date field for misc charges
                                            if (remainingDateInput) {
                                                remainingDateInput.removeAttribute('required');
                                                remainingDateInput.disabled = true; // Disable to prevent validation
                                            }
                                            // Uncheck the checkbox if it was checked
                                            if (createRemainingCheckbox) {
                                                createRemainingCheckbox.checked = false;
                                            }
                                        } else if (selectedPenalty && penaltyIdSelectEl) {
                                            // Handle late fee penalty payment
                                            const selectedOption = penaltyIdSelectEl.options[penaltyIdSelectEl.selectedIndex];
                                            const penaltyAmount = parseFloat(selectedOption?.dataset.penaltyAmount || 0);
                                            if (penaltyAmount > 0 && amountReceivedEl) {
                                                amountReceivedEl.value = penaltyAmount.toFixed(2);
                                                amountReceivedEl.readOnly = false;
                                            }
                                            // Hide remaining installment section for penalties (full payment only)
                                            if (remainingSection) {
                                                remainingSection.classList.add('hidden');
                                            }
                                            if (remainingDateInput) {
                                                remainingDateInput.removeAttribute('required');
                                                remainingDateInput.disabled = true;
                                            }
                                            if (createRemainingCheckbox) {
                                                createRemainingCheckbox.checked = false;
                                            }
                                        } else if (selectedManualPenalty && manualPenaltyIdSelectEl) {
                                            // Handle manual penalty payment
                                            const selectedOption = manualPenaltyIdSelectEl.options[manualPenaltyIdSelectEl.selectedIndex];
                                            const penaltyAmount = parseFloat(selectedOption?.dataset.penaltyAmount || 0);
                                            if (penaltyAmount > 0 && amountReceivedEl) {
                                                amountReceivedEl.value = penaltyAmount.toFixed(2);
                                                amountReceivedEl.readOnly = false;
                                            }
                                            // Hide remaining installment section for penalties (full payment only)
                                            if (remainingSection) {
                                                remainingSection.classList.add('hidden');
                                            }
                                            if (remainingDateInput) {
                                                remainingDateInput.removeAttribute('required');
                                                remainingDateInput.disabled = true;
                                            }
                                            if (createRemainingCheckbox) {
                                                createRemainingCheckbox.checked = false;
                                            }
                                        } else if (selectedGstPenalty && gstPenaltyChargeIdSelectEl) {
                                            // Handle GST penalty payment
                                            const selectedOption = gstPenaltyChargeIdSelectEl.options[gstPenaltyChargeIdSelectEl.selectedIndex];
                                            const penaltyAmount = parseFloat(selectedOption?.dataset.penaltyAmount || 0);
                                            if (penaltyAmount > 0 && amountReceivedEl) {
                                                amountReceivedEl.value = penaltyAmount.toFixed(2);
                                                amountReceivedEl.readOnly = false;
                                            }
                                            // Hide remaining installment section for penalties (full payment only)
                                            if (remainingSection) {
                                                remainingSection.classList.add('hidden');
                                            }
                                            if (remainingDateInput) {
                                                remainingDateInput.removeAttribute('required');
                                                remainingDateInput.disabled = true;
                                            }
                                            if (createRemainingCheckbox) {
                                                createRemainingCheckbox.checked = false;
                                            }
                                        } else if (amountReceivedEl) {
                                            amountReceivedEl.readOnly = false; // Allow editing for tuition payments
                                            amountReceivedEl.classList.remove('bg-emerald-50', 'border-emerald-300');
                                            amountReceivedEl.placeholder = '';
                                            // Re-enable remaining date field for tuition payments (will be controlled by checkbox)
                                            if (remainingDateInput) {
                                                remainingDateInput.disabled = false;
                                            }
                                        }
                                        
                                        // Smooth scroll to payment details section
                                        setTimeout(() => {
                                            paymentDetailsSectionEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                        }, 100);
                                    } else {
                                        // Hide payment details section
                                        paymentDetailsSectionEl.classList.add('hidden');
                                        const amountReceivedEl = document.getElementById('amount_received');
                                        if (amountReceivedEl) {
                                            amountReceivedEl.value = '';
                                            amountReceivedEl.readOnly = false;
                                        }
                                        // Reset remaining installment fields
                                        const remainingSection = document.getElementById('remaining-installment-section');
                                        const remainingDateInput = document.getElementById('remaining_installment_due_date');
                                        const createRemainingCheckbox = document.getElementById('create_remaining_installment');
                                        if (remainingSection) {
                                            remainingSection.classList.add('hidden');
                                        }
                                        if (remainingDateInput) {
                                            remainingDateInput.removeAttribute('required');
                                            remainingDateInput.disabled = true;
                                            remainingDateInput.value = '';
                                        }
                                        if (createRemainingCheckbox) {
                                            createRemainingCheckbox.checked = false;
                                        }
                                    }
                                }
                                
                                // Add event listeners
                                if (paymentTypeSelect) {
                                    paymentTypeSelect.addEventListener('change', function() {
                                        handlePaymentTypeChange();
                                        // Hide GST penalty warning for non-tuition payments
                                        @if($onlineAllowance > 0)
                                        const warningDiv = document.getElementById('gst-penalty-warning');
                                        const paymentType = paymentTypeSelect?.value;
                                        if (paymentType !== 'tuition' && warningDiv) {
                                            warningDiv.classList.add('hidden');
                                        } else if (paymentType === 'tuition' && typeof checkOnlineAllowance === 'function') {
                                            // Re-check for tuition payments
                                            setTimeout(function() {
                                                checkOnlineAllowance();
                                            }, 100);
                                        }
                                        @endif
                                    });
                                    // Check on page load
                                    handlePaymentTypeChange();
                                }
                                
                                if (installmentSelect) {
                                    installmentSelect.addEventListener('change', togglePaymentDetailsSection);
                                    if (installmentSelect.value && installmentSelect.value !== '') {
                                        togglePaymentDetailsSection();
                                    }
                                }
                                
                                // Attach event listener to misc charge select - use a timeout to ensure element exists
                                setTimeout(function() {
                                    const miscChargeSelectEl = document.getElementById('misc_charge_id');
                                    if (miscChargeSelectEl) {
                                        miscChargeSelectEl.addEventListener('change', function() {
                                            console.log('Misc charge changed:', miscChargeSelectEl.value);
                                            togglePaymentDetailsSection();
                                        });
                                        // Check if there's a pre-selected value
                                        if (miscChargeSelectEl.value && miscChargeSelectEl.value !== '') {
                                            togglePaymentDetailsSection();
                                        }
                                    }
                                }, 100);
                                
                                // Handle penalty type change (late fee vs GST vs manual)
                                function handlePenaltyTypeChange() {
                                    const penaltyTypeSelect = document.getElementById('penalty_type');
                                    const lateFeeSection = document.getElementById('late-fee-penalty-section');
                                    const gstPenaltySection = document.getElementById('gst-penalty-section');
                                    const manualPenaltySection = document.getElementById('manual-penalty-section');
                                    const penaltyIdSelect = document.getElementById('penalty_id');
                                    const manualPenaltyIdSelect = document.getElementById('manual_penalty_id');
                                    const gstPenaltyChargeIdSelect = document.getElementById('gst_penalty_charge_id');
                                    
                                    const penaltyType = penaltyTypeSelect?.value;
                                    
                                    // Hide all penalty sub-sections
                                    if (lateFeeSection) lateFeeSection.classList.add('hidden');
                                    if (gstPenaltySection) gstPenaltySection.classList.add('hidden');
                                    if (manualPenaltySection) manualPenaltySection.classList.add('hidden');
                                    
                                    // Hide payment details section until a specific penalty is selected
                                    if (paymentDetailsSection) paymentDetailsSection.classList.add('hidden');
                                    
                                    // Clear selections
                                    if (penaltyIdSelect) {
                                        penaltyIdSelect.value = '';
                                        penaltyIdSelect.required = false;
                                    }
                                    if (manualPenaltyIdSelect) {
                                        manualPenaltyIdSelect.value = '';
                                        manualPenaltyIdSelect.required = false;
                                    }
                                    if (gstPenaltyChargeIdSelect) {
                                        gstPenaltyChargeIdSelect.value = '';
                                        gstPenaltyChargeIdSelect.required = false;
                                    }
                                    
                                    if (penaltyType === 'late_fee') {
                                        // Show late fee penalty section
                                        if (lateFeeSection) lateFeeSection.classList.remove('hidden');
                                        if (penaltyIdSelect) {
                                            penaltyIdSelect.required = true;
                                            // Add event listener if not already added
                                            if (!penaltyIdSelect.hasAttribute('data-listener-added')) {
                                                penaltyIdSelect.addEventListener('change', function() {
                                                    console.log('Penalty selected:', penaltyIdSelect.value);
                                                    togglePaymentDetailsSection();
                                                });
                                                penaltyIdSelect.setAttribute('data-listener-added', 'true');
                                            }
                                        }
                                    } else if (penaltyType === 'gst') {
                                        // Show GST penalty section
                                        if (gstPenaltySection) gstPenaltySection.classList.remove('hidden');
                                        if (gstPenaltyChargeIdSelect) {
                                            gstPenaltyChargeIdSelect.required = true;
                                            // Add event listener if not already added
                                            if (!gstPenaltyChargeIdSelect.hasAttribute('data-listener-added')) {
                                                gstPenaltyChargeIdSelect.addEventListener('change', function() {
                                                    console.log('GST penalty selected:', gstPenaltyChargeIdSelect.value);
                                                    togglePaymentDetailsSection();
                                                });
                                                gstPenaltyChargeIdSelect.setAttribute('data-listener-added', 'true');
                                            }
                                        }
                                    } else if (penaltyType === 'manual') {
                                        // Show manual penalty section
                                        if (manualPenaltySection) manualPenaltySection.classList.remove('hidden');
                                        if (manualPenaltyIdSelect) {
                                            manualPenaltyIdSelect.required = true;
                                            // Add event listener if not already added
                                            if (!manualPenaltyIdSelect.hasAttribute('data-listener-added')) {
                                                manualPenaltyIdSelect.addEventListener('change', function() {
                                                    console.log('Manual penalty selected:', manualPenaltyIdSelect.value);
                                                    togglePaymentDetailsSection();
                                                });
                                                manualPenaltyIdSelect.setAttribute('data-listener-added', 'true');
                                            }
                                        }
                                    }
                                }
                                
                                // Add event listener for penalty type selection - use a timeout to ensure element exists
                                setTimeout(function() {
                                    const penaltyTypeSelectEl = document.getElementById('penalty_type');
                                    if (penaltyTypeSelectEl) {
                                        penaltyTypeSelectEl.addEventListener('change', handlePenaltyTypeChange);
                                    }
                                }, 100);
                                
                                const paymentMode = document.getElementById('payment_mode');
                                // amountReceived is already declared at line 1352, reuse it
                                
                                // Field containers
                                const cashFields = document.getElementById('cash-fields');
                                const onlineFields = document.getElementById('online-fields');
                                const chequeFields = document.getElementById('cheque-fields');
                                
                                // GST Penalty Warning
                                @if($onlineAllowance > 0)
                                const warningDiv = document.getElementById('gst-penalty-warning');
                                const excessAmountSpan = document.getElementById('excess-amount');
                                const penaltyAmountSpan = document.getElementById('penalty-amount');
                                const excessBaseSpan = document.getElementById('excess-base');
                                const onlineAllowance = {{ $onlineAllowance }};
                                const currentOnlineTotal = {{ $currentOnlineTotal }};
                                const gstRate = {{ \App\Models\Setting::getValue('penalty.gst_percentage', config('fees.gst_percentage', 18.0)) }};
                                @endif

                                function syncValuesToHidden() {
                                    if (!paymentMode) {
                                        console.warn('paymentMode element not found');
                                        return;
                                    }
                                    
                                    const mode = paymentMode.value;
                                    const transactionIdField = document.getElementById('transaction_id');
                                    const bankIdField = document.getElementById('bank_id');
                                    const depositedToField = document.getElementById('deposited_to');
                                    
                                    // Voucher number and employee name are common fields with name attributes, submitted directly
                                    // Only sync mode-specific fields (transaction_id, bank_id, deposited_to) to hidden fields
                                    
                                    if (!transactionIdField || !bankIdField || !depositedToField) {
                                        console.warn('Hidden fields not found for syncing');
                                        return;
                                    }
                                    
                                    if (mode === 'cash') {
                                        // Cash mode: Only voucher_number and employee_name are required (submitted directly via name attributes)
                                        transactionIdField.value = '';
                                        bankIdField.value = '';
                                        depositedToField.value = '';
                                    } else if (mode === 'cheque') {
                                        const transactionId = document.getElementById('transaction_id_cheque');
                                        const bankId = document.getElementById('bank_id_cheque');
                                        const depositedTo = document.getElementById('deposited_to_cheque');
                                        if (transactionId) transactionIdField.value = transactionId.value;
                                        if (bankId) bankIdField.value = bankId.value;
                                        if (depositedTo) depositedToField.value = depositedTo.value;
                                    } else if (['upi', 'bank_transfer'].includes(mode)) {
                                        const transactionId = document.getElementById('transaction_id_online');
                                        const bankId = document.getElementById('bank_id_online');
                                        if (transactionId) transactionIdField.value = transactionId.value;
                                        if (bankId) bankIdField.value = bankId.value;
                                        depositedToField.value = '';
                                    }
                                }

                                function togglePaymentFields() {
                                    if (!paymentMode) {
                                        console.warn('paymentMode element not found');
                                        return;
                                    }
                                    
                                    const mode = paymentMode.value;
                                    
                                    // Hide all fields first
                                    if (cashFields) cashFields.classList.add('hidden');
                                    if (onlineFields) onlineFields.classList.add('hidden');
                                    if (chequeFields) chequeFields.classList.add('hidden');

                                    // Show fields based on payment mode
                                    if (mode === 'cash') {
                                        if (cashFields) cashFields.classList.remove('hidden');
                                    } else if (mode === 'cheque') {
                                        if (chequeFields) chequeFields.classList.remove('hidden');
                                    } else if (['upi', 'bank_transfer'].includes(mode)) {
                                        if (onlineFields) onlineFields.classList.remove('hidden');
                                    }

                                    // Sync values to hidden fields
                                    try {
                                        syncValuesToHidden();
                                    } catch (error) {
                                        console.error('Error syncing values:', error);
                                    }

                                    @if($onlineAllowance > 0)
                                    // Check online allowance for GST penalty warning (ONLY for tuition payments)
                                    // Miscellaneous and Penalty payments are NOT bound by online allowance
                                    const paymentTypeEl = document.getElementById('payment_type');
                                    const paymentType = paymentTypeEl?.value;
                                    if (paymentType === 'tuition' && typeof checkOnlineAllowance === 'function') {
                                        try {
                                            checkOnlineAllowance();
                                        } catch (error) {
                                            console.error('Error checking online allowance:', error);
                                        }
                                    } else {
                                        // Hide warning for non-tuition payments
                                        if (warningDiv) warningDiv.classList.add('hidden');
                                    }
                                    @endif
                                }

                                // Sync values on input change and form submit
                                const paymentForm = document.getElementById('payment-form');
                                if (paymentForm) {
                                    // Sync on form submit (before validation)
                                    paymentForm.addEventListener('submit', function(e) {
                                        try {
                                            syncValuesToHidden();
                                        } catch (error) {
                                            console.error('Error syncing values before form submission:', error);
                                            // Don't prevent form submission if sync fails
                                        }
                                    });

                                    // Sync on field change (mode-specific fields only)
                                    // Voucher number and employee name are common fields with name attributes, no need to sync
                                    ['transaction_id_online', 'bank_id_online', 'transaction_id_cheque', 'bank_id_cheque', 'deposited_to_cheque'].forEach(function(fieldId) {
                                        const field = document.getElementById(fieldId);
                                        if (field) {
                                            field.addEventListener('input', syncValuesToHidden);
                                            field.addEventListener('change', syncValuesToHidden);
                                        }
                                    });
                                }

                                @if($onlineAllowance > 0)
                                function checkOnlineAllowance() {
                                    if (!paymentMode || !amountReceived) {
                                        return;
                                    }
                                    
                                    // CRITICAL: Only check online allowance for TUITION payments
                                    // Miscellaneous and Penalty payments are NOT bound by online allowance restrictions
                                    const paymentTypeEl = document.getElementById('payment_type');
                                    const paymentType = paymentTypeEl?.value;
                                    
                                    if (paymentType !== 'tuition') {
                                        // Hide warning for non-tuition payments (miscellaneous and penalty)
                                        if (warningDiv) warningDiv.classList.add('hidden');
                                        return;
                                    }
                                    
                                    const mode = paymentMode.value;
                                    const amount = parseFloat(amountReceived.value) || 0;
                                    const offlineModes = ['cash'];
                                    const isOnlineMode = !offlineModes.includes(mode);

                                    if (!isOnlineMode || amount === 0 || !warningDiv) {
                                        if (warningDiv) warningDiv.classList.add('hidden');
                                        return;
                                    }

                                    if (!excessBaseSpan || !excessAmountSpan || !penaltyAmountSpan) {
                                        return;
                                    }

                                    const totalOnline = currentOnlineTotal + amount;
                                    const excess = Math.max(0, totalOnline - onlineAllowance);
                                    const previousExcess = Math.max(0, currentOnlineTotal - onlineAllowance);
                                    const incrementalExcess = Math.max(0, excess - previousExcess);

                                    if (incrementalExcess > 0) {
                                        // Calculate ONLY the GST amount on excess (not excess + GST)
                                        // The excess amount itself is counted as tuition fees
                                        // Only GST on excess is the penalty
                                        const gstPenaltyAmount = incrementalExcess * (gstRate / 100);
                                        excessBaseSpan.textContent = incrementalExcess.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        excessAmountSpan.textContent = incrementalExcess.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        penaltyAmountSpan.textContent = gstPenaltyAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        warningDiv.classList.remove('hidden');
                                    } else {
                                        warningDiv.classList.add('hidden');
                                    }
                                }
                                @endif

                                // NEW FEATURE: Credit Balance and Overpayment Detection
                                @if($creditBalance > 0)
                                const creditBalance = {{ number_format($creditBalance, 2, '.', '') }};
                                const useCreditCheckbox = document.getElementById('use_credit_balance');
                                const creditPreview = document.getElementById('credit-preview');
                                const creditToUseSpan = document.getElementById('credit-to-use');
                                const remainingPaymentSpan = document.getElementById('remaining-payment-amount');
                                const overpaymentNotification = document.getElementById('overpayment-notification');
                                const overpaymentAmountSpan = document.getElementById('overpayment-amount');

                                function updateCreditBalancePreview() {
                                    if (!useCreditCheckbox || !amountReceived || !creditPreview) return;
                                    
                                    const useCredit = useCreditCheckbox.checked;
                                    if (!useCredit) {
                                        creditPreview.classList.add('hidden');
                                        return;
                                    }
                                    
                                    // For miscellaneous payments, use the charge amount if available
                                    // For tuition payments, use the entered amount
                                    // Note: paymentTypeSelect is declared in the DOMContentLoaded block above
                                    const paymentTypeEl = document.getElementById('payment_type');
                                    const paymentType = paymentTypeEl?.value;
                                    let chargeAmount = 0;
                                    
                                    if (paymentType === 'miscellaneous') {
                                        const miscChargeSelect = document.getElementById('misc_charge_id');
                                        if (miscChargeSelect && miscChargeSelect.value) {
                                            const selectedOption = miscChargeSelect.options[miscChargeSelect.selectedIndex];
                                            chargeAmount = parseFloat(selectedOption?.dataset.chargeAmount || 0);
                                        }
                                    }
                                    
                                    // Use charge amount for misc, or entered amount for tuition
                                    const paymentAmount = chargeAmount > 0 ? chargeAmount : (parseFloat(amountReceived.value) || 0);
                                    
                                    if (paymentAmount > 0) {
                                        // Credit can only be used up to the payment amount needed
                                        const creditToUse = Math.min(creditBalance, paymentAmount);
                                        // Remaining payment is what still needs to be paid after credit
                                        const remainingPayment = Math.max(0, paymentAmount - creditToUse);
                                        // Calculate remaining credit after this payment
                                        const creditRemainingAfter = Math.max(0, creditBalance - creditToUse);
                                        
                                        // Update preview display
                                        const paymentAmountNeededSpan = document.getElementById('payment-amount-needed');
                                        if (paymentAmountNeededSpan) paymentAmountNeededSpan.textContent = paymentAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        
                                        if (creditToUseSpan) creditToUseSpan.textContent = creditToUse.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        if (remainingPaymentSpan) remainingPaymentSpan.textContent = remainingPayment.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        
                                        const creditRemainingAfterSpan = document.getElementById('credit-remaining-after');
                                        if (creditRemainingAfterSpan) {
                                            creditRemainingAfterSpan.textContent = '₹' + creditRemainingAfter.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        }
                                        
                                        creditPreview.classList.remove('hidden');
                                    } else {
                                        creditPreview.classList.add('hidden');
                                    }
                                }

                                function checkOverpayment() {
                                    if (!amountReceived || !overpaymentNotification || !overpaymentAmountSpan) return;
                                    
                                    // Note: paymentTypeSelect is declared in the DOMContentLoaded block above
                                    const paymentTypeEl = document.getElementById('payment_type');
                                    const paymentType = paymentTypeEl?.value;
                                    if (paymentType !== 'tuition') {
                                        overpaymentNotification.classList.add('hidden');
                                        return;
                                    }
                                    
                                    const selectedInstallment = installmentSelect?.value;
                                    if (!selectedInstallment) {
                                        overpaymentNotification.classList.add('hidden');
                                        return;
                                    }
                                    
                                    const selectedOption = installmentSelect.options[installmentSelect.selectedIndex];
                                    const installmentAmount = parseFloat(selectedOption?.dataset.installmentAmount || 0);
                                    const paidAmount = parseFloat(selectedOption?.dataset.paidAmount || 0);
                                    const outstandingAmount = installmentAmount - paidAmount;
                                    
                                    const paymentAmount = parseFloat(amountReceived.value) || 0;
                                    const useCredit = useCreditCheckbox?.checked || false;
                                    const creditToUse = useCredit ? Math.min(creditBalance, paymentAmount) : 0;
                                    const actualPaymentAmount = paymentAmount - creditToUse;
                                    
                                    // Calculate total outstanding for all installments (rough estimate)
                                    // For simplicity, we'll check if payment exceeds the selected installment's outstanding
                                    // The backend will handle the full calculation
                                    const excess = Math.max(0, actualPaymentAmount - outstandingAmount);
                                    
                                    if (excess > 0.01) {
                                        overpaymentAmountSpan.textContent = excess.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        overpaymentNotification.classList.remove('hidden');
                                    } else {
                                        overpaymentNotification.classList.add('hidden');
                                    }
                                }

                                // Get payment type select and misc charge select for credit calculation
                                // Note: paymentTypeSelect is already declared above, so we reuse it
                                const miscChargeSelectForCredit = document.getElementById('misc_charge_id');

                                if (useCreditCheckbox) {
                                    useCreditCheckbox.addEventListener('change', function() {
                                        updateCreditBalancePreview();
                                        checkOverpayment();
                                        
                                        // Update amount field for miscellaneous charges when credit is toggled
                                        const paymentTypeEl = document.getElementById('payment_type');
                                        const miscChargeSelect = document.getElementById('misc_charge_id');
                                        if (paymentTypeEl?.value === 'miscellaneous' && miscChargeSelect?.value) {
                                            // Trigger the toggle function to recalculate amount
                                            togglePaymentDetailsSection();
                                        }
                                    });
                                }

                                if (amountReceived) {
                                    amountReceived.addEventListener('input', function() {
                                        updateCreditBalancePreview();
                                        checkOverpayment();
                                        // Check online allowance only for tuition payments
                                        @if($onlineAllowance > 0)
                                        const paymentTypeEl = document.getElementById('payment_type');
                                        const paymentType = paymentTypeEl?.value;
                                        if (paymentType === 'tuition' && typeof checkOnlineAllowance === 'function') {
                                            setTimeout(function() {
                                                checkOnlineAllowance();
                                            }, 100);
                                        } else {
                                            const warningDiv = document.getElementById('gst-penalty-warning');
                                            if (warningDiv) warningDiv.classList.add('hidden');
                                        }
                                        @endif
                                    });
                                    amountReceived.addEventListener('change', function() {
                                        updateCreditBalancePreview();
                                        checkOverpayment();
                                        // Check online allowance only for tuition payments
                                        @if($onlineAllowance > 0)
                                        const paymentTypeEl = document.getElementById('payment_type');
                                        const paymentType = paymentTypeEl?.value;
                                        if (paymentType === 'tuition' && typeof checkOnlineAllowance === 'function') {
                                            setTimeout(function() {
                                                checkOnlineAllowance();
                                            }, 100);
                                        } else {
                                            const warningDiv = document.getElementById('gst-penalty-warning');
                                            if (warningDiv) warningDiv.classList.add('hidden');
                                        }
                                        @endif
                                    });
                                }

                                if (miscChargeSelectForCredit) {
                                    miscChargeSelectForCredit.addEventListener('change', function() {
                                        setTimeout(() => {
                                            updateCreditBalancePreview();
                                            checkOverpayment();
                                        }, 100);
                                    });
                                }

                                // Note: paymentTypeSelect is already declared above and has an event listener
                                // We add another listener here for credit balance updates
                                const paymentTypeSelectForCredit = document.getElementById('payment_type');
                                if (paymentTypeSelectForCredit) {
                                    paymentTypeSelectForCredit.addEventListener('change', function() {
                                        setTimeout(() => {
                                            updateCreditBalancePreview();
                                            checkOverpayment();
                                        }, 100);
                                    });
                                }

                                if (installmentSelect) {
                                    installmentSelect.addEventListener('change', function() {
                                        setTimeout(() => {
                                            checkOverpayment();
                                        }, 100);
                                    });
                                }
                                @endif

                                if (paymentMode) {
                                    paymentMode.addEventListener('change', function() {
                                        togglePaymentFields();
                                        // Check online allowance only for tuition payments (handled in togglePaymentFields)
                                        // Additional check here to ensure warning is hidden for non-tuition payments
                                        @if($onlineAllowance > 0)
                                        const paymentTypeEl = document.getElementById('payment_type');
                                        const paymentType = paymentTypeEl?.value;
                                        if (paymentType !== 'tuition') {
                                            const warningDiv = document.getElementById('gst-penalty-warning');
                                            if (warningDiv) warningDiv.classList.add('hidden');
                                        }
                                        @endif
                                    });
                                    
                                    // Initialize on page load
                                    togglePaymentFields();
                                }

                                // Remaining Installment Feature (must be accessible globally for the payment form)
                                function checkRemainingAmount() {
                                const installmentSelect = document.getElementById('installment_id');
                                const amountReceivedField = document.getElementById('amount_received');
                                const remainingSection = document.getElementById('remaining-installment-section');
                                const remainingAmountSpan = document.getElementById('remaining-amount');
                                const createRemainingCheckbox = document.getElementById('create_remaining_installment');
                                const remainingDateField = document.getElementById('remaining-installment-date-field');
                                const remainingDateInput = document.getElementById('remaining_installment_due_date');
                                // Note: paymentTypeSelect is already declared above, so we get it via getElementById
                                const paymentTypeSelectForRemaining = document.getElementById('payment_type');

                                if (!installmentSelect || !amountReceivedField || !remainingSection || !remainingAmountSpan) {
                                    return;
                                }
                                
                                // Hide remaining installment section for miscellaneous payments (full payment only)
                                const paymentType = paymentTypeSelectForRemaining?.value;
                                if (paymentType === 'miscellaneous') {
                                    remainingSection.classList.add('hidden');
                                    if (createRemainingCheckbox) {
                                        createRemainingCheckbox.checked = false;
                                    }
                                    return; // Don't process remaining amount logic for misc charges
                                }

                                const selectedOption = installmentSelect.options[installmentSelect.selectedIndex];
                                const selectedInstallmentId = installmentSelect.value;

                                // Only show remaining installment option if:
                                // 1. An installment is selected (not auto-select)
                                // 2. Payment amount is less than outstanding amount (partial payment)
                                // Note: Auto-apply is now always enabled, but we still allow creating remaining installment
                                // when user explicitly wants to split a partial payment into a new installment
                                if (selectedInstallmentId && selectedOption) {
                                    const installmentAmount = parseFloat(selectedOption.getAttribute('data-installment-amount')) || 0;
                                    const paidAmount = parseFloat(selectedOption.getAttribute('data-paid-amount')) || 0;
                                    const outstandingAmount = installmentAmount - paidAmount;
                                    const paymentAmount = parseFloat(amountReceivedField.value) || 0;

                                    if (paymentAmount > 0 && paymentAmount < outstandingAmount) {
                                        // Partial payment detected - show the remaining installment section
                                        const remainingAmount = outstandingAmount - paymentAmount;
                                        remainingAmountSpan.textContent = '₹' + remainingAmount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                                        remainingSection.classList.remove('hidden');
                                        
                                        // Scroll to the section to make it more visible (with slight delay)
                                        setTimeout(function() {
                                            remainingSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                                        }, 150);

                                        // Set minimum date for remaining installment (today)
                                        if (remainingDateInput) {
                                            const today = new Date().toISOString().split('T')[0];
                                            remainingDateInput.setAttribute('min', today);
                                            if (!remainingDateInput.value) {
                                                // Suggest a date 30 days from today
                                                const suggestedDate = new Date();
                                                suggestedDate.setDate(suggestedDate.getDate() + 30);
                                                remainingDateInput.value = suggestedDate.toISOString().split('T')[0];
                                            }
                                        }
                                        
                                        // If checkbox is already checked, show the date field
                                        if (createRemainingCheckbox && createRemainingCheckbox.checked && remainingDateField) {
                                            remainingDateField.classList.remove('hidden');
                                            if (remainingDateInput) {
                                                remainingDateInput.required = true;
                                            }
                                        }
                                    } else {
                                        // Full payment or no payment, hide section
                                        remainingSection.classList.add('hidden');
                                        if (createRemainingCheckbox) {
                                            createRemainingCheckbox.checked = false;
                                        }
                                        if (remainingDateField) {
                                            remainingDateField.classList.add('hidden');
                                        }
                                        if (remainingDateInput) {
                                            remainingDateInput.required = false;
                                            remainingDateInput.value = '';
                                        }
                                    }
                                } else {
                                    // No installment selected, hide section
                                    remainingSection.classList.add('hidden');
                                    if (createRemainingCheckbox) {
                                        createRemainingCheckbox.checked = false;
                                    }
                                    if (remainingDateField) {
                                        remainingDateField.classList.add('hidden');
                                    }
                                }
                            }

                            // Initialize remaining installment feature
                            // Get references to elements (already declared in parent scope, but needed for event listeners)
                            const createRemainingCheckbox = document.getElementById('create_remaining_installment');
                            const remainingDateField = document.getElementById('remaining-installment-date-field');
                            const remainingDateInput = document.getElementById('remaining_installment_due_date');

                            // Toggle date field when checkbox is checked/unchecked
                            if (createRemainingCheckbox && remainingDateField) {
                                createRemainingCheckbox.addEventListener('change', function() {
                                    if (this.checked) {
                                        remainingDateField.classList.remove('hidden');
                                        if (remainingDateInput) {
                                            remainingDateInput.required = true;
                                        }
                                    } else {
                                        remainingDateField.classList.add('hidden');
                                        if (remainingDateInput) {
                                            remainingDateInput.required = false;
                                            remainingDateInput.value = '';
                                        }
                                    }
                                });
                            }

                            // Check remaining amount when installment or amount changes
                            // installmentSelect and amountReceived are already declared at lines 1349 and 1352
                            if (installmentSelect) {
                                installmentSelect.addEventListener('change', checkRemainingAmount);
                            }
                            if (amountReceived) {
                                amountReceived.addEventListener('input', checkRemainingAmount);
                                amountReceived.addEventListener('change', checkRemainingAmount);
                                amountReceived.addEventListener('blur', checkRemainingAmount);
                            }

                                // Auto-apply is now always enabled, so we don't need to listen for checkbox changes

                                // Initial check on page load (with delay to ensure DOM is ready)
                                setTimeout(function() {
                                    checkRemainingAmount();
                                }, 200);
                        });
                    </script>

                    <!-- Accordion Script - Moved to end for reliability -->
                    <script>
                        // Re-initialize accordion at the very end to ensure it works
                        (function() {
                            function setupAccordions() {
                                const headers = document.querySelectorAll('.accordion-header');
                                console.log('Re-checking accordions, found:', headers.length);
                                
                                headers.forEach(function(btn) {
                                    // Remove any existing listeners by cloning
                                    var newBtn = btn.cloneNode(true);
                                    btn.parentNode.replaceChild(newBtn, btn);
                                    
                                    // Add fresh listener
                                    newBtn.onclick = function(e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        
                                        var content = this.nextElementSibling;
                                        var chevron = this.querySelector('.accordion-chevron');
                                        
                                        if (!content || !chevron) return;
                                        
                                        var wasOpen = !content.classList.contains('hidden');
                                        
                                        // Close all
                                        document.querySelectorAll('.accordion-content').forEach(function(c) {
                                            c.classList.add('hidden');
                                        });
                                        document.querySelectorAll('.accordion-chevron').forEach(function(c) {
                                            c.classList.remove('rotate-180');
                                        });
                                        
                                        // Open clicked one if it was closed
                                        if (!wasOpen) {
                                            content.classList.remove('hidden');
                                            chevron.classList.add('rotate-180');
                                        }
                                    };
                                });
                                
                                // Restore open state for items with errors
                                headers.forEach(function(btn) {
                                    var content = btn.nextElementSibling;
                                    var chevron = btn.querySelector('.accordion-chevron');
                                    if (content && !content.classList.contains('hidden') && chevron) {
                                        chevron.classList.add('rotate-180');
                                    }
                                });
                            }
                            
                            if (document.readyState === 'complete') {
                                setTimeout(setupAccordions, 300);
                            } else {
                                window.addEventListener('load', function() {
                                    setTimeout(setupAccordions, 300);
                                });
                            }
                        })();
                    </script>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Basic Info Modal -->
    @can('update', $student)
    <div id="editBasicInfoModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Edit Basic Information</h3>
                    <button type="button" onclick="closeEditBasicInfoModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="{{ route('students.basic-info.update', $student) }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    
                    <div>
                        <x-input-label for="edit_name" value="Student Name *" />
                        <x-text-input id="edit_name" name="name" type="text" value="{{ old('name', $student->name) }}" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    
                    <div>
                        <x-input-label for="edit_guardian_1_name" value="Father's Name *" />
                        <x-text-input id="edit_guardian_1_name" name="guardian_1_name" type="text" value="{{ old('guardian_1_name', $student->guardian_1_name) }}" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('guardian_1_name')" class="mt-2" />
                    </div>
                    
                    <div>
                        <x-input-label for="edit_guardian_1_whatsapp" value="Registered Mobile Number *" />
                        <div class="relative mt-1">
                            <span class="absolute inset-y-0 left-2 flex items-center text-xs font-medium text-gray-700">+91</span>
                            <input id="edit_guardian_1_whatsapp" name="guardian_1_whatsapp" type="tel" pattern="[0-9]{10}" maxlength="10" 
                                   value="{{ old('guardian_1_whatsapp', preg_replace('/^\+91|[^0-9]/', '', $student->guardian_1_whatsapp ?? '')) }}" 
                                   class="block w-full text-sm rounded-xl border-gray-300 pl-12 pr-2 py-2 focus:border-indigo-500 focus:ring-indigo-500 {{ $errors->has('guardian_1_whatsapp') ? 'border-red-300 focus:border-red-500 focus:ring-red-500' : '' }}" 
                                   placeholder="10 digits" required />
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Enter 10-digit mobile number</p>
                        <x-input-error :messages="$errors->get('guardian_1_whatsapp')" class="mt-2" />
                    </div>
                    
                    <div class="flex items-center justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeEditBasicInfoModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </button>
                        <x-primary-button>Update Information</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Format phone number input - set up once on page load
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.getElementById('edit_guardian_1_whatsapp');
            if (phoneInput) {
                // Remove existing listeners by cloning
                const newInput = phoneInput.cloneNode(true);
                phoneInput.parentNode.replaceChild(newInput, phoneInput);
                
                newInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
                });
                
                newInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = paste.replace(/[^0-9]/g, '').substring(0, 10);
                    this.value = digits;
                });
            }
        });
        
        function openEditBasicInfoModal() {
            document.getElementById('editBasicInfoModal').classList.remove('hidden');
        }
        
        function closeEditBasicInfoModal() {
            document.getElementById('editBasicInfoModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('editBasicInfoModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeEditBasicInfoModal();
                    }
                });
            }
        });
    </script>
    @endcan

    <!-- Add Miscellaneous Charge Modal -->
    <div id="addMiscChargeModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add Miscellaneous Charge</h3>
                    <button type="button" onclick="closeAddMiscChargeModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="{{ route('students.misc-charges.store', $student) }}" class="space-y-4" id="add-misc-charge-form">
                    @csrf
                    
                    <div>
                        <x-input-label for="misc_label" value="Charge Label *" />
                        <x-text-input id="misc_label" name="label" type="text" value="{{ old('label') }}" class="mt-1 block w-full" placeholder="e.g., Books, Uniform, Materials" required />
                        <x-input-error :messages="$errors->get('label')" class="mt-2" />
                    </div>
                    
                    <div>
                        <x-input-label for="misc_amount" value="Amount (₹) *" />
                        <x-text-input id="misc_amount" name="amount" type="number" step="0.01" min="0.01" value="{{ old('amount') }}" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    
                    <div>
                        <x-input-label for="misc_due_date" value="Due Date" />
                        <x-text-input id="misc_due_date" name="due_date" type="date" value="{{ old('due_date') }}" min="{{ now()->toDateString() }}" class="mt-1 block w-full" />
                        <p class="mt-1 text-xs text-gray-500">Leave empty if no specific due date</p>
                        <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                    </div>
                    
                    <div class="flex items-center justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeAddMiscChargeModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </button>
                        <x-primary-button>Add Charge</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Penalty Modal -->
    <div id="addPenaltyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Add Manual Penalty</h3>
                    <button type="button" onclick="closeAddPenaltyModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                <form method="POST" action="{{ route('students.penalties.store', $student) }}" class="space-y-4" id="add-penalty-form">
                    @csrf
                    
                    <!-- Type of Penalty at the top -->
                    <div>
                        <x-input-label for="penalty_type_name" value="Type of Penalty *" />
                        <x-text-input id="penalty_type_name" name="penalty_type_name" type="text" value="{{ old('penalty_type_name') }}" class="mt-1 block w-full" placeholder="e.g., Late Payment, Absence, etc." required />
                        <x-input-error :messages="$errors->get('penalty_type_name')" class="mt-2" />
                        <p class="mt-1 text-sm text-gray-500">This will be displayed as a tag/badge when recording payment.</p>
                    </div>
                    
                    <div>
                        <x-input-label for="penalty_amount_input" value="Penalty Amount (₹) *" />
                        <x-text-input id="penalty_amount_input" name="penalty_amount" type="number" step="0.01" min="0.01" value="{{ old('penalty_amount') }}" class="mt-1 block w-full" required />
                        <x-input-error :messages="$errors->get('penalty_amount')" class="mt-2" />
                    </div>
                    
                    <div class="flex items-center justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="closeAddPenaltyModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Cancel
                        </button>
                        <x-primary-button>Add Penalty</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddMiscChargeModal() {
            document.getElementById('addMiscChargeModal').classList.remove('hidden');
        }
        
        function closeAddMiscChargeModal() {
            document.getElementById('addMiscChargeModal').classList.add('hidden');
        }
        
        function openAddPenaltyModal() {
            document.getElementById('addPenaltyModal').classList.remove('hidden');
        }
        
        function closeAddPenaltyModal() {
            document.getElementById('addPenaltyModal').classList.add('hidden');
        }
        
        // Close modals when clicking outside
        document.getElementById('addMiscChargeModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddMiscChargeModal();
            }
        });
        
        document.getElementById('addPenaltyModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddPenaltyModal();
            }
        });

        // Reload page after successful form submission to refresh the dropdowns
        document.getElementById('add-misc-charge-form')?.addEventListener('submit', function(e) {
            // Let the form submit normally, page will reload on success
        });
        
        document.getElementById('add-penalty-form')?.addEventListener('submit', function(e) {
            // Let the form submit normally, page will reload on success
        });
    </script>
</x-app-layout>
