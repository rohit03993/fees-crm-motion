<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->foreignId('misc_charge_id')
                ->nullable()
                ->after('installment_id')
                ->constrained('misc_charges')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_logs', function (Blueprint $table) {
            $table->dropForeign(['misc_charge_id']);
            $table->dropColumn('misc_charge_id');
        });
    }
};
