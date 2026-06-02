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
        Schema::table('academic_records', function (Blueprint $table) {
            $table->foreignId('course_program_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('course_programs')
                ->nullOnDelete();
            $table->foreignId('archived_by')
                ->nullable()
                ->after('approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestampTz('archived_at')
                ->nullable()
                ->after('archived_by');

            $table->index(['course_program_id', 'recorded_on'], 'academic_course_recorded_idx');
            $table->index('archived_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropIndex('academic_course_recorded_idx');
            $table->dropIndex(['archived_by']);
            $table->dropConstrainedForeignId('course_program_id');
            $table->dropConstrainedForeignId('archived_by');
            $table->dropColumn('archived_at');
        });
    }
};
