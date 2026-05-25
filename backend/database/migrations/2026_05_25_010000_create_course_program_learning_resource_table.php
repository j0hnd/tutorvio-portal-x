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
        Schema::create('course_program_learning_resource', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_program_id')->constrained('course_programs')->cascadeOnDelete();
            $table->foreignId('learning_resource_id')->constrained('learning_resources')->cascadeOnDelete();
            $table->foreignId('attached_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('attached_at');
            $table->timestamps();

            $table->unique(['course_program_id', 'learning_resource_id'], 'course_program_resource_unique');
            $table->index(['learning_resource_id', 'attached_at'], 'course_program_resource_resource_attached_index');
            $table->index('attached_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_program_learning_resource');
    }
};
