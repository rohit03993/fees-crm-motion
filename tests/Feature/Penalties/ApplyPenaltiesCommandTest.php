<?php

namespace Tests\Feature\Penalties;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Installment;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ApplyPenaltiesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_applies_penalties_after_grace_period(): void
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
            'student_uid' => 'STU-PEN-001',
            'name' => 'Penalty Candidate',
            'contact_number' => '9000000001',
            'whatsapp_number' => '9000000001',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'admission_date' => now()->subMonths(1)->toDateString(),
            'program_start_date' => now()->subWeeks(3)->toDateString(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $fee = StudentFee::create([
            'student_id' => $student->id,
            'total_fee' => 10000,
            'cash_allowance' => 10000,
            'online_allowance' => 0,
            'payment_mode' => 'installments',
        ]);

        $dueDate = now()->subDays(8)->toDateString();

        $installment = Installment::create([
            'student_fee_id' => $fee->id,
            'installment_number' => 1,
            'due_date' => $dueDate,
            'amount' => 10000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $today = now()->toDateString();

        Artisan::call('penalties:apply', ['--date' => $today]);

        $installment->refresh();

        $this->assertEquals('overdue', $installment->status);

        $penalty = $student->penalties()->first();

        $this->assertNotNull($penalty);
        $this->assertEquals($installment->id, $penalty->installment_id);
        $this->assertSame('auto', $penalty->penalty_type);
        $this->assertEquals(3, $penalty->days_delayed); // 8 days late minus 5 grace days
        $this->assertEquals(450.0, (float) $penalty->penalty_amount);
        $this->assertDatabaseHas('penalties', [
            'installment_id' => $installment->id,
            'penalty_type' => 'auto',
        ]);
    }
}


