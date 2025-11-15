<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add guardian 1 fields
            $table->string('guardian_1_name')->nullable()->after('father_name');
            $table->string('guardian_1_whatsapp')->nullable()->after('guardian_1_name');
            $table->string('guardian_1_relation')->nullable()->after('guardian_1_whatsapp');
            
            // Add guardian 2 fields
            $table->string('guardian_2_name')->nullable()->after('guardian_1_relation');
            $table->string('guardian_2_whatsapp')->nullable()->after('guardian_2_name');
            $table->string('guardian_2_relation')->nullable()->after('guardian_2_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'guardian_1_name',
                'guardian_1_whatsapp',
                'guardian_1_relation',
                'guardian_2_name',
                'guardian_2_whatsapp',
                'guardian_2_relation',
            ]);
        });
    }
};
