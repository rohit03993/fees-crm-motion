<?php

namespace Tests\Feature\Discounts;

use App\Models\Branch;
use App\Models\Course;
use App\Models\Discount;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\User;
use App\Models\WhatsappLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscountWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function createStudent(): Student
    {
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

        $student = Student::create([
            'student_uid' => 'STU-DISC-001',
            'name' => 'Discount Candidate',
            'contact_number' => '9000000005',
            'whatsapp_number' => '9000000005',
            'course_id' => $course->id,
            'branch_id' => $branch->id,
            'admission_date' => now()->subWeeks(2)->toDateString(),
            'program_start_date' => now()->subWeek()->toDateString(),
            'status' => 'active',
        ]);

        StudentFee::create([
            'student_id' => $student->id,
            'total_fee' => 50000,
            'cash_allowance' => 50000,
            'online_allowance' => 0,
            'payment_mode' => 'installments',
        ]);

        return $student;
    }

    #[Test]
    public function staff_can_request_discount_with_document(): void
    {
        Storage::fake('public');

        $student = $this->createStudent();

        $staff = User::factory()->create([
            'role' => 'staff',
            'is_active' => true,
        ]);

        $document = UploadedFile::fake()->create('supporting.pdf', 120, 'application/pdf');

        $response = $this->actingAs($staff)
            ->from(route('students.show', $student))
            ->post(route('students.discounts.store', $student), [
                'amount' => 4000,
                'reason' => 'Sibling studying with us; extend loyalty benefit.',
                'document' => $document,
            ]);

        $response->assertRedirect(route('students.show', $student));
        $response->assertSessionHas('success');

        $discount = Discount::first();

        $this->assertNotNull($discount);
        $this->assertEquals('pending', $discount->status);
        Storage::disk('public')->assertExists($discount->document_path);

        $this->assertEquals(1, WhatsappLog::where('message_type', 'discount_request')->count());
    }

    #[Test]
    public function admin_can_approve_discount_and_log_decision(): void
    {
        $student = $this->createStudent();

        $discount = Discount::create([
            'student_id' => $student->id,
            'amount' => 3500,
            'reason' => 'Merit scholarship adjustment',
            'status' => 'pending',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('discounts.index'))
            ->put(route('discounts.update', $discount), [
                'decision' => 'approved',
                'decision_notes' => 'Approved after reviewing marksheets.',
            ]);

        $response->assertRedirect(route('discounts.index'));
        $response->assertSessionHas('success');

        $discount->refresh();

        $this->assertEquals('approved', $discount->status);
        $this->assertNotNull($discount->approved_at);
        $this->assertEquals('Approved after reviewing marksheets.', $discount->decision_notes);

        $this->assertEquals(1, WhatsappLog::where('message_type', 'discount_approved')->count());
    }
}


