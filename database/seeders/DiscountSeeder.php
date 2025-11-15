<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Models\Student;
use Illuminate\Database\Seeder;

class DiscountSeeder extends Seeder
{
    public function run(): void
    {
        $student = Student::first();

        if (! $student) {
            return;
        }

        Discount::create([
            'student_id' => $student->id,
            'amount' => 5000,
            'reason' => 'Early bird scholarship adjustment',
            'status' => 'approved',
            'requested_by' => null,
            'approved_by' => null,
            'approved_at' => now()->subDays(3),
        ]);
    }
}


