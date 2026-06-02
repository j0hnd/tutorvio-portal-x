<?php

use App\Models\LessonNote;
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
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->string('review_status')->default(LessonNote::REVIEW_STATUS_PENDING)->after('submitted_at');
            $table->text('review_note')->nullable()->after('review_status');
            $table->foreignId('reviewed_by')->nullable()->after('review_note')->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by');

            $table->index('review_status');
            $table->index('reviewed_by');
            $table->index('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_notes', function (Blueprint $table) {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['review_status']);
            $table->dropIndex(['reviewed_by']);
            $table->dropIndex(['reviewed_at']);
            $table->dropColumn(['review_status', 'review_note', 'reviewed_by', 'reviewed_at']);
        });
    }
};
