<?php

use App\Models\TeacherCompensation;
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
        Schema::create('teacher_compensation_rate_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_compensation_id')->constrained()->cascadeOnDelete();
            $table->string('lesson_type')->nullable();
            $table->string('experience_level')->nullable();
            $table->string('contract_agreement')->nullable();
            $table->foreignId('course_type_id')->nullable()->constrained('course_types')->nullOnDelete();
            $table->foreignId('course_program_id')->nullable()->constrained('course_programs')->nullOnDelete();
            $table->enum('pay_model', TeacherCompensation::PAY_MODELS)->nullable();
            $table->decimal('pay_rate', 10, 2);
            $table->char('currency', 3)->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('internal_admin_notes')->nullable();
            $table->timestamps();

            $table->index('teacher_compensation_id');
            $table->index('lesson_type');
            $table->index('experience_level');
            $table->index('contract_agreement');
            $table->index('course_type_id');
            $table->index('course_program_id');
            $table->index(['teacher_compensation_id', 'is_active', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_compensation_rate_rules');
    }
};
