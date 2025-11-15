<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_fee_id')->constrained('student_fees')->cascadeOnDelete();
            $table->unsignedInteger('installment_number');
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'partially_paid', 'paid', 'overdue', 'rescheduled', 'waived'])->default('pending');
            $table->date('grace_end_date')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamps();

            $table->unique(['student_fee_id', 'installment_number']);
            $table->index(['due_date']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};

