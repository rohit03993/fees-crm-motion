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
        // Validate installment belongs to student and get installment details
        $installment = \App\Models\Installment::where('id', $data['installment_id'])
            ->whereHas('fee.student', fn($q) => $q->where('id', $student->id))
            ->firstOrFail();

        // Validate discount amount against installment's unpaid amount
        $this->ensureAmountIsValidForInstallment($installment, $data['amount']);

        return DB::transaction(function () use ($student, $data) {
            $discount = Discount::create([
                'student_id' => $student->id,
                'installment_id' => $data['installment_id'],
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

            // Apply discount to the specific installment
            $this->applyDiscountToInstallments($discount->student, $discount->amount, $discount->installment_id);

            $this->logWhatsappDecision($discount, 'approved');

            return $discount->load(['student']);
        });
    }

    /**
     * Apply approved discount to the specific installment
     * IMPORTANT: Discounts are applied ONLY to online_allowance to avoid GST penalties
     */
    private function applyDiscountToInstallments(Student $student, float $discountAmount, int $installmentId): void
    {
        $fee = $student->fee;
        if (!$fee) {
            return;
        }

        $installment = \App\Models\Installment::where('id', $installmentId)
            ->where('student_fee_id', $fee->id)
            ->firstOrFail();

        $originalTotalFee = $fee->total_fee;
        $originalOnlineAllowance = $fee->online_allowance ?? 0;

        // Apply discount ONLY to online_allowance (cash_allowance remains unchanged)
        // This prevents GST penalties when discounts are given
        $fee->online_allowance = max(0, $originalOnlineAllowance - $discountAmount);
        
        // Apply discount to the specific installment
        // Reduce installment amount (but don't go below paid_amount)
        $unpaidAmount = $installment->amount - $installment->paid_amount;
        $newAmount = max($installment->paid_amount, $installment->amount - $discountAmount);
        $installment->amount = $newAmount;
        $installment->save();

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

    private function ensureAmountIsValidForInstallment(\App\Models\Installment $installment, float $amount): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Discount amount must be greater than zero.',
            ]);
        }

        // Calculate unpaid amount for this installment
        $unpaidAmount = $installment->amount - $installment->paid_amount;

        if ($unpaidAmount <= 0) {
            throw ValidationException::withMessages([
                'installment_id' => 'This installment is already fully paid. Cannot apply discount.',
            ]);
        }

        // Check if discount amount exceeds unpaid amount
        if ($amount > $unpaidAmount) {
            throw ValidationException::withMessages([
                'amount' => 'Discount amount (₹' . number_format($amount, 2) . ') cannot exceed the unpaid amount (₹' . number_format($unpaidAmount, 2) . ') for this installment.',
            ]);
        }

        // Check for existing approved discounts on this installment
        $existingApprovedDiscounts = \App\Models\Discount::where('installment_id', $installment->id)
            ->where('status', 'approved')
            ->sum('amount');

        $remainingUnpaidAfterExistingDiscounts = $unpaidAmount - $existingApprovedDiscounts;

        if ($amount > $remainingUnpaidAfterExistingDiscounts) {
            throw ValidationException::withMessages([
                'amount' => 'Discount amount (₹' . number_format($amount, 2) . ') exceeds the remaining unpaid amount (₹' . number_format($remainingUnpaidAfterExistingDiscounts, 2) . ') after existing discounts.',
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


