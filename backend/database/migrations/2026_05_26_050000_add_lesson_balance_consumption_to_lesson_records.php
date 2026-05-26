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
        Schema::table('lesson_records', function (Blueprint $table) {
            $table->foreignId('lesson_balance_consumed_subscription_id')
                ->nullable()
                ->after('completed_by')
                ->constrained('subscriptions')
                ->nullOnDelete();
            $table->timestampTz('lesson_balance_consumed_at')->nullable()->after('lesson_balance_consumed_subscription_id');

            $table->index('lesson_balance_consumed_subscription_id', 'lesson_records_balance_subscription_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_records', function (Blueprint $table) {
            $table->dropForeign(['lesson_balance_consumed_subscription_id']);
            $table->dropIndex('lesson_records_balance_subscription_index');
            $table->dropColumn([
                'lesson_balance_consumed_subscription_id',
                'lesson_balance_consumed_at',
            ]);
        });
    }
};
