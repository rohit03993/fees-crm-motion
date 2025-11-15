<?php

namespace Database\Seeders;

use App\Services\StudentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class StudentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(StudentService::class);

        $students = [
            [
                'name' => 'Rohan Sharma',
                'father_name' => 'Mahesh Sharma',
                'contact_number' => '9876543210',
                'whatsapp_number' => '9876543210',
                'course_id' => 1,
                'branch_id' => 1,
                'admission_date' => Carbon::now()->subDays(10)->toDateString(),
                'program_start_date' => Carbon::now()->toDateString(),
                'total_fee' => 85000,
                'cash_allowance' => 85000,
                'online_allowance' => 0,
                'payment_mode' => 'full',
                'installment_meta' => [
                    'count' => 1,
                    'frequency' => 0,
                    'notes' => 'Full payment collected at admission',
                    'first_due_date' => Carbon::now()->toDateString(),
                ],
                'installments' => [
                    [
                        'installment_number' => 1,
                        'due_date' => Carbon::now()->toDateString(),
                        'amount' => 85000,
                    ],
                ],
                'misc_charges' => [
                    [
                        'label' => 'Study Material Kit',
                        'amount' => 3500,
                        'due_date' => Carbon::now()->addDays(7)->toDateString(),
                    ],
                ],
            ],
            [
                'name' => 'Tanvi Jain',
                'father_name' => 'Suresh Jain',
                'contact_number' => '9123456780',
                'whatsapp_number' => '9123456780',
                'course_id' => 2,
                'branch_id' => 2,
                'admission_date' => Carbon::now()->subDays(20)->toDateString(),
                'program_start_date' => Carbon::now()->subDays(5)->toDateString(),
                'total_fee' => 99000,
                'cash_allowance' => 60000,
                'online_allowance' => 39000,
                'payment_mode' => 'installments',
                'installment_meta' => [
                    'count' => 5,
                    'frequency' => 2,
                    'notes' => 'Five installments every 2 months',
                    'first_due_date' => Carbon::now()->toDateString(),
                ],
                'installments' => [
                    ['installment_number' => 1, 'due_date' => Carbon::now()->toDateString(), 'amount' => 20000],
                    ['installment_number' => 2, 'due_date' => Carbon::now()->addMonths(2)->toDateString(), 'amount' => 20000],
                    ['installment_number' => 3, 'due_date' => Carbon::now()->addMonths(4)->toDateString(), 'amount' => 20000],
                    ['installment_number' => 4, 'due_date' => Carbon::now()->addMonths(6)->toDateString(), 'amount' => 20000],
                    ['installment_number' => 5, 'due_date' => Carbon::now()->addMonths(8)->toDateString(), 'amount' => 19000],
                ],
                'misc_charges' => [
                    ['label' => 'Jacket Fee', 'amount' => 1800, 'due_date' => Carbon::now()->addDays(30)->toDateString()],
                    ['label' => 'Lab Charges', 'amount' => 2200, 'due_date' => Carbon::now()->addMonths(3)->toDateString()],
                ],
            ],
            [
                'name' => 'Aditi Verma',
                'father_name' => 'Vikas Verma',
                'contact_number' => '9012345678',
                'whatsapp_number' => '9012345678',
                'course_id' => 3,
                'branch_id' => 3,
                'admission_date' => Carbon::now()->subDays(5)->toDateString(),
                'program_start_date' => Carbon::now()->addDays(10)->toDateString(),
                'total_fee' => 76000,
                'cash_allowance' => 30000,
                'online_allowance' => 46000,
                'payment_mode' => 'installments',
                'installment_meta' => [
                    'count' => 3,
                    'frequency' => 1,
                    'notes' => 'Monthly installments',
                    'first_due_date' => Carbon::now()->addDays(15)->toDateString(),
                ],
                'installments' => [
                    ['installment_number' => 1, 'due_date' => Carbon::now()->addDays(15)->toDateString(), 'amount' => 26000],
                    ['installment_number' => 2, 'due_date' => Carbon::now()->addMonths(1)->toDateString(), 'amount' => 25000],
                    ['installment_number' => 3, 'due_date' => Carbon::now()->addMonths(2)->toDateString(), 'amount' => 25000],
                ],
                'misc_charges' => [
                    ['label' => 'Transport Pass', 'amount' => 3000, 'due_date' => Carbon::now()->addDays(20)->toDateString()],
                ],
            ],
        ];

        foreach ($students as $studentData) {
            $service->createStudent($studentData);
        }
    }
}
