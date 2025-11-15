<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('installment_id')->constrained('installments')->cascadeOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('reminder_type')->default('overdue');
            $table->date('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['installment_id', 'reminder_type', 'scheduled_for'], 'reminders_unique_installment_type_day');
            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_reminders');
    }
};


