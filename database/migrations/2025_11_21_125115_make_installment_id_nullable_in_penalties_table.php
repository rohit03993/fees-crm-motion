<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            // Drop the foreign key constraint first
            $table->dropForeign(['installment_id']);
            
            // Drop the unique constraint that includes installment_id
            $table->dropUnique('penalties_unique_installment_date_type');
            
            // Make installment_id nullable
            $table->foreignId('installment_id')->nullable()->change();
            
            // Re-add the foreign key with nullOnDelete
            $table->foreign('installment_id')->references('id')->on('installments')->nullOnDelete();
            
            // Re-add the unique constraint (MySQL allows multiple NULLs in unique constraints)
            $table->unique(['installment_id', 'applied_date', 'penalty_type'], 'penalties_unique_installment_date_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('penalties', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['installment_id']);
            
            // Drop the unique constraint
            $table->dropUnique('penalties_unique_installment_date_type');
            
            // Make installment_id not nullable
            $table->foreignId('installment_id')->nullable(false)->change();
            
            // Re-add the foreign key with cascadeOnDelete
            $table->foreign('installment_id')->references('id')->on('installments')->cascadeOnDelete();
            
            // Re-add the unique constraint
            $table->unique(['installment_id', 'applied_date', 'penalty_type'], 'penalties_unique_installment_date_type');
        });
    }
};
