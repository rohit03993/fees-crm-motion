<?php

namespace Tests\Feature\Payments;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\StudentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnlineAllowancePenaltyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function gst_penalty_is_added_when_online_payments_exceed_allowance(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $course = Course::create([
            'name' => 'Foundation Course',
            'code' => 'FND',
            'duration_months' => 12,
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'name' => 'City Center',
            'code' => 'CITY',
            'is_active' => true,
        ]);

        /** @var StudentService $studentService */
        $studentService = app(StudentService::class);

        /** @var Student $student */
        $student = $studentService->createStudent([
            'name' => 'Online Heavy Student',
            'contact_number' => '9000000006',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'admission_date' => now()->toDateString(),
            'total_fee' => 80000,
            'cash_allowance' => 50000,
            'online_allowance' => 30000,
            'payment_mode' => 'installments',
            'installment_meta' => [
                'count' => 3,
                'frequency' => 1,
                'first_due_date' => now()->addMonth()->toDateString(),
            ],
            'installments' => [
                ['due_date' => now()->addMonth()->toDateString(), 'amount' => 30000],
                ['due_date' => now()->addMonths(2)->toDateString(), 'amount' => 30000],
                ['due_date' => now()->addMonths(3)->toDateString(), 'amount' => 20000],
            ],
        ]);

        /** @var PaymentService $paymentService */
        $paymentService = app(PaymentService::class);

        // First online payment within allowance
        $paymentService->recordPayment($student, [
            'installment_id' => $student->fee->installments()->first()->id,
            'amount_received' => 20000,
            'payment_mode' => 'upi',
            'payment_date' => now()->toDateString(),
            'auto_apply' => false,
        ]);

        $student->refresh();
        $this->assertCount(0, $student->miscCharges()->get());

        // Second online payment pushes the total 5k over allowance
        $paymentService->recordPayment($student, [
            'installment_id' => $student->fee->installments()->skip(1)->first()->id,
            'amount_received' => 15000,
            'payment_mode' => 'upi',
            'payment_date' => now()->addDays(10)->toDateString(),
            'auto_apply' => false,
        ]);

        $student->refresh();

        $this->assertCount(1, $student->miscCharges);
        $penalty = $student->miscCharges->first();
        $this->assertStringContainsString('GST Penalty', $penalty->label);
        $this->assertEquals(5900.0, (float) $penalty->amount); // 5,000 overage + 18% GST
    }
}


