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
        Schema::table('issue_reports', function (Blueprint $table) {
            $table->foreignId('course_program_id')->nullable()->after('related_teacher_id')->constrained('course_programs')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->after('course_program_id')->constrained('materials')->nullOnDelete();
            $table->foreignId('learning_resource_id')->nullable()->after('material_id')->constrained('learning_resources')->nullOnDelete();

            $table->index('course_program_id');
            $table->index('material_id');
            $table->index('learning_resource_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issue_reports', function (Blueprint $table) {
            $table->dropForeign(['course_program_id']);
            $table->dropForeign(['material_id']);
            $table->dropForeign(['learning_resource_id']);
            $table->dropIndex(['course_program_id']);
            $table->dropIndex(['material_id']);
            $table->dropIndex(['learning_resource_id']);
            $table->dropColumn(['course_program_id', 'material_id', 'learning_resource_id']);
        });
    }
};
