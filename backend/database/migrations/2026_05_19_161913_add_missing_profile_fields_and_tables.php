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
        // Add new fields to student_profiles
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->string('current_level')->nullable()->after('english_level');
            $table->text('teacher_notes')->nullable()->after('notes');
            $table->text('internal_notes')->nullable()->after('teacher_notes');
        });

        // Add new fields to teacher_profiles
        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('specialization');
            $table->text('expertise')->nullable()->after('bio');
            $table->integer('class_load')->nullable()->after('expertise');
            $table->text('performance_summary')->nullable()->after('teaching_availability');
            $table->text('internal_remarks')->nullable()->after('teaching_notes');
        });

        // Create references tables
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained('lessons')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('status'); // present, absent, late
            $table->timestamps();
        });

        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('student_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // student
            $table->string('plan_name');
            $table->string('status')->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('user_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // admin/staff
            $table->string('action');
            $table->text('description')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activities');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('student_materials');
        Schema::dropIfExists('materials');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('lessons');

        Schema::table('teacher_profiles', function (Blueprint $table) {
            $table->dropColumn(['bio', 'expertise', 'class_load', 'performance_summary', 'internal_remarks']);
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['current_level', 'teacher_notes', 'internal_notes']);
        });
    }
};
