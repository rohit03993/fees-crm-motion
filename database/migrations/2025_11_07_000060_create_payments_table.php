<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('installment_id')->nullable()->constrained('installments')->nullOnDelete();
            $table->enum('payment_mode', ['cash', 'card', 'upi', 'bank_transfer', 'cheque', 'other'])->default('cash');
            $table->decimal('amount_received', 12, 2);
            $table->decimal('base_amount', 12, 2)->default(0);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('gst_percentage', 5, 2)->default(0);
            $table->string('transaction_id')->nullable();
            $table->string('deposited_to')->nullable();
            $table->date('payment_date');
            $table->string('status')->default('recorded');
            $table->text('remarks')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['payment_date']);
            $table->index(['status']);
            $table->index(['payment_mode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};


