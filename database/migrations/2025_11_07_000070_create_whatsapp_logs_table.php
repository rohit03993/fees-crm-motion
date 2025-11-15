<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('message_type');
            $table->text('message_content');
            $table->string('phone_number');
            $table->string('status')->default('pending');
            $table->json('response_data')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['message_type']);
            $table->index(['status']);
            $table->index(['sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_logs');
    }
};


