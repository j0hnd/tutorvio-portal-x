<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_reminders', function (Blueprint $table) {
            $table->unique(
                ['class_schedule_id', 'user_id', 'channel', 'scheduled_for'],
                'schedule_reminders_unique_delivery'
            );
        });
    }

    public function down(): void
    {
        Schema::table('schedule_reminders', function (Blueprint $table) {
            $table->dropUnique('schedule_reminders_unique_delivery');
        });
    }
};
