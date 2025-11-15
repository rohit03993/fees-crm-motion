<?php

namespace Tests\Feature\Reschedules;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Installment;
use App\Models\Reschedule;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\User;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RescheduleWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createStudentWithInstallment(): array
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $course = Course::create([
            'name' => 'Integrated Program',
            'code' => 'INT',
            'duration_months' => 12,
        ]);

        $branch = Branch::create([
            'name' => 'Main',
            'code' => 'MAIN',
        ]);

        $student = Student::create([
            'student_uid' => 'STU-RS-001',
            'name' => 'Reschedule Student',
            'contact_number' => '9000000003',
            'whatsapp_number' => '9000000003',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'admission_date' => now()->subWeeks(2)->toDateString(),
            'program_start_date' => now()->subWeek()->toDateString(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $fee = StudentFee::create([
            'student_id' => $student->id,
            'total_fee' => 50000,
            'cash_allowance' => 30000,
            'online_allowance' => 20000,
            'payment_mode' => 'installments',
        ]);

        $installment = Installment::create([
            'student_fee_id' => $fee->id,
            'installment_number' => 1,
            'due_date' => now()->addDays(7)->toDateString(),
            'amount' => 25000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        return [$student, $installment];
    }

    #[Test]
    public function staff_can_request_reschedule_and_is_limited_to_two_attempts(): void
    {
        [$student, $installment] = $this->createStudentWithInstallment();

        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        $newDate = now()->addDays(14)->toDateString();

        $response = $this->actingAs($staff)
            ->from(route('students.show', $student))
            ->post(route('students.reschedules.store', $student), [
                'installment_id' => $installment->id,
                'new_due_date' => $newDate,
                'reason' => 'Parent requested additional time due to travel plans.',
            ]);

        $response->assertRedirect(route('students.show', $student));
        $response->assertSessionHas('success');

        $this->assertEquals(1, Reschedule::count());
        $reschedule = Reschedule::first();
        $this->assertEquals('pending', $reschedule->status);
        $this->assertEquals(1, $reschedule->attempt_number);
        $this->assertEquals($newDate, $reschedule->new_due_date->toDateString());

        $this->assertEquals(1, WhatsappLog::where('message_type', 'reschedule_request')->count());

        // second attempt allowed
        $this->actingAs($staff)->post(route('students.reschedules.store', $student), [
            'installment_id' => $installment->id,
            'new_due_date' => now()->addDays(21)->toDateString(),
            'reason' => 'Follow-up request after discussion with parent.',
        ])->assertRedirect(route('students.show', $student));

        $this->assertEquals(2, Reschedule::where('installment_id', $installment->id)->count());

        // third attempt should fail
        $this->actingAs($staff)->post(route('students.reschedules.store', $student), [
            'installment_id' => $installment->id,
            'new_due_date' => now()->addDays(28)->toDateString(),
            'reason' => 'Another request exceeding attempt limit.',
        ])->assertRedirect()
            ->assertSessionHasErrors(['installment_id']);

        $this->assertEquals(2, Reschedule::where('installment_id', $installment->id)->count());
    }

    #[Test]
    public function admin_can_approve_reschedule_and_update_installment(): void
    {
        [$student, $installment] = $this->createStudentWithInstallment();

        $reschedule = Reschedule::create([
            'student_id' => $student->id,
            'installment_id' => $installment->id,
            'old_due_date' => $installment->due_date,
            'new_due_date' => now()->addDays(20),
            'reason' => 'Parent requested more time for payment.',
            'attempt_number' => 1,
            'status' => 'pending',
            'requested_by' => null,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('reschedules.index'))
            ->put(route('reschedules.update', $reschedule), [
                'decision' => 'approved',
                'decision_notes' => 'Approved after reviewing payment history.',
            ]);

        $response->assertRedirect(route('reschedules.index'));
        $response->assertSessionHas('success');

        $reschedule->refresh();
        $installment->refresh();

        $this->assertEquals('approved', $reschedule->status);
        $this->assertNotNull($reschedule->approved_at);
        $this->assertEquals('rescheduled', $installment->status);
        $this->assertEquals($reschedule->new_due_date->toDateString(), $installment->due_date->toDateString());

        $this->assertGreaterThan(0, WhatsappLog::where('message_type', 'reschedule_approved')->count());
    }
}


