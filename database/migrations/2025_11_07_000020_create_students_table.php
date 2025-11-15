<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_uid')->unique();
            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('contact_number');
            $table->string('whatsapp_number')->nullable();
            $table->foreignId('course_id')->constrained('courses');
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('admission_date');
            $table->date('program_start_date')->nullable();
            $table->enum('status', ['active', 'inactive', 'archived'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['course_id']);
            $table->index(['branch_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};

