<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRemainingInstallmentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Installment;
use App\Models\Student;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService)
    {
    }

    public function store(StorePaymentRequest $request, Student $student): RedirectResponse
    {
        try {
            $this->paymentService->recordPayment($student, $request->validated());

            return redirect()
                ->route('students.show', $student)
                ->with('success', 'Payment recorded successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('students.show', $student)
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->route('students.show', $student)
                ->with('error', 'Failed to record payment: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function createRemainingInstallment(CreateRemainingInstallmentRequest $request, Student $student, Installment $installment): RedirectResponse
    {
        // Load the fee relationship
        $installment->load('fee');
        
        // Verify the installment belongs to the student
        if (!$installment->fee || $installment->fee->student_id !== $student->id) {
            abort(404);
        }

        // Check if there's actually a remaining amount
        $remainingAmount = $installment->amount - $installment->paid_amount;
        if ($remainingAmount <= 0.01) {
            return redirect()
                ->route('students.show', $student)
                ->with('error', 'This installment has no remaining amount.');
        }

        // Create the remaining installment
        $this->paymentService->createRemainingInstallment(
            $student,
            $installment,
            $request->validated()['due_date'],
            $remainingAmount
        );

        return redirect()
            ->route('students.show', $student)
            ->with('success', 'New installment created for remaining amount successfully.');
    }
}


