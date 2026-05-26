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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('package_type')->default('subscription');
            $table->unsignedInteger('total_lesson_count')->default(0);
            $table->unsignedInteger('consumed_lesson_count')->default(0);
            $table->unsignedInteger('remaining_lesson_count')->default(0);
            $table->boolean('is_frozen')->default(false);
            $table->timestampTz('frozen_at')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('invoice_reference')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('renewed_from_subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->index('package_type');
            $table->index('payment_status');
            $table->index('is_frozen');
            $table->index('invoice_id');
            $table->index('renewed_from_subscription_id');
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'payment_status']);
            $table->index(['user_id', 'is_frozen']);
        });

        Schema::create('subscription_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type');
            $table->string('plan_name')->nullable();
            $table->string('package_type')->nullable();
            $table->unsignedInteger('total_lesson_count')->nullable();
            $table->unsignedInteger('consumed_lesson_count')->nullable();
            $table->unsignedInteger('remaining_lesson_count')->nullable();
            $table->string('status')->nullable();
            $table->boolean('is_frozen')->nullable();
            $table->string('payment_status')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->json('previous_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('notes')->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('subscription_id');
            $table->index('student_id');
            $table->index('event_type');
            $table->index('effective_at');
            $table->index(['student_id', 'event_type']);
            $table->index(['subscription_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_histories');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['renewed_from_subscription_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);

            $table->dropIndex(['package_type']);
            $table->dropIndex(['payment_status']);
            $table->dropIndex(['is_frozen']);
            $table->dropIndex(['invoice_id']);
            $table->dropIndex(['renewed_from_subscription_id']);
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['user_id', 'payment_status']);
            $table->dropIndex(['user_id', 'is_frozen']);

            $table->dropColumn([
                'package_type',
                'total_lesson_count',
                'consumed_lesson_count',
                'remaining_lesson_count',
                'is_frozen',
                'frozen_at',
                'payment_status',
                'invoice_id',
                'invoice_reference',
                'internal_notes',
                'renewed_from_subscription_id',
                'created_by',
                'updated_by',
            ]);
        });
    }
};
