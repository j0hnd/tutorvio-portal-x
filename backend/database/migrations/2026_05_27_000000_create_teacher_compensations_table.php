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
        Schema::create('teacher_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('pay_model', TeacherCompensation::PAY_MODELS);
            $table->decimal('default_pay_rate', 10, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('effective_start_date');
            $table->date('effective_end_date')->nullable();
            $table->text('internal_admin_notes')->nullable();
            $table->timestamps();

            $table->index(['teacher_id', 'effective_start_date']);
            $table->index(['teacher_id', 'effective_end_date']);
            $table->index('pay_model');
            $table->index('currency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_compensations');
    }
};
