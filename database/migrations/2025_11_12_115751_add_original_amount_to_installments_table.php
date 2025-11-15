<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->decimal('original_amount', 12, 2)->nullable()->after('amount');
        });

        // Set original_amount = amount for all existing installments
        DB::table('installments')->update(['original_amount' => DB::raw('amount')]);
        
        // Make it not nullable after setting values
        Schema::table('installments', function (Blueprint $table) {
            $table->decimal('original_amount', 12, 2)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('installments', function (Blueprint $table) {
            $table->dropColumn('original_amount');
        });
    }
};
