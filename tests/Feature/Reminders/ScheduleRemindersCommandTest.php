<?php

namespace Tests\Feature\Reminders;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Installment;
use App\Models\InstallmentReminder;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\User;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_schedules_overdue_reminders_and_logs_whatsapp_entry(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $course = Course::create([
            'name' => 'Foundation',
            'code' => 'FND',
            'duration_months' => 12,
        ]);

        $branch = Branch::create([
            'name' => 'City Center',
            'code' => 'CITY',
        ]);

        $student = Student::create([
            'student_uid' => 'STU-REM-001',
            'name' => 'Reminder Candidate',
            'contact_number' => '9000000002',
            'whatsapp_number' => '9000000002',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'admission_date' => now()->subMonths(1)->toDateString(),
            'program_start_date' => now()->subWeeks(3)->toDateString(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $fee = StudentFee::create([
            'student_id' => $student->id,
            'total_fee' => 15000,
            'cash_allowance' => 15000,
            'online_allowance' => 0,
            'payment_mode' => 'installments',
        ]);

        $installment = Installment::create([
            'student_fee_id' => $fee->id,
            'installment_number' => 1,
            'due_date' => now()->subDays(2)->toDateString(),
            'amount' => 15000,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $today = now()->toDateString();

        Artisan::call('reminders:schedule', ['--date' => $today]);

        $this->assertEquals(1, InstallmentReminder::count());

        $reminder = InstallmentReminder::first();

        $this->assertEquals($installment->id, $reminder->installment_id);
        $this->assertEquals('queued', $reminder->status);

        $this->assertEquals(1, WhatsappLog::where('message_type', 'installment_reminder')->count());

        Artisan::call('reminders:schedule', ['--date' => now()->addDay()->toDateString()]);

        $this->assertEquals(1, InstallmentReminder::count(), 'Reminder cadence should prevent duplicates within configured window.');
    }
}


