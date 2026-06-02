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
        Schema::create('course_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_archived')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('name');
            $table->index('sort_order');
            $table->index('is_archived');
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('archived_by');
        });

        Schema::create('course_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_type_id')->constrained('course_types')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('placement_level')->nullable();
            $table->unsignedSmallInteger('number_of_sessions')->nullable();
            $table->json('lesson_structure')->nullable();
            $table->json('milestones')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestampTz('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('course_type_id');
            $table->index('placement_level');
            $table->index('is_archived');
            $table->index(['course_type_id', 'is_archived']);
            $table->index(['placement_level', 'is_archived']);
            $table->index('created_by');
            $table->index('updated_by');
            $table->index('archived_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_programs');
        Schema::dropIfExists('course_types');
    }
};
