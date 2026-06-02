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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('course_program_id')->nullable()->constrained('course_programs')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('USD');
            $table->date('issued_date');
            $table->date('due_date');
            $table->date('paid_date')->nullable();
            $table->string('status')->default('unpaid');
            $table->string('payment_gateway')->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->string('gateway_invoice_id')->nullable();
            $table->string('gateway_payment_intent_id')->nullable();
            $table->string('gateway_checkout_session_id')->nullable();
            $table->string('gateway_payment_method_id')->nullable();
            $table->string('gateway_status')->nullable();
            $table->string('payment_reference')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('student_id');
            $table->index('subscription_id');
            $table->index('course_program_id');
            $table->index('status');
            $table->index('invoice_number');
            $table->index('due_date');
            $table->index(['student_id', 'status']);
            $table->index(['student_id', 'due_date']);
            $table->index(['subscription_id', 'status']);
            $table->index(['course_program_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
