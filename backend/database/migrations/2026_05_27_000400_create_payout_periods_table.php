<?php

use App\Models\PayoutPeriod;
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
        Schema::create('payout_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('cutoff_date');
            $table->date('payout_date');
            $table->enum('status', PayoutPeriod::STATUSES)->default(PayoutPeriod::STATUS_DRAFT);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index(['start_date', 'end_date']);
            $table->index('cutoff_date');
            $table->index('payout_date');
        });

        Schema::create('payout_period_teacher_earning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_period_id')->constrained('payout_periods')->cascadeOnDelete();
            $table->foreignId('teacher_earning_id')->constrained('teacher_earnings')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['payout_period_id', 'teacher_earning_id'], 'payout_period_earning_unique');
            $table->unique('teacher_earning_id', 'teacher_earning_single_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_period_teacher_earning');
        Schema::dropIfExists('payout_periods');
    }
};
