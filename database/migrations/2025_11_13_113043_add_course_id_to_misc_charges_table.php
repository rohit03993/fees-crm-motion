<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('misc_charges', function (Blueprint $table) {
            // Add course_id to link charges to courses (for course-level charges)
            // If course_id is set, this charge applies to all students in that course
            // If course_id is null, it's a student-specific charge
            $table->foreignId('course_id')->nullable()->after('student_id')->constrained('courses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('misc_charges', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }
};
