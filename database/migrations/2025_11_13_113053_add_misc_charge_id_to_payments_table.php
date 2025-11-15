<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add misc_charge_id to link payments to miscellaneous charges
            // If misc_charge_id is set, this payment is for a misc charge
            // If installment_id is set, this payment is for an installment
            // Only one should be set at a time
            $table->foreignId('misc_charge_id')->nullable()->after('installment_id')->constrained('misc_charges')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['misc_charge_id']);
            $table->dropColumn('misc_charge_id');
        });
    }
};
