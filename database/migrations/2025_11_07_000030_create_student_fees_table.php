<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('total_fee', 12, 2);
            $table->enum('payment_mode', ['full', 'installments'])->default('full');
            $table->unsignedInteger('installment_count')->nullable();
            $table->unsignedInteger('installment_frequency_months')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_fees');
    }
};

