<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->foreignId('installment_id')
                ->nullable()
                ->after('student_id')
                ->constrained('installments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->dropForeign(['installment_id']);
            $table->dropColumn('installment_id');
        });
    }
};


