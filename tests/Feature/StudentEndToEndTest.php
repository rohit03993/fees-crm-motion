<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Course;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentEndToEndTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function student_can_be_created_with_installments_and_payment_recorded(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $course = Course::create([
            'name' => 'Integrated Program',
            'code' => 'INT',
            'duration_months' => 12,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'name' => 'Main Campus',
            'code' => 'MAIN',
            'is_active' => true,
        ]);

        $studentService = app(StudentService::class);

        $student = $studentService->createStudent([
            'name' => 'End To End Student',
            'father_name' => 'Guardian',
            'contact_number' => '9000000004',
            'whatsapp_number' => '9000000004',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'admission_date' => now()->toDateString(),
            'total_fee' => 60000,
            'cash_allowance' => 30000,
            'online_allowance' => 30000,
            'payment_mode' => 'installments',
            'installment_meta' => [
                'count' => 3,
                'frequency' => 1,
                'notes' => 'Monthly plan',
                'first_due_date' => now()->addMonth()->toDateString(),
            ],
            'installments' => [
                ['due_date' => now()->addMonth()->toDateString(), 'amount' => 20000],
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => 20000],
                ['due_date' => now()->addMonths(3)->toDateString(), 'amount' => 20000],
            ],
        ]);

        $this->assertEquals(3, $student->fee->installments()->count());
        $this->assertEquals(60000.0, (float) $student->fee->total_fee);
        $this->assertEquals(30000.0, (float) $student->fee->cash_allowance);
        $this->assertEquals(30000.0, (float) $student->fee->online_allowance);

        $paymentService = app(PaymentService::class);

        $payment = $paymentService->recordPayment($student, [
            'installment_id' => $student->fee->installments()->first()->id,
            'amount_received' => 20000,
            'payment_mode' => 'upi',
            'payment_date' => now()->toDateString(),
            'transaction_id' => 'E2E123',
            'deposited_to' => 'HDFC',
            'remarks' => 'Initial payment',
            'auto_apply' => false,
        ]);

        $this->assertEquals(20000.0, (float) $payment->amount_received);
        $this->assertEquals(20000.0, (float) $payment->base_amount); // No GST calculation
        $this->assertEquals(0.0, (float) $payment->gst_amount); // No GST on payment
        $installment = $student->fee->installments()->first();
        $installment->refresh();
        $this->assertEquals(20000.0, (float) $installment->paid_amount);
        $this->assertEquals('paid', $installment->status);
    }
}


