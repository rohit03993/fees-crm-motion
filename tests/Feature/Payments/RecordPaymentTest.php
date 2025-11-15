<?php

namespace Tests\Feature\Payments;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Installment;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\User;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_record_payment_and_update_installment(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $course = Course::create([
            'name' => 'Integration Program',
            'code' => 'INTG',
            'duration_months' => 12,
        ]);

        $branch = Branch::create([
            'name' => 'Main',
            'code' => 'MAIN',
        ]);

        $student = Student::create([
            'student_uid' => 'STU-2025-TEST1',
            'name' => 'Test Student',
            'contact_number' => '9999999999',
            'whatsapp_number' => '9999999999',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'admission_date' => now()->subDays(5)->toDateString(),
            'program_start_date' => now()->toDateString(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $fee = StudentFee::create([
            'student_id' => $student->id,
            'total_fee' => 10000,
            'cash_allowance' => 0,
            'online_allowance' => 20000,
            'payment_mode' => 'installments',
            'installment_count' => 1,
        ]);

        $installment = Installment::create([
            'student_fee_id' => $fee->id,
            'installment_number' => 1,
            'due_date' => now()->toDateString(),
            'amount' => 10000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('students.payments.store', $student), [
                'amount_received' => 10000,
                'payment_mode' => 'upi',
                'payment_date' => now()->toDateString(),
                'installment_id' => $installment->id,
                'transaction_id' => 'TXN123',
                'deposited_to' => 'HDFC',
                'remarks' => 'Paid via UPI',
            ])
            ->assertRedirect(route('students.show', $student))
            ->assertSessionHas('success');

        $payment = $student->payments()->first();

        $this->assertNotNull($payment);
        $this->assertEquals(10000.0, (float) $payment->amount_received);
        $this->assertEquals(10000.0, (float) $payment->base_amount); // No GST calculation
        $this->assertEquals(0.0, (float) $payment->gst_amount); // No GST on payment
        $this->assertEquals(0.0, (float) $payment->gst_percentage); // No GST percentage
        $this->assertEquals($installment->id, $payment->installment_id);

        $installment->refresh();
        $this->assertEquals(10000.0, (float) $installment->paid_amount);
        $this->assertEquals('paid', $installment->status);

        $this->assertEquals(1, WhatsappLog::count());
        $log = WhatsappLog::first();

        $this->assertEquals($student->id, $log->student_id);
        $this->assertEquals('payment_receipt', $log->message_type);
        $this->assertEquals('queued', $log->status);
    }
}


