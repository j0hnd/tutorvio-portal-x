<?php

use App\Models\TeacherPayoutAdjustment;
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
        Schema::create('teacher_payout_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payout_period_id')->nullable()->constrained('payout_periods')->nullOnDelete();
            $table->enum('type', TeacherPayoutAdjustment::TYPES);
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->text('reason');
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('teacher_id');
            $table->index('payout_period_id');
            $table->index('type');
            $table->index('created_by');
            $table->index(['teacher_id', 'payout_period_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_payout_adjustments');
    }
};
