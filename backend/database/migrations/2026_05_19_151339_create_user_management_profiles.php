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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->string('timezone')->nullable()->after('phone');
            $table->index('status');
        });

        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('english_level')->nullable();
            $table->string('course')->nullable();
            $table->foreignId('assigned_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('class_type')->nullable();
            $table->date('start_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('preferences')->nullable();
            $table->text('goals')->nullable();
            $table->text('learning_concerns')->nullable();
            $table->timestamps();

            $table->index('assigned_teacher_id');
        });

        Schema::create('teacher_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('specialization')->nullable();
            $table->json('teaching_availability')->nullable();
            $table->string('internal_status')->nullable();
            $table->text('teaching_notes')->nullable();
            $table->string('document_contract_status')->nullable();
            $table->timestamps();
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('department')->nullable();
            $table->text('access_limitations')->nullable();
            $table->timestamps();
        });

        Schema::create('user_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['user_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_status_histories');
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('teacher_profiles');
        Schema::dropIfExists('student_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['phone', 'timezone']);
        });
    }
};
