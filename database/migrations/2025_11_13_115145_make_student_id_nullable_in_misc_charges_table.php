<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the foreign key constraint first
        Schema::table('misc_charges', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        // Modify the column to be nullable
        Schema::table('misc_charges', function (Blueprint $table) {
            // Make student_id nullable to support course-level charges
            // null = course-level charge (template)
            // set = student-specific instance
            $table->foreignId('student_id')->nullable()->change();
        });

        // Recreate the foreign key constraint with nullable support
        Schema::table('misc_charges', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Drop the foreign key constraint
        Schema::table('misc_charges', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
        });

        // Make student_id NOT NULL again
        // Note: This will fail if there are any null values
        DB::statement('ALTER TABLE `misc_charges` MODIFY `student_id` BIGINT UNSIGNED NOT NULL');

        // Recreate the foreign key constraint
        Schema::table('misc_charges', function (Blueprint $table) {
            $table->foreign('student_id')->references('id')->on('students')->cascadeOnDelete();
        });
    }
};
