<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->enum('transaction_type', ['credit', 'debit']); // credit = added, debit = used
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2); // Credit balance after this transaction
            $table->string('description')->nullable(); // e.g., "Overpayment from Payment #123", "Applied to Payment #456"
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('student_id');
            $table->index('payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_balance_transactions');
    }
};
