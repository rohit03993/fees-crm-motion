<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            ['name' => 'Integrated Program', 'code' => 'INT', 'duration_months' => 24],
            ['name' => 'Foundation Course', 'code' => 'FND', 'duration_months' => 12],
            ['name' => 'Crash Course', 'code' => 'CRH', 'duration_months' => 6],
        ];

        foreach ($courses as $course) {
            Course::updateOrCreate(
                ['code' => $course['code']],
                $course
            );
        }
    }
}

