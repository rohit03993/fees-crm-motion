<?php

namespace App\Services;

use App\Models\CreditBalanceTransaction;
use App\Models\Installment;
use App\Models\MiscCharge;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\WhatsappLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    private const OFFLINE_PAYMENT_MODES = ['cash'];

    public function recordPayment(Student $student, array $data): Payment
    {
        return DB::transaction(function () use ($student, $data) {
            $amount = (float) $data['amount_received'];
            $useCreditBalance = filter_var($data['use_credit_balance'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            // Credit balance will be calculated separately for tuition vs miscellaneous payments
            // Initialize variables
            $creditUsed = 0;
            $amountToAllocate = $amount;
            
            // No GST calculation on payment recording
            // Amount received = Base amount (no GST split)
            // GST penalty is only applied when online allowance is exceeded (handled separately)
            $baseAmount = $amount; // Keep original amount for record-keeping
            $gstAmount = 0.0;
            $gstPercentage = 0.0;

            $paymentType = $data['payment_type'] ?? 'tuition';
            $miscChargeId = $data['misc_charge_id'] ?? null;
            $installmentId = $data['installment_id'] ?? null;
            
            // NEW: Payment Cap Validation - Calculate maximum allowed payment
            // Maximum allowed = Total Program Fee + Total Miscellaneous Charges
            $totalProgramFee = (float) ($student->fee->total_fee ?? 0);
            $totalMiscCharges = (float) $student->miscCharges()->sum('amount');
            $maximumAllowedPayment = $totalProgramFee + $totalMiscCharges;
            
            // Calculate total payments made so far (excluding current payment)
            $totalPaymentsMade = (float) $student->payments()->sum('amount_received');
            
            // Calculate what the total would be after this payment (accounting for credit balance)
            // If credit is used, the actual payment amount is reduced, so we need to account for that
            // For now, we check the payment amount before credit is applied
            $totalAfterThisPayment = $totalPaymentsMade + $amount;
            
            // Validate payment cap
            if ($totalAfterThisPayment > $maximumAllowedPayment + 0.01) { // Allow small rounding differences
                $excess = $totalAfterThisPayment - $maximumAllowedPayment;
                throw ValidationException::withMessages([
                    'amount_received' => 'Payment exceeds maximum allowed. Maximum allowed: ₹' . number_format($maximumAllowedPayment, 2) . ' (Program Fee: ₹' . number_format($totalProgramFee, 2) . ' + Miscellaneous: ₹' . number_format($totalMiscCharges, 2) . '). Current total payments: ₹' . number_format($totalPaymentsMade, 2) . '. This payment would exceed by ₹' . number_format($excess, 2) . '.',
                ]);
            }

            // Handle miscellaneous charge payment (full payment only)
            if ($paymentType === 'miscellaneous' && $miscChargeId) {
                $miscCharge = MiscCharge::findOrFail($miscChargeId);
                
                // Validate that payment is for the correct student
                // Allow both student-specific charges (student_id matches) OR course-level charges (student_id is null but course_id matches)
                $isStudentCharge = $miscCharge->student_id === $student->id;
                $isCourseCharge = $miscCharge->student_id === null && $miscCharge->course_id === $student->course_id;
                
                if (!$isStudentCharge && !$isCourseCharge) {
                    throw ValidationException::withMessages([
                        'misc_charge_id' => 'This miscellaneous charge does not belong to this student or their course.',
                    ]);
                }
                
                // NEW: Calculate credit balance BEFORE validation
                if ($useCreditBalance) {
                    $creditResult = $this->applyCreditBalance($student, $miscCharge->amount, true);
                    $creditUsed = $creditResult['credit_used'];
                }
                
                // Validate full payment (no partial payments allowed for misc charges)
                // NEW: Account for credit balance if used
                $totalPayment = $amount + ($creditUsed ?? 0);
                if (abs($totalPayment - $miscCharge->amount) > 0.01) {
                    $actualAmountNeeded = $miscCharge->amount - ($creditUsed ?? 0);
                    $errorMsg = 'Miscellaneous charges require full payment. ';
                    if ($creditUsed > 0 && $actualAmountNeeded > 0) {
                        $errorMsg .= 'After applying ₹' . number_format($creditUsed, 2) . ' credit, amount must be exactly ₹' . number_format($actualAmountNeeded, 2) . '.';
                    } elseif ($creditUsed > 0 && $actualAmountNeeded <= 0) {
                        $errorMsg .= 'Credit balance (₹' . number_format($creditUsed, 2) . ') fully covers this charge. Payment amount should be ₹0.00.';
                    } else {
                        $errorMsg .= 'Amount must be exactly ₹' . number_format($miscCharge->amount, 2) . '.';
                    }
                    throw ValidationException::withMessages([
                        'amount_received' => $errorMsg,
                    ]);
                }
                
                // Validate charge is not already paid
                if ($miscCharge->status === 'paid') {
                    throw ValidationException::withMessages([
                        'misc_charge_id' => 'This miscellaneous charge has already been paid.',
                    ]);
                }
            }

            $payment = Payment::create([
                'student_id' => $student->id,
                'installment_id' => $installmentId,
                'misc_charge_id' => $miscChargeId,
                'payment_mode' => $data['payment_mode'],
                'bank_id' => $data['bank_id'] ?? null,
                'voucher_number' => $data['voucher_number'] ?? null,
                'employee_name' => $data['employee_name'] ?? null,
                'amount_received' => $amount,
                'base_amount' => $baseAmount,
                'gst_amount' => $gstAmount,
                'gst_percentage' => $gstPercentage,
                'transaction_id' => $data['transaction_id'] ?? null,
                'deposited_to' => $data['deposited_to'] ?? null,
                'payment_date' => $data['payment_date'],
                'remarks' => $data['remarks'] ?? null,
                'status' => 'recorded',
                'recorded_by' => Auth::id(),
            ]);

            // Handle payment based on type
            if ($paymentType === 'miscellaneous' && $miscChargeId) {
                // Ensure payment is saved before processing
                $payment->save();
                
                // NEW FEATURE: Calculate and deduct credit balance if it was used
                // Make sure creditUsed is set (recalculate if needed to ensure it's correct)
                if ($useCreditBalance) {
                    $creditResult = $this->applyCreditBalance($student, $miscCharge->amount, true);
                    $creditUsed = $creditResult['credit_used'];
                    
                    // Deduct credit balance if any was used
                    if ($creditUsed > 0) {
                        $this->deductCreditBalance($student, $payment, $creditUsed);
                        // Refresh the fee relationship to ensure it's updated
                        $student->load('fee');
                    }
                }
                
                // For course-level charges (student_id is null), create a student-specific paid charge
                // For student-specific charges, just update the status
                if ($miscCharge->student_id === null) {
                    // This is a course-level charge - create a student-specific paid instance
                    $studentMiscCharge = MiscCharge::create([
                        'student_id' => $student->id,
                        'course_id' => $miscCharge->course_id,
                        'label' => $miscCharge->label,
                        'amount' => $miscCharge->amount,
                        'due_date' => $miscCharge->due_date,
                        'status' => 'paid',
                        'created_by' => Auth::id(),
                    ]);
                    
                    // Update payment to reference the student-specific charge
                    $payment->update(['misc_charge_id' => $studentMiscCharge->id]);
                    
                    // Log WhatsApp message for misc charge payment
                    $this->logWhatsappMiscChargePayment($student, $payment, $studentMiscCharge);
                    
                    // NOTE: Miscellaneous payments are NOT checked against online/cash allowances
                    // They can be paid in any mode without restrictions
                } else {
                    // This is a student-specific charge - just update status
                    $miscCharge->update(['status' => 'paid']);
                    $miscCharge->refresh(); // Refresh to ensure status is updated
                    
                    // Log WhatsApp message for misc charge payment
                    $this->logWhatsappMiscChargePayment($student, $payment, $miscCharge);
                    
                    // NOTE: Miscellaneous payments are NOT checked against online/cash allowances
                    // They can be paid in any mode without restrictions
                }
            } elseif ($paymentType === 'penalty') {
                // Handle penalty payment
                $payment->save();
                
                // Get penalty details based on penalty type
                $penaltyType = $data['penalty_type'] ?? null;
                
                if ($penaltyType === 'late_fee' && !empty($data['penalty_id'])) {
                    // Handle late fee penalty payment
                    $penalty = \App\Models\Penalty::findOrFail($data['penalty_id']);
                    
                    // Verify penalty belongs to this student
                    if ($penalty->student_id !== $student->id) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator([], []),
                            ['penalty_id' => ['The selected penalty does not belong to this student.']]
                        );
                    }
                    
                    // Verify payment amount matches penalty amount (full payment only)
                    if (abs($amount - $penalty->penalty_amount) > 0.01) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator([], []),
                            ['amount_received' => ['Late fee penalty requires full payment of ₹' . number_format($penalty->penalty_amount, 2) . '.']]
                        );
                    }
                    
                    // Update payment to reference the penalty
                    $payment->update(['penalty_id' => $penalty->id]);
                    
                    // Update penalty status to paid
                    $penalty->update(['status' => 'paid']);
                    
                    // Log WhatsApp message for penalty payment
                    $this->logWhatsappPenaltyPayment($student, $payment, $penalty);
                    
                } elseif ($penaltyType === 'gst' && !empty($data['gst_penalty_charge_id'])) {
                    // Handle GST penalty payment (stored as misc_charge)
                    $gstPenaltyCharge = MiscCharge::findOrFail($data['gst_penalty_charge_id']);
                    
                    // Verify it's a GST penalty
                    if (!str_starts_with($gstPenaltyCharge->label, 'GST Penalty')) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator([], []),
                            ['gst_penalty_charge_id' => ['The selected charge is not a GST penalty.']]
                        );
                    }
                    
                    // Verify charge belongs to this student
                    if ($gstPenaltyCharge->student_id !== $student->id) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator([], []),
                            ['gst_penalty_charge_id' => ['The selected GST penalty does not belong to this student.']]
                        );
                    }
                    
                    // Verify payment amount matches penalty amount (full payment only)
                    if (abs($amount - $gstPenaltyCharge->amount) > 0.01) {
                        throw new \Illuminate\Validation\ValidationException(
                            validator([], []),
                            ['amount_received' => ['GST penalty requires full payment of ₹' . number_format($gstPenaltyCharge->amount, 2) . '.']]
                        );
                    }
                    
                    // Update payment to reference the GST penalty charge
                    $payment->update(['misc_charge_id' => $gstPenaltyCharge->id]);
                    
                    // Update GST penalty status to paid
                    $gstPenaltyCharge->update(['status' => 'paid']);
                    
                    // Log WhatsApp message for GST penalty payment
                    $this->logWhatsappMiscChargePayment($student, $payment, $gstPenaltyCharge);
                    
                } else {
                    throw new \Illuminate\Validation\ValidationException(
                        validator([], []),
                        ['penalty_type' => ['Please select a valid penalty type and specific penalty to pay.']]
                    );
                }
                
                // NOTE: Penalty payments are NOT checked against online/cash allowances
                // They can be paid in any mode without restrictions
            } else {
                // Handle tuition payment (installment)
                // NEW FEATURE: Calculate and apply credit balance for tuition payments
                if ($useCreditBalance) {
                    $creditResult = $this->applyCreditBalance($student, $amount, true);
                    $creditUsed = $creditResult['credit_used'];
                    $actualAmountToRecord = $creditResult['remaining_payment'];
                    $amountToAllocate = $actualAmountToRecord > 0.01 ? $actualAmountToRecord : 0;
                    
                    // Deduct credit balance before allocating to installments
                    if ($creditUsed > 0) {
                        $this->deductCreditBalance($student, $payment, $creditUsed);
                    }
                }
                
                $installmentUsed = $this->applyToInstallments(
                    $student,
                    $payment,
                    amountToAllocate: $amountToAllocate, // Use adjusted amount (after credit)
                    preferredInstallmentId: $data['installment_id'] ?? null,
                    autoApply: true // Always auto-apply to subsequent installments
                );

                $payment->save();

                // Enforce online allowance - this will apply GST penalty if exceeded
                $this->enforceOnlineAllowance($student, $payment);

                // Create remaining installment if requested
                // Only create if: specific installment selected and checkbox is checked
                // Note: auto-apply is now always enabled, but we still allow creating remaining installment
                // when user explicitly wants to split a partial payment into a new installment
                if (!empty($data['create_remaining_installment']) && 
                    !empty($data['remaining_installment_due_date']) &&
                    !empty($data['installment_id']) &&
                    $installmentUsed) {
                    
                    // Reload installment to get current state after payment allocation
                    $installmentUsed->refresh();
                    $remainingAmount = $installmentUsed->amount - $installmentUsed->paid_amount;
                    
                    // Only create if there's actually a remaining amount
                    if ($remainingAmount > 0.01) {
                        $this->createRemainingInstallment(
                            $student,
                            $installmentUsed,
                            $data['remaining_installment_due_date'],
                            $remainingAmount
                        );
                    }
                }

                $this->logWhatsappReceipt($student, $payment);
            }

            return $payment->load(['installment', 'miscCharge', 'penalty', 'student']);
        });
    }

    private function resolveGstPercentage(array $data): float
    {
        $percentage = $data['gst_percentage'] ?? config('fees.gst_percentage', 18.0);

        return round((float) $percentage, 2);
    }

    private function calculateBaseAmount(float $grossAmount, float $gstPercentage): float
    {
        if ($gstPercentage <= 0) {
            return round($grossAmount, 2);
        }

        $divisor = 1 + ($gstPercentage / 100);

        return round($grossAmount / $divisor, 2);
    }

    private function applyToInstallments(
        Student $student,
        Payment $payment,
        float $amountToAllocate,
        ?int $preferredInstallmentId,
        bool $autoApply
    ): ?Installment {
        $installments = $this->resolveInstallmentsForAllocation(
            student: $student,
            preferredInstallmentId: $preferredInstallmentId,
            autoApply: $autoApply
        );

        if ($installments->isEmpty()) {
            throw ValidationException::withMessages([
                'installment_id' => 'No eligible installments available for allocation.',
            ]);
        }

        $remaining = $amountToAllocate;
        $primaryInstallment = null;
        $originalOutstanding = 0;

        // Store original outstanding amount for the first installment (for remaining installment creation)
        // Note: autoApply is now always true, but we still track this for remaining installment creation
        if ($preferredInstallmentId && $installments->isNotEmpty()) {
            $firstInstallment = $installments->first();
            $originalOutstanding = $firstInstallment->amount - $firstInstallment->paid_amount;
        }

        foreach ($installments as $installment) {
            if ($remaining <= 0) {
                break;
            }

            // Track the first installment that receives payment
            if (is_null($primaryInstallment)) {
                $primaryInstallment = $installment;
            }

            $applyAmount = $this->allocateAmountToInstallment($installment, $remaining);
            $remaining = round($remaining - $applyAmount, 2);

            if (is_null($payment->installment_id)) {
                $payment->installment()->associate($installment);
            }
        }

        // Handle overpayment: Store excess as credit balance
        // If $remaining > 0 after allocating to all available installments, 
        // it means the payment exceeds the outstanding balance (overpayment).
        if ($remaining > 0.01) {
            $this->addCreditBalance($student, $payment, $remaining);
        }

        // Return the primary installment that received the payment
        // This will be used to create remaining installment if needed (when payment < outstanding)
        return $primaryInstallment;
    }

    private function resolveInstallmentsForAllocation(
        Student $student,
        ?int $preferredInstallmentId,
        bool $autoApply
    ): Collection {
        $query = $student->installments()
            ->orderBy('due_date')
            ->orderBy('installment_number');

        $installments = $query->get()->filter(function (Installment $installment) {
            return $installment->amount > $installment->paid_amount;
        });

        if ($preferredInstallmentId) {
            if (! $installments->contains(fn (Installment $installment) => $installment->id === $preferredInstallmentId)) {
                throw ValidationException::withMessages([
                    'installment_id' => 'Selected installment is not eligible for allocation.',
                ]);
            }

            if (! $autoApply) {
                $installments = $installments->filter(
                    fn (Installment $installment) => $installment->id === $preferredInstallmentId
                );
            }

            if ($autoApply) {
                $preferred = $installments->firstWhere('id', $preferredInstallmentId);

                if ($preferred) {
                    $additional = $student->installments()
                        ->where('installments.id', '!=', $preferredInstallmentId)
                        ->orderBy('due_date')
                        ->orderBy('installment_number')
                        ->get()
                        ->filter(fn (Installment $installment) => $installment->amount > $installment->paid_amount);

                    $installments = collect([$preferred])->merge($additional);
                }
            }
        }

        if (! $preferredInstallmentId && ! $autoApply) {
            $installments = $installments->take(1);
        }

        return $installments->values();
    }

    private function allocateAmountToInstallment(Installment $installment, float $remaining): float
    {
        $outstanding = round($installment->amount - $installment->paid_amount, 2);

        if ($outstanding <= 0) {
            return 0;
        }

        $allocation = min($outstanding, $remaining);

        $installment->paid_amount = round($installment->paid_amount + $allocation, 2);
        $installment->status = match (true) {
            $installment->paid_amount >= $installment->amount - 0.01 => 'paid',
            $installment->paid_amount > 0 => 'partially_paid',
            default => $installment->status,
        };
        $installment->save();

        return $allocation;
    }

    private function logWhatsappPenaltyPayment(Student $student, Payment $payment, \App\Models\Penalty $penalty): void
    {
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = "Penalty Payment Receipt\n\n";
        $message .= "Student: {$student->name}\n";
        $message .= "Penalty Type: Late Fee Penalty\n";
        $message .= "Installment: #" . ($penalty->installment->installment_number ?? 'N/A') . "\n";
        $message .= "Amount: ₹" . number_format($payment->amount_received, 2) . "\n";
        $message .= "Payment Date: " . $payment->payment_date->format('d M Y') . "\n";
        $message .= "Mode: " . ucfirst(str_replace('_', ' ', $payment->payment_mode)) . "\n";
        
        if ($payment->transaction_id) {
            $message .= "Transaction ID: {$payment->transaction_id}\n";
        }
        
        $message .= "\nThank you!";
        
        \App\Models\WhatsappLog::create([
            'student_id' => $student->id,
            'phone' => $phone,
            'message' => $message,
            'sent_at' => now(),
        ]);
    }

    private function logWhatsappMiscChargePayment(Student $student, Payment $payment, MiscCharge $miscCharge): void
    {
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = "Payment Receipt\n\n";
        $message .= "Student: {$student->name}\n";
        $message .= "Item: {$miscCharge->label}\n";
        $message .= "Amount: ₹" . number_format($payment->amount_received, 2) . "\n";
        $message .= "Payment Date: " . $payment->payment_date->format('d M Y') . "\n";
        $message .= "Mode: " . ucfirst(str_replace('_', ' ', $payment->payment_mode)) . "\n";
        
        if ($payment->transaction_id) {
            $message .= "Reference: {$payment->transaction_id}\n";
        }
        
        $message .= "\nThank you for your payment!";

        WhatsappLog::create([
            'student_id' => $student->id,
            'misc_charge_id' => $miscCharge->id,
            'message_type' => 'payment_receipt',
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
            'response_data' => null,
            'sent_at' => null,
        ]);
    }

    private function logWhatsappReceipt(Student $student, Payment $payment): void
    {
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        // Simple receipt message - no GST split shown
        $message = sprintf(
            'Payment received: ₹%s on %s via %s. Thank you!',
            number_format($payment->amount_received, 2),
            $payment->payment_date->format('d M Y'),
            ucfirst(str_replace('_', ' ', $payment->payment_mode))
        );

        WhatsappLog::create([
            'student_id' => $student->id,
            'installment_id' => $payment->installment_id,
            'message_type' => 'payment_receipt',
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
            'response_data' => null,
            'sent_at' => null,
        ]);
    }

    private function enforceOnlineAllowance(Student $student, Payment $payment): void
    {
        $fee = $student->fee;

        if (! $fee) {
            return;
        }

        // Only enforce allowance restrictions for tuition payments (installment_id not null)
        // Miscellaneous payments are NOT bound by cash/online allowance limits
        if ($payment->misc_charge_id !== null) {
            return; // Skip allowance check for misc payments
        }

        if (! $this->isOnlineMode($payment->payment_mode)) {
            return;
        }

        $onlineAllowance = (float) $fee->online_allowance;

        // Only count TUITION payments (where installment_id is not null) towards allowance
        $previousOnline = Payment::where('student_id', $student->id)
            ->whereNotNull('installment_id') // Only tuition payments
            ->whereNull('misc_charge_id') // Exclude misc payments
            ->whereNotIn('payment_mode', self::OFFLINE_PAYMENT_MODES)
            ->where('id', '!=', $payment->id)
            ->sum('amount_received');

        $previousExcess = max(0, round($previousOnline - $onlineAllowance, 2));
        $currentTotal = $previousOnline + (float) $payment->amount_received;
        $currentExcess = max(0, round($currentTotal - $onlineAllowance, 2));
        $incrementalExcess = round($currentExcess - $previousExcess, 2);

        if ($incrementalExcess <= 0) {
            return;
        }

        // Get GST percentage from settings (configurable), fallback to config file
        $gstRate = (float) \App\Models\Setting::getValue('penalty.gst_percentage', config('fees.gst_percentage', 18.0));
        
        // IMPORTANT: Only the GST amount on the excess is the penalty
        // The excess amount itself (₹10,000) is a valid tuition fee payment
        // Only GST on excess (₹10,000 × 18% = ₹1,800) is the penalty
        $gstPenaltyAmount = round($incrementalExcess * ($gstRate / 100), 2);

        $student->miscCharges()->create([
            'label' => 'GST Penalty on Online Overage (Excess ₹' . number_format($incrementalExcess, 2) . ' + ' . number_format($gstRate, 2) . '% GST = ₹' . number_format($gstPenaltyAmount, 2) . ')',
            'amount' => $gstPenaltyAmount,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
            'created_by' => Auth::id(),
        ]);

        $this->logGstPenaltyNotification($student, $incrementalExcess, $gstPenaltyAmount, $gstRate);
    }

    private function logGstPenaltyNotification(Student $student, float $excessBase, float $penaltyAmount, float $gstRate): void
    {
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = sprintf(
            'Online allowance exceeded by ₹%s. This excess amount (₹%s) is counted as tuition fees. A GST penalty of ₹%s (%.2f%% GST on excess) has been added as a separate charge.',
            number_format($excessBase, 2),
            number_format($excessBase, 2),
            number_format($penaltyAmount, 2),
            $gstRate
        );

        WhatsappLog::create([
            'student_id' => $student->id,
            'installment_id' => null,
            'message_type' => 'gst_penalty_notice',
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
            'response_data' => null,
            'sent_at' => null,
        ]);
    }

    private function isOnlineMode(string $mode): bool
    {
        return ! in_array($mode, self::OFFLINE_PAYMENT_MODES, true);
    }

    /**
     * Create a new installment for the remaining amount after a partial payment
     */
    public function createRemainingInstallment(
        Student $student, 
        Installment $originalInstallment, 
        string $dueDate, 
        float $remainingAmount
    ): Installment {
        $fee = $student->fee;
        if (!$fee) {
            throw ValidationException::withMessages([
                'installment_id' => 'Student fee record not found.',
            ]);
        }

        if ($remainingAmount <= 0.01) {
            // No remaining amount, nothing to create
            return $originalInstallment;
        }

        return DB::transaction(function () use ($fee, $originalInstallment, $dueDate, $remainingAmount) {
            // Store the original amount before modification (for reference)
            // If original_amount is not set, use current amount (for backward compatibility)
            if (!$originalInstallment->original_amount) {
                $originalInstallment->original_amount = $originalInstallment->amount;
            }
            
            // Reduce the original installment amount by the remaining amount
            // This prevents double-counting: the remaining amount is now in a separate installment
            // Example: If original was ₹15,000 with ₹10,000 paid, reduce amount to ₹10,000
            // The remaining ₹5,000 becomes a new installment
            // IMPORTANT: Keep original_amount unchanged to preserve the record of the original installment amount
            $originalInstallment->amount = $originalInstallment->paid_amount;
            
            // IMPORTANT: Keep status as 'partially_paid' to reflect that this was a partial payment
            // Even though amount now equals paid_amount, we want to show it was originally partially paid
            // This preserves the history that a partial payment was made and the remaining was split off
            $originalInstallment->status = 'partially_paid';
            $originalInstallment->save();

            // Get all installments sorted by due date
            $allInstallments = $fee->installments()
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->get();

            // Create the new installment with the remaining amount
            $newInstallment = $fee->installments()->create([
                'installment_number' => 0, // Will be updated after reordering
                'due_date' => $dueDate,
                'amount' => $remainingAmount,
                'original_amount' => $remainingAmount, // Store original amount for record keeping
                'paid_amount' => 0,
                'status' => 'pending',
            ]);

            // Reorder all installments (including the new one) by due date
            // Store the desired order first (based on due_date, then by id for stability)
            $allInstallments = $fee->installments()
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();

            // Calculate a safe offset to avoid unique constraint violations
            // Use a high offset (e.g., 10000) plus the installment count
            $offset = 10000 + $allInstallments->count();

            // Step 1: Set all installment numbers to temporary offset values to avoid conflicts
            // This ensures no two installments have the same number during reordering
            foreach ($allInstallments as $index => $installment) {
                // Use DB::table() to update directly, avoiding model refresh issues
                DB::table('installments')
                    ->where('id', $installment->id)
                    ->update(['installment_number' => $offset + $index]);
            }

            // Step 2: Now set them to their correct sequential numbers based on due date order
            // Reload installments in the correct order (by due_date, then by id)
            $sortedInstallments = $fee->installments()
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();

            foreach ($sortedInstallments as $index => $installment) {
                DB::table('installments')
                    ->where('id', $installment->id)
                    ->update(['installment_number' => $index + 1]);
            }

            return $newInstallment;
        });
    }

    /**
     * Add credit balance when overpayment is detected
     * This is a NEW feature - does not affect existing payment flow
     */
    private function addCreditBalance(Student $student, Payment $payment, float $excessAmount): void
    {
        $fee = $student->fee;
        if (! $fee) {
            return;
        }

        DB::transaction(function () use ($student, $payment, $excessAmount, $fee) {
            // Add to credit balance
            $fee->credit_balance = round(($fee->credit_balance ?? 0) + $excessAmount, 2);
            $fee->save();

            // Create audit trail
            CreditBalanceTransaction::create([
                'student_id' => $student->id,
                'payment_id' => $payment->id,
                'transaction_type' => 'credit',
                'amount' => $excessAmount,
                'balance_after' => $fee->credit_balance,
                'description' => 'Overpayment from Payment #' . $payment->id . ' (₹' . number_format($payment->amount_received, 2) . ')',
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Apply credit balance to a payment
     * This is a NEW feature - can be used when recording payments
     */
    public function applyCreditBalance(Student $student, float $paymentAmount, bool $useCredit = false): array
    {
        $fee = $student->fee;
        if (! $fee || ! $useCredit) {
            return [
                'credit_used' => 0,
                'remaining_payment' => $paymentAmount,
                'credit_balance_after' => $fee->credit_balance ?? 0,
            ];
        }

        $creditBalance = $fee->credit_balance ?? 0;
        if ($creditBalance <= 0) {
            return [
                'credit_used' => 0,
                'remaining_payment' => $paymentAmount,
                'credit_balance_after' => 0,
            ];
        }

        $creditToUse = min($creditBalance, $paymentAmount);
        $remainingPayment = round($paymentAmount - $creditToUse, 2);

        return [
            'credit_used' => $creditToUse,
            'remaining_payment' => $remainingPayment,
            'credit_balance_after' => round($creditBalance - $creditToUse, 2),
        ];
    }

    /**
     * Deduct credit balance when applied to a payment
     * This is called after payment is successfully recorded
     */
    private function deductCreditBalance(Student $student, Payment $payment, float $creditUsed): void
    {
        if ($creditUsed <= 0) {
            return;
        }

        // Get fresh fee instance to ensure we have the latest data
        $fee = StudentFee::where('student_id', $student->id)->first();
        if (! $fee) {
            return;
        }

        // Note: We're already inside a DB transaction from recordPayment
        // Get current credit balance from database
        $currentBalance = (float) ($fee->credit_balance ?? 0);
        
        // Calculate new balance
        $newBalance = round(max(0, $currentBalance - $creditUsed), 2);
        
        // Update credit balance directly in database
        $fee->credit_balance = $newBalance;
        $fee->save();
        
        // Refresh the fee instance to ensure we have the updated value
        $fee->refresh();

        // Create audit trail
        CreditBalanceTransaction::create([
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'debit',
            'amount' => $creditUsed,
            'balance_after' => $newBalance,
            'description' => 'Applied to Payment #' . $payment->id . ' (₹' . number_format($payment->amount_received, 2) . ')',
            'created_by' => Auth::id(),
        ]);
    }
}


