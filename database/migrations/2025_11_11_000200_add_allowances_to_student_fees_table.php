<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_fees', function (Blueprint $table) {
            $table->decimal('cash_allowance', 12, 2)->default(0)->after('total_fee');
            $table->decimal('online_allowance', 12, 2)->default(0)->after('cash_allowance');
        });
    }

    public function down(): void
    {
        Schema::table('student_fees', function (Blueprint $table) {
            $table->dropColumn(['cash_allowance', 'online_allowance']);
        });
    }
};


