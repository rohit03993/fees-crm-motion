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
        Schema::table('students', function (Blueprint $table) {
            $table->string('student_photo')->nullable()->after('name');
            $table->string('guardian_1_photo')->nullable()->after('guardian_1_relation');
            $table->string('guardian_2_photo')->nullable()->after('guardian_2_relation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['student_photo', 'guardian_1_photo', 'guardian_2_photo']);
        });
    }
};
