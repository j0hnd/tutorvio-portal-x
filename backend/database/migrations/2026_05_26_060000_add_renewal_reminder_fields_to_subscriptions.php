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
            $table->timestampTz('renewal_reminder_due_at')->nullable()->after('renewed_from_subscription_id');
            $table->timestampTz('renewal_reminder_last_sent_at')->nullable()->after('renewal_reminder_due_at');
            $table->string('renewal_reminder_status')->default('none')->after('renewal_reminder_last_sent_at');
            $table->string('renewal_reminder_window_key')->nullable()->after('renewal_reminder_status');
            $table->boolean('renewal_eligible')->default(true)->after('renewal_reminder_window_key');
            $table->text('renewal_reminder_notes')->nullable()->after('renewal_eligible');

            $table->index('renewal_reminder_due_at');
            $table->index('renewal_reminder_status');
            $table->index('renewal_eligible');
            $table->index('renewal_reminder_window_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['renewal_reminder_due_at']);
            $table->dropIndex(['renewal_reminder_status']);
            $table->dropIndex(['renewal_eligible']);
            $table->dropIndex(['renewal_reminder_window_key']);

            $table->dropColumn([
                'renewal_reminder_due_at',
                'renewal_reminder_last_sent_at',
                'renewal_reminder_status',
                'renewal_reminder_window_key',
                'renewal_eligible',
                'renewal_reminder_notes',
            ]);
        });
    }
};
