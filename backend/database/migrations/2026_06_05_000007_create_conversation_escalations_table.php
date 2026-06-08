<?php

use App\Models\ConversationEscalation;
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
        Schema::create('conversation_escalations', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('conversation_message_id')->nullable()->constrained('conversation_messages')->cascadeOnDelete();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issue_report_id')->nullable()->constrained('issue_reports')->nullOnDelete();
            $table->string('status')->default(ConversationEscalation::STATUS_OPEN);
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('dismissed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('conversation_id');
            $table->index('conversation_message_id');
            $table->index('escalated_by');
            $table->index('reviewed_by');
            $table->index('issue_report_id');
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
            $table->index(['conversation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_escalations');
    }
};
