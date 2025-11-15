<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentFee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StudentService
{
    public function createStudent(array $data): Student
    {
        return DB::transaction(function () use ($data) {
            // Handle photo uploads
            $studentPhotoPath = $this->storePhoto($data['student_photo'] ?? null, 'students');
            $guardian1PhotoPath = $this->storePhoto($data['guardian_1_photo'] ?? null, 'guardians');
            $guardian2PhotoPath = $this->storePhoto($data['guardian_2_photo'] ?? null, 'guardians');
            
            $student = Student::create([
                'student_uid' => $this->generateStudentUid(),
                'name' => $data['name'],
                'student_photo' => $studentPhotoPath,
                'father_name' => $data['father_name'] ?? null,
                'guardian_1_name' => $data['guardian_1_name'],
                'guardian_1_whatsapp' => $data['guardian_1_whatsapp_formatted'] ?? ('+91' . ($data['guardian_1_whatsapp'] ?? '')),
                'guardian_1_relation' => $data['guardian_1_relation'],
                'guardian_1_photo' => $guardian1PhotoPath,
                'guardian_2_name' => $data['guardian_2_name'] ?? null,
                'guardian_2_whatsapp' => $data['guardian_2_whatsapp_formatted'] ?? null,
                'guardian_2_relation' => $data['guardian_2_relation'] ?? null,
                'guardian_2_photo' => $guardian2PhotoPath,
                'course_id' => $data['course_id'],
                'branch_id' => $data['branch_id'],
                'admission_date' => $data['admission_date'],
                'program_start_date' => $data['program_start_date'] ?? null,
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            $fee = StudentFee::create([
                'student_id' => $student->id,
                'total_fee' => $data['total_fee'],
                'cash_allowance' => $data['cash_allowance'],
                'online_allowance' => $data['online_allowance'],
                'payment_mode' => $data['payment_mode'],
                'installment_count' => $data['installment_meta']['count'] ?? null,
                'installment_frequency_months' => $data['installment_meta']['frequency'] ?? 1, // Default to 1 month
                'notes' => $data['installment_meta']['notes'] ?? null,
            ]);

            $installments = collect($data['installments'] ?? [])
                ->sortBy('installment_number')
                ->values()
                ->all();

            foreach ($installments as $index => $installment) {
                $fee->installments()->create([
                    'installment_number' => $index + 1,
                    'due_date' => $installment['due_date'],
                    'amount' => $installment['amount'],
                    'original_amount' => $installment['amount'], // Store original amount for record keeping
                    'grace_end_date' => $installment['grace_end_date'] ?? null,
                ]);
            }

            // Add student-specific misc charges (if any provided during enrollment)
            foreach ($data['misc_charges'] ?? [] as $charge) {
                $student->miscCharges()->create([
                    'course_id' => $data['course_id'],
                    'label' => $charge['label'],
                    'amount' => $charge['amount'],
                    'due_date' => $charge['due_date'] ?? null,
                    'status' => 'pending',
                    'created_by' => Auth::id(),
                ]);
            }

            // Automatically apply course-level misc charges to this student
            $courseLevelCharges = \App\Models\MiscCharge::where('course_id', $data['course_id'])
                ->whereNull('student_id')
                ->get();

            foreach ($courseLevelCharges as $courseCharge) {
                // Check if this charge already exists for this student
                $exists = $student->miscCharges()
                    ->where('course_id', $data['course_id'])
                    ->where('label', $courseCharge->label)
                    ->where('amount', $courseCharge->amount)
                    ->exists();

                if (!$exists) {
                    $student->miscCharges()->create([
                        'course_id' => $data['course_id'],
                        'label' => $courseCharge->label,
                        'amount' => $courseCharge->amount,
                        'due_date' => $courseCharge->due_date,
                        'status' => 'pending',
                        'created_by' => $courseCharge->created_by ?? Auth::id(),
                    ]);
                }
            }

            return $student->load(['course', 'branch', 'fee.installments', 'miscCharges']);
        });
    }

    private function generateStudentUid(): string
    {
        do {
            $uid = 'STU-' . now()->format('Y') . '-' . Str::upper(Str::random(5));
        } while (Student::where('student_uid', $uid)->exists());

        return $uid;
    }

    private function storePhoto($file, string $folder): ?string
    {
        if (!$file) {
            return null;
        }

        // Generate unique filename
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
        
        // Store in public/students or public/guardians
        $path = $file->storeAs($folder, $filename, 'public');
        
        return $path;
    }
}
