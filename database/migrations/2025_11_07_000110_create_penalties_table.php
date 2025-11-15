<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained('installments')->cascadeOnDelete();
            $table->enum('penalty_type', ['auto', 'manual'])->default('auto');
            $table->unsignedInteger('days_delayed');
            $table->decimal('penalty_rate', 8, 2);
            $table->decimal('penalty_amount', 12, 2);
            $table->date('applied_date');
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['installment_id', 'applied_date', 'penalty_type'], 'penalties_unique_installment_date_type');
            $table->index(['student_id', 'applied_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};


