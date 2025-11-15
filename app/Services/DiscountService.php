<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Student;
use App\Models\WhatsappLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function requestDiscount(Student $student, array $data): Discount
    {
        $this->ensureAmountIsValid($student, $data['amount']);

        return DB::transaction(function () use ($student, $data) {
            $discount = Discount::create([
                'student_id' => $student->id,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'document_path' => $this->storeDocument($data['document'] ?? null),
                'status' => 'pending',
                'requested_by' => Auth::id(),
            ]);

            $this->logWhatsappRequest($student, $discount);

            return $discount;
        });
    }

    private function storeDocument(?UploadedFile $document): ?string
    {
        if (! $document) {
            return null;
        }

        return $document->store('discount-documents', 'public');
    }

    public function approveDiscount(Discount $discount, ?string $notes = null): Discount
    {
        if ($discount->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending discounts can be approved.',
            ]);
        }

        return DB::transaction(function () use ($discount, $notes) {
            $discount->status = 'approved';
            $discount->decision_notes = $notes;
            $discount->approved_by = Auth::id();
            $discount->approved_at = now();
            $discount->save();

            // Apply discount to installments
            $this->applyDiscountToInstallments($discount->student, $discount->amount);

            $this->logWhatsappDecision($discount, 'approved');

            return $discount->load(['student']);
        });
    }

    /**
     * Apply approved discount to student's installments, online allowance, and total fee
     * IMPORTANT: Discounts are applied ONLY to online_allowance to avoid GST penalties
     */
    private function applyDiscountToInstallments(Student $student, float $discountAmount): void
    {
        $fee = $student->fee;
        if (!$fee) {
            return;
        }

        $originalTotalFee = $fee->total_fee;
        $originalCashAllowance = $fee->cash_allowance ?? 0;
        $originalOnlineAllowance = $fee->online_allowance ?? 0;

        // Apply discount ONLY to online_allowance (cash_allowance remains unchanged)
        // This prevents GST penalties when discounts are given
        $fee->online_allowance = max(0, $originalOnlineAllowance - $discountAmount);
        
        // Cash allowance remains unchanged
        // No need to update it as discounts only affect online allowance

        // Get all unpaid installments (where amount > paid_amount)
        $unpaidInstallments = $fee->installments()
            ->whereRaw('amount > paid_amount')
            ->orderBy('installment_number')
            ->get();

        if ($unpaidInstallments->isEmpty()) {
            // If all installments are paid, reduce the total fee only
            $fee->total_fee = max(0, $originalTotalFee - $discountAmount);
            $fee->save();
            return;
        }

        // Calculate total unpaid amount
        $totalUnpaidAmount = $unpaidInstallments->sum(function ($installment) {
            return $installment->amount - $installment->paid_amount;
        });

        if ($totalUnpaidAmount <= 0) {
            // Update total_fee and allowances even if no unpaid installments
            $fee->total_fee = max(0, $originalTotalFee - $discountAmount);
            $fee->save();
            return;
        }

        // Apply discount proportionally to each unpaid installment
        $remainingDiscount = $discountAmount;
        $installments = $unpaidInstallments->values();

        foreach ($installments as $index => $installment) {
            $unpaidAmount = $installment->amount - $installment->paid_amount;
            
            if ($remainingDiscount <= 0) {
                break;
            }

            // Calculate proportional discount for this installment
            $proportionalDiscount = ($unpaidAmount / $totalUnpaidAmount) * $discountAmount;
            
            // For the last installment, apply any remaining discount to avoid rounding issues
            if ($index === $installments->count() - 1) {
                $proportionalDiscount = $remainingDiscount;
            }

            // Reduce installment amount (but don't go below paid_amount)
            $newAmount = max($installment->paid_amount, $installment->amount - $proportionalDiscount);
            $installment->amount = $newAmount;
            $installment->save();

            $remainingDiscount -= $proportionalDiscount;
        }

        // Update total_fee to reflect the discount
        $fee->total_fee = max(0, $originalTotalFee - $discountAmount);
        $fee->save();
    }

    public function rejectDiscount(Discount $discount, ?string $notes = null): Discount
    {
        if ($discount->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Only pending discounts can be rejected.',
            ]);
        }

        return DB::transaction(function () use ($discount, $notes) {
            $discount->status = 'rejected';
            $discount->decision_notes = $notes;
            $discount->approved_by = Auth::id();
            $discount->approved_at = now();
            $discount->save();

            $this->logWhatsappDecision($discount, 'rejected');

            return $discount->load(['student']);
        });
    }

    private function ensureAmountIsValid(Student $student, float $amount): void
    {
        $totalFee = optional($student->fee)->total_fee ?? 0;
        $existingApproved = $student->discounts()->where('status', 'approved')->sum('amount');

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Discount amount must be greater than zero.',
            ]);
        }

        if ($amount + $existingApproved > $totalFee) {
            throw ValidationException::withMessages([
                'amount' => 'Discount amount exceeds total program fee.',
            ]);
        }
    }

    private function logWhatsappRequest(Student $student, Discount $discount): void
    {
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = sprintf(
            'Discount request submitted for ₹%s. Reason: %s.',
            number_format($discount->amount, 2),
            $discount->reason
        );

        WhatsappLog::create([
            'student_id' => $student->id,
            'message_type' => 'discount_request',
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
        ]);
    }

    private function logWhatsappDecision(Discount $discount, string $decision): void
    {
        $student = $discount->student;
        $phone = $student->guardian_1_whatsapp;

        if (! $phone) {
            return;
        }

        $message = match ($decision) {
            'approved' => sprintf(
                'Your discount request has been approved for ₹%s.',
                number_format($discount->amount, 2)
            ),
            default => 'Your discount request could not be approved at this time.',
        };

        WhatsappLog::create([
            'student_id' => $student->id,
            'message_type' => 'discount_'.$decision,
            'message_content' => $message,
            'phone_number' => $phone,
            'status' => 'queued',
        ]);
    }
}


