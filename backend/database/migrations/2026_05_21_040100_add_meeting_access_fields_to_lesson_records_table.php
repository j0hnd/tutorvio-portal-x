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
            $table->string('meeting_link', 2048)->nullable()->change();
            $table->string('meeting_provider')->nullable()->after('meeting_link');
            $table->json('meeting_metadata')->nullable()->after('meeting_provider');
            $table->timestampTz('join_available_from')->nullable()->after('meeting_metadata');
            $table->timestampTz('join_available_until')->nullable()->after('join_available_from');

            $table->index(['meeting_provider', 'join_available_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lesson_records', function (Blueprint $table) {
            $table->dropIndex(['meeting_provider', 'join_available_from']);
            $table->string('meeting_link')->nullable()->change();
            $table->dropColumn([
                'meeting_provider',
                'meeting_metadata',
                'join_available_from',
                'join_available_until',
            ]);
        });
    }
};
