<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if payments table exists and has payment_mode column
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_mode')) {
            // First, update any existing 'card' or 'other' payments to 'upi'
            DB::table('payments')
                ->whereIn('payment_mode', ['card', 'other'])
                ->update(['payment_mode' => 'upi']);

            // For MySQL, we need to alter the enum column
            // Note: This uses raw SQL as Laravel doesn't support modifying enum columns directly
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE payments MODIFY COLUMN payment_mode ENUM('cash', 'upi', 'bank_transfer', 'cheque') DEFAULT 'cash'");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the original enum values
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'payment_mode')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE payments MODIFY COLUMN payment_mode ENUM('cash', 'card', 'upi', 'bank_transfer', 'cheque', 'other') DEFAULT 'cash'");
            }
        }
    }
};
