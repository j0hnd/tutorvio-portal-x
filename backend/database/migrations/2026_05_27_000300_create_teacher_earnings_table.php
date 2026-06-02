<?php

use App\Models\TeacherEarning;
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
        Schema::create('teacher_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('lesson_record_id')->nullable()->constrained('lesson_records')->nullOnDelete();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('pay_model');
            $table->decimal('rate_used', 10, 2);
            $table->decimal('quantity', 8, 2);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->json('calculation_metadata')->nullable();
            $table->enum('status', TeacherEarning::STATUSES)->default(TeacherEarning::STATUS_PENDING);
            $table->timestamps();

            $table->unique(['source_type', 'source_id'], 'teacher_earnings_source_unique');
            $table->index('teacher_id');
            $table->index('lesson_record_id');
            $table->index('pay_model');
            $table->index('status');
            $table->index(['teacher_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_earnings');
    }
};
